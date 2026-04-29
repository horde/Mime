<?php

declare(strict_types=1);

namespace Horde\Mime\Test\Headers;

use Horde\Mime\Headers\ContentTransferEncoding;
use Horde\Mime\TransferEncoding;
use PHPUnit\Framework\TestCase;

class ContentTransferEncodingTest extends TestCase
{
    public function testSevenBit(): void
    {
        $cte = new ContentTransferEncoding('Content-Transfer-Encoding', '7bit');
        $this->assertSame(TransferEncoding::SevenBit, $cte->encoding);
        $this->assertTrue($cte->isDefault());
    }

    public function testBase64(): void
    {
        $cte = new ContentTransferEncoding('Content-Transfer-Encoding', 'base64');
        $this->assertSame(TransferEncoding::Base64, $cte->encoding);
        $this->assertFalse($cte->isDefault());
    }

    public function testQuotedPrintable(): void
    {
        $cte = new ContentTransferEncoding('Content-Transfer-Encoding', 'quoted-printable');
        $this->assertSame(TransferEncoding::QuotedPrintable, $cte->encoding);
    }

    public function testCaseInsensitive(): void
    {
        $cte = new ContentTransferEncoding('Content-Transfer-Encoding', 'BASE64');
        $this->assertSame(TransferEncoding::Base64, $cte->encoding);
    }

    public function testUnknownFallsToSevenBit(): void
    {
        $cte = new ContentTransferEncoding('Content-Transfer-Encoding', 'x-unknown');
        $this->assertSame(TransferEncoding::SevenBit, $cte->encoding);
    }

    public function testSendEncode(): void
    {
        $cte = new ContentTransferEncoding('Content-Transfer-Encoding', 'base64');
        $encoded = $cte->sendEncode();
        $this->assertSame(['Content-Transfer-Encoding: base64'], $encoded);
    }

    public function testHandles(): void
    {
        $this->assertSame(['content-transfer-encoding'], ContentTransferEncoding::handles());
    }
}
