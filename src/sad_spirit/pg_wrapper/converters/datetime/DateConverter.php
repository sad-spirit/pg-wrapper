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
 * Converter for DATE type
 */
class DateConverter extends BaseDateTimeConverter
{
    protected string $expectation = 'date string';

    protected function getFormats(string $style): array
    {
        [$output, $order] = \array_map(
            static fn(string $item): string => \trim($item, self::WHITESPACE),
            \explode(',', $style)
        );

        if (0 === \strcasecmp('ISO', $output)) {
            return ['Y-m-d'];

        } elseif (0 === \strcasecmp('Postgres', $output)) {
            return 0 === \strcasecmp('DMY', $order) ? ['d-m-Y'] : ['m-d-Y'];

        } elseif (0 === \strcasecmp('SQL', $output)) {
            return 0 === \strcasecmp('DMY', $order) ? ['d/m/Y'] : ['m/d/Y'];

        } elseif (0 === \strcasecmp('German', $output)) {
            return ['d.m.Y'];
        }

        throw TypeConversionException::unexpectedValue($this, 'input', 'valid DateStyle setting', $style);
    }
}
