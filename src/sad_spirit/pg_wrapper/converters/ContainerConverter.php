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
 * @copyright 2014-2020 Alexey Borzov
 * @author    Alexey Borzov <avb@php.net>
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD 2-Clause license
 * @link      https://github.com/sad-spirit/pg-wrapper
 */

declare(strict_types=1);

namespace sad_spirit\pg_wrapper\converters;

use sad_spirit\pg_wrapper\exceptions\TypeConversionException;

/**
 * Base class for types containing some other types
 */
abstract class ContainerConverter extends BaseConverter
{
    protected function inputNotNull(string $native)
    {
        $pos   = 0;
        $value = $this->parseInput($native, $pos);
        if (null !== $this->nextChar($native, $pos)) {
            throw TypeConversionException::parsingFailed($this, 'end of input', $native, $pos);
        }
        return $value;
    }

    /**
     * Parses a native value into PHP variable from given position
     *
     * @param string $native
     * @param int    $pos
     * @return mixed
     */
    abstract protected function parseInput(string $native, int &$pos);
}
