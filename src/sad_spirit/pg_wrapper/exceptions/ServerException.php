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
 * @copyright 2014-2017 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-wrapper
 */

declare(strict_types=1);

namespace sad_spirit\pg_wrapper\exceptions;

use sad_spirit\pg_wrapper\Exception;

/**
 * Exception thrown on failed query
 */
class ServerException extends \UnexpectedValueException implements Exception
{
    /**
     * Five-character 'SQLSTATE' error code
     * @var string
     */
    private $sqlState;

    public function __construct($message = "", $code = 0, \Exception $previous = null)
    {
        // We can only use pg_result_error_field() with async queries, so just parse the message
        // instead. See function pqGetErrorNotice3() in src/interfaces/libpq/fe-protocol3.c
        if (preg_match("/^[^\\r\\n]+:  ([A-Z0-9]{5}):/", $message, $m)) {
            $this->sqlState = $m[1];
        }
        parent::__construct($message, $code, $previous);
    }

    /**
     * Returns 'SQLSTATE' error code, if one is available
     *
     * @return string
     */
    public function getSqlState()
    {
        return $this->sqlState;
    }
}
