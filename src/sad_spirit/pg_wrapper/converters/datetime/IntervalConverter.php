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

use sad_spirit\pg_wrapper\converters\BaseConverter;
use sad_spirit\pg_wrapper\exceptions\TypeConversionException;

/**
 * Converter for interval type
 *
 * Unfortunately PHP's DateInterval class cannot properly parse *any* of the
 * formats Postgres may use for interval output (settings 'sql_standard',
 * 'postgres', 'postgres_verbose' and 'iso_8601' for IntervalStyle config
 * parameter), so this class basically reimplements the parser used internally
 * by Postgres.
 *
 * The reimplementation is greatly simplified as it only needs to parse values
 * that can be actually generated by Postgres, it will reject some of the input
 * that Postgres will accept (most notably, fractional values for anything but seconds).
 */
class IntervalConverter extends BaseConverter
{
    /**#@+
     * Token types used by tokenize() and createInterval()
     */
    private const TOKEN_STRING = 'string';
    private const TOKEN_NUMBER = 'number';
    private const TOKEN_DATE   = 'date';
    private const TOKEN_TIME   = 'time';
    /**#@-*/

    /**
     * Mapping of units used in 'postgres' and 'postgres_verbose' formats to DateInterval fields
     * @var array<string, string>
     */
    private const POSTGRES_UNITS = [
        'd'         => 'd',
        'day'       => 'd',
        'days'      => 'd',
        'h'         => 'h',
        'hour'      => 'h',
        'hours'     => 'h',
        'hr'        => 'h',
        'hrs'       => 'h',
        'm'         => 'i',
        'min'       => 'i',
        'mins'      => 'i',
        'minute'    => 'i',
        'minutes'   => 'i',
        'mon'       => 'm',
        'mons'      => 'm',
        'month'     => 'm',
        'months'    => 'm',
        's'         => 's',
        'sec'       => 's',
        'second'    => 's',
        'seconds'   => 's',
        'secs'      => 's',
        'y'         => 'y',
        'year'      => 'y',
        'years'     => 'y',
        'yr'        => 'y',
        'yrs'       => 'y'
    ];

    private readonly \DateInterval $intervalPrototype;

    /**
     * Returns the value of DateInterval object as an ISO 8601 time interval string
     *
     * This string will not necessarily work with DateInterval's constructor
     * as that cannot handle negative numbers.
     * Mostly intended for sending to Postgres as a value of interval type.
     */
    public static function formatAsISO8601(\DateInterval $interval): string
    {
        $string = 'P';
        $mult   = $interval->invert ? -1 : 1;
        foreach (['y' => 'Y', 'm' => 'M', 'd' => 'D'] as $key => $char) {
            if (0 !== $interval->{$key}) {
                $string .= \sprintf('%d%s', $interval->{$key} * $mult, $char);
            }
        }
        if (0 !== $interval->h || 0 !== $interval->i || 0 !== $interval->s || 0.0 !== $interval->f) {
            $string .= 'T';
            foreach (['h' => 'H', 'i' => 'M'] as $key => $char) {
                if (0 !== $interval->{$key}) {
                    $string .= \sprintf('%d%s', $interval->{$key} * $mult, $char);
                }
            }
            if (0 !== $interval->s || 0.0 !== $interval->f) {
                if (0.0 === $interval->f) {
                    $string .= \sprintf('%d%s', $interval->s * $mult, 'S');
                } else {
                    $string .= \rtrim(\sprintf('%.6f', ($interval->s + $interval->f) * $mult), '0');
                    $string .= 'S';
                }
            }
        }

        // prevent returning an empty string
        return 'P' === $string ? 'PT0S' : $string;
    }

    public function __construct()
    {
        $this->intervalPrototype = new \DateInterval('PT0S');
    }


    /**
     * Parses a string representing time, e.g. '01:02:03' or '01:02:03.45' and updates DateInterval's fields
     *
     * @throws TypeConversionException
     */
    private function parseTimeToken(string $token, \DateInterval $interval): void
    {
        $parts = \explode(':', $token);

        if (2 === \count($parts)) {
            if (!\str_contains($parts[1], '.')) {
                // treat as hours to minutes
                $parts[] = '0';
            } else {
                // treat as minutes to seconds
                \array_unshift($parts, '0');
            }
        }
        if (3 !== \count($parts)) {
            throw TypeConversionException::unexpectedValue($this, __FUNCTION__, $token, 'time token');
        }

        $interval->h = (int)$parts[0];
        $interval->i = (int)$parts[1];
        if (false === ($pos = \strpos($parts[2], '.'))) {
            $interval->s = (int)$parts[2];
        } else {
            $interval->s = (int)\substr($parts[2], 0, $pos);
            $interval->f = (float)\substr($parts[2], $pos);
        }
    }

    /**
     * Creates a DateInterval object from an array of tokens
     *
     * This is used to handle 'sql_standard', 'postgres', 'postgres_verbose'
     * output formats
     *
     * @param array<int, array{string, string}> $tokens
     * @return \DateInterval
     * @throws TypeConversionException
     */
    private function createInterval(array $tokens): \DateInterval
    {
        $interval    = clone $this->intervalPrototype;
        $intervalKey = null;
        $invert      = false;
        $keysHash    = [];

        for ($i = \count($tokens) - 1; $i >= 0; $i--) {
            [$tokenValue, $tokenType] = $tokens[$i];
            $keys = [];
            switch ($tokenType) {
                case self::TOKEN_NUMBER:
                    $intervalKey = $intervalKey ?: 's';

                    if (false === ($pos = \strpos($tokenValue, '.'))) {
                        $interval->{$intervalKey} = (int)$tokenValue;
                    } elseif ('s' !== $intervalKey) {
                        // Only allow fractional seconds, otherwise there is a non-trivial amount of work
                        // to e.g. properly convert '4.56 months'
                        throw TypeConversionException::parsingFailed($this, 'integer value', $tokenValue, 0);
                    } else {
                        $interval->s = (int)\substr($tokenValue, 0, $pos);
                        $interval->f = ('-' === $tokenValue[0] ? -1 : 1)
                                       * (float)\substr($tokenValue, $pos);
                    }

                    $keys = [$intervalKey];
                    break;

                case self::TOKEN_STRING:
                    if ('ago' === $tokenValue) {
                        $invert = true;

                    } elseif (isset(self::POSTGRES_UNITS[$tokenValue])) {
                        $intervalKey = self::POSTGRES_UNITS[$tokenValue];

                    } else {
                        throw TypeConversionException::unexpectedValue(
                            $this,
                            'input',
                            'interval unit name',
                            $tokenValue
                        );
                    }
                    break;

                case self::TOKEN_TIME:
                    if ('-' !== $tokenValue[0] && '+' !== $tokenValue[0]) {
                        $this->parseTimeToken($tokenValue, $interval);
                    } else {
                        $this->parseTimeToken(\substr($tokenValue, 1), $interval);
                        if ('-' === $tokenValue[0]) {
                            [$interval->h, $interval->i, $interval->s, $interval->f] =
                                [-$interval->h, -$interval->i, -$interval->s, -$interval->f];
                        }
                    }
                    $intervalKey = 'd';
                    $keys        = ['h', 'i', 's'];
                    break;

                case self::TOKEN_DATE:
                    // SQL "years-months" syntax
                    if ('-' !== $tokenValue[0] && '+' !== $tokenValue[0]) {
                        $sign  = '+';
                        $parts = \explode('-', $tokenValue);
                    } else {
                        $sign  = $tokenValue[0];
                        $parts = \explode('-', \substr($tokenValue, 1));
                    }
                    $interval->y = ('-' === $sign ? -1 : 1) * (int)$parts[0];
                    $interval->m = ('-' === $sign ? -1 : 1) * (int)$parts[1];
                    $keys        = ['y', 'm'];
                    break;

                default:
                    throw TypeConversionException::unexpectedValue($this, 'input', 'valid token type', $tokenType);
            }

            foreach ($keys as $key) {
                if (isset($keysHash[$key])) {
                    throw new TypeConversionException(\sprintf(
                        "%s: duplicate value for interval field '%s' found",
                        __METHOD__,
                        $key
                    ));
                }
                $keysHash[$key] = true;
            }
        }

        if ($invert) {
            $interval->invert = 1;
        }

        return $interval;
    }

    /**
     * Splits an interval string in 'sql_standard', 'postgres', 'postgres_verbose' formats into tokens
     *
     * @param string $native
     * @return array<int, array{string, string}>
     * @throws TypeConversionException
     */
    private function tokenize(string $native): array
    {
        $tokens = [];
        $pos    = 0;
        $length = \strlen($native);

        if ('@' === $native[0]) {
            // only skip first @, there can be no other punctuation in _output_
            $pos++;
        }

        while ($pos < $length) {
            // $native cannot have _trailing_ whitespace, it was trimmed in inputNotNull()
            $pos += \strspn($native, self::WHITESPACE, $pos);

            if (\preg_match('/[a-z]+/A', $native, $m, 0, $pos)) {
                $field  = $m[0];
                $pos   += \strlen($m[0]);
                $type   = self::TOKEN_STRING;

            } elseif (
                \preg_match(
                    '/[+-]? \d+ (?: (:\d+)?:(\d+(\.\d+)?|\.\d+) | ([-.]) \d+ )? /Ax',
                    $native,
                    $m,
                    0,
                    $pos
                )
            ) {
                $field  = $m[0];
                $pos   += \strlen($m[0]);
                if (!empty($m[2])) {
                    // has :[digit] part
                    $type = self::TOKEN_TIME;
                } elseif (!empty($m[4]) && '.' !== $m[4]) {
                    // y-m token
                    $type = self::TOKEN_DATE;
                } else {
                    $type = self::TOKEN_NUMBER;
                }

            } else {
                throw TypeConversionException::parsingFailed($this, 'valid interval part', $native, $pos);
            }

            $tokens[] = [$field, $type];
        }

        if (empty($tokens)) {
            throw TypeConversionException::unexpectedValue($this, 'input', 'interval literal', $native);
        }

        return $tokens;
    }

    /**
     * Creates a DateInterval object from 'iso_8601' format interval string
     *
     * Unlike native DateInterval::__construct() this handles negative values
     * and fractional seconds. Only integer values are allowed for other units.
     *
     * @throws TypeConversionException
     * @psalm-suppress PossiblyUndefinedArrayOffset
     */
    private function parseISO8601(string $native): \DateInterval
    {
        $interval = clone $this->intervalPrototype;
        $regexp   = '/^P(?=[T\d-])                      # P should be followed by something
(?<y>-?\d+Y)? (?<m>-?\d+M)? (?<w>-?\d+W)? (?<d>-?\d+D)? 
(?:T(?=[\d.-])                                          # T should be followed by something 
    (?<h>-?\d+H)? (?<i>-?\d+M)?                  
    (?<s>-? (?: \d+ (\.\d+)? | \.\d+) S)?               # seconds, allow fractional 
)?$/x';

        if (!\preg_match($regexp, $native, $m, \PREG_UNMATCHED_AS_NULL)) {
            throw TypeConversionException::unexpectedValue($this, 'input', 'interval literal', $native);
        }

        foreach (['y', 'm', 'w', 'd', 'h', 'i', 's'] as $key) {
            if (isset($m[$key])) {
                if ('w' === $key) {
                    $interval->d = 7 * (int)\substr($m['w'], 0, -1);
                } elseif ('s' === $key && false !== ($pos = \strpos($m['s'], '.'))) {
                    $interval->s = (int)\substr($m['s'], 0, $pos);
                    $interval->f = ('-' === $m['s'][0] ? -1 : 1) * (float)\substr($m['s'], $pos, -1);
                } else {
                    $interval->{$key} = (int)\substr($m[$key], 0, -1);
                }
            }
        }

        return $interval;
    }

    protected function inputNotNull(string $native): \DateInterval
    {
        if ('' === ($native = \trim($native, self::WHITESPACE))) {
            throw TypeConversionException::unexpectedValue($this, 'input', 'interval literal', $native);

        } elseif ('P' !== $native[0]) {
            return $this->createInterval($this->tokenize($native));

        } else {
            if (!\str_contains($native, '-') && !\str_contains($native, '.')) {
                // DateInterval in PHP 7.2+ supports fractional seconds, but still cannot parse them:
                // https://bugs.php.net/bug.php?id=53831
                // No minuses or dots -> built-in constructor can probably handle
                try {
                    return new \DateInterval($native);
                } catch (\Exception) {
                    // croaked; let our own parsing function work and throw an Exception
                }
            }
            return $this->parseISO8601($native);
        }
    }

    /**
     * Converts PHP variable not identical to null into native format
     *
     * Note: a passed string will be returned as-is without any attempts to parse it.
     * PostgreSQL's interval parser accepts a lot more possible formats than this
     * class can handle.
     *
     * @param mixed $value Actually accepts strings, numbers and instances of \DateInterval
     * @return string
     * @throws TypeConversionException if $value is of unexpected type
     */
    protected function outputNotNull(mixed $value): string
    {
        if (\is_string($value)) {
            return $value;

        } elseif (\is_int($value)) {
            return \sprintf('%d seconds', $value);

        } elseif (\is_float($value)) {
            return \preg_replace(
                '/\\.?0+$/',
                '',
                \number_format($value, 6, '.', '')
            ) . ' seconds';

        } elseif ($value instanceof \DateInterval) {
            return self::formatAsISO8601($value);
        }

        throw TypeConversionException::unexpectedValue(
            $this,
            'output',
            'a string, a number or an instance of DateInterval',
            $value
        );
    }
}
