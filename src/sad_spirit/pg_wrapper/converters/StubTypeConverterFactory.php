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

namespace sad_spirit\pg_wrapper\converters;

use sad_spirit\pg_wrapper\TypeConverterFactory,
    sad_spirit\pg_wrapper\TypeConverter,
    sad_spirit\pg_wrapper\Connection;

/**
 * Returns StubConverter for any database type
 *
 * Should be used when type conversion is not needed, all values will be returned as strings
 * just as stock pgsql extension does.
 */
class StubTypeConverterFactory implements TypeConverterFactory
{
    /** @var StubConverter */
    private $_converter;
    /** @var Connection */
    private $_connection;

    public function __construct()
    {
        $this->_converter = new StubConverter();
    }

    /**
     * {@inheritdoc}
     */
    public function getConverter($type)
    {
        if ($type instanceof TypeConverter) {
            if ($this->_connection && $type instanceof ConnectionAware) {
                $type->setConnectionResource($this->_connection->getResource());
            }
            return $type;
        }
        return $this->_converter;
    }

    /**
     * {@inheritdoc}
     */
    public function setConnection(Connection $connection)
    {
        $this->_connection = $connection;

        return $this;
    }
}