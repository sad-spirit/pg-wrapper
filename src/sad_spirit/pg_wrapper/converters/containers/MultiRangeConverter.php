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
     * Converter for the base type of the multirange
     * @var TypeConverter
     */
    private $subtypeConverter;

    /**
     * input() will return instances of this class
     * @var class-string<MultiRange>
     */
    protected $resultClass = MultiRange::class;

    /**
     * Constructor, sets converter for the base type
     *
     * @param TypeConverter $subtypeConverter
     */
    public function __construct(TypeConverter $subtypeConverter)
    {
        $this->subtypeConverter = $subtypeConverter;

        if ($subtypeConverter instanceof BaseNumericConverter) {
            $this->resultClass = NumericMultiRange::class;
        } elseif ($subtypeConverter instanceof BaseDateTimeConverter) {
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

    protected function parseInput(string $native, int &$pos)
    {
        $ranges    = [];
        $converter = new RangeConverter($this->subtypeConverter);

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

    protected function outputNotNull($value): string
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

        $converter = new RangeConverter($this->subtypeConverter);
        $ranges    = [];
        foreach ($value as $range) {
            $ranges[] = $converter->outputNotNull($range);
        }

        return '{' . \implode(',', $ranges) . '}';
    }
}
