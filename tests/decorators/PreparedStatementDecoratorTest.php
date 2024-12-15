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

namespace sad_spirit\pg_wrapper\tests\decorators;

use PHPUnit\Framework\TestCase;
use sad_spirit\pg_wrapper\{
    PreparedStatement,
    Result,
    decorators\PreparedStatementDecorator
};

/**
 * Unit test for PreparedStatementDecorator class
 */
class PreparedStatementDecoratorTest extends TestCase
{
    public function testSetResultTypes(): void
    {
        $statement = $this->createMock(PreparedStatement::class);
        $decorator = $this->createDecorator($statement);
        $statement->expects($this->once())
            ->method('setResultTypes')
            ->with(['foo', 'bar']);

        $this::assertSame($decorator, $decorator->setResultTypes(['foo', 'bar']));
    }

    public function testPrepareDeallocate(): void
    {
        $statement = $this->createMock(PreparedStatement::class);
        $decorator = $this->createDecorator($statement);
        $statement->expects($this->once())
            ->method('prepare');
        $statement->expects($this->once())
            ->method('deallocate');

        $this::assertSame($decorator, $decorator->prepare());
        $this::assertSame($decorator, $decorator->deallocate());
    }

    public function testParameterTypes(): void
    {
        $statement = $this->createMock(PreparedStatement::class);
        $decorator = $this->createDecorator($statement);
        $statement->expects($this->once())
            ->method('fetchParameterTypes')
            ->with(true);
        $statement->expects($this->once())
            ->method('setNumberOfParameters')
            ->with(10);
        $statement->expects($this->once())
            ->method('setParameterType')
            ->with(2, 'integer[]');

        $this::assertSame($decorator, $decorator->fetchParameterTypes(true));
        $this::assertSame($decorator, $decorator->setNumberOfParameters(10));
        $this::assertSame($decorator, $decorator->setParameterType(2, 'integer[]'));
    }

    public function testBind(): void
    {
        $param     = 'foo';
        $statement = $this->createMock(PreparedStatement::class);
        $decorator = $this->createDecorator($statement);
        $statement->expects($this->once())
            ->method('bindParam')
            ->with(1, $param);
        $statement->expects($this->once())
            ->method('bindValue')
            ->with(2, 'a value', 'a type');

        $this::assertSame($decorator, $decorator->bindParam(1, $param));
        $this::assertSame($decorator, $decorator->bindValue(2, 'a value', 'a type'));
    }

    public function testExecute(): void
    {
        $result    = $this->createMock(Result::class);
        $statement = $this->createMock(PreparedStatement::class);
        $decorator = $this->createDecorator($statement);
        $statement->expects($this->once())
            ->method('execute')
            ->willReturn($result);
        $statement->expects($this->once())
            ->method('executeParams')
            ->with(['foo', 'bar'])
            ->willReturn($result);

        $this::assertSame($result, $decorator->execute());
        $this::assertSame($result, $decorator->executeParams(['foo', 'bar']));
    }

    private function createDecorator(PreparedStatement $wrapped): PreparedStatementDecorator
    {
        return new class ($wrapped) extends PreparedStatementDecorator {
        };
    }
}
