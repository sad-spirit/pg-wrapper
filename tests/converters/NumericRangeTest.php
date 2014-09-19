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

namespace sad_spirit\pg_wrapper\tests\converters;

use sad_spirit\pg_wrapper\converters\containers\RangeConverter,
    sad_spirit\pg_wrapper\converters\NumericConverter,
    sad_spirit\pg_wrapper\exceptions\InvalidArgumentException,
    sad_spirit\pg_wrapper\exceptions\TypeConversionException,
    sad_spirit\pg_wrapper\types\NumericRange;

/**
 * Unit test for numrange type converter
 */
class NumericRangeTest extends TypeConverterTestCase
{
    public function setUp()
    {
        $this->converter = new RangeConverter(new NumericConverter());
    }

    protected function valuesBoth()
    {
        return array(
            array('empty',  NumericRange::createEmpty()),
            array('[,)',    new NumericRange()),
            array('("1",]', new NumericRange(1, null, false, true)),
            array('(,"2")', new NumericRange(null, 2, false, false))
        );
    }

    protected function valuesFrom()
    {
        return array(
            array(' (    2    ,   3 )', new NumericRange(2, 3, false, false)),
            array('[2, a]',             new TypeConversionException()),
            array('(3,2)',              new InvalidArgumentException())
        );
    }

    protected function valuesTo()
    {
        return array(
            array('["2","3"]',                    array('upper' => 3, 'lower' => 2, 'upperInclusive' => true)),
            array('["2","3")',                    array(2, 3)),
            array(new InvalidArgumentException(), array(3, 2)),
            array(new TypeConversionException(),  new \stdClass())
        );
    }
}
