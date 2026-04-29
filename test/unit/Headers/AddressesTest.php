<?php

declare(strict_types=1);

namespace Horde\Mime\Test\Headers;

use Horde\Mime\Headers\Addresses;
use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class AddressesTest extends TestCase
{
    public function testBasic(): void
    {
        $addr = new Addresses('From', 'user@example.com');
        $this->assertSame('From', $addr->name());
        $this->assertSame('user@example.com', $addr->value());
    }

    public function testAddressList(): void
    {
        $addr = new Addresses('To', 'user@example.com');
        $list = $addr->addressList();
        $this->assertCount(1, $list);
        $this->assertSame('user', $list->first()->mailbox);
    }

    public function testMultipleAddresses(): void
    {
        $addr = new Addresses('To', 'a@example.com, b@example.com');
        $list = $addr->addressList();
        $this->assertCount(2, $list);
    }

    public function testHandles(): void
    {
        $handles = Addresses::handles();
        $this->assertContains('from', $handles);
        $this->assertContains('to', $handles);
        $this->assertContains('cc', $handles);
        $this->assertContains('bcc', $handles);
        $this->assertContains('reply-to', $handles);
    }

    public function testSendEncode(): void
    {
        $addr = new Addresses('From', 'user@example.com');
        $encoded = $addr->sendEncode();
        $this->assertSame(['From: user@example.com'], $encoded);
    }
}
