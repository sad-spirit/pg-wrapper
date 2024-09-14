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
