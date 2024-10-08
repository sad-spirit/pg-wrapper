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
 * @copyright 2014-2024 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-wrapper
 */

declare(strict_types=1);

namespace sad_spirit\pg_wrapper\converters\datetime;

use sad_spirit\pg_wrapper\exceptions\TypeConversionException;

/**
 * Converter for TIME WITH TIME ZONE type
 *
 * Not recommended to use even in the official PostgreSQL manual.
 */
class TimeTzConverter extends BaseDateTimeConverter
{
    protected function getFormats(string $style): array
    {
        return ['H:i:s.uO', 'H:i:sO'];
    }

    protected function inputNotNull(string $native): \DateTimeImmutable
    {
        foreach ($this->getFormats(self::DEFAULT_STYLE) as $format) {
            if ($value = \DateTimeImmutable::createFromFormat('!' . $format, $native)) {
                return $value;
            }
        }
        throw TypeConversionException::unexpectedValue(
            $this,
            'input',
            'time string with time zone info',
            $native
        );
    }
}
