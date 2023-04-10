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
 * @copyright 2014-2023 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-wrapper
 */

declare(strict_types=1);

namespace sad_spirit\pg_wrapper\exceptions;

/**
 * Contains helper method to build a string representation of value
 */
trait Stringifier
{
    /**
     * Returns a string representation of $value for exception message
     *
     * @param mixed $value
     * @return string
     */
    protected static function stringify($value): string
    {
        if (is_object($value)) {
            return 'Object(' . get_class($value) . ')';

        } elseif (is_array($value)) {
            $strings = [];
            foreach ($value as $k => $v) {
                $strings[] = sprintf('%s => %s', $k, self::stringify($v));
            }
            return 'Array(' . implode(', ', $strings) . ')';

        } elseif (is_resource($value)) {
            return 'Resource (' . get_resource_type($value) . ')';

        } elseif (is_null($value)) {
            return 'null';

        } elseif (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        return "'" . (string)$value . "'";
    }
}
