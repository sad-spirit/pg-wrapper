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
 * @copyright 2014-2021 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   https://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-wrapper
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
    /**
     * Converter for numbers within Tid
     * @var IntegerConverter
     */
    private $integerConverter;

    public function __construct()
    {
        $this->integerConverter = new IntegerConverter();
    }

    protected function parseInput(string $native, int &$pos)
    {
        $this->expectChar($native, $pos, '(');

        $len = strcspn($native, ",)", $pos);
        $blockNumber = substr($native, $pos, $len);
        $pos += $len;

        $this->expectChar($native, $pos, ',');

        $len = strcspn($native, ",)", $pos);
        $offset = substr($native, $pos, $len);
        $pos += $len;

        $this->expectChar($native, $pos, ')');

        return new Tid($this->integerConverter->input($blockNumber), $this->integerConverter->input($offset));
    }

    protected function outputNotNull($value): string
    {
        if (is_array($value)) {
            $value = Tid::createFromArray($value);
        } elseif (!($value instanceof Tid)) {
            throw TypeConversionException::unexpectedValue($this, 'output', 'instance of Tid or an array', $value);
        }
        /* @var $value Tid */
        return sprintf('(%d,%d)', $value->block, $value->tuple);
    }

    public function dimensions(): int
    {
        return 1;
    }
}
