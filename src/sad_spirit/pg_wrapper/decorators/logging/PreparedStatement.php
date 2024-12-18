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

namespace sad_spirit\pg_wrapper\decorators\logging;

use sad_spirit\pg_wrapper\{
    PreparedStatement as BasePreparedStatement,
    Result,
    decorators\PreparedStatementDecorator
};
use Psr\Log\LoggerInterface;

/**
 * Decorator for PreparedStatement that logs queries using an implementation of PSR-3 LoggerInterface
 *
 * @since 3.0.0
 */
final class PreparedStatement extends PreparedStatementDecorator
{
    /** @var array<int, mixed> */
    private array $values = [];

    public function __construct(
        BasePreparedStatement $wrapped,
        private readonly LoggerInterface $logger,
        private readonly string $sql
    ) {
        parent::__construct($wrapped);
    }

    public function bindValue(int $parameterNumber, mixed $value, mixed $type = null): self
    {
        parent::bindValue($parameterNumber, $value, $type);

        // AFTER call to parent::bindValue() as that can fail
        $this->values[$parameterNumber - 1] = $value;

        return $this;
    }

    public function bindParam(int $parameterNumber, mixed &$param, mixed $type = null): self
    {
        parent::bindParam($parameterNumber, $param, $type);

        // AFTER call to parent::bindParam() as that can fail
        $this->values[$parameterNumber - 1] =& $param;

        return $this;
    }


    public function execute(): Result
    {
        $this->logger->debug(
            "Executing prepared statement {sql} with bound parameters {params}",
            ['sql' => $this->sql, 'params' => $this->values]
        );

        return parent::execute();
    }

    public function executeParams(array $params): Result
    {
        $this->logger->debug(
            "Executing prepared statement {sql} with parameters {params}",
            ['sql' => $this->sql, 'params' => $params]
        );

        return parent::executeParams($params);
    }
}
