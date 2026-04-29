<?php

declare(strict_types=1);

namespace Horde\Mime\Test\Headers;

use Horde\Mime\Headers\Received;
use PHPUnit\Framework\TestCase;

class ReceivedTest extends TestCase
{
    public function testMultipleValues(): void
    {
        $r = new Received('Received', ['from a by b', 'from c by d']);
        $this->assertCount(2, $r->values());
        $this->assertSame('from a by b, from c by d', $r->value());
    }

    public function testWithValue(): void
    {
        $r = new Received('Received', ['from a by b']);
        $r2 = $r->withValue('from c by d');

        $this->assertCount(1, $r->values());
        $this->assertCount(2, $r2->values());
    }

    public function testSendEncode(): void
    {
        $r = new Received('Received', ['from a', 'from b']);
        $encoded = $r->sendEncode();
        $this->assertCount(2, $encoded);
        $this->assertSame('Received: from a', $encoded[0]);
        $this->assertSame('Received: from b', $encoded[1]);
    }

    public function testHandles(): void
    {
        $this->assertSame(['received'], Received::handles());
    }
}
