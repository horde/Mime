<?php

declare(strict_types=1);

namespace Horde\Mime\Test\Headers;

use Horde\Mime\Headers\MessageId;
use PHPUnit\Framework\TestCase;

class MessageIdTest extends TestCase
{
    public function testCreate(): void
    {
        $mid = MessageId::create();
        $this->assertSame('Message-ID', $mid->name());
        $this->assertStringStartsWith('<', $mid->value());
        $this->assertStringEndsWith('>', $mid->value());
        $this->assertStringContainsString('@', $mid->value());
    }

    public function testCreateWithPrefix(): void
    {
        $mid = MessageId::create('Test');
        $this->assertStringContainsString('Test', $mid->value());
    }

    public function testIds(): void
    {
        $mid = new MessageId('Message-ID', '<abc123@example.com>');
        $ids = $mid->ids();
        $this->assertCount(1, $ids);
        $this->assertSame('abc123@example.com', $ids[0]);
    }

    public function testHandles(): void
    {
        $this->assertSame(['message-id'], MessageId::handles());
    }

    public function testSendEncode(): void
    {
        $mid = new MessageId('Message-ID', '<test@example.com>');
        $encoded = $mid->sendEncode();
        $this->assertSame(['Message-ID: <test@example.com>'], $encoded);
    }
}
