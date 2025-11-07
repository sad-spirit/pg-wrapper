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

namespace sad_spirit\pg_wrapper\converters;

use sad_spirit\pg_wrapper\TypeConverter;

/**
 * Implementation of TypeConverter that performs no conversion
 *
 * Always returned by {@see \sad_spirit\pg_wrapper\converters\StubTypeConverterFactory StubTypeConverterFactory},
 * returned by {@see \sad_spirit\pg_wrapper\converters\DefaultTypeConverterFactory DefaultTypeConverterFactory}
 * in case proper converter could not be determined.
 */
class StubConverter implements TypeConverter
{
    /**
     * {@inheritdoc}
     */
    public function output(mixed $value): ?string
    {
        return null === $value ? null : (string)$value;
    }

    /**
     * {@inheritdoc}
     */
    public function input(?string $native): ?string
    {
        return $native;
    }

    /**
     * {@inheritdoc}
     */
    public function dimensions(): int
    {
        return 0;
    }
}
