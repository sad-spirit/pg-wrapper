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

namespace sad_spirit\pg_wrapper\decorators;

use sad_spirit\pg_wrapper\PreparedStatement;
use sad_spirit\pg_wrapper\Result;

/**
 * Decorator for PreparedStatement class
 *
 * This base class delegates all method calls to the decorated instance, child classes will implement
 * the additional logic
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
