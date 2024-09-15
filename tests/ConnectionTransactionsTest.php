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

namespace sad_spirit\pg_wrapper\tests;

use PHPUnit\Framework\TestCase;
use sad_spirit\pg_wrapper\Connection;
use sad_spirit\pg_wrapper\exceptions\{
    RuntimeException,
    server\FeatureNotSupportedException,
    server\ProgrammingException
};

/**
 * Unit test for transactions handling in Connection class
 */
class ConnectionTransactionsTest extends TestCase
{
    /**
     * @var Connection
     */
    protected $conn;

    protected function setUp(): void
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this->markTestSkipped('Connection string is not configured');
        }
        $this->conn = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING);
        $this->conn->execute('drop table if exists test_trans');
        $this->conn->execute('create table test_trans (id integer)');
    }

    protected function store(int $id): void
    {
        $this->conn->executeParams('insert into test_trans values ($1)', [$id]);
    }

    protected function assertStored(array $ids): void
    {
        $this::assertEquals(
            $ids,
            $this->conn->execute('select id from test_trans order by 1')
                ->fetchColumn('id')
        );
    }

    public function testCommit(): void
    {
        $result = $this->conn->atomic(function (): string {
            $this->store(1);
            return 'success!';
        });
        $this::assertEquals('success!', $result);
        $this::assertFalse($this->conn->inTransaction());
        $this->assertStored([1]);
    }

    public function testRollback(): void
    {
        try {
            $this->conn->atomic(function (): void {
                $this->store(1);
                throw new FeatureNotSupportedException('Oopsie');
            });
            $this::fail('Expected FeatureNotSupportedException was not thrown');
        } catch (FeatureNotSupportedException $exception) {
            $this->assertEquals('Oopsie', $exception->getMessage());
        }
        $this::assertFalse($this->conn->inTransaction());
        $this->assertStored([]);
    }

    public function testNestedCommitAndCommit(): void
    {
        $this->conn->atomic(function (Connection $connection): void {
            $this->store(1);
            $connection->atomic(function (): void {
                $this->store(2);
            }, true);
        });
        $this->assertStored([1, 2]);
    }

    public function testNestedCommitAndRollback(): void
    {
        $this->conn->atomic(function (Connection $connection): void {
            $this->store(1);
            try {
                $connection->atomic(function (): void {
                    $this->store(2);
                    throw new FeatureNotSupportedException('Oopsie');
                }, true);
            } catch (FeatureNotSupportedException) {
            }
        });
        $this->assertStored([1]);
    }

    public function testNestedRollbackAndCommit(): void
    {
        try {
            $this->conn->atomic(function (Connection $connection): void {
                $this->store(1);
                $connection->atomic(function (): void {
                    $this->store(2);
                }, true);
                throw new FeatureNotSupportedException('Oopsie');
            });
        } catch (FeatureNotSupportedException) {
        }
        $this->assertStored([]);
    }

    public function testNestedRollbackAndRollback(): void
    {
        try {
            $this->conn->atomic(function (): void {
                $this->store(1);
                try {
                    $this->conn->atomic(function (): void {
                        $this->store(2);
                        throw new FeatureNotSupportedException('Oopsie');
                    }, true);
                } catch (FeatureNotSupportedException) {
                }
                throw new FeatureNotSupportedException('Another oopsie');
            });
        } catch (FeatureNotSupportedException) {
        }
        $this->assertStored([]);
    }

    public function testMergedCommitAndCommit(): void
    {
        $this->conn->atomic(function (Connection $connection): void {
            $this->store(1);
            $connection->atomic(function (): void {
                $this->store(2);
            });
        });
        $this->assertStored([1, 2]);
    }

    public function testMergedCommitAndRollback(): void
    {
        $this->conn->atomic(function (Connection $connection): void {
            $this->store(1);
            try {
                $connection->atomic(function (): void {
                    $this->store(2);
                    throw new FeatureNotSupportedException('Oopsie');
                });
            } catch (FeatureNotSupportedException) {
            }
        });
        $this->assertStored([]);
    }

    public function testMergedRollbackAndCommit(): void
    {
        try {
            $this->conn->atomic(function (Connection $connection): void {
                $this->store(1);
                $connection->atomic(function (): void {
                    $this->store(2);
                });
                throw new FeatureNotSupportedException('Oopsie');
            });
        } catch (FeatureNotSupportedException) {
        }
        $this->assertStored([]);
    }

    public function testMergedRollbackAndRollback(): void
    {
        try {
            $this->conn->atomic(function (): void {
                $this->store(1);
                try {
                    $this->conn->atomic(function (): void {
                        $this->store(2);
                        throw new FeatureNotSupportedException('Oopsie');
                    });
                } catch (FeatureNotSupportedException) {
                }
                throw new FeatureNotSupportedException('Another oopsie');
            });
        } catch (FeatureNotSupportedException) {
        }
        $this->assertStored([]);
    }

    public function testAtomicInsideOpenTransaction(): void
    {
        $onCommitCalled = false;

        $this->conn->beginTransaction();

        $this->conn->atomic(function (Connection $connection) use (&$onCommitCalled): void {
            $connection->onCommit(function () use (&$onCommitCalled): void {
                $onCommitCalled = true;
            });
        });

        $this::assertTrue($this->conn->inTransaction(), "Transaction should still be open");
        $this::assertFalse($onCommitCalled, "onCommit callbacks should not be called yet");

        $this->conn->commit();
        $this::assertTrue($onCommitCalled);
    }

    public function testAtomicErrorInsideOpenTransaction(): void
    {
        $onRollbackCalled = false;

        $this->conn->beginTransaction();

        try {
            $this->conn->atomic(function (Connection $connection) use (&$onRollbackCalled): void {
                $connection->onRollback(function () use (&$onRollbackCalled): void {
                    $onRollbackCalled = true;
                });
                $connection->execute('blah');
            });
            $this::fail('Expected ProgrammingException was not thrown');
        } catch (ProgrammingException $e) {
        }

        $this::assertTrue($this->conn->inTransaction(), "Transaction should still be open");
        $this::assertFalse($onRollbackCalled, "onRollback callbacks should not be called yet");
        $this::assertTrue($this->conn->needsRollback());

        // I suspect there's an error here in django: it unconditionally sets needs_rollback = false when
        // entering the outermost atomic block. So in case when first atomic() fails and the second
        // succeeds both can succeed
        try {
            $this->conn->atomic(function (): void {
                // no-op
            });
            $this::fail('Expected RuntimeException was not thrown');
        } catch (RuntimeException $e) {
            $this::assertStringContainsString('marked for rollback', $e->getMessage());
        }

        $this->conn->rollback();
        $this::assertTrue($onRollbackCalled);
    }

    public function testForceRollback(): void
    {
        $this->conn->atomic(function (): void {
            $this->store(1);
            $this::assertFalse($this->conn->needsRollback());
            $this->conn->setNeedsRollback(true);
        });
        $this->assertStored([]);
    }

    public function testPreventRollback(): void
    {
        $this->conn->atomic(function (): void {
            $this->store(1);
            $this->conn->createSavepoint('manual_savepoint');
            try {
                $this->conn->atomic(function (): void {
                    $this->conn->execute('blah');
                });
            } catch (ProgrammingException) {
            }
            $this::assertTrue($this->conn->needsRollback());
            $this->conn->setNeedsRollback(false);
            $this->conn->rollbackToSavepoint('manual_savepoint');
        });
        $this->assertStored([1]);
    }
}
