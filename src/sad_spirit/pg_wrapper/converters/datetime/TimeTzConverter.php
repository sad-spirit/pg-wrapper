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
