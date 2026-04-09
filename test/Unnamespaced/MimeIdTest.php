<?php

/**
 * Copyright 2014-2026 Horde LLC (http://www.horde.org/)
 *
 * @category   Horde
 * @copyright  2014-2016 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Mime
 * @subpackage UnitTests
 */

namespace Horde\Mime\Test\Unnamespaced;

use PHPUnit\Framework\TestCase;
use Horde_Mime_Id;

/**
 * Tests for the Horde_Mime_Id class.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @category   Horde
 * @copyright  2014-2016 Horde LLC
 * @internal
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Mime
 * @subpackage UnitTests
 * @coversNothing
 */
class MimeIdTest extends TestCase
{
    public function testToString()
    {
        $id = '2.1';
        $id_ob = new Horde_Mime_Id($id);

        $this->assertEquals(
            $id,
            strval($id_ob)
        );
    }

    /**
     * @dataProvider idArithmeticProvider
     */
    public function testIdArithmetic($id, $action, $opts, $expected)
    {
        $id_ob = new Horde_Mime_Id($id);

        $this->assertEquals(
            $expected,
            $id_ob->idArithmetic($action, $opts)
        );
    }

    public static function idArithmeticProvider()
    {
        return [
            [
                '0',
                Horde_Mime_Id::ID_DOWN,
                [],
                '1',
            ],
            [
                '1.1',
                Horde_Mime_Id::ID_DOWN,
                [],
                '1.1.0',
            ],
            [
                '1.1',
                Horde_Mime_Id::ID_DOWN,
                ['no_rfc822' => true],
                '1.1.1',
            ],
            [
                '1.1',
                Horde_Mime_Id::ID_NEXT,
                [],
                '1.2',
            ],
            [
                '1.1',
                Horde_Mime_Id::ID_NEXT,
                ['count' => 3],
                '1.4',
            ],
            [
                '1',
                Horde_Mime_Id::ID_NEXT,
                [],
                '2',
            ],
            [
                '1.2',
                Horde_Mime_Id::ID_PREV,
                [],
                '1.1',
            ],
            [
                '1.1',
                Horde_Mime_Id::ID_PREV,
                [],
                null,
            ],
            [
                '2',
                Horde_Mime_Id::ID_PREV,
                [],
                '1',
            ],
            [
                '1.1',
                Horde_Mime_Id::ID_UP,
                [],
                '1.0',
            ],
            [
                '1.1',
                Horde_Mime_Id::ID_UP,
                ['no_rfc822' => true],
                '1',
            ],
            [
                '2',
                Horde_Mime_Id::ID_UP,
                [],
                '0',
            ],
        ];
    }

    /**
     * @dataProvider isChildProvider
     */
    public function testIsChild($base, $id, $expected)
    {
        $id_ob = new Horde_Mime_Id($base);

        if ($expected) {
            $this->assertTrue($id_ob->isChild($id));
        } else {
            $this->assertFalse($id_ob->isChild($id));
        }
    }

    public static function isChildProvider()
    {
        return [
            ['1', '1.0', true],
            ['1', '1.1', true],
            ['1', '1.1.0', true],
            ['1', '1.1.1.1.1.1.1', true],
            ['1', '1', false],
            ['1', '2', false],
            ['1', '2.1', false],
            ['1', '10', false],
            ['1', '10.0', false],
        ];
    }

}
