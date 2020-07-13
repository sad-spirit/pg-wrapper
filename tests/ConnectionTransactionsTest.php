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
    server\FeatureNotSupportedException
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
        $result = $this->conn->atomic(function () {
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
            $this->conn->atomic(function () {
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
        $this->conn->atomic(function (Connection $connection) {
            $this->store(1);
            $connection->atomic(function () {
                $this->store(2);
            }, true);
        });
        $this->assertStored([1, 2]);
    }

    public function testNestedCommitAndRollback(): void
    {
        $this->conn->atomic(function (Connection $connection) {
            $this->store(1);
            try {
                $connection->atomic(function () {
                    $this->store(2);
                    throw new FeatureNotSupportedException('Oopsie');
                }, true);
            } catch (FeatureNotSupportedException $e) {
            }
        });
        $this->assertStored([1]);
    }

    public function testNestedRollbackAndCommit(): void
    {
        try {
            $this->conn->atomic(function (Connection $connection) {
                $this->store(1);
                $connection->atomic(function () {
                    $this->store(2);
                }, true);
                throw new FeatureNotSupportedException('Oopsie');
            });
        } catch (FeatureNotSupportedException $e) {
        }
        $this->assertStored([]);
    }

    public function testNestedRollbackAndRollback(): void
    {
        try {
            $this->conn->atomic(function () {
                $this->store(1);
                try {
                    $this->conn->atomic(function () {
                        $this->store(2);
                        throw new FeatureNotSupportedException('Oopsie');
                    }, true);
                } catch (FeatureNotSupportedException $e) {
                }
                throw new FeatureNotSupportedException('Another oopsie');
            });
        } catch (FeatureNotSupportedException $e) {
        }
        $this->assertStored([]);
    }

    public function testMergedCommitAndCommit(): void
    {
        $this->conn->atomic(function (Connection $connection) {
            $this->store(1);
            $connection->atomic(function () {
                $this->store(2);
            });
        });
        $this->assertStored([1, 2]);
    }

    public function testMergedCommitAndRollback(): void
    {
        $this->conn->atomic(function (Connection $connection) {
            $this->store(1);
            try {
                $connection->atomic(function () {
                    $this->store(2);
                    throw new FeatureNotSupportedException('Oopsie');
                });
            } catch (FeatureNotSupportedException $e) {
            }
        });
        $this->assertStored([]);
    }

    public function testMergedRollbackAndCommit(): void
    {
        try {
            $this->conn->atomic(function (Connection $connection) {
                $this->store(1);
                $connection->atomic(function () {
                    $this->store(2);
                });
                throw new FeatureNotSupportedException('Oopsie');
            });
        } catch (FeatureNotSupportedException $e) {
        }
        $this->assertStored([]);
    }

    public function testMergedRollbackAndRollback(): void
    {
        try {
            $this->conn->atomic(function () {
                $this->store(1);
                try {
                    $this->conn->atomic(function () {
                        $this->store(2);
                        throw new FeatureNotSupportedException('Oopsie');
                    });
                } catch (FeatureNotSupportedException $e) {
                }
                throw new FeatureNotSupportedException('Another oopsie');
            });
        } catch (FeatureNotSupportedException $e) {
        }
        $this->assertStored([]);
    }
}
