<?php

declare(strict_types=1);

namespace Horde\Mime\Test;

use Horde\Mime\Encoding\EncodingDetector;
use Horde\Mime\Headers\ContentTransferEncoding;
use Horde\Mime\Headers\ContentType;
use Horde\Mime\Headers\HeaderCollection;
use Horde\Mime\Headers\MimeVersion;
use Horde\Mime\Headers\Subject;
use Horde\Mime\MessageRenderer;
use Horde\Mime\Part;
use Horde\Mime\PartBuilder;
use Horde\Mime\TransferEncoding;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MessageRenderer::class)]
class MessageRendererTest extends TestCase
{
    public function testRenderSimpleTextPart(): void
    {
        $part = PartBuilder::text('Hello, World!')->build();
        $headers = (new HeaderCollection())
            ->with(new Subject('Subject', 'Test'))
            ->withRaw('From', 'sender@example.com');

        $output = MessageRenderer::render($part, $headers);

        $this->assertStringContainsString('Subject: Test', $output);
        $this->assertStringContainsString('From: sender@example.com', $output);
        $this->assertStringContainsString('MIME-Version: 1.0', $output);
        $this->assertStringContainsString('Content-Type: text/plain', $output);
        $this->assertStringContainsString('Hello, World!', $output);
    }

    public function testRenderBodyOnly(): void
    {
        $part = PartBuilder::text('Body content.')->build();

        $body = MessageRenderer::renderBody($part);

        $this->assertSame('Body content.', $body);
    }

    public function testRenderMultipartMixed(): void
    {
        $text = PartBuilder::text('Text body.')->build();
        $attachment = PartBuilder::attachment('binary data', 'file.bin')->build();
        $multipart = PartBuilder::multipart('mixed', $text, $attachment)->build();

        $headers = (new HeaderCollection())
            ->with(new Subject('Subject', 'With attachment'));

        $output = MessageRenderer::render($multipart, $headers);

        $boundary = $multipart->boundary();
        $this->assertNotNull($boundary);
        $this->assertStringContainsString('--' . $boundary, $output);
        $this->assertStringContainsString('--' . $boundary . '--', $output);
        $this->assertStringContainsString('Text body.', $output);
        $this->assertStringContainsString('Content-Disposition: attachment', $output);
    }

    public function testRenderMultipartAlternative(): void
    {
        $text = PartBuilder::text('Plain version.')->build();
        $html = PartBuilder::html('<p>HTML version.</p>')->build();
        $multipart = PartBuilder::multipart('alternative', $text, $html)->build();

        $body = MessageRenderer::renderBody($multipart);

        $boundary = $multipart->boundary();
        $this->assertStringContainsString('--' . $boundary, $body);
        $this->assertStringContainsString('Plain version.', $body);
        $this->assertStringContainsString('<p>HTML version.</p>', $body);
        $this->assertStringContainsString('text/plain', $body);
        $this->assertStringContainsString('text/html', $body);
    }

    public function testRenderNestedMultipart(): void
    {
        $text = PartBuilder::text('Plain text.')->build();
        $html = PartBuilder::html('<p>HTML</p>')->build();
        $alternative = PartBuilder::multipart('alternative', $text, $html)->build();
        $attachment = PartBuilder::attachment('file content', 'doc.pdf', 'application/pdf')->build();
        $mixed = PartBuilder::multipart('mixed', $alternative, $attachment)->build();

        $body = MessageRenderer::renderBody($mixed);

        $this->assertStringContainsString($mixed->boundary(), $body);
        $this->assertStringContainsString($alternative->boundary(), $body);
        $this->assertStringContainsString('Plain text.', $body);
        $this->assertStringContainsString('<p>HTML</p>', $body);
        $this->assertStringContainsString('doc.pdf', $body);
    }

    public function testEncodingDetectionBase64ForBinary(): void
    {
        $binary = "\x00\x01\x02\x03\x04\x05";
        $part = PartBuilder::attachment($binary, 'data.bin')->build();

        $body = MessageRenderer::renderBody($part);

        $this->assertSame(base64_encode($binary), rtrim($body));
    }

    public function testEncodingDetectionQuotedPrintableFor8bit(): void
    {
        $text = "Hello \xC3\xA9l\xC3\xA8ve";
        $part = PartBuilder::text($text)->build();

        $body = MessageRenderer::renderBody($part);

        $this->assertStringContainsString('=C3=A9', $body);
    }

    public function testExplicitEncodingOverridesDetection(): void
    {
        $text = 'Simple ASCII text';
        $part = (new PartBuilder())
            ->setContentType('text/plain', ['charset' => 'utf-8'])
            ->setTransferEncoding(TransferEncoding::Base64)
            ->setBody($text)
            ->build();

        $body = MessageRenderer::renderBody($part);

        $this->assertSame(base64_encode($text), rtrim($body));
    }

    public function testRenderHeadersIncludesMimeVersion(): void
    {
        $part = PartBuilder::text('test')->build();
        $headers = new HeaderCollection();

        $headerStr = MessageRenderer::renderHeaders($part, $headers);

        $this->assertStringContainsString('MIME-Version: 1.0', $headerStr);
    }

    public function testRenderHeadersIncludesContentType(): void
    {
        $part = PartBuilder::text('test', 'plain', 'iso-8859-1')->build();
        $headers = new HeaderCollection();

        $headerStr = MessageRenderer::renderHeaders($part, $headers);

        $this->assertStringContainsString('text/plain', $headerStr);
    }

    public function testHeadersToArray(): void
    {
        $part = PartBuilder::text('test')->build();
        $headers = (new HeaderCollection())
            ->with(new Subject('Subject', 'Array test'));

        $array = MessageRenderer::headersToArray($part, $headers);

        $this->assertArrayHasKey('Subject', $array);
        $this->assertSame('Array test', $array['Subject']);
        $this->assertArrayHasKey('MIME-Version', $array);
    }

    public function testMissingBoundaryThrows(): void
    {
        $part = new Part(
            headers: (new HeaderCollection())->with(
                new ContentType('Content-Type', 'multipart/mixed', []),
            ),
            children: [PartBuilder::text('child')->build()],
        );

        $this->expectException(\Horde\Mime\MimeException::class);
        MessageRenderer::renderBody($part);
    }

    public function testEightBitAllowed(): void
    {
        $text = "Hello \xC3\xA9l\xC3\xA8ve";
        $part = PartBuilder::text($text)->build();

        $body = MessageRenderer::renderBody(
            $part,
            encodingMask: EncodingDetector::ALLOW_7BIT | EncodingDetector::ALLOW_8BIT,
        );

        $this->assertSame($text, $body);
    }

    public function testPreambleInMultipart(): void
    {
        $text = PartBuilder::text('Part.')->build();
        $multipart = PartBuilder::multipart('mixed', $text)->build();

        $body = MessageRenderer::renderBody($multipart);

        $this->assertStringContainsString('This is a MIME message.', $body);
    }
}
