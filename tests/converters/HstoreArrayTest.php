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

namespace sad_spirit\pg_wrapper\tests\converters;

use sad_spirit\pg_wrapper\converters\{
    containers\HstoreConverter,
    containers\ArrayConverter
};

/**
 * Unit test for a combination of array and hstore type converters
 */
class HstoreArrayTest extends TypeConverterTestCase
{
    public function setUp(): void
    {
        $this->converter = new ArrayConverter(new HstoreConverter());
    }

    protected function valuesBoth()
    {
        return [
            [null, null],
            ['{"\"a\"=>\"b\"","\"c\"=>\"d\", \"e\"=>\"f\""}', [['a' => 'b'], ['c' => 'd', 'e' => 'f']]],
            ['{"\"g\"=>\"h\"",NULL}', [['g' => 'h'], null]],
            [
                '{{"","\"a\"=>\"b\""},{"\"c\"=>\"d\"",NULL}}',
                [[[], ['a' => 'b']], [['c' => 'd'], null]]
            ]
        ];
    }

    protected function valuesFrom()
    {
        return [];
    }

    protected function valuesTo()
    {
        return [];
    }
}
