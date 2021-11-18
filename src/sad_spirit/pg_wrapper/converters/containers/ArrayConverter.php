<?php

/**
 * Converter of complex PostgreSQL types and an OO wrapper for PHP's pgsql extension
 *
 * LICENSE
 *
 * This source file is subject to BSD 2-Clause License that is bundled
 * with this package in the file LICENSE and available at the URL
 * https://raw.githubusercontent.com/sad-spirit/pg-wrapper/master/LICENSE
 *
 * @package   sad_spirit\pg_wrapper
 * @copyright 2014-2021 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-wrapper
 */

declare(strict_types=1);

namespace sad_spirit\pg_wrapper\converters\containers;

use sad_spirit\pg_wrapper\{
    Connection,
    converters\ConnectionAware,
    exceptions\TypeConversionException,
    exceptions\InvalidArgumentException,
    TypeConverter,
    Exception as PackageException,
    converters\ContainerConverter
};

/**
 * Class for arrays, including multi-dimensional ones
 */
class ArrayConverter extends ContainerConverter implements ConnectionAware
{
    /**
     * Base type for elements of the array
     * @var TypeConverter
     */
    private $itemConverter;

    public function __construct(TypeConverter $itemConverter)
    {
        if ($itemConverter instanceof self) {
            throw new InvalidArgumentException('ArrayConverters should not be nested');
        }
        $this->itemConverter = $itemConverter;
    }

    /**
     * Number of array dimensions for PHP variable
     *
     * Return value is meaningless as Postgres arrays, by design, can have a variable number of
     * dimensions. As ArrayConverters cannot be nested this method is not actually called anywhere.
     *
     * @return int
     */
    public function dimensions(): int
    {
        return -1;
    }

    /**
     * Propagates $connection to ConnectionAware converters of base type
     *
     * @param Connection $connection
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
     * of values in each dimension. However it does require that multidimensional
     * arrays have matching sizes for each dimension. This means that e.g.
     * ARRAY[['foo', 'bar'], ['baz', 'quux']] is a valid array while
     * ARRAY[['foo', 'bar'], ['baz']] is invalid.
     *
     * This method calculates the sizes to match based on first elements of the given
     * array and then checks that all other subarrays match these sizes.
     *
     * @param mixed $value Will throw an exception on anything but array
     * @return string
     * @throws TypeConversionException
     */
    protected function outputNotNull($value): string
    {
        if (!is_array($value)) {
            throw TypeConversionException::unexpectedValue($this, 'output', 'array', $value);
        }
        if (0 === count($value)) {
            return '{}';
        }

        $requiredSizes = $this->calculateRequiredSizes($value);
        // this can only happen if $item->dimensions() > 0, i.e. it should be an array itself
        if (count($requiredSizes) < 1) {
            throw TypeConversionException::unexpectedValue(
                $this,
                'output',
                "array with at least {$this->itemConverter->dimensions()} dimension(s)",
                reset($value)
            );
        }
        return $this->buildArrayLiteral($value, $requiredSizes);
    }

    /**
     * Builds an array literal checking the required sizes for sub-arrays
     *
     * @param array $value
     * @param int[] $requiredSizes
     * @return string
     * @throws TypeConversionException
     */
    private function buildArrayLiteral(array $value, array $requiredSizes): string
    {
        $requiredCount = array_shift($requiredSizes);
        if ($requiredCount !== count($value)) {
            throw TypeConversionException::unexpectedValue(
                $this,
                'output',
                "array with {$requiredCount} value(s)",
                $value
            );
        }

        $parts = [];
        if (empty($requiredSizes)) {
            foreach ($value as $v) {
                $item    = $this->itemConverter->output($v);
                $parts[] = ($item === null) ? 'NULL' : '"' . addcslashes($item, '"\\') . '"';
            }

        } else {
            foreach ($value as $v) {
                if (!is_array($v)) {
                    throw TypeConversionException::unexpectedValue($this, 'output', 'array', $v);
                }
                $parts[] = $this->buildArrayLiteral($v, $requiredSizes);
            }
        }

        return '{' . implode(',', $parts) . '}';
    }

    /**
     * Calculates the number of array dimensions and required sizes for sub-arrays
     *
     * @param array $value
     * @return int[]
     * @throws TypeConversionException
     */
    private function calculateRequiredSizes(array $value): array
    {
        $sizes = [];
        while (is_array($value)) {
            $sizes[] = count($value);
            if (!count($value) || array_keys($value) !== range(0, count($value) - 1)) {
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
                array_pop($sizes);
                return $sizes;
            }
            if (null === ($value = $value[0])) {
                // null sub-arrays are not allowed, so that should be a base-type null
                return $sizes;
            }
        }
        if ($this->itemConverter->dimensions() > 0) {
            // check whether we have an object representing base type
            if (is_object($value)) {
                try {
                    $this->itemConverter->output($value);
                    return $sizes;
                } catch (PackageException $e) {
                }
            }
            array_splice($sizes, -$this->itemConverter->dimensions());
        }
        return $sizes;
    }

    protected function parseInput(string $native, int &$pos): array
    {
        $result = [];

        $this->expectChar($native, $pos, '{'); // Leading "{".

        while ('}' !== ($char = $this->nextChar($native, $pos))) {
            // require a comma delimiter between elements
            if (!empty($result)) {
                if (',' !== $char) {
                    throw TypeConversionException::parsingFailed($this, "','", $native, $pos);
                }
                $pos++;
                $char = $this->nextChar($native, $pos);
            }

            if ('{' === $char) {
                // parse sub-array
                $result[] = $this->parseInput($native, $pos);

            } elseif ('"' === $char) {
                // quoted string
                if (!preg_match('/"((?>[^"\\\\]+|\\\\.)*)"/As', $native, $m, 0, $pos)) {
                    throw TypeConversionException::parsingFailed($this, 'quoted string', $native, $pos);
                }
                $result[]  = $this->itemConverter->input(stripcslashes($m[1]));
                $pos      += strlen($m[0]);

            } else {
                // zero-length string can appear only quoted
                if (0 === ($len = strcspn($native, ",} \t\r\n", $pos))) {
                    throw TypeConversionException::parsingFailed(
                        $this,
                        'subarray, quoted or unquoted string',
                        $native,
                        $pos
                    );
                }
                $v         = substr($native, $pos, $len);
                $result[]  = strcasecmp($v, "null") ? $this->itemConverter->input(stripcslashes($v)) : null;
                $pos      += $len;
            }
        }
        $pos++; // skip trailing "}"

        return $result;
    }
}
