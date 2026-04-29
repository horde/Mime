<?php

declare(strict_types=1);

namespace Horde\Mime\Test;

use Horde\Mime\TransferEncoding;
use PHPUnit\Framework\TestCase;
use ValueError;

class TransferEncodingTest extends TestCase
{
    public function testAllCases(): void
    {
        $this->assertSame('7bit', TransferEncoding::SevenBit->value);
        $this->assertSame('8bit', TransferEncoding::EightBit->value);
        $this->assertSame('binary', TransferEncoding::Binary->value);
        $this->assertSame('quoted-printable', TransferEncoding::QuotedPrintable->value);
        $this->assertSame('base64', TransferEncoding::Base64->value);
    }

    public function testIsDefault(): void
    {
        $this->assertTrue(TransferEncoding::SevenBit->isDefault());
        $this->assertFalse(TransferEncoding::EightBit->isDefault());
        $this->assertFalse(TransferEncoding::Binary->isDefault());
        $this->assertFalse(TransferEncoding::QuotedPrintable->isDefault());
        $this->assertFalse(TransferEncoding::Base64->isDefault());
    }

    public function testFromString(): void
    {
        $this->assertSame(TransferEncoding::SevenBit, TransferEncoding::fromString('7bit'));
        $this->assertSame(TransferEncoding::Base64, TransferEncoding::fromString('BASE64'));
        $this->assertSame(TransferEncoding::QuotedPrintable, TransferEncoding::fromString(' Quoted-Printable '));
    }

    public function testFromStringInvalid(): void
    {
        $this->expectException(ValueError::class);
        TransferEncoding::fromString('x-unknown');
    }
}
