<?php
/**
 * Copyright 2010-2017 Horde LLC (http://www.horde.org/)
 *
 * @category   Horde
 * @copyright  2010-2016 Horde LLC
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Mime
 * @subpackage UnitTests
 */
namespace Horde\Mime\Test\Unnamespaced;
use PHPUnit\Framework\TestCase;
use \Horde_Mime_Magic;

/**
 * Tests for the Horde_Mime_Magic class.
 *
 * @author     Michael Slusarz <slusarz@horde.org>
 * @category   Horde
 * @copyright  2010-2016 Horde LLC
 * @internal
 * @license    http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package    Mime
 * @subpackage UnitTests
 */
class MagicTest extends TestCase
{
    /**
     * @requires extension fileinfo
     */
    public function testBug325()
    {
        $this->assertEquals(
            'text/plain',
            Horde_Mime_Magic::analyzeFile(__DIR__ . '/fixtures/flowed_msg.txt')
        );
    }

}
