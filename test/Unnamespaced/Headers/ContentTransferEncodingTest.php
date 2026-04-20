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
use Horde_Mime_Headers_ContentTransferEncoding;

/**
 * Tests for the Horde_Mime_Headers_ContentTransferEncoding class.
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
class ContentTransferEncodingTest extends TestCase
{
    /**
     * @dataProvider valuesProvider
     */
    #[DataProvider('valuesProvider')]
    public function testValues($input, $expected_val, $is_default)
    {
        $ob = new Horde_Mime_Headers_ContentTransferEncoding(null, $input);

        $this->assertEquals(
            $expected_val,
            $ob->value
        );

        if ($is_default) {
            $this->assertTrue($ob->isDefault());
        } else {
            $this->assertFalse($ob->isDefault());
        }
    }

    public static function valuesProvider()
    {
        return [
            [
                '7bit',
                '7bit',
                true,
            ],
            [
                ' 8BIT',
                '8bit',
                false,
            ],
            [
                ' quoted-pRiNtAbLe   ',
                'quoted-printable',
                false,
            ],
            [
                'BINARY',
                'binary',
                false,
            ],
            [
                'base64',
                'base64',
                false,
            ],
            [
                "7\0bit",
                '7bit',
                true,
            ],
            [
                ' X-foo',
                'x-foo',
                false,
            ],
            [
                'foo',
                Horde_Mime_Headers_ContentTransferEncoding::UNKNOWN_ENCODING,
                false,
            ],
        ];
    }

}
