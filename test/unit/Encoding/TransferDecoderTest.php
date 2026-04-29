<?php

declare(strict_types=1);

namespace Horde\Mime\Test\Encoding;

use Horde\Mime\Encoding\TransferDecoder;
use Horde\Mime\TransferEncoding;
use PHPUnit\Framework\TestCase;

class TransferDecoderTest extends TestCase
{
    public function testBase64(): void
    {
        $encoded = base64_encode('Hello World');
        $this->assertSame('Hello World', TransferDecoder::decode($encoded, TransferEncoding::Base64));
    }

    public function testQuotedPrintable(): void
    {
        $this->assertSame('=', TransferDecoder::decode('=3D', TransferEncoding::QuotedPrintable));
    }

    public function testSevenBitPassthrough(): void
    {
        $this->assertSame('data', TransferDecoder::decode('data', TransferEncoding::SevenBit));
    }

    public function testEightBitPassthrough(): void
    {
        $this->assertSame("data\xC3\xBC", TransferDecoder::decode("data\xC3\xBC", TransferEncoding::EightBit));
    }

    public function testBinaryPassthrough(): void
    {
        $data = "\x00\x01\x02";
        $this->assertSame($data, TransferDecoder::decode($data, TransferEncoding::Binary));
    }

    public function testInvalidBase64ReturnsEmpty(): void
    {
        $this->assertSame('', TransferDecoder::decode('!!!not-base64!!!', TransferEncoding::Base64));
    }
}
