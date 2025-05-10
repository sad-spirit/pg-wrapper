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
 * Converter for TIMESTAMP WITH TIME ZONE type
 */
class TimeStampTzConverter extends BaseDateTimeConverter
{
    protected string $expectation = "date and time string in '%s' format with time zone info";

    protected function getFormats(string $style): array
    {
        [$output, $order] = \array_map(
            static fn(string $item): string => \trim($item, self::WHITESPACE),
            \explode(',', $style)
        );

        if (0 === \strcasecmp('ISO', $output)) {
            return ['Y-m-d H:i:s.uO', 'Y-m-d H:i:sO'];

        } elseif (0 === \strcasecmp('Postgres', $output)) {
            return 0 === \strcasecmp('DMY', $order)
                   ? ['* d M H:i:s.u Y T', '* d M H:i:s Y T']
                   : ['* M d H:i:s.u Y T', '* M d H:i:s Y T'];

        } elseif (0 === \strcasecmp('SQL', $output)) {
            return 0 === \strcasecmp('DMY', $order)
                   ? ['d/m/Y H:i:s.u T', 'd/m/Y H:i:s T']
                   : ['m/d/Y H:i:s.u T', 'm/d/Y H:i:s T'];

        } elseif (0 === \strcasecmp('German', $output)) {
            return ['d.m.Y H:i:s.u T', 'd.m.Y H:i:s T'];
        }

        throw TypeConversionException::unexpectedValue($this, 'input', 'valid DateStyle setting', $style);
    }
}
