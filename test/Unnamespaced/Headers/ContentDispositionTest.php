<?php

/**
 * Copyright 2015-2026 Horde LLC (http://www.horde.org/)
 *
 * @category   Horde
 * @copyright  2015-2016 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Mime
 * @subpackage UnitTests
 */

namespace Horde\Mime\Test\Unnamespaced\Headers;

use PHPUnit\Framework\TestCase;
use PHPUnit\Framework\Attributes\DataProvider;
use Horde_Mime_Headers_ContentParam_ContentDisposition;

/**
 * Tests for the Horde_Mime_Headers_ContentParam_ContentDisposition class.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @category   Horde
 * @copyright  2015-2016 Horde LLC
 * @internal
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Mime
 * @subpackage UnitTests
 * @coversNothing
 */
class ContentDispositionTest extends TestCase
{
    /**
     * @dataProvider parsingOfInputProvider
     */
    #[DataProvider('parsingOfInputProvider')]
    public function testParsingOfInput($input, $expected_val, $expected_params)
    {
        $ob = new Horde_Mime_Headers_ContentParam_ContentDisposition(
            'Content-Disposition',
            $input
        );

        $this->assertEquals(
            $expected_val,
            $ob->value
        );

        $params = $ob->params;
        ksort($params);

        $this->assertEquals(
            $expected_params,
            $params
        );
    }

    public static function parsingOfInputProvider()
    {
        return [
            [
                'inline',
                'inline',
                [],
            ],
            [
                '    INLINE',
                'inline',
                [],
            ],
            [
                'attachment',
                'attachment',
                [],
            ],
            [
                ' AtTaChMeNt   ',
                'attachment',
                [],
            ],
            [
                'bogus',
                '',
                [],
            ],
            [
                " iNLINe   ;   filename=\"foo\";\n bar=33;    size = 22",
                'inline',
                [
                    'bar' => '33',
                    'filename' => 'foo',
                    'size' => 22,
                ],
            ],
            [
                "attachMENT;Filename=\"foo\";bar=33;SIZE=\"22\"",
                'attachment',
                [
                    'bar' => '33',
                    'filename' => 'foo',
                    'size' => 22,
                ],
            ],
        ];
    }

    /**
     * @dataProvider fullValueProvider
     */
    #[DataProvider('fullValueProvider')]
    public function testFullValue($value, $params, $expected)
    {
        $ob = new Horde_Mime_Headers_ContentParam_ContentDisposition(
            'Content-Disposition',
            ''
        );

        $ob->setContentParamValue($value);
        foreach ($params as $key => $val) {
            $ob[$key] = $val;
        }

        $this->assertEquals(
            $expected,
            $ob->full_value
        );
    }

    public static function fullValueProvider()
    {
        return [
            [
                'attachment',
                ['foo' => 'bar'],
                'attachment; foo=bar',
            ],
            [
                'inline',
                [
                    'Foo' => 'BAR',
                    'BAZ' => 345,
                ],
                'inline; Foo=BAR; BAZ=345',
            ],
            [
                '',
                [
                    'Foo' => 'BAR',
                ],
                'attachment; Foo=BAR',
            ],
            [
                'inline; foo=bar',
                [],
                'inline',
            ],
        ];
    }

    public function testSerialize()
    {
        $ob = new Horde_Mime_Headers_ContentParam_ContentDisposition(
            'Content-Disposition',
            'inline; foo=bar;'
        );

        $ob2 = unserialize(serialize($ob));

        $this->assertEquals(
            'inline',
            $ob2->value
        );
        $this->assertEquals(
            ['foo' => 'bar'],
            $ob2->params
        );
    }

    public function testClone()
    {
        $ob = new Horde_Mime_Headers_ContentParam_ContentDisposition(
            'Content-Disposition',
            'inline; foo=bar;'
        );

        $ob2 = clone $ob;

        $ob->setContentParamValue('attachment');
        $ob['foo'] = 123;

        $this->assertEquals(
            'inline',
            $ob2->value
        );
        $this->assertEquals(
            ['foo' => 'bar'],
            $ob2->params
        );
    }

    /**
     * @dataProvider isDefaultProvider
     */
    #[DataProvider('isDefaultProvider')]
    public function testIsDefault($value, $is_default)
    {
        $ob = new Horde_Mime_Headers_ContentParam_ContentDisposition(
            'Content-Disposition',
            $value
        );

        if ($is_default) {
            $this->assertTrue($ob->isDefault());
        } else {
            $this->assertFalse($ob->isDefault());
        }
    }

    public static function isDefaultProvider()
    {
        return [
            [
                '',
                true,
            ],
            [
                'attachment',
                false,
            ],
            [
                'attachment; foo=bar',
                false,
            ],
            [
                'inline',
                false,
            ],
            [
                'inline; foo=bar',
                false,
            ],
        ];
    }

}
