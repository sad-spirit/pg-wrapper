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

namespace sad_spirit\pg_wrapper\exceptions\server;

use sad_spirit\pg_wrapper\exceptions\ServerException;
use sad_spirit\pg_wrapper\exceptions\SqlState;

/**
 * Thrown when database integrity constraint is violated
 */
class ConstraintViolationException extends ServerException
{
    /** Name of violated constraint */
    private ?string $constraintName = null;

    public function __construct(string $message = "", SqlState $sqlState = null, \Throwable $previous = null)
    {
        parent::__construct($message, $sqlState, $previous);

        // NOT NULL violation messages do not contain constraint names, in case of any other violation
        // try to extract the name
        if (SqlState::NOT_NULL_VIOLATION !== $sqlState) {
            $parts = \preg_split("/\n/", $message, -1, \PREG_SPLIT_NO_EMPTY) ?: [];
            if (
                \count($parts) > 2
                // last line of message points to source file and line of error?
                && \preg_match('/\.c:\d+$/', $parts[\count($parts) - 1])
                // previous line should have constraint name, unfortunately "CONSTRAINT NAME" string can be localized
                && \preg_match('/:\s+(.*)$/', $parts[\count($parts) - 2], $m)
                // constraint name is repeated in main error message?
                && \str_contains($parts[0], $m[1])
            ) {
                $this->constraintName = $m[1];
            }
        }
    }

    /**
     * Returns the name of the violated constraint, if available
     *
     * @return string|null
     */
    public function getConstraintName(): ?string
    {
        return $this->constraintName;
    }
}
