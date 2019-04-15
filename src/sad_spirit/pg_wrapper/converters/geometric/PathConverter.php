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

namespace sad_spirit\pg_wrapper\converters\geometric;

use sad_spirit\pg_wrapper\{
    exceptions\TypeConversionException,
    types\Path
};

/**
 * Converter for path data type
 */
class PathConverter extends BaseGeometricConverter
{
    protected function parseInput(string $native, int &$pos): Path
    {
        return Path::createFromArray($this->parsePoints($native, $pos, $this->countPoints($native), true));
    }

    protected function outputNotNull($value): string
    {
        if (is_array($value)) {
            $value = Path::createFromArray($value);
        } elseif (!($value instanceof Path)) {
            throw TypeConversionException::unexpectedValue($this, 'output', 'instance of Path or an array', $value);
        }

        $points = [];
        foreach ($value as $point) {
            $points[] = $this->point->output($point);
        }
        return ($value->open ? '[' : '(') . implode(',', $points) . ($value->open ? ']' : ')');
    }

    public function dimensions(): int
    {
        return 2;
    }
}
