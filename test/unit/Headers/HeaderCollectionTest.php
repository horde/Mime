<?php

declare(strict_types=1);

namespace Horde\Mime\Test\Headers;

use Horde\Mime\Headers\Addresses;
use Horde\Mime\Headers\ContentDisposition;
use Horde\Mime\Headers\ContentTransferEncoding;
use Horde\Mime\Headers\ContentType;
use Horde\Mime\Headers\DateHeader;
use Horde\Mime\Headers\GenericHeader;
use Horde\Mime\Headers\HeaderCollection;
use Horde\Mime\Headers\MessageId;
use Horde\Mime\Headers\Received;
use Horde\Mime\Headers\Subject;
use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class HeaderCollectionTest extends TestCase
{
    public function testEmptyCollection(): void
    {
        $c = new HeaderCollection();
        $this->assertCount(0, $c);
        $this->assertNull($c->get('Subject'));
        $this->assertFalse($c->has('Subject'));
    }

    public function testWithAndGet(): void
    {
        $c = new HeaderCollection();
        $subject = new Subject('Subject', 'Hello');
        $c2 = $c->with($subject);

        $this->assertCount(0, $c);
        $this->assertCount(1, $c2);
        $this->assertSame($subject, $c2->get('Subject'));
        $this->assertSame($subject, $c2->get('subject'));
    }

    public function testWithout(): void
    {
        $c = (new HeaderCollection())
            ->with(new Subject('Subject', 'Hello'))
            ->with(new DateHeader('Date', 'Thu, 24 Apr 2026 12:00:00 +0000'));

        $this->assertCount(2, $c);

        $c2 = $c->without('subject');
        $this->assertCount(1, $c2);
        $this->assertNull($c2->subject());
        $this->assertNotNull($c2->date());
    }

    public function testWithRaw(): void
    {
        $c = (new HeaderCollection())->withRaw('Subject', 'Test value');

        $this->assertCount(1, $c);
        $s = $c->subject();
        $this->assertInstanceOf(Subject::class, $s);
        $this->assertSame('Test value', $s->value());
    }

    public function testTypedAccessors(): void
    {
        $c = (new HeaderCollection())
            ->with(new Subject('Subject', 'Hello'))
            ->with(ContentType::fromRaw('Content-Type', 'text/html; charset=utf-8'))
            ->with(new Addresses('From', 'test@example.com'))
            ->with(DateHeader::create())
            ->with(MessageId::create());

        $this->assertInstanceOf(Subject::class, $c->subject());
        $this->assertInstanceOf(ContentType::class, $c->contentType());
        $this->assertInstanceOf(Addresses::class, $c->from());
        $this->assertInstanceOf(DateHeader::class, $c->date());
        $this->assertInstanceOf(MessageId::class, $c->messageId());
        $this->assertNull($c->to());
    }

    public function testImmutability(): void
    {
        $c1 = new HeaderCollection();
        $c2 = $c1->with(new Subject('Subject', 'Hello'));
        $c3 = $c2->with(new Subject('Subject', 'World'));

        $this->assertCount(0, $c1);
        $this->assertSame('Hello', $c2->subject()->value());
        $this->assertSame('World', $c3->subject()->value());
    }

    public function testParse(): void
    {
        $raw = "From: user@example.com\r\nSubject: Test\r\nContent-Type: text/plain; charset=utf-8\r\nX-Custom: foo\r\n\r\n";
        $c = HeaderCollection::parse($raw);

        $this->assertCount(4, $c);
        $this->assertInstanceOf(Addresses::class, $c->from());
        $this->assertSame('user@example.com', $c->from()->value());
        $this->assertInstanceOf(Subject::class, $c->subject());
        $this->assertSame('Test', $c->subject()->value());
        $this->assertInstanceOf(ContentType::class, $c->contentType());
        $this->assertSame('text', $c->contentType()->primaryType);
        $this->assertSame('plain', $c->contentType()->subType);
        $this->assertSame('utf-8', $c->contentType()->charset());
        $this->assertInstanceOf(GenericHeader::class, $c->get('X-Custom'));
    }

    public function testParseFoldedHeaders(): void
    {
        $raw = "Subject: This is a\r\n very long subject\r\n\r\n";
        $c = HeaderCollection::parse($raw);

        $this->assertSame('This is a very long subject', $c->subject()->value());
    }

    public function testParseMultipleReceived(): void
    {
        $raw = "Received: from a\r\nReceived: from b\r\n\r\n";
        $c = HeaderCollection::parse($raw);

        $received = $c->get('Received');
        $this->assertInstanceOf(Received::class, $received);
        $this->assertCount(2, $received->values());
    }

    public function testToString(): void
    {
        $c = (new HeaderCollection())
            ->with(new Subject('Subject', 'Hello'))
            ->with(new DateHeader('Date', 'Thu, 24 Apr 2026 12:00:00 +0000'));

        $str = $c->toString("\r\n");
        $this->assertStringContainsString('Subject:', $str);
        $this->assertStringContainsString('Date:', $str);
    }

    public function testToArray(): void
    {
        $c = (new HeaderCollection())
            ->with(new Subject('Subject', 'Hello'))
            ->with(new Received('Received', ['from a', 'from b']));

        $arr = $c->toArray();
        $this->assertSame('Hello', $arr['Subject']);
        $this->assertSame(['from a', 'from b'], $arr['Received']);
    }

    public function testIteration(): void
    {
        $c = (new HeaderCollection())
            ->with(new Subject('Subject', 'Hello'))
            ->with(new DateHeader('Date', 'now'));

        $names = [];
        foreach ($c as $header) {
            $names[] = $header->name();
        }

        $this->assertSame(['Subject', 'Date'], $names);
    }

    public function testAll(): void
    {
        $c = (new HeaderCollection())
            ->with(new Subject('Subject', 'Hello'))
            ->with(new DateHeader('Date', 'now'));

        $all = $c->all();
        $this->assertCount(2, $all);
        $this->assertInstanceOf(Subject::class, $all[0]);
        $this->assertInstanceOf(DateHeader::class, $all[1]);
    }
}
