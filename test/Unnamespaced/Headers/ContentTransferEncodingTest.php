<?php
/**
 * Copyright 2015-2017 Horde LLC (http://www.horde.org/)
 *
 * @category   Horde
 * @copyright  2015-2016 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Mime
 * @subpackage UnitTests
 */
namespace Horde\Mime\Test\Unnamespaced\Headers;
use PHPUnit\Framework\TestCase;
use \Horde_Mime_Headers_ContentTransferEncoding;

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
 */
class ContentTransferEncodingTest extends TestCase
{
    /**
     * @dataProvider valuesProvider
     */
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

    public function valuesProvider()
    {
        return array(
            array(
                '7bit',
                '7bit',
                true
            ),
            array(
                ' 8BIT',
                '8bit',
                false
            ),
            array(
                ' quoted-pRiNtAbLe   ',
                'quoted-printable',
                false
            ),
            array(
                'BINARY',
                'binary',
                false
            ),
            array(
                'base64',
                'base64',
                false
            ),
            array(
                "7\0bit",
                '7bit',
                true
            ),
            array(
                ' X-foo',
                'x-foo',
                false
            ),
            array(
                'foo',
                Horde_Mime_Headers_ContentTransferEncoding::UNKNOWN_ENCODING,
                false
            )
        );
    }

}
