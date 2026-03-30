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
use Horde_Mail_Rfc822;
use Horde_Mime_Headers;
use Horde_Mime_Mdn;

/**
 * Tests for the Horde_Mime_Mdn object.
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
class MdnTest extends TestCase
{
    /**
     * @dataProvider getMdnReturnAddrProvider
     */
    public function testGetMdnReturnAddr($email)
    {
        $h = new Horde_Mime_Headers();
        $ob = new Horde_Mime_Mdn($h);

        if (!is_null($email)) {
            $ob->addMdnRequestHeaders($email);
        }

        $this->assertEquals(
            strval($email),
            $ob->getMdnReturnAddr()
        );
    }

    public function getMdnReturnAddrProvider()
    {
        $email = 'foo1@example.com, Test <foo2@example.com>';

        $rfc822 = new Horde_Mail_Rfc822();
        $mail_ob = $rfc822->parseAddressList($email);

        return [
            [null],
            ['foo@example.com'],
            [$email],
            [$mail_ob],
        ];
    }

    /**
     * @dataProvider UserConfirmationNeededProvider
     */
    public function testUserConfirmationNeeded($h, $expected)
    {
        $ob = new Horde_Mime_Mdn($h);
        if ($expected) {
            $this->assertTrue($ob->userConfirmationNeeded());
        } else {
            $this->assertFalse($ob->userConfirmationNeeded());
        }
    }

    public function userConfirmationNeededProvider()
    {
        $out = [];

        $h = new Horde_Mime_Headers();
        $out[] = [clone $h, true];

        $h->addHeader('Return-Path', 'foo@example.com');
        $out[] = [clone $h, false];

        $h->addHeader('Return-Path', 'foo2@example.com');
        $out[] = [clone $h, true];

        $h->replaceHeader('Return-Path', 'foo@example.com');

        $h->addHeader(Horde_Mime_Mdn::MDN_HEADER, 'FOO@example.com');
        $out[] = [clone $h, true];

        $h->replaceHeader(Horde_Mime_Mdn::MDN_HEADER, 'foo@EXAMPLE.com');
        $out[] = [clone $h, false];

        return $out;
    }

}
