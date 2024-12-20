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

namespace sad_spirit\pg_wrapper\converters;

use sad_spirit\pg_wrapper\{
    exceptions\TypeConversionException,
    types\Tid
};

/**
 * Converter for tid (tuple identifier) type, representing physical location of a row within its table
 */
class TidConverter extends ContainerConverter
{
    /** Converter for numbers within Tid */
    private readonly IntegerConverter $integerConverter;

    public function __construct()
    {
        $this->integerConverter = new IntegerConverter();
    }

    protected function parseInput(string $native, int &$pos): Tid
    {
        $this->expectChar($native, $pos, '(');

        $len = \strcspn($native, ",)", $pos);
        $blockNumber = \substr($native, $pos, $len);
        $pos += $len;

        $this->expectChar($native, $pos, ',');

        $len = \strcspn($native, ",)", $pos);
        $offset = \substr($native, $pos, $len);
        $pos += $len;

        $this->expectChar($native, $pos, ')');

        return new Tid($this->integerConverter->input($blockNumber), $this->integerConverter->input($offset));
    }

    protected function outputNotNull(mixed $value): string
    {
        if (\is_array($value)) {
            $value = Tid::createFromArray($value);
        } elseif (!$value instanceof Tid) {
            throw TypeConversionException::unexpectedValue($this, 'output', 'instance of Tid or an array', $value);
        }
        return \sprintf('(%d,%d)', $value->block, $value->tuple);
    }

    public function dimensions(): int
    {
        return 1;
    }
}
