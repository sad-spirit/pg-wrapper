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

namespace sad_spirit\pg_wrapper\converters\containers;

use sad_spirit\pg_wrapper\converters\{
    BaseNumericConverter,
    ConnectionAware,
    ContainerConverter,
    datetime\BaseDateTimeConverter
};
use sad_spirit\pg_wrapper\types\{
    DateTimeMultiRange,
    MultiRange,
    NumericMultiRange
};
use sad_spirit\pg_wrapper\Connection;
use sad_spirit\pg_wrapper\exceptions\TypeConversionException;
use sad_spirit\pg_wrapper\TypeConverter;

/**
 * Converter for multirange types of Postgres 14+
 */
class MultiRangeConverter extends ContainerConverter implements ConnectionAware
{
    /**
     * input() will return instances of this class
     * @var class-string<MultiRange>
     */
    protected string $resultClass = MultiRange::class;

    /**
     * Constructor, sets converter for the base type
     *
     * @param TypeConverter $subtypeConverter
     * @param class-string<MultiRange>|null $resultClass
     */
    public function __construct(
        /** Converter for the base type of the multirange */
        private readonly TypeConverter $subtypeConverter,
        ?string $resultClass = null
    ) {
        if (null !== $resultClass) {
            $this->resultClass = $resultClass;
        } elseif ($this->subtypeConverter instanceof BaseNumericConverter) {
            $this->resultClass = NumericMultiRange::class;
        } elseif ($this->subtypeConverter instanceof BaseDateTimeConverter) {
            $this->resultClass = DateTimeMultiRange::class;
        }
    }

    /**
     * Propagates $connection to ConnectionAware converters of base type
     *
     * @param Connection $connection
     */
    public function setConnection(Connection $connection): void
    {
        if ($this->subtypeConverter instanceof ConnectionAware) {
            $this->subtypeConverter->setConnection($connection);
        }
    }

    protected function parseInput(string $native, int &$pos): mixed
    {
        $ranges    = [];
        $converter = new RangeConverter($this->subtypeConverter, $this->resultClass::getItemClass());

        $this->expectChar($native, $pos, '{');
        while ('}' !== ($char = $this->nextChar($native, $pos))) {
            // require a comma delimiter between ranges
            if (!empty($ranges)) {
                if (',' !== $char) {
                    throw TypeConversionException::parsingFailed($this, "','", $native, $pos);
                }
                $pos++;
            }
            $ranges[] = $converter->parseInput($native, $pos);
        }
        // skip trailing '}'
        $pos++;

        return new $this->resultClass(...$ranges);
    }

    protected function outputNotNull(mixed $value): string
    {
        if (\is_array($value)) {
            $value = \call_user_func([$this->resultClass, 'createFromArray'], $value);
        } elseif (!$value instanceof MultiRange) {
            throw TypeConversionException::unexpectedValue(
                $this,
                'output',
                'instance of MultiRange or an array',
                $value
            );
        }

        if (0 === \count($value)) {
            return '{}';
        }

        $converter = new RangeConverter($this->subtypeConverter, $this->resultClass::getItemClass());
        $ranges    = [];
        foreach ($value as $range) {
            $ranges[] = $converter->outputNotNull($range);
        }

        return '{' . \implode(',', $ranges) . '}';
    }
}
