<?php

declare(strict_types=1);

namespace Horde\Mime\Test\Encoding;

use Horde\Mime\Encoding\TransferDecoder;
use Horde\Mime\Encoding\TransferEncoder;
use Horde\Mime\TransferEncoding;
use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class TransferEncoderTest extends TestCase
{
    public function testBase64(): void
    {
        $encoded = TransferEncoder::encode('Hello World', TransferEncoding::Base64);
        $this->assertSame('Hello World', base64_decode($encoded, true));
    }

    public function testBase64LineWrapping(): void
    {
        $data = str_repeat('A', 200);
        $encoded = TransferEncoder::encode($data, TransferEncoding::Base64, "\r\n");
        $lines = explode("\r\n", $encoded);
        foreach ($lines as $line) {
            $this->assertLessThanOrEqual(76, strlen($line));
        }
    }

    public function testQuotedPrintable(): void
    {
        $encoded = TransferEncoder::encode("Hello \xC3\xBC", TransferEncoding::QuotedPrintable);
        $this->assertStringContainsString('=C3=BC', $encoded);
    }

    public function testSevenBitPassthrough(): void
    {
        $this->assertSame('data', TransferEncoder::encode('data', TransferEncoding::SevenBit));
    }

    public function testBinaryPassthrough(): void
    {
        $data = "\x00\x01\x02";
        $this->assertSame($data, TransferEncoder::encode($data, TransferEncoding::Binary));
    }

    public function testBase64RoundTrip(): void
    {
        $original = random_bytes(256);
        $encoded = TransferEncoder::encode($original, TransferEncoding::Base64);
        $decoded = TransferDecoder::decode($encoded, TransferEncoding::Base64);
        $this->assertSame($original, $decoded);
    }
}
