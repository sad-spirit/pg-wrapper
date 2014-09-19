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
 * @copyright 2014 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-wrapper
 */

namespace sad_spirit\pg_wrapper\converters;

use sad_spirit\pg_wrapper\exceptions\TypeConversionException;

/**
 * Converter for json / jsonb PostgreSQL types
 *
 * This will only work correctly with non-ASCII symbols if both PHP encoding and
 * database encoding is UTF-8
 */
class JSONConverter extends BaseConverter
{
    protected static $errors = array(
        JSON_ERROR_DEPTH          => 'Maximum stack depth exceeded',
        JSON_ERROR_STATE_MISMATCH => 'Underflow or the modes mismatch',
        JSON_ERROR_CTRL_CHAR      => 'Unexpected control character found',
        JSON_ERROR_SYNTAX         => 'Syntax error, malformed JSON',
        JSON_ERROR_UTF8           => 'Malformed UTF-8 characters, possibly incorrectly encoded'
    );

    /**
     * A replacement for json_last_error_msg() for older PHP versions
     *
     * @param int $errorCode
     * @return string
     */
    protected static function errorMessage($errorCode)
    {
        return isset(self::$errors[$errorCode]) ? self::$errors[$errorCode] : "Unknown error ({$errorCode})";
    }

    protected function inputNotNull($native)
    {
        // Postgres stores numbers in JSON as values of "numeric" type, not "float"
        // To prevent loss of precision we should (try to) return these as strings
        if (defined('JSON_BIGINT_AS_STRING')) {
            $result = json_decode($native, true, 512, JSON_BIGINT_AS_STRING);
        } else {
            $result = json_decode($native, true);
        }

        if (null === $result && ($code = json_last_error())) {
            $msg = function_exists('json_last_error_msg')
                   ? json_last_error_msg() : self::errorMessage($code);
            throw new TypeConversionException(sprintf('%s(): %s', __METHOD__, $msg));
        }

        return $result;
    }

    protected function outputNotNull($value)
    {
        $warning = '';

        // older PHP versions return bogus encoded values and throw warnings for invalid input
        set_error_handler(function ($errno, $errstr) use (&$warning) {
            $warning = $errstr;
            return true;
        }, E_WARNING);

        $result = json_encode($value);

        restore_error_handler();

        if (false === $result) {
            $msg = function_exists('json_last_error_msg')
                   ? json_last_error_msg() : self::errorMessage(json_last_error());
            throw new TypeConversionException(sprintf('%s(): %s', __METHOD__, $msg));

        } elseif ($warning) {
            throw new TypeConversionException(sprintf('%s(): %s', __METHOD__, $warning));
        }

        return $result;
    }
}