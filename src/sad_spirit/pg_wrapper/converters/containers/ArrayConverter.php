<?php

/*
 * This file is part of sad_spirit/pg_wrapper:
 * converter of complex PostgreSQL types and an OO wrapper for PHP's pgsql extension.
 *
 * (c) Alexey Borzov <avb@php.net>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

declare(strict_types=1);

namespace sad_spirit\pg_wrapper\converters\containers;

use sad_spirit\pg_wrapper\{
    Connection,
    Exception as PackageException,
    TypeConverter,
    converters\ConnectionAware,
    converters\ContainerConverter,
    converters\CustomArrayDelimiter,
    exceptions\InvalidArgumentException,
    exceptions\TypeConversionException
};

/**
 * Class for arrays, including multidimensional ones
 */
class ArrayConverter extends ContainerConverter implements ConnectionAware
{
    /** Base type converter for elements of the array */
    private readonly TypeConverter $itemConverter;

    /** Delimiter for elements in string representation of an array */
    private string $delimiter = ',';

    public function __construct(TypeConverter $itemConverter)
    {
        if ($itemConverter instanceof self) {
            throw new InvalidArgumentException('ArrayConverters should not be nested');
        }
        if ($itemConverter instanceof CustomArrayDelimiter) {
            $this->delimiter = $itemConverter->getArrayDelimiter();
        }
        $this->itemConverter = $itemConverter;
    }

    /**
     * Number of array dimensions for PHP variable
     *
     * Return value is meaningless as Postgres arrays, by design, can have a variable number of
     * dimensions. As ArrayConverters cannot be nested this method is not actually called anywhere.
     */
    public function dimensions(): int
    {
        return -1;
    }

    /**
     * Propagates $connection to ConnectionAware converters of base type
     */
    public function setConnection(Connection $connection): void
    {
        if ($this->itemConverter instanceof ConnectionAware) {
            $this->itemConverter->setConnection($connection);
        }
    }

    /**
     * Builds a Postgres array literal from given PHP array variable
     *
     * Postgres enforces neither a number of dimensions of an array nor a number
     * of values in each dimension. However, it does require that multidimensional
     * arrays have matching sizes for each dimension. This means that e.g.
     * `ARRAY[['foo', 'bar'], ['baz', 'quux']]` is a valid array while
     * `ARRAY[['foo', 'bar'], ['baz']]` is invalid.
     *
     * This method calculates the sizes to match based on first elements of the given
     * array and then checks that all other sub-arrays match these sizes.
     *
     * @param mixed $value Will throw an exception on anything but array
     * @throws TypeConversionException
     */
    protected function outputNotNull(mixed $value): string
    {
        if (!\is_array($value)) {
            throw TypeConversionException::unexpectedValue($this, 'output', 'array', $value);
        }
        if (0 === \count($value)) {
            return '{}';
        }

        $requiredSizes = $this->calculateRequiredSizes($value);
        // this can only happen if $item->dimensions() > 0, i.e. it should be an array itself
        if (\count($requiredSizes) < 1) {
            throw TypeConversionException::unexpectedValue(
                $this,
                'output',
                "array with at least {$this->itemConverter->dimensions()} dimension(s)",
                \reset($value)
            );
        }
        return $this->buildArrayLiteral($value, $requiredSizes);
    }

    /**
     * Builds an array literal checking the required sizes for sub-arrays
     *
     * @param int[] $requiredSizes
     * @throws TypeConversionException
     */
    private function buildArrayLiteral(array $value, array $requiredSizes): string
    {
        $requiredCount = \array_shift($requiredSizes);
        if ($requiredCount !== \count($value)) {
            throw TypeConversionException::unexpectedValue(
                $this,
                'output',
                "array with $requiredCount value(s)",
                $value
            );
        }

        $parts = [];
        if (empty($requiredSizes)) {
            foreach ($value as $v) {
                $item    = $this->itemConverter->output($v);
                $parts[] = ($item === null) ? 'NULL' : '"' . \addcslashes($item, '"\\') . '"';
            }

        } else {
            foreach ($value as $v) {
                if (!\is_array($v)) {
                    throw TypeConversionException::unexpectedValue($this, 'output', 'array', $v);
                }
                $parts[] = $this->buildArrayLiteral($v, $requiredSizes);
            }
        }

        return '{' . \implode($this->delimiter, $parts) . '}';
    }

    /**
     * Calculates the number of array dimensions and required sizes for sub-arrays
     *
     * @return int[]
     * @throws TypeConversionException
     */
    private function calculateRequiredSizes(array $value): array
    {
        $sizes = [];
        while (\is_array($value)) {
            $sizes[] = \count($value);
            if (!\count($value) || \array_keys($value) !== \range(0, \count($value) - 1)) {
                if (0 === $this->itemConverter->dimensions()) {
                    // scalar base type? "weird" sub-array is not allowed
                    throw TypeConversionException::unexpectedValue(
                        $this,
                        'output',
                        'non-empty array with 0-based numeric indexes',
                        $value
                    );
                }
                // assume that we reached an array representing base type
                \array_pop($sizes);
                return $sizes;
            }
            if (null === ($value = $value[0])) {
                // null sub-arrays are not allowed, so that should be a base-type null
                return $sizes;
            }
        }
        if ($this->itemConverter->dimensions() > 0) {
            // check whether we have an object representing base type
            if (\is_object($value)) {
                try {
                    $this->itemConverter->output($value);
                    return $sizes;
                } catch (PackageException) {
                }
            }
            \array_splice($sizes, -$this->itemConverter->dimensions());
        }
        return $sizes;
    }

    /**
     * {@inheritDoc}
     *
     * This is a non-recursive part of array literal parsing, it handles the possible array dimensions that can only
     * appear at the very beginning.
     */
    protected function parseInput(string $native, int &$pos): array
    {
        if ('[' === $this->nextChar($native, $pos)) {
            $dimensions = $this->parseDimensions($native, $pos);
        }
        return $this->parseArrayRecursive($native, $pos, $dimensions ?? null);
    }

    /**
     * Parses the array dimensions specification
     *
     * @return list<array{int,int}> Contains the first key and number of elements for each dimension
     */
    private function parseDimensions(string $native, int &$pos): array
    {
        $dimensions = [];

        do {
            // Postgres does not allow whitespace inside dimension specifications, neither should we
            if (!\preg_match('/\[([+-]?\d+)(?::([+-]?\d+))?/A', $native, $m, 0, $pos)) {
                throw TypeConversionException::parsingFailed(
                    $this,
                    "array bounds after '['",
                    $native,
                    $pos + 1
                );
            }
            if (!isset($m[2])) {
                $lower = 1;
                $upper = (int)$m[1];
            } else {
                $lower = (int)$m[1];
                $upper = (int)$m[2];
            }
            if ($lower > $upper) {
                throw new TypeConversionException(\sprintf(
                    'Array upper bound (%d) cannot be less than lower bound (%d)',
                    $upper,
                    $lower
                ));
            }

            // Convert to standard PHP 0-based array unless lower bound was given
            if (!isset($m[2])) {
                $dimensions[] = [0, $upper];
            } else {
                $dimensions[] = [$lower, $upper - $lower + 1];
            }

            $pos += \strlen($m[0]);
            $this->expectChar($native, $pos, ']');
        } while ('[' === ($char = $this->nextChar($native, $pos)));

        if ('=' !== $char) {
            throw TypeConversionException::parsingFailed($this, "'=' after array dimensions", $native, $pos);
        }
        $pos++;

        return $dimensions;
    }

    /**
     * Recursively parses the string representation of an array
     *
     * @param list<array{int,int}>|null $dimensions Will be not null if the literal contained dimensions
     */
    private function parseArrayRecursive(string $native, int &$pos, ?array $dimensions = null): array
    {
        $result = [];

        if (null === $dimensions) {
            $key   = 0;
            $count = null;
        } elseif ([] !== $dimensions) {
            [$key, $count] = \array_shift($dimensions);
        } else {
            throw new TypeConversionException("Specified array dimensions do not match array contents");
        }

        $this->expectChar($native, $pos, '{'); // Leading "{".

        while ('}' !== ($char = $this->nextChar($native, $pos))) {
            // require a delimiter between elements
            if ([] !== $result) {
                if ($this->delimiter !== $char) {
                    throw TypeConversionException::parsingFailed($this, "'$this->delimiter'", $native, $pos);
                }
                $pos++;
                $char = $this->nextChar($native, $pos);
            }

            if ('{' === $char) {
                // parse sub-array
                $result[$key++] = $this->parseArrayRecursive($native, $pos, $dimensions);

            } elseif ([] !== (array)$dimensions) {
                throw new TypeConversionException("Specified array dimensions do not match array contents");

            } elseif ('"' === $char) {
                // quoted string
                if (!\preg_match('/"((?>[^"\\\\]+|\\\\.)*)"/As', $native, $m, 0, $pos)) {
                    throw TypeConversionException::parsingFailed($this, 'quoted string', $native, $pos);
                }
                $result[$key++]  = $this->itemConverter->input(\stripcslashes($m[1]));
                $pos            += \strlen($m[0]);

            } else {
                // zero-length string can appear only quoted
                if (0 === ($len = \strcspn($native, $this->delimiter . '}' . self::WHITESPACE, $pos))) {
                    throw TypeConversionException::parsingFailed(
                        $this,
                        'subarray, quoted or unquoted string',
                        $native,
                        $pos
                    );
                }
                $v               = \substr($native, $pos, $len);
                $result[$key++]  = \strcasecmp($v, "null") ? $this->itemConverter->input(\stripcslashes($v)) : null;
                $pos            += $len;
            }
        }
        $pos++; // skip trailing "}"

        if (null !== $count && \count($result) !== $count) {
            throw new TypeConversionException("Specified array dimensions do not match array contents");
        }

        return $result;
    }
}
