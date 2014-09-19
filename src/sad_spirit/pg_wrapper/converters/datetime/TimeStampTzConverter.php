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

namespace sad_spirit\pg_wrapper\converters\datetime;

use sad_spirit\pg_wrapper\exceptions\TypeConversionException;

/**
 * Converter for TIMESTAMP WITH TIME ZONE type
 */
class TimeStampTzConverter extends BaseDateTimeConverter
{
    protected $expectation = 'date and time string with time zone info';

    protected function getFormats($style)
    {
        list($output, $order) = array_map('trim', explode(',', $style));

        if (0 === strcasecmp('ISO', $output)) {
            return array('Y-m-d H:i:s.uO', 'Y-m-d H:i:sO');

        } elseif (0 === strcasecmp('Postgres', $output)) {
            return 0 === strcasecmp('DMY', $order)
                   ? array('* d M H:i:s.u Y T', '* d M H:i:s Y T')
                   : array('* M d H:i:s.u Y T', '* M d H:i:s Y T');

        } elseif (0 === strcasecmp('SQL', $output)) {
            return 0 === strcasecmp('DMY', $order)
                   ? array('d/m/Y H:i:s.u T', 'd/m/Y H:i:s T')
                   : array('m/d/Y H:i:s.u T', 'm/d/Y H:i:s T');

        } elseif (0 === strcasecmp('German', $output)) {
            return array('d.m.Y H:i:s.u T', 'd.m.Y H:i:s T');
        }

        throw TypeConversionException::unexpectedValue($this, 'input', 'valid DateStyle setting', $style);
    }
}
