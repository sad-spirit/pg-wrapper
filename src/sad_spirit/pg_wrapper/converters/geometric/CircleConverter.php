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

namespace sad_spirit\pg_wrapper\converters\geometric;

use sad_spirit\pg_wrapper\{
    converters\FloatConverter,
    exceptions\TypeConversionException,
    types\Circle
};

/**
 * Converter for circle type, represented by a centre point and radius
 */
class CircleConverter extends BaseGeometricConverter
{
    /**
     * Converter for circle's radius
     * @var FloatConverter
     */
    private $floatConverter;

    public function __construct()
    {
        $this->floatConverter = new FloatConverter();
        parent::__construct();
    }

    protected function parseInput(string $native, int &$pos): Circle
    {
        $hasDelimiters = $angleDelimiter = $singleOpen = false;

        if ('<' === ($char = $this->nextChar($native, $pos)) || '(' === $char) {
            $hasDelimiters = true;
            if ('<' === $char) {
                $angleDelimiter = true;
            } else {
                $singleOpen     = $pos === strrpos($native, '(');
            }
            $pos++;
        }

        $center = $this->point->parseInput($native, $pos);
        if (')' === $this->nextChar($native, $pos) && $singleOpen) {
            $hasDelimiters = false;
            $pos++;
        }
        $this->expectChar($native, $pos, ',');
        $len    = strcspn($native, ',)>', $pos);
        $radius = substr($native, $pos, $len);
        $pos   += $len;

        if ($hasDelimiters) {
            $this->expectChar($native, $pos, $angleDelimiter ? '>' : ')');
        }

        return new Circle($center, $this->floatConverter->input($radius));
    }

    protected function outputNotNull($value): string
    {
        if (is_array($value)) {
            $value = Circle::createFromArray($value);
        } elseif (!($value instanceof Circle)) {
            throw TypeConversionException::unexpectedValue($this, 'output', 'instance of Circle or an array', $value);
        }
        return '<' . $this->point->output($value->center) . ',' . $this->floatConverter->output($value->radius) . '>';
    }

    public function dimensions(): int
    {
        return 2;
    }
}
