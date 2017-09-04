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

use sad_spirit\pg_wrapper\converters\containers\ArrayConverter,
    sad_spirit\pg_wrapper\converters\StringConverter,
    sad_spirit\pg_wrapper\exceptions\TypeConversionException;

/**
 * Unit test for an array of strings type converter
 */
class StringArrayTest extends TypeConverterTestCase
{
    public function setUp()
    {
        $this->converter = new ArrayConverter(new StringConverter());
    }

    protected function valuesBoth()
    {
        return array(
            array(null, null),
            array('{}', array()),
            array('{NULL,NULL}', array(null, null)),
            array('{NULL,"test"}', array(null, 'test')),
            array('{NULL,"te\\"s\\\\t"}', array(null, 'te"s\\t')),
            array('{"abc","def"}', array('abc', 'def')),
            array('{{"abc","def"},{"g","h"}}', array(array('abc', 'def'), array('g', 'h'))),
            array('{"test"}', array('test')),
        );
    }

    protected function valuesFrom()
    {
        return array(
            array('{ {NULL} ,{  NULL, "string" }}  ', array(array(null), array(null, 'string'))),
            array('{{NULL},{test,"\\"y"},{NULL,string}}', array(array(null), array('test', '"y'), array(null, 'string'))),
        );
    }

    protected function valuesTo()
    {
        return array(
            array(new TypeConversionException(), 1),
            array(new TypeConversionException(), 'string'),
            array(new TypeConversionException(), array(array('string'), null)),
            array(new TypeConversionException(), array(array('ab', 'de'), array())),
            array(new TypeConversionException(), array(array(), array('ab', 'de'))),
            // the result is accepted by Postgres, but probably shouldn't be
            // http://www.postgresql.org/message-id/E1VEETa-0007KM-8O@wrigleys.postgresql.org
            array(new TypeConversionException(), array(array(null), array(null, 'test'))),
            // empty sub-arrays
            array(new TypeConversionException(), array(array())),
            array(new TypeConversionException(), array(array(), array()))
        );
    }
}
