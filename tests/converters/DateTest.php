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

namespace sad_spirit\pg_wrapper\tests\converters;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use sad_spirit\pg_wrapper\Connection;
use sad_spirit\pg_wrapper\converters\datetime\DateConverter;
use sad_spirit\pg_wrapper\exceptions\InvalidArgumentException;
use sad_spirit\pg_wrapper\exceptions\TypeConversionException;

/**
 * Unit test for date type converter
 */
class DateTest extends TestCase
{
    protected DateConverter $caster;

    public function setUp(): void
    {
        $this->caster = new DateConverter();
    }

    public function testIgnoresClosedConnection(): void
    {
        if (!TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING) {
            $this::markTestSkipped('Connection string is not configured');
        }

        $connection = new Connection(TESTS_SAD_SPIRIT_PG_WRAPPER_CONNECTION_STRING);
        $connection->execute("set datestyle to 'German'");
        $this->caster->setConnection($connection);

        $this::assertEquals('2021-02-11', $this->caster->input('11.02.2021')->format('Y-m-d'));
        $connection->disconnect();

        $this::expectException(TypeConversionException::class);
        $this->caster->input('2021-02-11');
    }

    #[DataProvider('getValuesFrom')]
    public function testCastFrom(?string $style, ?string $native, ?\DateTime $value): void
    {
        if ($value instanceof \Throwable) {
            $this->expectException($value::class);
        }
        if (null !== $style) {
            $this->caster->setDateStyle($style);
        }
        $this->assertEquals($value, $this->caster->input($native));
    }

    #[DataProvider('getValuesTo')]
    public function testCastTo(string|\Throwable|null $native, mixed $value): void
    {
        if ($native instanceof \Throwable) {
            $this->expectException($native::class);
        }
        $this->assertEquals($native, $this->caster->output($value));
    }

    public static function getValuesFrom(): array
    {
        return [
            [null,             null,         null],
            [null,             '2001-02-03', new \DateTime('2001-02-03')],
            [null,             '1800-01-01', new \DateTime('1800-01-01')],
            ['ISO, MDY',       '2001-02-03', new \DateTime('2001-02-03')],
            ['Postgres, DMY',  '03-02-2001', new \DateTime('2001-02-03')],
            ['Postgres, MDY',  '02-03-2001', new \DateTime('2001-02-03')],
            ['SQL, DMY',       '03/02/2001', new \DateTime('2001-02-03')],
            ['SQL, MDY',       '02/03/2001', new \DateTime('2001-02-03')],
            ['German, YMD',    '03.02.2001', new \DateTime('2001-02-03')]
        ];
    }

    public static function getValuesTo(): array
    {
        $dateTime = new \DateTime('2001-05-25');
        return [
            ['whatever',       'whatever'],
            ['1970-01-01',     0],
            ['2001-05-25',     $dateTime],
            ['2001-05-25',     $dateTime->getTimestamp()],
            ['2001-05-25',     \DateTimeImmutable::createFromMutable($dateTime)],

            [new TypeConversionException(), false],
            [new TypeConversionException(), 1.234],
            [new TypeConversionException(), []],
            [new TypeConversionException(), new \stdClass()]
        ];
    }
}
