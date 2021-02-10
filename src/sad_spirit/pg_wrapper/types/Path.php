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

/**
 * Class representing a 'path' geometric type
 *
 * Contains a list of points and 'open' property
 *
 * @property-read boolean $open
 */
final class Path extends PointList implements ArrayRepresentable
{
    use ReadOnlyProperties;

    /** @var bool */
    private $openProp;

    public function __construct(bool $open, Point ...$points)
    {
        parent::__construct(...$points);
        $this->openProp = $open;
    }

    /**
     * Returns whether path is open
     *
     * @return bool
     */
    public function isOpen(): bool
    {
        return $this->openProp;
    }

    /**
     * Creates a Path from a given array
     *
     * @param array $input
     * @return self
     */
    public static function createFromArray(array $input): self
    {
        if (is_bool(reset($input))) {
            $open = array_shift($input);
        } else {
            $open = false;
        }

        return new self($open, ...self::createPointArray($input));
    }
}
