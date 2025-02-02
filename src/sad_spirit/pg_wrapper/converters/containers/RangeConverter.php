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
    TypeConverter,
    exceptions\TypeConversionException,
    types\DateTimeRange,
    types\NumericRange,
    types\Range
};
use sad_spirit\pg_wrapper\converters\{
    BaseNumericConverter,
    ConnectionAware,
    ContainerConverter,
    datetime\BaseDateTimeConverter
};

/**
 * Converter for range types of PostgreSQL 9.2+
 */
class RangeConverter extends ContainerConverter implements ConnectionAware
{
    /**
     * input() will return instances of this class
     * @var class-string<Range>
     */
    protected string $resultClass = Range::class;

    /**
     * Constructor, sets converter for the base type
     *
     * @param TypeConverter $subtypeConverter
     * @param class-string<Range>|null $resultClass
     */
    public function __construct(
        /** Converter for the base type of the range */
        private readonly TypeConverter $subtypeConverter,
        ?string $resultClass = null
    ) {
        if (null !== $resultClass) {
            $this->resultClass = $resultClass;
        } elseif ($this->subtypeConverter instanceof BaseNumericConverter) {
            $this->resultClass = NumericRange::class;
        } elseif ($this->subtypeConverter instanceof BaseDateTimeConverter) {
            $this->resultClass = DateTimeRange::class;
        }
    }

    /**
     * Propagates $connection to ConnectionAware converters of base type
     *
     * @param Connection $connection
     */
    public function setConnection(Connection $connection): void
    {
        if ($this->subtypeConverter instanceof ConnectionAware) {
            $this->subtypeConverter->setConnection($connection);
        }
    }

    /**
     * Reads a range bound (upper or lower) from input
     *
     * @param string $string
     * @param int    $pos
     *
     * @return null|string
     * @throws TypeConversionException
     */
    private function readRangeBound(string $string, int &$pos): ?string
    {
        $bound = null;
        while (true) {
            switch ($string[$pos]) {
                case ',':
                case ']':
                case ')':
                    break 2;

                case '"':
                    if (!\preg_match('/"((?>[^"\\\\]+|\\\\.|"")*)"/As', $string, $m, 0, $pos)) {
                        throw TypeConversionException::parsingFailed($this, 'quoted string', $string, $pos);
                    }
                    $pos   += \strlen($m[0]);
                    $bound .= \strtr($m[1], ['\\\\' => '\\', '\\"' => '"', '""' => '"']);
                    break;

                default:
                    if (!\preg_match("/(?>[^\"\\\\\\]),]+|\\\\.)+/As", $string, $m, 0, $pos)) {
                        throw TypeConversionException::parsingFailed($this, 'unquoted string', $string, $pos);
                    }
                    $pos   += \strlen($m[0]);
                    $bound .= \stripcslashes($m[0]);
            }
        }

        return $bound;
    }

    /**
     * Parses a native value into PHP variable from given position
     *
     * @param string $native
     * @param int    $pos
     *
     * @return Range
     * @throws TypeConversionException
     * @noinspection PhpMissingBreakStatementInspection
     */
    protected function parseInput(string $native, int &$pos): Range
    {
        switch ($char = $this->nextChar($native, $pos)) {
            case '(':
            case '[':
                $pos++;
                $lowerInclusive = '[' === $char;
                break;

            case 'e':
            case 'E':
                if (\preg_match('/empty/Ai', $native, $m, 0, $pos)) {
                    $pos += 5;
                    return \call_user_func([$this->resultClass, 'createEmpty']);
                }
                // fall-through is intentional

            default:
                throw TypeConversionException::parsingFailed($this, '[ or (', $native, $pos);
        }

        $lower = $this->readRangeBound($native, $pos);
        $this->expectChar($native, $pos, ',');
        $upper = $this->readRangeBound($native, $pos);

        if (']' === $native[$pos]) {
            $upperInclusive = true;
            $pos++;

        } elseif (')' === $native[$pos]) {
            $upperInclusive = false;
            $pos++;

        } else {
            throw TypeConversionException::parsingFailed($this, '] or )', $native, $pos);
        }

        return new $this->resultClass(
            $this->subtypeConverter->input($lower),
            $this->subtypeConverter->input($upper),
            $lowerInclusive,
            $upperInclusive
        );
    }

    protected function outputNotNull(mixed $value): string
    {
        if (\is_array($value)) {
            $value = \call_user_func([$this->resultClass, 'createFromArray'], $value);
        } elseif (!$value instanceof Range) {
            throw TypeConversionException::unexpectedValue($this, 'output', 'instance of Range or an array', $value);
        }
        if ($value->empty) {
            return 'empty';
        }
        return ($value->lowerInclusive ? '[' : '(')
               . (null === $value->lower
                  ? '' : '"' . \addcslashes($this->subtypeConverter->output($value->lower) ?? '', '"\\') . '"')
               . ','
               . (null === $value->upper
                  ? '' : '"' . \addcslashes($this->subtypeConverter->output($value->upper) ?? '', '"\\') . '"')
               . ($value->upperInclusive ? ']' : ')');
    }
}
