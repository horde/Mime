<?php

/**
 * Copyright 2010-2026 Horde LLC (http://www.horde.org/)
 *
 * @category   Horde
 * @copyright  2010-2016 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Mime
 * @subpackage UnitTests
 */

namespace Horde\Mime\Test\Unnamespaced;

use PHPUnit\Framework\TestCase;
use Horde_Mime;

/**
 * Tests for the Horde_Mime class.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @category   Horde
 * @copyright  2010-2016 Horde LLC
 * @internal
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Mime
 * @subpackage UnitTests
 * @coversNothing
 */
class MimeTest extends TestCase
{
    /**
     * @dataProvider is8bitProvider
     */
    public function testIs8bit($data, $expected)
    {
        $this->assertEquals(
            $expected,
            Horde_Mime::is8bit($data)
        );
    }

    public static function is8bitProvider()
    {
        return [
            ['A', false],
            ['a', false],
            ['1', false],
            ['!', false],
            ["\0", false],
            ["\10", false],
            ["\127", false],
            ["\x80", true],
            ['ä', true],
            ['A©B', true],
            [' ® ', true],
            // This string is in Windows-1252
            [base64_decode('UnVubmVyc5IgQWxlcnQh='), true],
        ];
    }

    /**
     * @dataProvider decodeProvider
     */
    public function testDecode($data, $expected)
    {
        $this->assertEquals(
            $expected,
            Horde_Mime::decode($data)
        );
    }

    public static function decodeProvider()
    {
        return [
            [
                '=?utf-8?Q?_Fran=C3=A7ois_Xavier=2E_XXXXXX_?= <foo@example.com>',
                ' François Xavier. XXXXXX  <foo@example.com>',
            ],

            /* Adapted from Dovecot's
             * src/lib-mail/test-message-header-decode.c. */
            [
                " \t=?utf-8?q?=c3=a4?=  =?utf-8?q?=c3=a4?=  b  \t\r\n ",
                " \tää  b  \t\r\n ",
            ],
            [
                "a =?utf-8?q?=c3=a4?= b",
                "a ä b",
            ],
            [
                "a =?utf-8?q?=c3=a4?=\t\t\r\n =?utf-8?q?=c3=a4?= b",
                "a ää b",
            ],
            [
                "a =?utf-8?q?=c3=a4?=  x  =?utf-8?q?=c3=a4?= b",
                "a ä  x  ä b",
            ],
            [
                "a =?utf-8?b?w6TDpCDDpA==?= b",
                "a ää ä b",
            ], [
                "=?utf-8?b?w6Qgw6Q=?=",
                "ä ä",
            ],

            /* Not MIME encoded. */
            [
                '=? required=?',
                '=? required=?',
            ],
        ];
    }

    /**
     * @dataProvider encodeProvider
     */
    public function testEncode($data, $charset, $expected)
    {
        $this->assertEquals(
            $expected,
            Horde_Mime::encode($data, $charset)
        );
    }

    public static function encodeProvider()
    {
        return [
            /* Adapted from Dovecot's
             * src/lib-mail/test-message-header-encode.c. */
            [
                'a b',
                'utf-8',
                'a b',
            ],
            [
                'a bcäde f',
                'utf-8',
                'a =?utf-8?b?YmPDpGRl?= f',
            ],
            [
                'a ää ä b',
                'utf-8',
                'a =?utf-8?b?w6TDpCDDpA==?= b',
            ],
            [
                'ä a ä',
                'utf-8',
                '=?utf-8?b?w6Q=?= a =?utf-8?b?w6Q=?=',
            ],
            [
                'ää a ä',
                'utf-8',
                // Dovecot: '=?utf-8?b?w6TDpCBhIMOk?='
                '=?utf-8?b?w6TDpA==?= a =?utf-8?b?w6Q=?=',
            ],
            [
                '=',
                'utf-8',
                '=',
            ],
            [
                '?',
                'utf-8',
                '?',
            ],
            [
                'a=?',
                'utf-8',
                'a=?',
            ],
            [
                '=?',
                'utf-8',
                // Dovecot: '=?utf-8?q?=3D=3F?='
                '=?utf-8?b?PT8=?=',
            ],
            [
                '=?x',
                'utf-8',
                // Dovecot: '=?utf-8?q?=3D=3Fx?='
                '=?utf-8?b?PT94?=',
            ],
            [
                "a\n=?",
                'utf-8',
                // Dovecot: "a\n\t=?utf-8?q?=3D=3F?="
                "a\n=?utf-8?b?PT8=?=",
            ],
            [
                "a\t=?",
                'utf-8',
                // Dovecot: "a\t=?utf-8?q?=3D=3F?="
                "a\t=?utf-8?b?PT8=?=",
            ],
            [
                "a =?",
                'utf-8',
                // Dovecot: "a =?utf-8?q?=3D=3F?="
                'a =?utf-8?b?PT8=?=',
            ],
            [
                "foo\001bar",
                'utf-8',
                // Dovecot: "=?utf-8?q?foo=01bar?="
                '=?utf-8?b?Zm9vAWJhcg==?=',
            ],
            [
                "\x01\x02\x03\x04\x05\x06\x07\x08",
                'utf-8',
                "=?utf-8?b?AQIDBAUGBwg=?=",
            ],

            /* Null character in encode output. */
            [
                "\x00",
                'UTF-16LE',
                '=?utf-16le?b?AAA=?=',
            ],
        ];
    }

}
