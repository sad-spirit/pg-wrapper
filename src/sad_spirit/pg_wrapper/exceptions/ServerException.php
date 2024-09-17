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

namespace sad_spirit\pg_wrapper\exceptions;

use sad_spirit\pg_wrapper\Connection;

/**
 * Exception thrown on failed query
 */
class ServerException extends RuntimeException
{
    /**
     * Creates a proper exception object based on connection resource
     *
     * @param Connection $connection
     * @return self
     */
    public static function fromConnection(Connection $connection): self
    {
        $message = $connection->getLastError() ?? 'Unknown error';
        // We can only use pg_result_error_field() with async queries, so just try to parse the message
        // instead. See function pqBuildErrorMessage3() in src/interfaces/libpq/fe-protocol3.c
        if (
            !\preg_match("/^[^\r\n]+: {2}([A-Z0-9]{5}):/", $message, $m)
            || null === $sqlState = SqlState::tryFrom($m[1])
        ) {
            return $connection->isConnected() ? new self($message) : new ConnectionException($message);

        } else {
            // Always throw ConnectionException if connection is broken, no matter what code
            if (!$connection->isConnected()) {
                throw new ConnectionException($message, $sqlState);
            }
            // Make "generic subclass" for the current error code and create a specific exception based on that
            return match ($sqlState->genericSubclass()) {
                SqlState::FEATURE_NOT_SUPPORTED =>
                    new server\FeatureNotSupportedException($message, $sqlState),

                SqlState::DATA_EXCEPTION =>
                    new server\DataException($message, $sqlState),

                SqlState::INTEGRITY_CONSTRAINT_VIOLATION =>
                    new server\ConstraintViolationException($message, $sqlState),

                SqlState::TRANSACTION_ROLLBACK =>
                    new server\TransactionRollbackException($message, $sqlState),

                SqlState::SYNTAX_ERROR_OR_ACCESS_RULE_VIOLATION =>
                    SqlState::INSUFFICIENT_PRIVILEGE === $sqlState
                    ? new server\InsufficientPrivilegeException($message, $sqlState)
                    : new server\ProgrammingException($message, $sqlState),

                SqlState::CARDINALITY_VIOLATION,
                SqlState::CASE_NOT_FOUND,
                SqlState::INVALID_SQL_STATEMENT_NAME,
                SqlState::INVALID_CURSOR_NAME,
                SqlState::INVALID_CATALOG_NAME,
                SqlState::INVALID_SCHEMA_NAME,
                SqlState::WITH_CHECK_OPTION_VIOLATION =>
                    new server\ProgrammingException($message, $sqlState),

                SqlState::OPERATOR_INTERVENTION =>
                    SqlState::QUERY_CANCELED === $sqlState
                    ? new server\QueryCanceledException($message, $sqlState)
                    : new ConnectionException($message, $sqlState),

                SqlState::TRIGGERED_DATA_CHANGE_VIOLATION,
                SqlState::INVALID_AUTHORIZATION_SPECIFICATION,
                SqlState::INSUFFICIENT_RESOURCES,
                SqlState::PROGRAM_LIMIT_EXCEEDED,
                SqlState::OBJECT_NOT_IN_PREREQUISITE_STATE,
                SqlState::SYSTEM_ERROR,
                SqlState::FDW_ERROR =>
                    new server\OperationalException($message, $sqlState),

                SqlState::INVALID_CURSOR_STATE,
                SqlState::INVALID_TRANSACTION_STATE,
                SqlState::DEPENDENT_PRIVILEGE_DESCRIPTORS_STILL_EXIST,
                SqlState::INVALID_TRANSACTION_TERMINATION,
                SqlState::SQL_ROUTINE_EXCEPTION,
                SqlState::EXTERNAL_ROUTINE_EXCEPTION,
                SqlState::EXTERNAL_ROUTINE_INVOCATION_EXCEPTION,
                SqlState::SAVEPOINT_EXCEPTION,
                SqlState::CONFIG_FILE_ERROR,
                SqlState::PLPGSQL_ERROR,
                SqlState::INTERNAL_ERROR =>
                    new server\InternalErrorException($message, $sqlState),

                default => new self($message, $sqlState)
            };
        }
    }

    public function __construct(
        string $message = "",
        /** Error code */
        private readonly ?SqlState $sqlState = null,
        \Throwable $previous = null
    ) {
        parent::__construct($message, 0, $previous);
    }

    /**
     * Returns 'SQLSTATE' error code, if one is available
     */
    public function getSqlState(): ?SqlState
    {
        return $this->sqlState;
    }
}
