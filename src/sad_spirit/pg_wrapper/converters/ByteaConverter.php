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
 * Converter for bytea (binary string) type
 *
 * Handles both old 'escape' format via pg_escape_bytea() / pg_unescape_bytea()
 * and newer 'hex' format used by Postgres 9+
 */
class ByteaConverter extends BaseConverter implements ConnectionAware
{
    /**
     * Whether to use 'hex' encoding for output
     * @var boolean
     */
    protected $useHex = false;

    /**
     * Connection resource, used for calls to pg_escape_bytea()
     * @var resource
     */
    private $_connection = null;

    /**
     * Constructor, possibly sets the connection this converter works with
     *
     * @param resource|null $resource Connection resource
     */
    public function __construct($resource = null)
    {
        if (null !== $resource) {
            $this->setConnectionResource($resource);
        }
    }

    public function setConnectionResource($resource)
    {
        $this->_connection = $resource;

        // if connection was made to PostgreSQL 9.0+, then use 'hex' encoding
        $this->useHexEncoding(version_compare(
            pg_parameter_status($resource, 'server_version'), '9.0.0', '>='
        ));
    }

    /**
     * Sets whether 'hex' encoding should be used for output
     *
     * @param bool $hex
     */
    public function useHexEncoding($hex)
    {
        $this->useHex = (bool)$hex;
    }

    protected function inputNotNull($native)
    {
        if ('\x' !== call_user_func(self::$substr, $native, 0, 2)) {
            return pg_unescape_bytea($native);

        } else {
            // http://www.postgresql.org/docs/current/interactive/datatype-binary.html says:
            // The "hex" format encodes binary data as 2 hexadecimal digits per byte, most significant nibble first.
            // The entire string is preceded by the sequence \x (to distinguish it from the escape format).
            // The hexadecimal digits can be either upper or lower case, and whitespace is permitted between digit
            // pairs (but not within a digit pair nor in the starting \x sequence).
            $warning = '';
            $result  = '';
            $start   = 2;
            $length  = call_user_func(self::$strlen, $native);
            while ($start < $length) {
                $start += strspn($native, " \n\r\t", $start);
                $hexes  = strcspn($native, " \n\r\t", $start);
                if ($hexes > 0) {
                    if ($hexes % 2) {
                        throw new TypeConversionException(sprintf(
                            '%s(): expecting even number of hex digits, %d hex digit(s) found',
                            __METHOD__, $hexes
                        ));
                    }

                    // pack() throws a warning, but returns a string nonetheless, so use warnings handler
                    set_error_handler(function($errno, $errstr) use (&$warning) {
                        $warning = $errstr;
                        return true;
                    }, E_WARNING);
                    $result .= pack('H*', call_user_func(self::$substr, $native, $start, $hexes));
                    $start  += $hexes;
                    restore_error_handler();

                    if ($warning) {
                        throw new TypeConversionException(sprintf('%s(): %s', __METHOD__, $warning));
                    }
                }
            }
            return $result;
        }
    }

    /**
     * Returns the encoded binary string
     *
     * PHP's pg_escape_bytea() creates a string representation that can be used
     * when building a query manually, but not in pg_execute(). There is also
     * no means to specify that an argument to pg_execute() should be treated
     * as a binary string (unlike in PDO which allows setting PDO::PARAM_LOB or
     * in underlying PQexecParams).
     *
     * This method returns a string that can be passed to pg_execute(), either
     * by using 'hex' encoding or by stripping the outer level of escapes from
     * the result of pg_escape_bytea(), leaving only the escapes that will be
     * handled by bytea input routine.
     *
     * @param string $value
     * @return string
     */
    protected function outputNotNull($value)
    {
        if ($this->useHex) {
            list(, $encoded) = unpack('H*', $value);
            return '\x' . $encoded;

        } else {
            // this basically tests whether standard_conforming_strings is on for a (default) connection
            $test     = $this->_connection ? pg_escape_bytea($this->_connection, '\\') : pg_escape_bytea('\\');
            $unescape = array("''" => "'") + ('\\\\\\\\' === $test ? array('\\\\' => '\\') : array());

            $escaped  = $this->_connection ? pg_escape_bytea($this->_connection, $value) : pg_escape_bytea($value);
            return strtr($escaped, $unescape);
        }
    }
}
