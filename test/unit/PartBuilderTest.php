<?php

declare(strict_types=1);

namespace Horde\Mime\Test;

use Horde\Mime\Headers\ContentDisposition;
use Horde\Mime\Headers\ContentType;
use Horde\Mime\Headers\HeaderCollection;
use Horde\Mime\Part;
use Horde\Mime\PartBuilder;
use Horde\Mime\TransferEncoding;
use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class PartBuilderTest extends TestCase
{
    public function testBasicBuild(): void
    {
        $part = (new PartBuilder())
            ->setContentType('text/plain', ['charset' => 'UTF-8'])
            ->setBody('Hello World')
            ->build();

        $this->assertInstanceOf(Part::class, $part);
        $this->assertSame('Hello World', $part->body);
        $this->assertSame('text', $part->primaryType());
        $this->assertSame('plain', $part->subType());
        $this->assertSame('UTF-8', $part->charset());
    }

    public function testFluentChaining(): void
    {
        $builder = new PartBuilder();
        $result = $builder->setContentType('text/plain');
        $this->assertSame($builder, $result);

        $result = $builder->setBody('test');
        $this->assertSame($builder, $result);

        $result = $builder->setCharset('UTF-8');
        $this->assertSame($builder, $result);
    }

    public function testSetCharset(): void
    {
        $part = (new PartBuilder())
            ->setContentType('text/plain')
            ->setCharset('ISO-8859-1')
            ->build();

        $this->assertSame('ISO-8859-1', $part->charset());
    }

    public function testSetCharsetWithoutContentType(): void
    {
        $part = (new PartBuilder())
            ->setCharset('UTF-8')
            ->build();

        $this->assertSame('text', $part->primaryType());
        $this->assertSame('UTF-8', $part->charset());
    }

    public function testSetDisposition(): void
    {
        $part = (new PartBuilder())
            ->setDisposition('attachment', ['filename' => 'doc.pdf'])
            ->build();

        $disp = $part->contentDisposition();
        $this->assertNotNull($disp);
        $this->assertTrue($disp->isAttachment());
        $this->assertSame('doc.pdf', $disp->filename());
    }

    public function testSetFilename(): void
    {
        $part = (new PartBuilder())
            ->setContentType('application/pdf')
            ->setFilename('report.pdf')
            ->build();

        $this->assertSame('report.pdf', $part->filename());
        $disp = $part->contentDisposition();
        $this->assertNotNull($disp);
        $this->assertTrue($disp->isAttachment());
    }

    public function testSetDescription(): void
    {
        $part = (new PartBuilder())
            ->setDescription('A test file')
            ->build();

        $this->assertSame('A test file', $part->description());
    }

    public function testSetTransferEncoding(): void
    {
        $part = (new PartBuilder())
            ->setTransferEncoding(TransferEncoding::Base64)
            ->build();

        $this->assertSame(TransferEncoding::Base64, $part->transferEncoding());
    }

    public function testBodyDecodingBase64(): void
    {
        $encoded = base64_encode('Hello World');
        $part = (new PartBuilder())
            ->setBody($encoded, TransferEncoding::Base64)
            ->build();

        $this->assertSame('Hello World', $part->body);
    }

    public function testBodyDecodingQuotedPrintable(): void
    {
        $part = (new PartBuilder())
            ->setBody('Hello=20World', TransferEncoding::QuotedPrintable)
            ->build();

        $this->assertSame('Hello World', $part->body);
    }

    public function testBodyPassthroughSevenBit(): void
    {
        $part = (new PartBuilder())
            ->setBody('raw data', TransferEncoding::SevenBit)
            ->build();

        $this->assertSame('raw data', $part->body);
    }

    public function testStreamBody(): void
    {
        $stream = fopen('php://temp', 'r+');
        fwrite($stream, 'stream content');

        $part = (new PartBuilder())
            ->setBody($stream)
            ->build();

        fclose($stream);

        $this->assertSame('stream content', $part->body);
    }

    public function testSizeHint(): void
    {
        $part = (new PartBuilder())
            ->setSizeHint(4096)
            ->build();

        $this->assertSame(4096, $part->sizeHint);
    }

    public function testMetadata(): void
    {
        $part = (new PartBuilder())
            ->setMetadata('source', 'imap')
            ->build();

        $this->assertSame('imap', $part->metadata('source'));
    }

    public function testMultipartBuild(): void
    {
        $part = PartBuilder::multipart(
            'mixed',
            PartBuilder::text('Hello'),
            PartBuilder::text('<b>Hello</b>', 'html'),
        )->build();

        $this->assertTrue($part->isMultipart());
        $this->assertSame(2, $part->childCount());
        $this->assertNotNull($part->boundary());
    }

    public function testMultipartBoundaryAutoGeneration(): void
    {
        $part = PartBuilder::multipart(
            'mixed',
            PartBuilder::text('test'),
        )->build();

        $boundary = $part->boundary();
        $this->assertNotNull($boundary);
        $this->assertStringStartsWith('=_', $boundary);
    }

    public function testMultipartPreservesExistingBoundary(): void
    {
        $part = (new PartBuilder())
            ->setContentType('multipart/mixed', ['boundary' => 'my-boundary'])
            ->addChild(PartBuilder::text('test'))
            ->build();

        $this->assertSame('my-boundary', $part->boundary());
    }

    public function testAddChildWithPartBuilder(): void
    {
        $builder = new PartBuilder();
        $builder->setContentType('multipart/mixed');
        $builder->addChild(PartBuilder::text('one'));
        $builder->addChild(PartBuilder::text('two'));

        $part = $builder->build();
        $this->assertSame(2, $part->childCount());
        $this->assertSame('one', $part->child(0)->body);
        $this->assertSame('two', $part->child(1)->body);
    }

    public function testAddChildWithBuiltPart(): void
    {
        $child = PartBuilder::text('prebuilt')->build();
        $parent = PartBuilder::multipart('mixed', $child)->build();

        $this->assertSame(1, $parent->childCount());
        $this->assertSame('prebuilt', $parent->child(0)->body);
    }

    public function testNestedMultipart(): void
    {
        $part = PartBuilder::multipart(
            'mixed',
            PartBuilder::multipart(
                'alternative',
                PartBuilder::text('Plain text'),
                PartBuilder::html('<p>HTML</p>'),
            ),
            PartBuilder::attachment('binary data', 'file.bin'),
        )->build();

        $this->assertSame(2, $part->childCount());
        $inner = $part->child(0);
        $this->assertTrue($inner->isMultipart());
        $this->assertSame(2, $inner->childCount());
    }

    public function testTextFactory(): void
    {
        $builder = PartBuilder::text('Hello');
        $part = $builder->build();

        $this->assertSame('text', $part->primaryType());
        $this->assertSame('plain', $part->subType());
        $this->assertSame('utf-8', $part->charset());
        $this->assertSame('Hello', $part->body);
    }

    public function testHtmlFactory(): void
    {
        $builder = PartBuilder::html('<p>Hi</p>');
        $part = $builder->build();

        $this->assertSame('text', $part->primaryType());
        $this->assertSame('html', $part->subType());
    }

    public function testAttachmentFactory(): void
    {
        $builder = PartBuilder::attachment('data', 'file.txt', 'text/plain');
        $part = $builder->build();

        $this->assertSame('text', $part->primaryType());
        $disp = $part->contentDisposition();
        $this->assertNotNull($disp);
        $this->assertTrue($disp->isAttachment());
        $this->assertSame('file.txt', $disp->filename());
    }

    public function testSetHeader(): void
    {
        $part = (new PartBuilder())
            ->setHeader('X-Custom', 'value')
            ->build();

        $this->assertTrue($part->headers->has('X-Custom'));
        $this->assertSame('value', $part->headers->get('X-Custom')->value());
    }

    public function testSetHeaders(): void
    {
        $headers = (new HeaderCollection())
            ->with(new ContentType('Content-Type', 'image/png'));

        $part = (new PartBuilder())
            ->setHeaders($headers)
            ->build();

        $this->assertSame('image', $part->primaryType());
    }

    public function testSetContentId(): void
    {
        $part = (new PartBuilder())
            ->setContentId('<test@example.com>')
            ->build();

        $this->assertSame('test@example.com', $part->contentId());
    }

    public function testSetContentIdGenerated(): void
    {
        $part = (new PartBuilder())
            ->setContentId()
            ->build();

        $this->assertNotNull($part->contentId());
    }

    public function testSetLanguage(): void
    {
        $part = (new PartBuilder())
            ->setLanguage('en', 'de')
            ->build();

        $h = $part->headers->get('content-language');
        $this->assertNotNull($h);
        $this->assertStringContainsString('en', $h->value());
        $this->assertStringContainsString('de', $h->value());
    }
}
