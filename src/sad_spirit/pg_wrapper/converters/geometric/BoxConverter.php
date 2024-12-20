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
    converters\CustomArrayDelimiter,
    exceptions\TypeConversionException,
    types\Box
};

/**
 * Converter for box type, box is represented with two points that are its opposite corners
 */
class BoxConverter extends BaseGeometricConverter implements CustomArrayDelimiter
{
    protected function parseInput(string $native, int &$pos): Box
    {
        return new Box(...$this->parsePoints($native, $pos, 2));
    }

    protected function outputNotNull(mixed $value): string
    {
        if (\is_array($value)) {
            $value = Box::createFromArray($value);
        } elseif (!$value instanceof Box) {
            throw TypeConversionException::unexpectedValue($this, 'output', 'instance of Box or an array', $value);
        }
        return '(' . $this->point->output($value->start) . ',' . $this->point->output($value->end) . ')';
    }

    public function dimensions(): int
    {
        return 2;
    }

    public function getArrayDelimiter(): string
    {
        return ';';
    }
}
