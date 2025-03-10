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

namespace sad_spirit\pg_wrapper\decorators;

use sad_spirit\pg_wrapper\PreparedStatement;
use sad_spirit\pg_wrapper\Result;

/**
 * Decorator for PreparedStatement class
 *
 * This base class forwards all method calls to the decorated instance, child classes will implement
 * the additional logic
 *
 * @since 3.0.0
 */
abstract class PreparedStatementDecorator extends PreparedStatement
{
    /** @noinspection PhpMissingParentConstructorInspection */
    public function __construct(private readonly PreparedStatement $wrapped)
    {
    }

    public function __destruct()
    {
        // No-op, logic from parent __destruct() is not needed here
    }

    public function __clone()
    {
        // No-op, logic from parent __clone() is not needed here
    }

    public function setResultTypes(array $resultTypes): self
    {
        $this->wrapped->setResultTypes($resultTypes);

        return $this;
    }

    public function prepare(): self
    {
        $this->wrapped->prepare();

        return $this;
    }

    public function deallocate(): self
    {
        $this->wrapped->deallocate();

        return $this;
    }

    public function fetchParameterTypes(bool $overrideExistingTypes = false): self
    {
        $this->wrapped->fetchParameterTypes($overrideExistingTypes);

        return $this;
    }

    public function setNumberOfParameters(int $numberOfParameters): self
    {
        $this->wrapped->setNumberOfParameters($numberOfParameters);

        return $this;
    }

    public function setParameterType(int $parameterNumber, mixed $type): PreparedStatement
    {
        $this->wrapped->setParameterType($parameterNumber, $type);

        return $this;
    }

    public function bindValue(int $parameterNumber, mixed $value, mixed $type = null): self
    {
        $this->wrapped->bindValue($parameterNumber, $value, $type);

        return $this;
    }

    public function bindParam(int $parameterNumber, mixed &$param, mixed $type = null): self
    {
        $this->wrapped->bindParam($parameterNumber, $param, $type);

        return $this;
    }

    public function execute(): Result
    {
        return $this->wrapped->execute();
    }

    public function executeParams(array $params): Result
    {
        return $this->wrapped->executeParams($params);
    }
}
