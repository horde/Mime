<?php

declare(strict_types=1);

namespace Horde\Mime\Test\Encoding;

use Horde\Mime\Encoding\QuotedPrintable;
use PHPUnit\Framework\TestCase;

class QuotedPrintableTest extends TestCase
{
    public function testDecodeSimple(): void
    {
        $this->assertSame('Hello World', QuotedPrintable::decode('Hello World'));
    }

    public function testDecodeEncoded(): void
    {
        $this->assertSame('=', QuotedPrintable::decode('=3D'));
    }

    public function testEncodePureAscii(): void
    {
        $this->assertSame('Hello', QuotedPrintable::encode('Hello'));
    }

    public function testEncodeHighBytes(): void
    {
        $encoded = QuotedPrintable::encode("\xC3\xBC");
        $this->assertStringContainsString('=C3=BC', $encoded);
    }

    public function testRoundTrip(): void
    {
        $original = "Hello W\xC3\xB6rld with sp=cial chars";
        $encoded = QuotedPrintable::encode($original);
        $decoded = QuotedPrintable::decode($encoded);
        $this->assertSame($original, $decoded);
    }

    public function testCustomEol(): void
    {
        $long = str_repeat('a', 200);
        $encoded = QuotedPrintable::encode($long, "\r\n");
        $this->assertStringContainsString("\r\n", $encoded);
    }
}
