<?php

/**
 * Wrapper for PHP's pgsql extension providing conversion of complex DB types
 *
 * LICENSE
 *
 * This source file is subject to BSD 2-Clause License that is bundled
 * with this package in the file LICENSE and available at the URL
 * https://raw.githubusercontent.com/sad-spirit/pg-wrapper/master/LICENSE
 *
 * @package   sad_spirit\pg_wrapper
 * @copyright 2014-2020 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-wrapper
 */

namespace sad_spirit\pg_wrapper\tests;

use PHPUnit\Framework\TestCase;
use sad_spirit\pg_wrapper\Connection;
use sad_spirit\pg_wrapper\exceptions\{
    BadMethodCallException,
    ConnectionException,
    server\ConstraintViolationException,
    server\FeatureNotSupportedException,
    server\ProgrammingException
};

/**
 * Tests for callbacks added in atomic() closures
 */
class TransactionCallbacksTest extends TestCase
{
    /**
     * @var Connection
     */
    protected $conn;

    /** @var int[] */
    protected $committed;
    /** @var int[] */
    protected $rolledBack;

    protected function setUp(): void
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this->markTestSkipped('Connection string is not configured');
        }
        $this->conn = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING);
        $this->conn->execute('drop table if exists test_trans');
        $this->conn->execute('create table test_trans (id integer)');

        $this->committed = $this->rolledBack = [];
    }

    protected function doStuff(int $stuffId): void
    {
        $this->conn->onCommit(function () use ($stuffId) {
            $this->committed[] = $stuffId;
        });
        $this->conn->onRollback(function () use ($stuffId) {
            $this->rolledBack[] = $stuffId;
        });
        $this->conn->executeParams('insert into test_trans values ($1)', [$stuffId]);
    }

    protected function assertStuffDone(array $stuff): void
    {
        $this::assertEquals($stuff, $this->committed);
        $this::assertEquals(
            $stuff,
            $this->conn->execute('select id from test_trans order by 1')
                ->fetchColumn('id')
        );
    }

    protected function assertStuffNotDone(array $stuff): void
    {
        $this::assertEquals($stuff, $this->rolledBack);
    }

    public function testDisallowOnCommitOutsideAtomic(): void
    {
        $this::expectException(BadMethodCallException::class);
        $this->conn->onCommit(function () {
            // no-op
        });
    }

    public function testDisallowOnRollbackOutsideAtomic(): void
    {
        $this::expectException(BadMethodCallException::class);
        $this->conn->onCommit(function () {
            // no-op
        });
    }

    public function testCommitCallbacksAfterSuccessfulCommit(): void
    {
        $this->conn->atomic(function () {
            $this->doStuff(1);
            $this::assertEquals([], $this->committed);
        });
        $this->assertStuffDone([1]);
    }

    public function testRollbackCallbacksAfterFailure(): void
    {
        try {
            $this->conn->atomic(function () {
                $this->doStuff(1);
                throw new FeatureNotSupportedException('Oopsie');
            });
        } catch (FeatureNotSupportedException $e) {
        }

        $this->assertStuffDone([]);
        $this->assertStuffNotDone([1]);
    }

    public function testCommitCallbacksAfterFinalCommit(): void
    {
        $this->conn->atomic(function () {
            $this->conn->atomic(function () {
                $this->doStuff(1);
                $this::assertEquals([], $this->committed);
            }, true);
            $this::assertEquals([], $this->committed);
        }, true);
        $this::assertEquals([1], $this->committed);
    }

    public function testCallbacksFromRolledBackSavepoints(): void
    {
        $this->conn->atomic(function () {
            $this->conn->atomic(function () {
                $this->doStuff(1);
            }, true);

            try {
                $this->conn->atomic(function () {
                    $this->doStuff(2);
                    throw new FeatureNotSupportedException('Oopsie');
                }, true);
            } catch (FeatureNotSupportedException $e) {
            }

            // The callbacks should run after final commit
            $this->assertStuffNotDone([]);

            $this->conn->atomic(function () {
                $this->doStuff(3);
            });
        });

        $this->assertStuffDone([1, 3]);
        $this->assertStuffNotDone([2]);
    }

    // A deferred PK constraint will error on commit rather than immediately on insertion of duplicate value
    public function testExceptionDuringCommit(): void
    {
        $this->conn->execute(<<<SQL
alter table test_trans 
    add constraint test_trans_pkey primary key (id) 
        deferrable initially deferred
SQL
        );

        try {
            $this->conn->atomic(function () {
                $this->doStuff(1);
                $this->doStuff(1);
                $this->doStuff(2);
            });
            $this->fail('Expected ConstraintViolationException was not thrown');
        } catch (ConstraintViolationException $e) {
        }

        $this->assertStuffDone([]);
        $this->assertStuffNotDone([1, 1, 2]);
    }

    public function testCallbacksAreClearedAfterCommit(): void
    {
        $this->conn->atomic(function () {
            $this->doStuff(1);
        });

        $this->conn->atomic(function () {
            $this->doStuff(2);
        });

        $this->assertStuffDone([1, 2]);
    }

    public function testCallbacksAreClearedAfterRollback(): void
    {
        try {
            $this->conn->atomic(function () {
                $this->doStuff(1);
                throw new FeatureNotSupportedException('Oopsie');
            });
        } catch (FeatureNotSupportedException $e) {
        }

        $this->conn->atomic(function () {
            $this->doStuff(2);
        });

        $this->assertStuffDone([2]);
        $this->assertStuffNotDone([1]);
    }

    public function testBrokenConnectionInAtomic(): void
    {
        try {
            $this->conn->execute("set idle_in_transaction_session_timeout = 500");
        } catch (ProgrammingException $e) {
            $this::markTestSkipped("Postgres 9.6+ required to run this test");
        }

        try {
            $this->conn->atomic(function () {
                $this->doStuff(1);
                sleep(1);
                $this->doStuff(2);
            });
        } catch (ConnectionException $e) {
        }

        $this::assertFalse($this->conn->isConnected());
        $this->assertStuffNotDone([1, 2]);
    }

    public function testDisconnectInAtomic(): void
    {
        try {
            $this->conn->atomic(function () {
                $this->doStuff(1);
                $this->conn->disconnect();
            });
        } catch (ConnectionException $e) {
        }

        $this::assertFalse($this->conn->isConnected());
        $this->assertStuffNotDone([1]);
    }
}
