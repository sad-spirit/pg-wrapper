<?php
/**
 * Wrapper for PHP's pgsql extension providing conversion of complex DB types
 *
 * LICENSE
 *
 * This source file is subject to BSD 2-Clause License that is bundled
 * with this package in the file LICENSE and available at the URL
 * https://raw.githubusercontent.com/sad-spirit/pg-wrapper/master/LICENSE
 *
 * @package   sad_spirit\pg_wrapper
 * @copyright 2014 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-wrapper
 */

namespace sad_spirit\pg_wrapper\converters\geometric;

use sad_spirit\pg_wrapper\exceptions\TypeConversionException,
    sad_spirit\pg_wrapper\types\Polygon;

/**
 * Converter for polygon data type
 */
class PolygonConverter extends BaseGeometricConverter
{
    protected function parseInput($native, &$pos)
    {
        return new Polygon($this->parsePoints($native, $pos, $this->countPoints($native), false));
    }

    protected function outputNotNull($value)
    {
        if (is_array($value)) {
            $value = Polygon::createFromArray($value);
        } elseif (!($value instanceof Polygon)) {
            throw TypeConversionException::unexpectedValue($this, 'output', 'instance of Polygon or an array', $value);
        }

        $points = array();
        foreach ($value as $point) {
            $points[] = $this->point->output($point);
        }
        return '(' . implode(',', $points) . ')';
    }

    public function dimensions()
    {
        return 2;
    }
}
