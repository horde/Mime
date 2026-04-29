<?php

declare(strict_types=1);

namespace Horde\Mime\Test\Encoding;

use Horde\Mime\Encoding\ContentParamDecoder;
use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class ContentParamDecoderTest extends TestCase
{
    public function testSimpleParams(): void
    {
        $decoder = new ContentParamDecoder();
        $result = $decoder->decode('charset=UTF-8');
        $this->assertSame(['charset' => 'UTF-8'], $result);
    }

    public function testMultipleParams(): void
    {
        $decoder = new ContentParamDecoder();
        $result = $decoder->decode('charset=UTF-8; boundary=abc123');
        $this->assertSame(['charset' => 'UTF-8', 'boundary' => 'abc123'], $result);
    }

    public function testQuotedValues(): void
    {
        $decoder = new ContentParamDecoder();
        $result = $decoder->decode('boundary="----=_Part_123"');
        $this->assertSame(['boundary' => '----=_Part_123'], $result);
    }

    public function testEmptyString(): void
    {
        $decoder = new ContentParamDecoder();
        $result = $decoder->decode('');
        $this->assertSame([], $result);
    }

    public function testIsAtextNonTspecial(): void
    {
        $this->assertTrue(ContentParamDecoder::isAtextNonTspecial('a'));
        $this->assertTrue(ContentParamDecoder::isAtextNonTspecial('Z'));
        $this->assertTrue(ContentParamDecoder::isAtextNonTspecial('0'));
        $this->assertFalse(ContentParamDecoder::isAtextNonTspecial('"'));
        $this->assertFalse(ContentParamDecoder::isAtextNonTspecial('('));
        $this->assertFalse(ContentParamDecoder::isAtextNonTspecial(' '));
        $this->assertFalse(ContentParamDecoder::isAtextNonTspecial("\x00"));
    }
}
