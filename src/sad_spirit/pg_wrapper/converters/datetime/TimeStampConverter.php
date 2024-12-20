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
 * Converter for TIMESTAMP [WITHOUT TIME ZONE] type
 */
class TimeStampConverter extends BaseDateTimeConverter
{
    protected string $expectation = 'date and time string without time zone info';

    protected function getFormats(string $style): array
    {
        [$output, $order] = \array_map(
            static fn(string $item): string => \trim($item, self::WHITESPACE),
            \explode(',', $style)
        );

        if (0 === \strcasecmp('ISO', $output)) {
            return ['Y-m-d H:i:s.u', 'Y-m-d H:i:s'];

        } elseif (0 === \strcasecmp('Postgres', $output)) {
            return 0 === \strcasecmp('DMY', $order)
                   ? ['* d M H:i:s.u Y', '* d M H:i:s Y']
                   : ['* M d H:i:s.u Y', '* M d H:i:s Y'];

        } elseif (0 === \strcasecmp('SQL', $output)) {
            return 0 === \strcasecmp('DMY', $order)
                   ? ['d/m/Y H:i:s.u', 'd/m/Y H:i:s']
                   : ['m/d/Y H:i:s.u', 'm/d/Y H:i:s'];

        } elseif (0 === \strcasecmp('German', $output)) {
            return ['d.m.Y H:i:s.u', 'd.m.Y H:i:s'];
        }

        throw TypeConversionException::unexpectedValue($this, 'input', 'valid DateStyle setting', $style);
    }
}
