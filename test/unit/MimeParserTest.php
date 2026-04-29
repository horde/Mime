<?php

declare(strict_types=1);

namespace Horde\Mime\Test;

use Horde\Mime\MimeParser;
use Horde\Mime\MimeParserConfig;
use Horde\Mime\Part;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MimeParser::class)]
#[CoversClass(MimeParserConfig::class)]
class MimeParserTest extends TestCase
{
    private function fixture(string $name): string
    {
        return file_get_contents(__DIR__ . '/../Unnamespaced/fixtures/' . $name);
    }

    public function testParseSimpleTextMessage(): void
    {
        $msg = "From: sender@example.com\r\nTo: rcpt@example.com\r\n"
            . "Subject: Test\r\nMIME-Version: 1.0\r\n"
            . "Content-Type: text/plain; charset=utf-8\r\n\r\n"
            . "Hello, World!";

        $part = MimeParser::parse($msg);

        $this->assertSame('text/plain', $part->fullType());
        $this->assertSame('Hello, World!', $part->body);
        $this->assertTrue($part->headers->has('from'));
        $this->assertTrue($part->headers->has('subject'));
    }

    public function testParseMultipartMixed(): void
    {
        $part = MimeParser::parse($this->fixture('sample_msg.txt'));

        $this->assertSame('multipart/mixed', $part->fullType());
        $this->assertCount(3, $part->children);

        $textPart = $part->children[0];
        $this->assertSame('text/plain', $textPart->fullType());
        $this->assertStringContainsString('Test.', $textPart->body);

        $messagePart = $part->children[1];
        $this->assertSame('message/rfc822', $messagePart->fullType());
        $this->assertCount(1, $messagePart->children);

        $innerMessage = $messagePart->children[0];
        $this->assertSame('multipart/mixed', $innerMessage->fullType());
        $this->assertCount(2, $innerMessage->children);

        $innerText = $innerMessage->children[0];
        $this->assertStringContainsString('Test text.', $innerText->body);

        $innerAttachment = $innerMessage->children[1];
        $this->assertSame('text/plain', $innerAttachment->fullType());
        $this->assertStringContainsString('Test.', $innerAttachment->body);

        $imagePart = $part->children[2];
        $this->assertSame('image/png', $imagePart->fullType());
        $this->assertNotEmpty($imagePart->body);
    }

    public function testParseBase64Decoding(): void
    {
        $part = MimeParser::parse($this->fixture('sample_msg.txt'));

        $imagePart = $part->children[2];
        $this->assertStringStartsWith("\x89PNG", $imagePart->body);
    }

    public function testParseNonMimeMessage(): void
    {
        $msg = "From: sender@example.com\r\nSubject: No MIME\r\n\r\nPlain body.";

        $part = MimeParser::parse($msg);

        $this->assertSame('text/plain', $part->fullType());
        $this->assertSame('Plain body.', $part->body);
    }

    public function testParseForceMime(): void
    {
        $msg = "From: sender@example.com\r\n"
            . "Content-Type: text/html; charset=utf-8\r\n\r\n"
            . "<p>Hello</p>";

        $part = MimeParser::parse($msg, new MimeParserConfig(forceMime: true));

        $this->assertSame('text/html', $part->fullType());
        $this->assertSame('<p>Hello</p>', $part->body);
    }

    public function testParseNoBody(): void
    {
        $msg = "From: sender@example.com\r\nMIME-Version: 1.0\r\n"
            . "Content-Type: text/plain\r\n\r\n"
            . "This body should not be loaded.";

        $part = MimeParser::parse($msg, new MimeParserConfig(noBody: true));

        $this->assertSame('', $part->body);
        $this->assertSame(31, $part->sizeHint);
    }

    public function testParseQuotedPrintable(): void
    {
        $msg = "MIME-Version: 1.0\r\n"
            . "Content-Type: text/plain; charset=utf-8\r\n"
            . "Content-Transfer-Encoding: quoted-printable\r\n\r\n"
            . "Hello =C3=A9l=C3=A8ve";

        $part = MimeParser::parse($msg);

        $this->assertSame("Hello \xC3\xA9l\xC3\xA8ve", $part->body);
    }

    public function testParseMultipartAlternative(): void
    {
        $boundary = '----=_Part_123';
        $msg = "MIME-Version: 1.0\r\n"
            . "Content-Type: multipart/alternative; boundary=\"$boundary\"\r\n\r\n"
            . "------=_Part_123\r\n"
            . "Content-Type: text/plain\r\n\r\n"
            . "Plain text.\r\n"
            . "------=_Part_123\r\n"
            . "Content-Type: text/html\r\n\r\n"
            . "<p>HTML</p>\r\n"
            . "------=_Part_123--\r\n";

        $part = MimeParser::parse($msg);

        $this->assertSame('multipart/alternative', $part->fullType());
        $this->assertCount(2, $part->children);
        $this->assertSame('text/plain', $part->children[0]->fullType());
        $this->assertStringContainsString('Plain text.', $part->children[0]->body);
        $this->assertSame('text/html', $part->children[1]->fullType());
        $this->assertStringContainsString('<p>HTML</p>', $part->children[1]->body);
    }

    public function testParseRelatedMessage(): void
    {
        $part = MimeParser::parse($this->fixture('related_msg.txt'));

        $this->assertTrue($part->isMultipart());
    }

    public function testGetRawPartTextHeader(): void
    {
        $raw = MimeParser::getRawPartText($this->fixture('sample_msg.txt'), 'header', '0');

        $this->assertNotNull($raw);
        $this->assertStringContainsString('Subject: Fwd: Test', $raw);
        $this->assertStringContainsString('Content-Type: multipart/mixed', $raw);
    }

    public function testGetRawPartTextBody(): void
    {
        $raw = MimeParser::getRawPartText($this->fixture('sample_msg.txt'), 'body', '0');

        $this->assertNotNull($raw);
        $this->assertStringContainsString('--=_k4kgcwkwggwc', $raw);
    }

    public function testGetRawPartTextChild(): void
    {
        $raw = MimeParser::getRawPartText($this->fixture('sample_msg.txt'), 'body', '1');

        $this->assertNotNull($raw);
        $this->assertStringContainsString('Test.', $raw);
    }

    public function testGetRawPartTextChildHeader(): void
    {
        $raw = MimeParser::getRawPartText($this->fixture('sample_msg.txt'), 'header', '3');

        $this->assertNotNull($raw);
        $this->assertStringContainsString('image/png', $raw);
    }

    public function testParseMessageRfc822Structure(): void
    {
        $part = MimeParser::parse($this->fixture('sample_msg.txt'));

        $messagePart = $part->children[1];
        $this->assertSame('message/rfc822', $messagePart->fullType());
        $this->assertCount(1, $messagePart->children);

        $inner = $messagePart->children[0];
        $this->assertTrue($inner->headers->has('from'));
        $this->assertSame('multipart/mixed', $inner->fullType());
    }

    public function testParseImmutableResult(): void
    {
        $part = MimeParser::parse($this->fixture('sample_msg.txt'));

        $this->assertInstanceOf(Part::class, $part);
    }

    public function testNestingLimit(): void
    {
        $boundary = 'test_boundary';
        $msg = "MIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary=\"$boundary\"\r\n\r\n";

        for ($i = 0; $i < 110; $i++) {
            $msg .= "--$boundary\r\nContent-Type: message/rfc822\r\n\r\n"
                . "MIME-Version: 1.0\r\nContent-Type: multipart/mixed; boundary=\"b{$i}\"\r\n\r\n";
            $boundary = "b{$i}";
        }
        $msg .= "--$boundary--\r\n";

        $part = MimeParser::parse($msg);

        $this->assertInstanceOf(Part::class, $part);
    }
}
