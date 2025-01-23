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

use sad_spirit\pg_wrapper\exceptions\InvalidArgumentException;
use sad_spirit\pg_wrapper\exceptions\TypeConversionException;

/**
 * Converter for Postgres `ENUM` types, converts to a string-backed PHP enum
 */
class EnumConverter extends BaseConverter
{
    /** @var class-string<\BackedEnum> */
    public readonly string $enumName;

    /**
     * Constructor, accepts the class name for the string-backed enum that will be returned by `input()`
     *
     * @param class-string<\BackedEnum> $enumName
     */
    public function __construct(string $enumName)
    {
        try {
            if ('string' === (string)(new \ReflectionEnum($enumName))->getBackingType()) {
                $this->enumName = $enumName;
                return;
            }
        } catch (\ReflectionException $e) {
        }

        throw new InvalidArgumentException(\sprintf(
            '%s requires a name of string-backed enum, "%s" given',
            __CLASS__,
            $enumName
        ), 0, $e ?? null);
    }

    protected function inputNotNull(string $native): \BackedEnum
    {
        try {
            return \call_user_func([$this->enumName, 'from'], $native);
        } catch (\ValueError $e) {
            throw new TypeConversionException(\sprintf(
                "Failed to convert '%s' to a case of '%s' enum",
                $native,
                $this->enumName
            ), 0, $e);
        }
    }

    protected function outputNotNull(mixed $value): string
    {
        if (\is_string($value)) {
            try {
                $value = \call_user_func([$this->enumName, 'from'], $value);
            } catch (\ValueError $e) {
                throw new TypeConversionException(\sprintf(
                    "Failed to convert '%s' to a case of '%s' enum",
                    $value,
                    $this->enumName
                ), 0, $e);
            }
        } elseif (!$value instanceof $this->enumName) {
            throw TypeConversionException::unexpectedValue(
                $this,
                'output',
                \sprintf("a case of '%s' enum or a string", $this->enumName),
                $value
            );
        }
        return $value->value;
    }
}
