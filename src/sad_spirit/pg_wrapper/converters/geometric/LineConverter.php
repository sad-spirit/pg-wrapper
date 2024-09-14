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
 * @copyright 2014-2024 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-wrapper
 */

declare(strict_types=1);

namespace sad_spirit\pg_wrapper\converters\geometric;

use sad_spirit\pg_wrapper\{
    converters\ContainerConverter,
    converters\FloatConverter,
    exceptions\TypeConversionException,
    types\Line
};

/**
 * Converter for line type, represented by three coefficients of linear equation (PostgreSQL 9.4+)
 */
class LineConverter extends ContainerConverter
{
    /**
     * Converter for line's coefficients
     * @var FloatConverter
     */
    private $floatConverter;

    public function __construct()
    {
        $this->floatConverter = new FloatConverter();
    }

    protected function parseInput(string $native, int &$pos): Line
    {
        $this->expectChar($native, $pos, '{');

        $len  = \strcspn($native, ',}', $pos);
        $A    = \substr($native, $pos, $len);
        $pos += $len;

        $this->expectChar($native, $pos, ',');

        $len  = \strcspn($native, ',}', $pos);
        $B    = \substr($native, $pos, $len);
        $pos += $len;

        $this->expectChar($native, $pos, ',');

        $len  = \strcspn($native, ',}', $pos);
        $C    = \substr($native, $pos, $len);
        $pos += $len;

        $this->expectChar($native, $pos, '}');

        return new Line(
            $this->floatConverter->input($A),
            $this->floatConverter->input($B),
            $this->floatConverter->input($C)
        );
    }

    protected function outputNotNull($value): string
    {
        if (\is_array($value)) {
            $value = Line::createFromArray($value);
        } elseif (!($value instanceof Line)) {
            throw TypeConversionException::unexpectedValue($this, 'output', 'instance of Line or an array', $value);
        }
        return '{' . $this->floatConverter->output($value->A) . ',' . $this->floatConverter->output($value->B)
               . ',' . $this->floatConverter->output($value->C) . '}';
    }

    public function dimensions(): int
    {
        return 1;
    }
}
