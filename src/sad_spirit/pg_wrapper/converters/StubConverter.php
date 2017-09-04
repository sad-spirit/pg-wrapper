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

namespace sad_spirit\pg_wrapper\converters;

use sad_spirit\pg_wrapper\TypeConverter;

/**
 * Implementation of TypeConverter that performs no conversion
 *
 * Always returned by StubTypeConverterFactory, returned by DefaultTypeConverterFactory in case proper converter
 * could not be determined.
 */
class StubConverter implements TypeConverter
{
    /**
     * {@inheritdoc}
     */
    public function output($value)
    {
        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function input($native)
    {
        return $native;
    }

    /**
     * {@inheritdoc}
     */
    public function dimensions()
    {
        return 0;
    }
}