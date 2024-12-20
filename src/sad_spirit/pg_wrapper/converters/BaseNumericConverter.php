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

use sad_spirit\pg_wrapper\Connection;

/**
 * Base class for numeric converters, used to check for connection to Postgres 16+
 */
abstract class BaseNumericConverter extends BaseConverter implements ConnectionAware
{
    public const REGEXP_INTEGER = '/^-?(?>0[bB](_?[01])+|0[oO](_?[0-7])+|0[xX](_?[0-9a-fA-F])+|\d(_?\d)*)$/';
    public const REGEXP_REAL    = '/^-?(\d(_?\d)*(\.(\d(_?\d)*)?)?|\.\d(_?\d)*)([Ee][-+]?\d(_?\d)*)?$/';

    private ?bool $allowNonDecimal = null;
    private ?Connection $connection = null;

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
            \pg_parameter_status($this->connection->getNative(), 'server_version'),
            '15.999',
            '>='
        );
    }
}
