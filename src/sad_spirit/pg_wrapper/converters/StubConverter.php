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
 * @copyright 2014-2020 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-wrapper
 */

declare(strict_types=1);

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
    public function output($value): ?string
    {
        return $value;
    }

    /**
     * {@inheritdoc}
     */
    public function input(?string $native)
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
