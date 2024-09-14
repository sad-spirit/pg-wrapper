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

use sad_spirit\pg_wrapper\converters\ContainerConverter;
use sad_spirit\pg_wrapper\converters\IntegerConverter;
use sad_spirit\pg_wrapper\exceptions\TypeConversionException;

/**
 * Converter for int2vector and oidvector types used in system catalogs
 *
 * NB: we do not implement ConnectionAware here as (currently) input routines in Postgres 16+ do not accept
 * special numeric literals
 */
class IntegerVectorConverter extends ContainerConverter
{
    private readonly IntegerConverter $integerConverter;

    public function __construct()
    {
        $this->integerConverter = new IntegerConverter();
    }

    protected function outputNotNull(mixed $value): string
    {
        if (!\is_array($value)) {
            throw TypeConversionException::unexpectedValue($this, 'output', 'array', $value);
        }

        return \implode(' ', \array_map(fn($v) => $this->integerConverter->outputNotNull($v), $value));
    }

    /**
     * @return array<int|numeric-string>
     */
    protected function parseInput(string $native, int &$pos): array
    {
        $values = [];

        while (null !== $this->nextChar($native, $pos)) {
            $length    = \strcspn($native, " \n\r\t", $pos);
            $values[]  = $this->integerConverter->input(\substr($native, $pos, $length));
            $pos      += $length;
        }

        return $values;
    }

    public function dimensions(): int
    {
        return 1;
    }
}
