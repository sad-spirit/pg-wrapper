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
 * @copyright 2014-2017 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-wrapper
 */

namespace sad_spirit\pg_wrapper\types;

use sad_spirit\pg_wrapper\exceptions\InvalidArgumentException;

/**
 * Class representing a range with numeric bounds
 *
 * Used to convert PostgreSQL's int4range, int8range, numrange types
 */
class NumericRange extends Range
{
    public function __set($name, $value)
    {
        if (('lower' === $name || 'upper' === $name) && null !== $value) {
            if (!is_numeric($value)) {
                throw new InvalidArgumentException("NumericRange {$name} bound should be numeric");
            }
            if ('upper' === $name && null !== $this->lower && floatval($this->lower) > floatval($value)
                || 'lower' === $name && null !== $this->upper && floatval($this->upper) < floatval($value)
            ) {
                throw new InvalidArgumentException(
                    "Range lower bound must be less than or equal to range upper bound"
                );
            }
        }
        parent::__set($name, $value);
    }
}