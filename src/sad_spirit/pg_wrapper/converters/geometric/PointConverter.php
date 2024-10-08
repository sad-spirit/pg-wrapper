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
    types\Point
};

/**
 * Converter for point type, building block for other geometric types
 */
class PointConverter extends ContainerConverter
{
    /** Converter for point's coordinates */
    private readonly FloatConverter $floatConverter;

    public function __construct()
    {
        $this->floatConverter = new FloatConverter();
    }

    protected function parseInput(string $native, int &$pos): Point
    {
        $hasDelimiters = false;
        if ('(' === $this->nextChar($native, $pos)) {
            $hasDelimiters = true;
            $pos++;
        }
        $len  = \strcspn($native, ',)', $pos);
        $x    = \substr($native, $pos, $len);
        $pos += $len;

        $this->expectChar($native, $pos, ',');

        $len  = \strcspn($native, ',)', $pos);
        $y    = \substr($native, $pos, $len);
        $pos += $len;

        if ($hasDelimiters) {
            $this->expectChar($native, $pos, ')');
        }
        return new Point($this->floatConverter->input($x), $this->floatConverter->input($y));
    }

    protected function outputNotNull(mixed $value): string
    {
        if (\is_array($value)) {
            $value = Point::createFromArray($value);
        } elseif (!$value instanceof Point) {
            throw TypeConversionException::unexpectedValue($this, 'output', 'instance of Point or an array', $value);
        }
        return '(' . $this->floatConverter->output($value->x) . ',' . $this->floatConverter->output($value->y) . ')';
    }

    public function dimensions(): int
    {
        return 1;
    }
}
