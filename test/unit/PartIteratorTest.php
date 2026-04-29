<?php

declare(strict_types=1);

namespace Horde\Mime\Test;

use Horde\Mime\Headers\ContentType;
use Horde\Mime\Headers\HeaderCollection;
use Horde\Mime\Part;
use Horde\Mime\PartBuilder;
use Horde\Mime\PartIterator;
use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class PartIteratorTest extends TestCase
{
    private function collectKeys(PartIterator $iter): array
    {
        $keys = [];
        foreach ($iter as $k => $v) {
            $keys[] = $k;
        }

        return $keys;
    }

    private function collectParts(PartIterator $iter): array
    {
        $result = [];
        foreach ($iter as $k => $v) {
            $result[$k] = $v;
        }

        return $result;
    }

    public function testSingleLeafPart(): void
    {
        $part = PartBuilder::text('Hello')->build();
        $iter = new PartIterator($part, true);

        $keys = $this->collectKeys($iter);
        $this->assertCount(1, $keys);
        $this->assertSame('1', $keys[0]);
    }

    public function testSingleLeafExcludeSelf(): void
    {
        $part = PartBuilder::text('Hello')->build();
        $iter = new PartIterator($part, false);

        $keys = $this->collectKeys($iter);
        $this->assertCount(0, $keys);
    }

    public function testMultipartIncludeSelf(): void
    {
        $part = PartBuilder::multipart(
            'mixed',
            PartBuilder::text('one'),
            PartBuilder::text('two'),
            PartBuilder::text('three'),
        )->build();

        $iter = new PartIterator($part, true);
        $keys = $this->collectKeys($iter);

        $this->assertCount(4, $keys);
        $this->assertSame('0', $keys[0]);
        $this->assertSame('1', $keys[1]);
        $this->assertSame('2', $keys[2]);
        $this->assertSame('3', $keys[3]);
    }

    public function testMultipartExcludeSelf(): void
    {
        $part = PartBuilder::multipart(
            'mixed',
            PartBuilder::text('one'),
            PartBuilder::text('two'),
        )->build();

        $iter = new PartIterator($part, false);
        $keys = $this->collectKeys($iter);

        $this->assertCount(2, $keys);
        $this->assertSame('1', $keys[0]);
        $this->assertSame('2', $keys[1]);
    }

    public function testNestedMultipart(): void
    {
        $part = PartBuilder::multipart(
            'mixed',
            PartBuilder::multipart(
                'alternative',
                PartBuilder::text('plain'),
                PartBuilder::html('<p>html</p>'),
            ),
            PartBuilder::attachment('data', 'file.bin'),
        )->build();

        $iter = new PartIterator($part, false);
        $keys = $this->collectKeys($iter);

        $this->assertContains('1', $keys);
        $this->assertContains('2', $keys);

        $allKeys = $this->collectKeys(new PartIterator($part, true));
        $this->assertGreaterThanOrEqual(4, count($allKeys));
    }

    public function testCountMatchesIteration(): void
    {
        $part = PartBuilder::multipart(
            'mixed',
            PartBuilder::text('one'),
            PartBuilder::text('two'),
            PartBuilder::text('three'),
        )->build();

        $iter = new PartIterator($part, true);
        $this->assertSame(4, $iter->count());
    }

    public function testCountExcludingSelf(): void
    {
        $part = PartBuilder::multipart(
            'mixed',
            PartBuilder::text('one'),
            PartBuilder::text('two'),
        )->build();

        $iter = new PartIterator($part, false);
        $this->assertSame(2, $iter->count());
    }

    public function testCurrentId(): void
    {
        $part = PartBuilder::text('Hello')->build();
        $iter = new PartIterator($part, true);
        $iter->rewind();

        $id = $iter->currentId();
        $this->assertNotNull($id);
        $this->assertSame('1', $id->id);
    }

    public function testEmptyMultipart(): void
    {
        $headers = (new HeaderCollection())
            ->with(new ContentType('Content-Type', 'multipart/mixed', ['boundary' => 'test']));
        $part = new Part($headers);

        $iter = new PartIterator($part, false);
        $keys = $this->collectKeys($iter);
        $this->assertCount(0, $keys);
    }

    public function testRewindResetsState(): void
    {
        $part = PartBuilder::multipart(
            'mixed',
            PartBuilder::text('one'),
            PartBuilder::text('two'),
        )->build();

        $iter = new PartIterator($part, false);

        $first = $this->collectKeys($iter);
        $iter->rewind();
        $second = $this->collectKeys($iter);

        $this->assertSame($first, $second);
    }

    public function testDepthFirstOrder(): void
    {
        $part = PartBuilder::multipart(
            'mixed',
            PartBuilder::multipart(
                'alternative',
                PartBuilder::text('plain'),
                PartBuilder::html('<p>html</p>'),
            ),
            PartBuilder::text('other'),
        )->build();

        $iter = new PartIterator($part, false);
        $bodies = [];
        foreach ($iter as $p) {
            if ($p->hasBody()) {
                $bodies[] = $p->body;
            }
        }

        $this->assertContains('plain', $bodies);
        $this->assertContains('<p>html</p>', $bodies);
        $this->assertContains('other', $bodies);
    }

    public function testPartIterateMethod(): void
    {
        $part = PartBuilder::multipart(
            'mixed',
            PartBuilder::text('one'),
        )->build();

        $iter = $part->iterate(false);
        $keys = $this->collectKeys($iter);
        $this->assertCount(1, $keys);
    }

    public function testNestedIds(): void
    {
        $part = PartBuilder::multipart(
            'mixed',
            PartBuilder::multipart(
                'alternative',
                PartBuilder::text('plain'),
                PartBuilder::html('<p>html</p>'),
            ),
            PartBuilder::text('other'),
        )->build();

        $keys = $this->collectKeys(new PartIterator($part, false));

        $this->assertSame('1', $keys[0]);
        $this->assertContains('1.1', $keys);
        $this->assertContains('1.2', $keys);
        $this->assertContains('2', $keys);
    }
}
