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

namespace sad_spirit\pg_wrapper\converters\datetime;

use sad_spirit\pg_wrapper\exceptions\TypeConversionException;

/**
 * Converter for TIME [WITHOUT TIME ZONE] type
 */
class TimeConverter extends BaseDateTimeConverter
{
    protected function getFormats($style)
    {
        return ['H:i:s.u', 'H:i:s'];
    }

    protected function inputNotNull($native)
    {
        foreach ($this->getFormats(self::DEFAULT_STYLE) as $format) {
            if ($value = \DateTime::createFromFormat('!' . $format, $native)) {
                return $value;
            }
        }
        throw TypeConversionException::unexpectedValue(
            $this, 'input', 'time string without time zone info', $native
        );
    }
}
