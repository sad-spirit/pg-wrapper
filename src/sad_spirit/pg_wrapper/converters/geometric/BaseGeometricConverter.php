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
    exceptions\TypeConversionException,
    converters\ContainerConverter,
    types\Point
};

/**
 * Base class for geometric types
 */
abstract class BaseGeometricConverter extends ContainerConverter
{
    /** Point instance used to parse input and build output */
    protected readonly PointConverter $point;

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
     * @throws TypeConversionException
     */
    protected function countPoints(string $native): int
    {
        $commas = \substr_count($native, ',');
        if ($commas % 2) {
            return \intval(($commas + 1) / 2);
        } else {
            throw TypeConversionException::unexpectedValue($this, 'input', 'even number of numeric values', $native);
        }
    }

    /**
     * Parses given number of points from native database value
     *
     * @param string    $native          native database value
     * @param int       $pos             position
     * @param int       $count           number of points to parse
     * @param bool      $allowSquare     whether square brackets [] are allowed around points
     * @param bool|null $squareDelimiter whether square brackets were actually used
     * @param-out bool  $squareDelimiter
     * @return Point[]
     * @throws TypeConversionException
     */
    protected function parsePoints(
        string $native,
        int &$pos,
        int $count,
        bool $allowSquare = false,
        ?bool &$squareDelimiter = null
    ): array {
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
            if ($pos === \strrpos($native, '(') || '(' === $this->nextChar($native, $nextPos)) {
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

        return $points;
    }
}
