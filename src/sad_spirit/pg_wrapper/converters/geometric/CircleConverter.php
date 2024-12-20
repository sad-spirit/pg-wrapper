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

namespace sad_spirit\pg_wrapper\converters\geometric;

use sad_spirit\pg_wrapper\{
    converters\FloatConverter,
    exceptions\TypeConversionException,
    types\Circle
};

/**
 * Converter for circle type, represented by a centre point and radius
 */
class CircleConverter extends BaseGeometricConverter
{
    /** Converter for circle's radius */
    private readonly FloatConverter $floatConverter;

    public function __construct()
    {
        $this->floatConverter = new FloatConverter();
        parent::__construct();
    }

    protected function parseInput(string $native, int &$pos): Circle
    {
        $hasDelimiters = $angleDelimiter = $singleOpen = false;

        if ('<' === ($char = $this->nextChar($native, $pos)) || '(' === $char) {
            $hasDelimiters = true;
            if ('<' === $char) {
                $angleDelimiter = true;
            } else {
                $singleOpen     = $pos === \strrpos($native, '(');
            }
            $pos++;
        }

        $center = $this->point->parseInput($native, $pos);
        if (')' === $this->nextChar($native, $pos) && $singleOpen) {
            $hasDelimiters = false;
            $pos++;
        }
        $this->expectChar($native, $pos, ',');
        $len    = \strcspn($native, ',)>', $pos);
        $radius = \substr($native, $pos, $len);
        $pos   += $len;

        if ($hasDelimiters) {
            $this->expectChar($native, $pos, $angleDelimiter ? '>' : ')');
        }

        return new Circle($center, $this->floatConverter->input($radius));
    }

    protected function outputNotNull(mixed $value): string
    {
        if (\is_array($value)) {
            $value = Circle::createFromArray($value);
        } elseif (!$value instanceof Circle) {
            throw TypeConversionException::unexpectedValue($this, 'output', 'instance of Circle or an array', $value);
        }
        return '<' . $this->point->output($value->center) . ',' . $this->floatConverter->output($value->radius) . '>';
    }

    public function dimensions(): int
    {
        return 2;
    }
}
