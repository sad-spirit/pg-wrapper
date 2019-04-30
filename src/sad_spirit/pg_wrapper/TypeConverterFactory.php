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
 * @copyright 2014-2017 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-wrapper
 */

declare(strict_types=1);

namespace sad_spirit\pg_wrapper;

use sad_spirit\pg_wrapper\exceptions\TypeConversionException;

/**
 * Interface for classes that create type converters
 */
interface TypeConverterFactory
{
    /**
     * Returns a converter for a given database type
     *
     * @param mixed $type
     * @return TypeConverter
     */
    public function getConverter($type): TypeConverter;

    /**
     * Tries to return a converter based on type of $value
     *
     * Should throw TypeConversionException if it is not possible to find a proper converter, e.g.
     *  - input is ambiguous (PHP arrays can map to several DB types)
     *  - $value is an instance of class not explicitly known to Factory
     *
     * @param mixed $value
     * @return TypeConverter
     * @throws TypeConversionException
     */
    public function getConverterForPHPValue($value): TypeConverter;

    /**
     * Sets database connection details for this object
     *
     * @param Connection $connection
     * @return $this
     */
    public function setConnection(Connection $connection): self;
}
