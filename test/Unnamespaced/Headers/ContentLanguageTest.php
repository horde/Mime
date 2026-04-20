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
use Horde_Mime_Headers_ContentLanguage;

/**
 * Tests for the Horde_Mime_Headers_ContentLanguage class.
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
class ContentLanguageTest extends TestCase
{
    /**
     * @dataProvider parsingOfInputProvider
     */
    #[DataProvider('parsingOfInputProvider')]
    public function testParsingOfInput($input, $expected_val, $expected_langs)
    {
        $ob = new Horde_Mime_Headers_ContentLanguage(null, $input);

        $this->assertEquals(
            $expected_val,
            $ob->value
        );

        $this->assertEquals(
            $expected_langs,
            $ob->langs
        );
    }

    public static function parsingOfInputProvider()
    {
        return [
            [
                'en',
                'en',
                ['en'],
            ],
            [
                'en, de',
                'en,de',
                ['en', 'de'],
            ],
            [
                '    eN  , de      ,PT',
                'en,de,pt',
                ['en', 'de', 'pt'],
            ],
            [
                ['en', 'de'],
                'en,de',
                ['en', 'de'],
            ],
            [
                "e\0n",
                'en',
                ['en'],
            ],
        ];
    }

}
