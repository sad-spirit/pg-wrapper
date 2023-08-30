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

use sad_spirit\pg_wrapper\Connection;

/**
 * Base class for numeric converters, used to check for connection to Postgres 16+
 */
abstract class BaseNumericConverter extends BaseConverter implements ConnectionAware
{
    public const REGEXP_INTEGER = '/^-?(?>0[bB](_?[01])+|0[oO](_?[0-7])+|0[xX](_?[0-9a-fA-F])+|\d(_?\d)*)$/';
    public const REGEXP_REAL    = '/^-?(\d(_?\d)*(\.(\d(_?\d)*)?)?|\.\d(_?\d)*)([Ee][-+]?\d(_?\d)*)?$/';

    /** @var bool|null  */
    private $allowNonDecimal = null;
    /** @var Connection|null  */
    private $connection = null;

    public function __construct(?Connection $connection = null)
    {
        if (null !== $connection) {
            $this->setConnection($connection);
        }
    }

    public function setConnection(Connection $connection): void
    {
        $this->connection = $connection;
    }

    /**
     * Sets whether converter should accept non-decimal numeric literals and underscores as digit separators
     */
    public function setAllowNonDecimalLiteralsAndUnderscores(bool $allow): void
    {
        $this->allowNonDecimal = $allow;
    }

    /**
     * Returns whether converter accepts non-decimal numeric literals and underscores as digit separators
     *
     * @return bool
     */
    public function allowNonDecimalLiteralsAndUnderscores(): bool
    {
        return (bool)(
            $this->allowNonDecimal
            ?? ($this->allowNonDecimal = $this->getAllowNonDecimalLiteralsFromConnection())
        );
    }

    /**
     * Checks whether the connection is made to Postgres 16+ or not
     *
     * @return bool|null Returns null if no connection available or connection is closed
     */
    protected function getAllowNonDecimalLiteralsFromConnection(): ?bool
    {
        if (null === $this->connection || !$this->connection->isConnected()) {
            return null;
        }

        return \version_compare(
            \pg_parameter_status($this->connection->getResource(), 'server_version'),
            '15.999',
            '>='
        );
    }
}
