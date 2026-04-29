<?php

declare(strict_types=1);

namespace Horde\Mime\Test;

use Horde\Mime\MessageRenderer;
use Horde\Mime\Part;
use Horde\Mime\PartBuilder;
use Horde\Mime\Headers\HeaderCollection;
use Horde\Stream\StreamInterface;
use Horde\Stream\Temp;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Part::class)]
class StreamBodyTest extends TestCase
{
    private function createStream(string $content): StreamInterface
    {
        $stream = new Temp();
        $stream->add($content);
        $stream->rewind();

        return $stream;
    }

    public function testPartAcceptsStreamBody(): void
    {
        $stream = $this->createStream('Hello Stream');
        $part = new Part(body: $stream);

        $this->assertInstanceOf(StreamInterface::class, $part->body);
        $this->assertTrue($part->isStream());
    }

    public function testBodyStringFromStream(): void
    {
        $stream = $this->createStream('Stream content');
        $part = new Part(body: $stream);

        $this->assertSame('Stream content', $part->bodyString());
    }

    public function testBodyStringFromString(): void
    {
        $part = new Part(body: 'String content');

        $this->assertSame('String content', $part->bodyString());
        $this->assertFalse($part->isStream());
    }

    public function testHasBodyWithStream(): void
    {
        $stream = $this->createStream('data');
        $part = new Part(body: $stream);

        $this->assertTrue($part->hasBody());
    }

    public function testBodySizeWithStream(): void
    {
        $stream = $this->createStream('12345');
        $part = new Part(body: $stream);

        $this->assertSame(5, $part->bodySize());
    }

    public function testPartBuilderAcceptsStream(): void
    {
        $stream = $this->createStream('Built from stream');
        $part = (new PartBuilder())
            ->setContentType('text/plain')
            ->setBody($stream)
            ->build();

        $this->assertTrue($part->isStream());
        $this->assertSame('Built from stream', $part->bodyString());
    }

    public function testWithBodyAcceptsStream(): void
    {
        $part = new Part(body: 'original');
        $stream = $this->createStream('replaced');
        $updated = $part->withBody($stream);

        $this->assertFalse($part->isStream());
        $this->assertTrue($updated->isStream());
        $this->assertSame('replaced', $updated->bodyString());
    }

    public function testMessageRendererHandlesStream(): void
    {
        $stream = $this->createStream('Stream body text');
        $part = (new PartBuilder())
            ->setContentType('text/plain', ['charset' => 'utf-8'])
            ->setBody($stream)
            ->build();

        $headers = (new HeaderCollection())
            ->withRaw('From', 'test@example.com')
            ->withRaw('MIME-Version', '1.0');

        $rendered = MessageRenderer::render($part, $headers);
        $this->assertStringContainsString('Stream body text', $rendered);
        $this->assertStringContainsString('Content-Type: text/plain', $rendered);
    }
}
