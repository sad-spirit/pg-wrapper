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

namespace sad_spirit\pg_wrapper\converters\datetime;

use sad_spirit\pg_wrapper\{
    Connection,
    converters\BaseConverter,
    converters\ConnectionAware,
    exceptions\TypeConversionException
};

/**
 * Base class for date/time type converters (except intervals)
 *
 * All of these converters parse dates / times in 'ISO' format by default and
 * return `\DateTimeImmutable` objects.
 */
abstract class BaseDateTimeConverter extends BaseConverter implements ConnectionAware
{
    /**
     * Default DateStyle setting for Postgres
     */
    public const DEFAULT_STYLE = 'ISO, MDY';

    /** Current DateStyle setting, used for input */
    private string $style = self::DEFAULT_STYLE;

    /** Connection, used for checking DateStyle setting */
    private ?Connection $connection = null;

    /** What to display in exception message if conversion failed */
    protected string $expectation = 'date/time string';

    /**
     * Constructor, possibly sets the connection this converter works with
     */
    public function __construct(?Connection $connection = null)
    {
        if (null !== $connection) {
            $this->setConnection($connection);
        }
    }

    /**
     * {@inheritDoc}
     */
    public function setConnection(Connection $connection): void
    {
        $this->connection = $connection;

        $this->updateDateStyleFromConnection();
    }

    /**
     * Tries to update date style from current connection, if available
     *
     * @return bool Whether `$style` actually changed
     */
    private function updateDateStyleFromConnection(): bool
    {
        $style = null !== $this->connection && $this->connection->isConnected()
                 ? \pg_parameter_status($this->connection->getNative(), 'DateStyle')
                 : false;
        if (false === $style || $style === $this->style) {
            return false;
        }

        $this->style = $style;
        return true;
    }

    /**
     * Sets the DateStyle to use when converting date / time fields from native format
     */
    public function setDateStyle(string $style = self::DEFAULT_STYLE): void
    {
        $this->style = $style;
    }

    /**
     * Returns possible formats for a datetime field (usually with and without '.u') based on style
     *
     * @return string[]
     * @throws TypeConversionException
     */
    abstract protected function getFormats(string $style): array;

    protected function inputNotNull(string $native): \DateTimeImmutable
    {
        foreach ($this->getFormats($this->style) as $format) {
            if ($value = \DateTimeImmutable::createFromFormat('!' . $format, $native)) {
                return $value;
            }
        }
        // check whether datestyle setting changed
        if ($this->updateDateStyleFromConnection()) {
            foreach ($this->getFormats($this->style) as $format) {
                if ($value = \DateTimeImmutable::createFromFormat('!' . $format, $native)) {
                    return $value;
                }
            }
        }
        $expectation = \str_contains($this->expectation, '%s')
            ? \sprintf($this->expectation, $this->style)
            : $this->expectation;
        throw TypeConversionException::unexpectedValue($this, 'input', $expectation, $native);
    }

    /**
     * Converts PHP variable not identical to null into native format
     *
     * Actually accepts strings, integers and instances of `\DateTimeInterface`
     *
     * Note: a passed string will be returned as-is without any attempts to parse it.
     * PostgreSQL's date and time parser accepts a lot more possible formats than this
     * class can handle. Integer will be handled using `date()` with an appropriate
     * format specification.
     *
     * @throws TypeConversionException if given a $value of unexpected type
     */
    protected function outputNotNull(mixed $value): string
    {
        if (\is_string($value)) {
            return $value;

        } elseif (\is_int($value)) {
            $formats = $this->getFormats(self::DEFAULT_STYLE);
            return \date($formats[0], $value);

        } elseif ($value instanceof \DateTimeInterface) {
            $formats = $this->getFormats(self::DEFAULT_STYLE);
            return $value->format($formats[0]);
        }

        throw TypeConversionException::unexpectedValue(
            $this,
            'output',
            'a string, an integer or an implementation of DateTimeInterface',
            $value
        );
    }
}
