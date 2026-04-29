<?php

declare(strict_types=1);

namespace Horde\Mime\Test;

use Horde\Mime\MimeException;
use Horde\Mime\Part;
use Horde\Mime\PartBuilder;
use Horde\Mime\Related;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Related::class)]
class RelatedTest extends TestCase
{
    private function buildRelatedPart(): Part
    {
        $html = (new PartBuilder())
            ->setContentType('text/html', ['charset' => 'utf-8'])
            ->setBody('<html><body><img src="cid:image001"></body></html>')
            ->build();

        $image = (new PartBuilder())
            ->setContentType('image/png')
            ->setContentId('image001')
            ->setBody('PNG DATA')
            ->build();

        return (new PartBuilder())
            ->setContentType('multipart/related')
            ->addChild($html)
            ->addChild($image)
            ->build();
    }

    public function testStartIndex(): void
    {
        $related = new Related($this->buildRelatedPart());

        $this->assertSame(0, $related->startIndex());
    }

    public function testStartPart(): void
    {
        $part = $this->buildRelatedPart();
        $related = new Related($part);

        $this->assertSame('text/html', $related->startPart()->fullType());
    }

    public function testCidSearch(): void
    {
        $related = new Related($this->buildRelatedPart());

        $this->assertSame(1, $related->cidSearch('image001'));
        $this->assertNull($related->cidSearch('nonexistent'));
    }

    public function testCidPart(): void
    {
        $related = new Related($this->buildRelatedPart());

        $part = $related->cidPart('image001');
        $this->assertNotNull($part);
        $this->assertSame('image/png', $part->fullType());
        $this->assertNull($related->cidPart('nonexistent'));
    }

    public function testCidReplace(): void
    {
        $related = new Related($this->buildRelatedPart());

        $html = '<img src="cid:image001">';
        $result = $related->cidReplace($html, function (int $index, string $cid, string $attr): string {
            return 'https://example.com/images/' . $cid . '.png';
        });

        $this->assertStringContainsString('https://example.com/images/image001.png', $result);
        $this->assertStringNotContainsString('cid:', $result);
    }

    public function testCidReplaceUnknownCidUnchanged(): void
    {
        $related = new Related($this->buildRelatedPart());

        $html = '<img src="cid:unknown_id">';
        $result = $related->cidReplace($html, function (): string {
            return 'replaced';
        });

        $this->assertStringContainsString('cid:unknown_id', $result);
    }

    public function testNonRelatedPartThrows(): void
    {
        $part = PartBuilder::text('not multipart/related')->build();

        $this->expectException(MimeException::class);
        new Related($part);
    }

    public function testIterator(): void
    {
        $related = new Related($this->buildRelatedPart());

        $cids = iterator_to_array($related);
        $this->assertCount(1, $cids);
        $this->assertSame('image001', $cids[1]);
    }

    public function testStartParameter(): void
    {
        $html = (new PartBuilder())
            ->setContentType('text/html')
            ->setContentId('startpart')
            ->setBody('<html></html>')
            ->build();

        $other = (new PartBuilder())
            ->setContentType('text/plain')
            ->setBody('Other part')
            ->build();

        $related = (new PartBuilder())
            ->setContentType('multipart/related', ['start' => '<startpart>'])
            ->addChild($other)
            ->addChild($html)
            ->build();

        $rel = new Related($related);
        $this->assertSame(1, $rel->startIndex());
        $this->assertSame('text/html', $rel->startPart()->fullType());
    }
}
