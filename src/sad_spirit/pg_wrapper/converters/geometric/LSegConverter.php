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
    sad_spirit\pg_wrapper\types\LineSegment;

/**
 * Converter for line segment type, represented by a pair of points
 */
class LSegConverter extends BaseGeometricConverter
{
    protected function parseInput($native, &$pos)
    {
        $points = $this->parsePoints($native, $pos, 2, true);
        unset($points['open']);
        return new LineSegment(array_shift($points), array_shift($points));
    }

    protected function outputNotNull($value)
    {
        if (is_array($value)) {
            $value = LineSegment::createFromArray($value);
        } elseif (!($value instanceof LineSegment)) {
            throw TypeConversionException::unexpectedValue($this, 'output', 'instance of LineSegment or an array', $value);
        }
        return '[' . $this->point->output($value->start) . ',' . $this->point->output($value->end) . ']';
    }

    public function dimensions()
    {
        return 2;
    }
}
