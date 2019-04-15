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

namespace sad_spirit\pg_wrapper\tests\converters;

use sad_spirit\pg_wrapper\exceptions\InvalidArgumentException,
    sad_spirit\pg_wrapper\types\Tid,
    sad_spirit\pg_wrapper\converters\TidConverter,
    sad_spirit\pg_wrapper\exceptions\TypeConversionException;

/**
 * Unit test for tid type converter
 */
class TidTest extends TypeConverterTestCase
{
    public function setUp()
    {
        $this->converter = new TidConverter();
    }

    protected function valuesBoth()
    {
        return [
            ['(0,0)', new Tid(0, 0)],
            ['(1,2)', new Tid(1, 2)]
        ];
    }

    protected function valuesFrom()
    {
        return [
            [' ( 3 , 4 ) ', new Tid(3, 4)],
            ['666',         new TypeConversionException()],
            ['(5)',         new TypeConversionException()],
            ['(1,2,3)',     new TypeConversionException()],
            ['(1,2',        new TypeConversionException()],
            ['(-1,1)',      new InvalidArgumentException()],
            ['(1, -1)',     new InvalidArgumentException()]
        ];
    }

    protected function valuesTo()
    {
        return [
            ['(1,2)',                       ['tuple' => 2, 'block' => 1]],
            ['(1,2)',                       [1, 2]],
            [new TypeConversionException(), 'a string'],
            [new InvalidArgumentException(), [1]],
            [new InvalidArgumentException(), [1, 2, 3]],
            [new InvalidArgumentException(), [-1, 2]],
            [new InvalidArgumentException(), [1, 'foo']]
        ];
    }
}