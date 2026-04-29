<?php

declare(strict_types=1);

namespace Horde\Mime\Test;

use Horde\Mime\BodyFinder;
use Horde\Mime\ContentTypeMap;
use Horde\Mime\MimeParser;
use Horde\Mime\Part;
use Horde\Mime\PartBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(BodyFinder::class)]
#[CoversClass(ContentTypeMap::class)]
class PartHelpersTest extends TestCase
{
    private function fixture(string $name): string
    {
        return file_get_contents(__DIR__ . '/../Unnamespaced/fixtures/' . $name);
    }

    public function testBodyFinderSimpleMessage(): void
    {
        $part = MimeParser::parse(
            "MIME-Version: 1.0\r\nContent-Type: text/plain\r\n\r\nHello"
        );

        $finder = new BodyFinder();
        $id = $finder($part);
        $this->assertSame('1', $id);
    }

    public function testBodyFinderMultipart(): void
    {
        $part = MimeParser::parse($this->fixture('sample_msg.txt'));

        $finder = new BodyFinder();
        $id = $finder($part);
        $this->assertNotNull($id);
        $this->assertSame('1', $id);
    }

    public function testBodyFinderSubtype(): void
    {
        $boundary = '----=_alt';
        $msg = "MIME-Version: 1.0\r\n"
            . "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n\r\n"
            . "------=_alt\r\nContent-Type: text/plain\r\n\r\nPlain\r\n"
            . "------=_alt\r\nContent-Type: text/html\r\n\r\n<p>HTML</p>\r\n"
            . "------=_alt--\r\n";

        $part = MimeParser::parse($msg);
        $finder = new BodyFinder();

        $this->assertSame('1', $finder($part, 'plain'));
        $this->assertSame('2', $finder($part, 'html'));
    }

    public function testBodyFinderSkipsAttachments(): void
    {
        $boundary = '----=_mix';
        $msg = "MIME-Version: 1.0\r\n"
            . "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n\r\n"
            . "------=_mix\r\n"
            . "Content-Type: text/plain\r\n"
            . "Content-Disposition: attachment; filename=\"notes.txt\"\r\n\r\n"
            . "Attached text\r\n"
            . "------=_mix\r\n"
            . "Content-Type: text/plain\r\n\r\n"
            . "Body text\r\n"
            . "------=_mix--\r\n";

        $part = MimeParser::parse($msg);
        $finder = new BodyFinder();
        $id = $finder($part);
        $this->assertSame('2', $id);
    }

    public function testBodyFinderReturnsNullWhenNoBody(): void
    {
        $boundary = '----=_mix';
        $msg = "MIME-Version: 1.0\r\n"
            . "Content-Type: multipart/mixed; boundary=\"$boundary\"\r\n\r\n"
            . "------=_mix\r\n"
            . "Content-Type: image/png\r\n\r\n"
            . "PNG data\r\n"
            . "------=_mix--\r\n";

        $part = MimeParser::parse($msg);
        $finder = new BodyFinder();
        $this->assertNull($finder($part));
    }

    public function testContentTypeMapSimple(): void
    {
        $part = MimeParser::parse(
            "MIME-Version: 1.0\r\nContent-Type: text/plain\r\n\r\nHello"
        );

        $map = new ContentTypeMap();
        $result = $map($part);
        $this->assertContains('text/plain', $result);
    }

    public function testContentTypeMapMultipart(): void
    {
        $part = MimeParser::parse($this->fixture('sample_msg.txt'));

        $map = new ContentTypeMap();
        $result = $map($part);

        $this->assertIsArray($result);
        $this->assertNotEmpty($result);
        $this->assertContains('multipart/mixed', $result);
        $this->assertContains('text/plain', $result);
        $this->assertContains('image/png', $result);
    }

    public function testContentTypeMapKeysAreMimeIds(): void
    {
        $part = MimeParser::parse($this->fixture('sample_msg.txt'));

        $map = new ContentTypeMap();
        $result = $map($part);

        foreach (array_keys($result) as $key) {
            $this->assertMatchesRegularExpression('/^[0-9]+(\.[0-9]+)*$/', (string) $key);
        }
    }
}
