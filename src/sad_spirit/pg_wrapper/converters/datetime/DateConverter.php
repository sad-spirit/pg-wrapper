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
 * Converter for DATE type
 */
class DateConverter extends BaseDateTimeConverter
{
    protected string $expectation = 'date string';

    protected function getFormats(string $style): array
    {
        [$output, $order] = \array_map('trim', \explode(',', $style));

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
