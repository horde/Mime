<?php

/**
 * Copyright 2012-2026 Horde LLC (http://www.horde.org/)
 *
 * @category   Horde
 * @copyright  2012-2016 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Mime
 * @subpackage UnitTests
 */

namespace Horde\Mime\Test\Unnamespaced;

use PHPUnit\Framework\TestCase;
use Horde_Mime_Related;
use Horde_Mime_Part;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * Tests for the Horde_Mime_Related class.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @category   Horde
 * @copyright  2012-2016 Horde LLC
 * @internal
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Mime
 * @subpackage UnitTests
 * @coversNothing
 */
class RelatedTest extends TestCase
{
    /**
     * @dataProvider startProvider
     */
    #[DataProvider('startProvider')]
    public function testStart($ob, $id)
    {
        $this->assertEquals(
            $id,
            $ob->startId()
        );
    }

    public static function startProvider()
    {
        return [
            [
                new Horde_Mime_Related(Horde_Mime_Part::parseMessage(
                    file_get_contents(__DIR__ . '/fixtures/related_msg.txt')
                )),
                1,
            ],
            [
                new Horde_Mime_Related(Horde_Mime_Part::parseMessage(
                    file_get_contents(__DIR__ . '/fixtures/related_msg_2.txt')
                )),
                2,
            ],
        ];
    }

    /**
     * @dataProvider searchProvider
     */
    #[DataProvider('searchProvider')]
    public function testSearch($ob, $search, $id)
    {
        $this->assertEquals(
            $id,
            $ob->cidSearch($search)
        );
    }

    public static function searchProvider()
    {
        return [
            [
                new Horde_Mime_Related(Horde_Mime_Part::parseMessage(
                    file_get_contents(__DIR__ . '/fixtures/related_msg.txt')
                )),
                '789',
                3,
            ],
            [
                new Horde_Mime_Related(Horde_Mime_Part::parseMessage(
                    file_get_contents(__DIR__ . '/fixtures/related_msg_2.txt')
                )),
                'abc',
                2,
            ],
        ];
    }

    /**
     * @dataProvider iteratorProvider
     */
    #[DataProvider('iteratorProvider')]
    public function testIterator($ob, $ids)
    {
        $this->assertEquals(
            $ids,
            iterator_to_array($ob)
        );
    }

    public static function iteratorProvider()
    {
        return [
            [
                new Horde_Mime_Related(Horde_Mime_Part::parseMessage(
                    file_get_contents(__DIR__ . '/fixtures/related_msg.txt')
                )),
                ['2' => '456', '3' => '789'],
            ],
            [
                new Horde_Mime_Related(Horde_Mime_Part::parseMessage(
                    file_get_contents(__DIR__ . '/fixtures/related_msg_2.txt')
                )),
                ['2' => 'abc'],
            ],
        ];
    }

    public function testReplace()
    {
        $part = Horde_Mime_Part::parseMessage(
            file_get_contents(__DIR__ . '/fixtures/related_msg.txt')
        );
        $related = new Horde_Mime_Related($part);

        $ob = $related->cidReplace(
            $part['1']->getContents(),
            [$this, 'callbackTestReplace']
        );

        $body = $ob->dom->getElementsByTagName('body');
        $this->assertEquals(
            1,
            $body->length
        );
        $this->assertEquals(
            '2',
            $body->item(0)->getAttribute('background')
        );

        $body = $ob->dom->getElementsByTagName('img');
        $this->assertEquals(
            1,
            $body->length
        );
        $this->assertEquals(
            '3',
            $body->item(0)->getAttribute('src')
        );
    }

    public function callbackTestReplace($id)
    {
        return $id;
    }

}
