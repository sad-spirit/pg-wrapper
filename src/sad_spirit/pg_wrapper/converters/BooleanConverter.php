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
 * @copyright 2014-2023 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-wrapper
 */

declare(strict_types=1);

namespace sad_spirit\pg_wrapper\converters;

/**
 * Converter for boolean type
 */
class BooleanConverter extends BaseConverter
{
    protected function inputNotNull(string $native)
    {
        $native = trim($native);
        return !($native === 'false' || $native === 'f' || $native === '0' || $native === '');
    }

    protected function outputNotNull($value): string
    {
        return (!$value || $value === 'false' || $value === 'f') ? 'f' : 't';
    }
}
