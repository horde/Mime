<?php

declare(strict_types=1);

namespace Horde\Mime\Test\Encoding;

use Horde\Mime\Encoding\EncodingDetector;
use Horde\Mime\TransferEncoding;
use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class EncodingDetectorTest extends TestCase
{
    public function testEmptyIsSevenBit(): void
    {
        $this->assertSame(TransferEncoding::SevenBit, EncodingDetector::detect(''));
    }

    public function testPureAsciiIsSevenBit(): void
    {
        $this->assertSame(TransferEncoding::SevenBit, EncodingDetector::detect('Hello World'));
    }

    public function testHighBytesIsEightBit(): void
    {
        $this->assertSame(TransferEncoding::EightBit, EncodingDetector::detect("Hello \xC3\xBC"));
    }

    public function testNulByteIsBinary(): void
    {
        $this->assertSame(TransferEncoding::Binary, EncodingDetector::detect("Hello\x00World"));
    }

    public function testLongLineIsBinary(): void
    {
        $this->assertSame(TransferEncoding::Binary, EncodingDetector::detect(str_repeat('a', 999)));
    }

    public function testLongLineWithNewlineIsNotBinary(): void
    {
        $data = str_repeat('a', 998) . "\n" . str_repeat('b', 998);
        $this->assertSame(TransferEncoding::SevenBit, EncodingDetector::detect($data));
    }

    public function testRecommendSevenBit(): void
    {
        $this->assertSame(
            TransferEncoding::SevenBit,
            EncodingDetector::recommend(TransferEncoding::SevenBit, 'text'),
        );
    }

    public function testRecommendBase64ForBinary(): void
    {
        $this->assertSame(
            TransferEncoding::Base64,
            EncodingDetector::recommend(TransferEncoding::Binary, 'image'),
        );
    }

    public function testRecommendBinaryWhenAllowed(): void
    {
        $this->assertSame(
            TransferEncoding::Binary,
            EncodingDetector::recommend(
                TransferEncoding::Binary,
                'image',
                EncodingDetector::ALLOW_BINARY,
            ),
        );
    }

    public function testRecommendQpForText8bit(): void
    {
        $this->assertSame(
            TransferEncoding::QuotedPrintable,
            EncodingDetector::recommend(TransferEncoding::EightBit, 'text'),
        );
    }

    public function testRecommendBase64ForNonText8bit(): void
    {
        $this->assertSame(
            TransferEncoding::Base64,
            EncodingDetector::recommend(TransferEncoding::EightBit, 'application'),
        );
    }

    public function testRecommend8bitWhenAllowed(): void
    {
        $this->assertSame(
            TransferEncoding::EightBit,
            EncodingDetector::recommend(
                TransferEncoding::EightBit,
                'text',
                EncodingDetector::ALLOW_8BIT,
            ),
        );
    }
}
