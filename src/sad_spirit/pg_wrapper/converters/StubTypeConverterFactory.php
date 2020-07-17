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

use sad_spirit\pg_wrapper\{
    TypeConverterFactory,
    TypeConverter,
    Connection
};

/**
 * Returns StubConverter for any database type
 *
 * Should be used when type conversion is not needed, all values will be returned as strings
 * just as stock pgsql extension does.
 */
final class StubTypeConverterFactory implements TypeConverterFactory
{
    /** @var StubConverter */
    private $converter;
    /** @var Connection */
    private $connection;

    public function __construct()
    {
        $this->converter = new StubConverter();
    }

    /**
     * {@inheritdoc}
     */
    public function getConverterForTypeSpecification($type): TypeConverter
    {
        if ($type instanceof TypeConverter) {
            if ($this->connection && $type instanceof ConnectionAware) {
                $type->setConnectionResource($this->connection->getResource());
            }
            return $type;
        }
        return $this->converter;
    }

    /**
     * {@inheritdoc}
     */
    public function setConnection(Connection $connection): TypeConverterFactory
    {
        $this->connection = $connection;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function getConverterForPHPValue($value): TypeConverter
    {
        return $this->converter;
    }

    /**
     * {@inheritdoc}
     */
    public function getConverterForTypeOID(int $oid): TypeConverter
    {
        return $this->converter;
    }
}
