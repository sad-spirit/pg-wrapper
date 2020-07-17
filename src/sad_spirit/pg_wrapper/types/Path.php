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

namespace sad_spirit\pg_wrapper\types;

use sad_spirit\pg_wrapper\exceptions\InvalidArgumentException;

/**
 * Class representing a 'path' geometric type
 *
 * Contains a list of points and 'open' property
 *
 * @property boolean $open
 */
class Path extends PointList
{
    private $openProp;

    public function __construct(array $points, bool $open = false)
    {
        parent::__construct($points);
        $this->__set('open', $open);
    }

    public function __get($name)
    {
        if ('open' === $name) {
            return $this->openProp;

        } else {
            throw new InvalidArgumentException("Unknown property '{$name}'");
        }
    }

    public function __set($name, $value)
    {
        if ('open' === $name) {
            $this->openProp = (bool)$value;

        } else {
            throw new InvalidArgumentException("Unknown property '{$name}'");
        }
    }

    public function __isset($name)
    {
        return 'open' === $name;
    }

    public static function createFromArray(array $input)
    {
        $open = !empty($input['open']);
        unset($input['open']);
        $path = parent::createFromArray($input);
        $path->open = $open;

        return $path;
    }
}
