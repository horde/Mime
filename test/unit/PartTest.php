<?php

declare(strict_types=1);

namespace Horde\Mime\Test;

use Horde\Mime\Headers\ContentDisposition;
use Horde\Mime\Headers\ContentType;
use Horde\Mime\Headers\HeaderCollection;
use Horde\Mime\Part;
use Horde\Mime\PartIterator;
use Horde\Mime\TransferEncoding;
use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class PartTest extends TestCase
{
    public function testDefaultConstruction(): void
    {
        $part = new Part();
        $this->assertSame('', $part->body);
        $this->assertEmpty($part->children);
        $this->assertNull($part->sizeHint);
        $this->assertEmpty($part->metadata);
    }

    public function testConvenienceAccessors(): void
    {
        $headers = (new HeaderCollection())
            ->with(new ContentType('Content-Type', 'text/html', ['charset' => 'UTF-8']));
        $part = new Part($headers, '<h1>Hello</h1>');

        $this->assertSame('text', $part->primaryType());
        $this->assertSame('html', $part->subType());
        $this->assertSame('text/html', $part->fullType());
        $this->assertSame('UTF-8', $part->charset());
        $this->assertFalse($part->isMultipart());
        $this->assertTrue($part->isLeaf());
    }

    public function testDefaultContentType(): void
    {
        $part = new Part();
        $ct = $part->contentType();
        $this->assertSame('application', $ct->primaryType);
        $this->assertSame('octet-stream', $ct->subType);
    }

    public function testCharsetFallbackForText(): void
    {
        $headers = (new HeaderCollection())
            ->with(new ContentType('Content-Type', 'text/plain'));
        $part = new Part($headers);
        $this->assertSame('us-ascii', $part->charset());
    }

    public function testCharsetNullForNonText(): void
    {
        $headers = (new HeaderCollection())
            ->with(new ContentType('Content-Type', 'image/png'));
        $part = new Part($headers);
        $this->assertNull($part->charset());
    }

    public function testTransferEncodingDefault(): void
    {
        $part = new Part();
        $this->assertSame(TransferEncoding::SevenBit, $part->transferEncoding());
    }

    public function testFilenameFromDisposition(): void
    {
        $headers = (new HeaderCollection())
            ->with(new ContentDisposition('Content-Disposition', 'attachment', ['filename' => 'doc.pdf']));
        $part = new Part($headers);
        $this->assertSame('doc.pdf', $part->filename());
    }

    public function testFilenameFromContentType(): void
    {
        $headers = (new HeaderCollection())
            ->with(new ContentType('Content-Type', 'application/pdf', ['name' => 'doc.pdf']));
        $part = new Part($headers);
        $this->assertSame('doc.pdf', $part->filename());
    }

    public function testContentId(): void
    {
        $headers = (new HeaderCollection())
            ->withRaw('Content-ID', '<abc123@example.com>');
        $part = new Part($headers);
        $this->assertSame('abc123@example.com', $part->contentId());
    }

    public function testDescription(): void
    {
        $headers = (new HeaderCollection())
            ->withRaw('Content-Description', 'A nice file');
        $part = new Part($headers);
        $this->assertSame('A nice file', $part->description());
    }

    public function testHasBody(): void
    {
        $empty = new Part();
        $this->assertFalse($empty->hasBody());

        $withBody = new Part(body: 'content');
        $this->assertTrue($withBody->hasBody());
    }

    public function testBodySize(): void
    {
        $part = new Part(body: 'Hello');
        $this->assertSame(5, $part->bodySize());
    }

    public function testBodySizeFallsBackToSizeHint(): void
    {
        $part = new Part(sizeHint: 1024);
        $this->assertSame(1024, $part->bodySize());
    }

    public function testBodySizeZeroWhenNoBodyOrHint(): void
    {
        $part = new Part();
        $this->assertSame(0, $part->bodySize());
    }

    public function testChildren(): void
    {
        $child1 = new Part(body: 'one');
        $child2 = new Part(body: 'two');
        $parent = new Part(children: [$child1, $child2]);

        $this->assertSame(2, $parent->childCount());
        $this->assertSame($child1, $parent->child(0));
        $this->assertSame($child2, $parent->child(1));
        $this->assertNull($parent->child(5));
        $this->assertFalse($parent->isLeaf());
    }

    public function testMetadata(): void
    {
        $part = new Part(metadata: ['key' => 'value']);
        $this->assertSame('value', $part->metadata('key'));
        $this->assertNull($part->metadata('missing'));
    }

    public function testWithBodyImmutability(): void
    {
        $original = new Part(body: 'old');
        $modified = $original->withBody('new');

        $this->assertSame('old', $original->body);
        $this->assertSame('new', $modified->body);
        $this->assertNotSame($original, $modified);
    }

    public function testWithChild(): void
    {
        $part = new Part();
        $child = new Part(body: 'child');
        $withChild = $part->withChild($child);

        $this->assertSame(0, $part->childCount());
        $this->assertSame(1, $withChild->childCount());
    }

    public function testWithoutChild(): void
    {
        $c1 = new Part(body: 'one');
        $c2 = new Part(body: 'two');
        $c3 = new Part(body: 'three');
        $parent = new Part(children: [$c1, $c2, $c3]);

        $modified = $parent->withoutChild(1);
        $this->assertSame(2, $modified->childCount());
        $this->assertSame('one', $modified->child(0)->body);
        $this->assertSame('three', $modified->child(1)->body);
    }

    public function testWithMetadata(): void
    {
        $part = new Part();
        $modified = $part->withMetadata('foo', 'bar');
        $this->assertNull($part->metadata('foo'));
        $this->assertSame('bar', $modified->metadata('foo'));
    }

    public function testWithSizeHint(): void
    {
        $part = new Part();
        $modified = $part->withSizeHint(500);
        $this->assertNull($part->sizeHint);
        $this->assertSame(500, $modified->sizeHint);
    }

    public function testIterate(): void
    {
        $part = new Part(body: 'content');
        $iterator = $part->iterate();
        $this->assertInstanceOf(PartIterator::class, $iterator);
    }
}
