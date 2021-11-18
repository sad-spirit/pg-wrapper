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
    converters\ContainerConverter,
    converters\FloatConverter,
    converters\IntegerConverter,
    converters\NumericConverter,
    TypeConverter,
    types\Range,
    types\NumericRange,
    types\DateTimeRange,
    exceptions\TypeConversionException
};
use sad_spirit\pg_wrapper\converters\datetime\BaseDateTimeConverter;

/**
 * Converter for range types of PostgreSQL 9.2+
 */
class RangeConverter extends ContainerConverter implements ConnectionAware
{
    /**
     * Converter for the base type of the range
     * @var TypeConverter
     */
    private $subtypeConverter;

    /**
     * input() will return instances of this class
     * @var class-string<Range>
     */
    protected $resultClass = Range::class;

    /**
     * Constructor, sets converter for the base type
     *
     * @param TypeConverter $subtypeConverter
     */
    public function __construct(TypeConverter $subtypeConverter)
    {
        $this->subtypeConverter = $subtypeConverter;

        if (
            $subtypeConverter instanceof FloatConverter
            || $subtypeConverter instanceof NumericConverter
            || $subtypeConverter instanceof IntegerConverter
        ) {
            $this->resultClass = NumericRange::class;
        } elseif ($subtypeConverter instanceof BaseDateTimeConverter) {
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
                    if (!preg_match('/"((?>[^"\\\\]+|\\\\.|"")*)"/As', $string, $m, 0, $pos)) {
                        throw TypeConversionException::parsingFailed($this, 'quoted string', $string, $pos);
                    }
                    $pos   += strlen($m[0]);
                    $bound .= strtr($m[1], ['\\\\' => '\\', '\\"' => '"', '""' => '"']);
                    break;

                default:
                    if (!preg_match("/(?>[^\"\\\\\\]),]+|\\\\.)+/As", $string, $m, 0, $pos)) {
                        throw TypeConversionException::parsingFailed($this, 'unquoted string', $string, $pos);
                    }
                    $pos   += strlen($m[0]);
                    $bound .= stripcslashes($m[0]);
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
                if (preg_match('/empty/Ai', $native, $m, 0, $pos)) {
                    $pos += 5;
                    return call_user_func([$this->resultClass, 'createEmpty']);
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

    protected function outputNotNull($value): string
    {
        if (is_array($value)) {
            $value = call_user_func([$this->resultClass, 'createFromArray'], $value);
        } elseif (!($value instanceof Range)) {
            throw TypeConversionException::unexpectedValue($this, 'output', 'instance of Range or an array', $value);
        }
        /* @var $value Range */
        if ($value->empty) {
            return 'empty';
        }
        return ($value->lowerInclusive ? '[' : '(')
               . (null === $value->lower
                  ? '' : '"' . addcslashes($this->subtypeConverter->output($value->lower) ?? '', '"\\') . '"')
               . ','
               . (null === $value->upper
                  ? '' : '"' . addcslashes($this->subtypeConverter->output($value->upper) ?? '', '"\\') . '"')
               . ($value->upperInclusive ? ']' : ')');
    }
}
