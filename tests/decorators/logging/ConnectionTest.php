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

namespace sad_spirit\pg_wrapper\tests\decorators\logging;

use PHPUnit\Framework\TestCase;
use Psr\Log\Test\TestLogger;
use sad_spirit\pg_wrapper\Connection;
use sad_spirit\pg_wrapper\decorators\logging\Connection as LoggingConnection;

class ConnectionTest extends TestCase
{
    private TestLogger $logger;
    private LoggingConnection $connection;

    protected function setUp(): void
    {
        $wrapped = $this->createMock(Connection::class);
        $wrapped->method('getConnectionId')
            ->willReturn('pg-fake');

        $this->logger     = new TestLogger();
        $this->connection = new LoggingConnection($wrapped, $this->logger);
    }

    public function testExplicitConnect(): void
    {
        $this->connection->connect();

        $this::assertTrue($this->logger->hasDebug([
            'message' => 'Connecting explicitly, connection ID {connectionId}',
            'context' => [
                'connectionId' => 'pg-fake'
            ]
        ]));
    }

    public function testExplicitDisconnect(): void
    {
        $this->connection->disconnect();

        $this::assertTrue($this->logger->hasDebug([
            'message' => 'Disconnecting explicitly, connection ID {connectionId}',
            'context' => [
                'connectionId' => 'pg-fake'
            ]
        ]));
    }

    public function testExecute(): void
    {
        $this->connection->execute('drop table pg_class');

        $this::assertTrue($this->logger->hasDebug([
            'message' => 'Executing statement {sql}',
            'context' => [
                'sql' => 'drop table pg_class'
            ]
        ]));
    }

    public function testExecuteParams(): void
    {
        $this->connection->executeParams('delete from pg_type where oid = $1', [16], ['integer']);

        $this::assertTrue($this->logger->hasDebug([
            'message' => 'Executing statement {sql} with parameters {params}, types {types}',
            'context' => [
                'sql'    => 'delete from pg_type where oid = $1',
                'params' => [16],
                'types'  => ['integer']
            ]
        ]));
    }

    public function testPrepareBindExecute(): void
    {
        $value     = 2;
        $statement = $this->connection->prepare('select $1, $2');
        $statement->bindValue(1, 1);
        $statement->bindParam(2, $value);
        $statement->execute();

        $this::assertTrue($this->logger->hasDebug([
            'message' => 'Executing prepared statement {sql} with bound parameters {params}',
            'context' => [
                'sql'    => 'select $1, $2',
                'params' => [1, 2],
            ]
        ]));
    }

    public function testPrepareExecuteParams(): void
    {
        $statement = $this->connection->prepare('select $1, $2');
        $statement->executeParams([1, 2]);

        $this::assertTrue($this->logger->hasDebug([
            'message' => 'Executing prepared statement {sql} with parameters {params}',
            'context' => [
                'sql'    => 'select $1, $2',
                'params' => [1, 2],
            ]
        ]));
    }

    public function testBeginCommitRollback(): void
    {
        $this->connection->beginTransaction();
        $this->connection->commit();
        $this->connection->rollBack();

        $this::assertTrue($this->logger->hasDebug('Beginning transaction'));
        $this::assertTrue($this->logger->hasDebug('Committing transaction'));
        $this::assertTrue($this->logger->hasDebug('Rolling back transaction'));
    }

    public function testSavepoints(): void
    {
        $this->connection->createSavepoint('fake');
        $this->connection->releaseSavepoint('fake');
        $this->connection->rollbackToSavepoint('fake');

        $this::assertTrue($this->logger->hasDebug([
            'message' => 'Creating savepoint {name}',
            'context' => [
                'name' => 'fake'
            ]
        ]));
        $this::assertTrue($this->logger->hasDebug([
            'message' => 'Releasing savepoint {name}',
            'context' => [
                'name' => 'fake'
            ]
        ]));
        $this::assertTrue($this->logger->hasDebug([
            'message' => 'Rolling back to savepoint {name}',
            'context' => [
                'name' => 'fake'
            ]
        ]));
    }

    public function testAtomic(): void
    {
        $this->connection->atomic(function (Connection $connection) {
            $connection->execute('drop database production');
        }, true);

        $this::assertTrue($this->logger->hasDebug([
            'message' => 'Executing a callback atomically with savepoint enabled'
        ]));
        $this::assertTrue($this->logger->hasDebug([
            'message' => 'Finished executing a callback atomically'
        ]));
    }
}
