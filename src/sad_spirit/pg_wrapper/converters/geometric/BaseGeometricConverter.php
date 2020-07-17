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
 * @copyright 2014-2020 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-wrapper
 */

declare(strict_types=1);

namespace sad_spirit\pg_wrapper\converters\geometric;

use sad_spirit\pg_wrapper\{
    exceptions\TypeConversionException,
    converters\ContainerConverter
};

/**
 * Base class for geometric types
 */
abstract class BaseGeometricConverter extends ContainerConverter
{
    /**
     * Point instance used to parse input and build output
     * @var PointConverter
     */
    protected $point;

    /**
     * Constructor, sets the Point instance used for input and output
     */
    public function __construct()
    {
        $this->point = new PointConverter();
    }

    /**
     * Counts the number of points in input (based on number of commas)
     *
     * @param string $native
     * @return int Number of points
     * @throws TypeConversionException
     */
    protected function countPoints(string $native): int
    {
        $commas = substr_count($native, ',');
        if ($commas % 2) {
            return intval(($commas + 1) / 2);
        } else {
            throw TypeConversionException::unexpectedValue($this, 'input', 'even number of numeric values', $native);
        }
    }

    /**
     * Parses given number of points from native database value
     *
     * @param string $native       native database value
     * @param int    $pos          position
     * @param int    $count        number of points to parse
     * @param bool   $allowSquare  whether square brackets [] are allowed around points
     * @return array
     * @throws TypeConversionException
     */
    protected function parsePoints(string $native, int &$pos, int $count, bool $allowSquare = false): array
    {
        $hasDelimiters = $squareDelimiter = false;

        $char = $this->nextChar($native, $pos);
        if ('[' === $char) {
            if (!$allowSquare) {
                throw TypeConversionException::parsingFailed($this, "'(' or numeric value", $native, $pos);
            }
            $hasDelimiters = $squareDelimiter = true;
            $pos++;

        } elseif ('(' === $char) {
            $nextPos = $pos + 1;
            if ($pos === strrpos($native, '(') || '(' === $this->nextChar($native, $nextPos)) {
                $hasDelimiters = true;
                $pos++;
            }
        }

        $points = [];
        for ($i = 0; $i < $count; $i++) {
            if ($i > 0) {
                $this->expectChar($native, $pos, ',');
            }
            $points[] = $this->point->parseInput($native, $pos);
        }

        if ($hasDelimiters) {
            $this->expectChar($native, $pos, $squareDelimiter ? ']' : ')');
        }

        if ($allowSquare) {
            $points['open'] = $squareDelimiter;
        }

        return $points;
    }
}
