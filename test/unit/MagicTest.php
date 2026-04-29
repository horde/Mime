<?php

declare(strict_types=1);

namespace Horde\Mime\Test;

use Horde\Mime\Magic;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Magic::class)]
class MagicTest extends TestCase
{
    public function testExtToMimeKnown(): void
    {
        $this->assertSame('application/pdf', Magic::extToMime('pdf'));
        $this->assertSame('image/png', Magic::extToMime('png'));
        $this->assertSame('text/html', Magic::extToMime('html'));
    }

    public function testExtToMimeUnknown(): void
    {
        $this->assertSame('x-extension/zzz999', Magic::extToMime('zzz999'));
    }

    public function testExtToMimeEmpty(): void
    {
        $this->assertSame('application/octet-stream', Magic::extToMime(''));
    }

    public function testExtToMimeCaseInsensitive(): void
    {
        $this->assertSame('application/pdf', Magic::extToMime('PDF'));
    }

    public function testFilenameToMime(): void
    {
        $this->assertSame('application/pdf', Magic::filenameToMime('report.pdf'));
        $this->assertSame('image/jpeg', Magic::filenameToMime('photo.jpg'));
    }

    public function testFilenameToMimeUnknown(): void
    {
        $this->assertSame('application/octet-stream', Magic::filenameToMime('file.zzz999'));
    }

    public function testFilenameToMimeNoExtension(): void
    {
        $this->assertSame('application/octet-stream', Magic::filenameToMime('noextension'));
    }

    public function testMimeToExtKnown(): void
    {
        $this->assertSame('pdf', Magic::mimeToExt('application/pdf'));
    }

    public function testMimeToExtUnknown(): void
    {
        $this->assertNull(Magic::mimeToExt('application/totally-made-up-type'));
    }

    public function testMimeToExtXExtension(): void
    {
        $this->assertSame('foo', Magic::mimeToExt('x-extension/foo'));
    }

    public function testMimeToExtEmpty(): void
    {
        $this->assertNull(Magic::mimeToExt(''));
    }

    public function testAnalyzeDataWithFileinfo(): void
    {
        if (!extension_loaded('fileinfo')) {
            $this->markTestSkipped('ext-fileinfo not available.');
        }

        $gif = "GIF89a\x01\x00\x01\x00\x80\x00\x00\xff\xff\xff\x00\x00\x00!\xf9\x04\x00\x00\x00\x00\x00,\x00\x00\x00\x00\x01\x00\x01\x00\x00\x02\x02D\x01\x00;";
        $type = Magic::analyzeData($gif);
        $this->assertSame('image/gif', $type);
    }

    public function testAnalyzeDataPlainText(): void
    {
        if (!extension_loaded('fileinfo')) {
            $this->markTestSkipped('ext-fileinfo not available.');
        }

        $text = "Hello, this is plain text content.\n";
        $type = Magic::analyzeData($text);
        $this->assertSame('text/plain', $type);
    }
}
