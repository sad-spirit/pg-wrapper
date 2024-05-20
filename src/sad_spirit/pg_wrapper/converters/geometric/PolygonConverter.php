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
    exceptions\TypeConversionException,
    types\Polygon
};

/**
 * Converter for polygon data type
 */
class PolygonConverter extends BaseGeometricConverter
{
    protected function parseInput(string $native, int &$pos): Polygon
    {
        return new Polygon(...$this->parsePoints($native, $pos, $this->countPoints($native)));
    }

    protected function outputNotNull($value): string
    {
        if (is_array($value)) {
            $value = Polygon::createFromArray($value);
        } elseif (!($value instanceof Polygon)) {
            throw TypeConversionException::unexpectedValue($this, 'output', 'instance of Polygon or an array', $value);
        }

        $points = [];
        foreach ($value as $point) {
            $points[] = $this->point->output($point);
        }
        return '(' . implode(',', $points) . ')';
    }

    public function dimensions(): int
    {
        return 2;
    }
}
