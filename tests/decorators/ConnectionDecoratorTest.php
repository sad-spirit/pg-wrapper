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

namespace sad_spirit\pg_wrapper\tests\decorators;

use sad_spirit\pg_wrapper\{
    Connection,
    decorators\ConnectionDecorator,
    PreparedStatement,
    Result,
    TypeConverter,
    TypeConverterFactory
};
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemPoolInterface;

/**
 * Unit test for ConnectionDecorator class
 */
class ConnectionDecoratorTest extends TestCase
{
    public function testConnecting(): void
    {
        $connection = $this->createMock(Connection::class);
        $decorator  = $this->createDecorator($connection);
        $connection->expects($this->once())
            ->method('connect');
        $connection->expects($this->once())
            ->method('isConnected')
            ->willReturn(true);
        $connection->expects($this->once())
            ->method('disconnect');

        $this::assertSame($decorator, $decorator->connect());
        $this::assertTrue($decorator->isConnected());
        $this::assertSame($decorator, $decorator->disconnect());
    }

    public function testGetLastError(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('getLastError')
            ->willReturn('oh noes!');

        $this::assertEquals('oh noes!', $this->createDecorator($connection)->getLastError());
    }

    public function testGetNative(): void
    {
        // We cannot mock \PgSql\Connection, so just throw an exception
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('getNative')
            ->willThrowException(new \Exception('oh noes!'));

        $this::expectException(\Exception::class);
        $this::expectExceptionMessage('oh noes!');
        $this->createDecorator($connection)->getNative();
    }

    public function testGetConnectionId(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('getConnectionId')
            ->willReturn('an id');

        $this::assertEquals('an id', $this->createDecorator($connection)->getConnectionId());
    }

    public function testQuoting(): void
    {
        $connection = $this->createMock(Connection::class);
        $decorator  = $this->createDecorator($connection);
        $connection->expects($this->once())
            ->method('quote')
            ->willReturn('quoted value');
        $connection->expects($this->once())
            ->method('quoteIdentifier')
            ->willReturn('quoted identifier');

        $this::assertEquals('quoted value', $decorator->quote('some value'));
        $this::assertEquals('quoted identifier', $decorator->quoteIdentifier('some identifier'));
    }

    public function testPrepare(): void
    {
        $statement  = $this->createMock(PreparedStatement::class);
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('prepare')
            ->willReturn($statement);

        $this::assertSame($statement, $this->createDecorator($connection)->prepare('select 1'));
    }

    public function testExecute(): void
    {
        $result     = $this->createMock(Result::class);
        $connection = $this->createMock(Connection::class);
        $decorator  = $this->createDecorator($connection);
        $connection->expects($this->once())
            ->method('execute')
            ->with('select 1')
            ->willReturn($result);
        $connection->expects($this->once())
            ->method('executeParams')
            ->with('select $1', [1])
            ->willReturn($result);

        $this::assertSame($result, $decorator->execute('select 1'));
        $this::assertSame($result, $decorator->executeParams('select $1', [1]));
    }

    public function testConverters(): void
    {
        $converter  = $this->createMock(TypeConverter::class);
        $factory    = $this->createMock(TypeConverterFactory::class);
        $connection = $this->createMock(Connection::class);
        $decorator  = $this->createDecorator($connection);
        $connection->expects($this->once())
            ->method('setTypeConverterFactory')
            ->with($factory);
        $connection->expects($this->once())
            ->method('getTypeConverterFactory')
            ->willReturn($factory);
        $connection->expects($this->once())
            ->method('getTypeConverter')
            ->with('foo')
            ->willReturn($converter);

        $this::assertSame($decorator, $decorator->setTypeConverterFactory($factory));
        $this::assertSame($factory, $decorator->getTypeConverterFactory());
        $this::assertSame($converter, $decorator->getTypeConverter('foo'));
    }

    public function testMetadataCache(): void
    {
        $cache = $this->createMock(CacheItemPoolInterface::class);
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('setMetadataCache')
            ->with($cache);
        $connection->expects($this->once())
            ->method('getMetadataCache')
            ->willReturn($cache);
        $decorator  = $this->createDecorator($connection);

        $this::assertSame($decorator, $decorator->setMetadataCache($cache));
        $this::assertSame($cache, $decorator->getMetadataCache());
    }

    public function testTransactions(): void
    {
        $connection = $this->createMock(Connection::class);
        $decorator  = $this->createDecorator($connection);
        $connection->expects($this->once())
            ->method('beginTransaction');
        $connection->expects($this->once())
            ->method('commit');
        $connection->expects($this->once())
            ->method('rollback');
        $connection->expects($this->once())
            ->method('inTransaction')
            ->willReturn(true);

        $this::assertSame($decorator, $decorator->beginTransaction());
        $this::assertSame($decorator, $decorator->commit());
        $this::assertSame($decorator, $decorator->rollback());
        $this::assertTrue($decorator->inTransaction());
    }

    public function testSavepoints(): void
    {
        $connection = $this->createMock(Connection::class);
        $decorator  = $this->createDecorator($connection);
        $connection->expects($this->once())
            ->method('createSavepoint');
        $connection->expects($this->once())
            ->method('releaseSavepoint');
        $connection->expects($this->once())
            ->method('rollbackToSavepoint');

        $this::assertSame($decorator, $decorator->createSavepoint('foo'));
        $this::assertSame($decorator, $decorator->releaseSavepoint('foo'));
        $this::assertSame($decorator, $decorator->rollbackToSavepoint('foo'));
    }

    public function testAtomic(): void
    {
        $connection = $this->createMock(Connection::class);
        $decorator  = $this->createDecorator($connection);
        $connection->expects($this->once())
            ->method('atomic')
            ->willReturnArgument(0);

        $callback = $decorator->atomic(fn(Connection $c) => $this::assertSame($decorator, $c));
        $callback();
    }

    public function testTransactionCallbacks(): void
    {
        $connection = $this->createMock(Connection::class);
        $decorator  = $this->createDecorator($connection);
        $connection->expects($this->once())
            ->method('onCommit');
        $connection->expects($this->once())
            ->method('onRollback');

        $this::assertSame($decorator, $decorator->onCommit(fn() => true));
        $this::assertSame($decorator, $decorator->onRollback(fn() => false));
    }

    public function testRollbackNeeded(): void
    {
        $connection = $this->createMock(Connection::class);
        $decorator  = $this->createDecorator($connection);
        $connection->expects($this->once())
            ->method('setNeedsRollback')
            ->with(true);
        $connection->expects($this->once())
            ->method('needsRollback')
            ->willReturn(false);
        $connection->expects($this->once())
            ->method('assertRollbackNotNeeded')
            ->willThrowException(new \Exception('oh noes!'));

        $this::assertSame($decorator, $decorator->setNeedsRollback(true));
        $this::assertFalse($decorator->needsRollback());
        $this::expectException(\Exception::class);
        $this::expectExceptionMessage('oh noes!');
        $decorator->assertRollbackNotNeeded();
    }

    private function createDecorator(Connection $wrapped): ConnectionDecorator
    {
        return new class ($wrapped) extends ConnectionDecorator {
        };
    }
}
