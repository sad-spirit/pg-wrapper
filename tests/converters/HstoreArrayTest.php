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

use sad_spirit\pg_wrapper\converters\containers\HstoreConverter,
    sad_spirit\pg_wrapper\converters\containers\ArrayConverter;

/**
 * Unit test for a combination of array and hstore type converters
 */
class HstoreArrayTest extends TypeConverterTestCase
{
    public function setUp()
    {
        $this->converter = new ArrayConverter(new HstoreConverter());
    }

    protected function valuesBoth()
    {
        return array(
            array(null, null),
            array('{"\"a\"=>\"b\"","\"c\"=>\"d\", \"e\"=>\"f\""}', array(array('a' => 'b'), array('c' => 'd', 'e' => 'f'))),
            array('{"\"g\"=>\"h\"",NULL}', array(array('g'=>'h'), null)),
            array(
                '{{"","\"a\"=>\"b\""},{"\"c\"=>\"d\"",NULL}}',
                array(array(array(), array('a' => 'b')), array(array('c' => 'd'), null))
            )
        );
    }

    protected function valuesFrom()
    {
        return array();
    }

    protected function valuesTo()
    {
        return array();
    }
}