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

    public function testAttachedMessageMultipartNumbering(): void
    {
        $plain = PartBuilder::text('plain')->build();
        $alternative = new Part(
            headers: HeaderCollection::parse("Content-Type: multipart/alternative\r\n"),
            children: [$plain],
        );
        $related = new Part(
            headers: HeaderCollection::parse("Content-Type: multipart/related\r\n"),
            children: [$alternative],
        );
        $mixed = new Part(
            headers: HeaderCollection::parse("Content-Type: multipart/mixed\r\n"),
            children: [$related],
        );
        $message = new Part(
            headers: HeaderCollection::parse("Content-Type: message/rfc822\r\n"),
            children: [$mixed],
        );
        $root = new Part(
            headers: HeaderCollection::parse("Content-Type: multipart/mixed\r\n"),
            children: [$plain, $plain, $plain, $message, $plain],
        );
        $includeSelf = $root->iterate()->count() <= 1;
        $iterator = $root->iterate($includeSelf);
        $parts = $this->collectParts($iterator);

        $this->assertSame(
            ['1', '2', '3', '4', '4.0', '4.1', '4.1.1', '4.1.1.1', '5'],
            $this->collectKeys($iterator),
        );
        $this->assertSame($message, $parts['4']);
        $this->assertSame($mixed, $parts['4.0']);
        $this->assertSame($related, $parts['4.1']);
        $this->assertSame($alternative, $parts['4.1.1']);
        $this->assertSame($plain, $parts['4.1.1.1']);
        $this->assertSame(9, $iterator->count());
    }

    public function testNestedMessagesAndSectionNumbersEndingInZero(): void
    {
        $plain = PartBuilder::text('plain')->build();
        $singleMessage = new Part(
            headers: HeaderCollection::parse("Content-Type: message/rfc822\r\n"),
            children: [$plain],
        );
        $embedded = new Part(
            headers: HeaderCollection::parse("Content-Type: multipart/mixed\r\n"),
            children: [$singleMessage, $plain],
        );
        $message = new Part(
            headers: HeaderCollection::parse("Content-Type: message/rfc822\r\n"),
            children: [$embedded],
        );
        $root = new Part(
            headers: HeaderCollection::parse("Content-Type: multipart/mixed\r\n"),
            children: [...array_fill(0, 9, $plain), $message, $singleMessage],
        );
        $parts = $this->collectParts($root->iterate(false));

        $this->assertSame($embedded, $parts['10.0'] ?? null);
        $this->assertSame($singleMessage, $parts['10.1'] ?? null);
        $this->assertSame($plain, $parts['10.1.1'] ?? null);
        $this->assertSame($plain, $parts['10.2'] ?? null);
        $this->assertSame($singleMessage, $parts['11'] ?? null);
        $this->assertSame($plain, $parts['11.1'] ?? null);
        $this->assertCount(16, $parts);
    }

    public function testRootMessageWithMultipartBodyHasDistinctIds(): void
    {
        $plain = PartBuilder::text('plain')->build();
        $mixed = new Part(
            headers: HeaderCollection::parse("Content-Type: multipart/mixed\r\n"),
            children: [$plain],
        );
        $message = new Part(
            headers: HeaderCollection::parse("Content-Type: message/rfc822\r\n"),
            children: [$mixed],
        );

        $this->assertSame(
            ['1.0', '1', '1.1'],
            $this->collectKeys($message->iterate()),
        );
        $this->assertSame(
            ['1', '1.1'],
            $this->collectKeys($message->iterate(false)),
        );
    }
}
