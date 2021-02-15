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
 * @copyright 2014-2021 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-wrapper
 */

declare(strict_types=1);

namespace sad_spirit\pg_wrapper\types;

/**
 * Class representing 'polygon' geometric type
 */
final class Polygon extends PointList implements ArrayRepresentable
{
    /**
     * Creates a Polygon from a given array
     *
     * @param array $input Expects an array of Points or Point-compatible arrays (=two floats)
     * @return self
     */
    public static function createFromArray(array $input): self
    {
        return new self(...self::createPointArray($input));
    }
}
