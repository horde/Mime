<?php

declare(strict_types=1);

namespace Horde\Mime\Test\Headers;

use Horde\Mime\Headers\ContentType;
use PHPUnit\Framework\TestCase;

class ContentTypeTest extends TestCase
{
    public function testBasicConstruction(): void
    {
        $ct = new ContentType('Content-Type', 'text/plain');
        $this->assertSame('text', $ct->primaryType);
        $this->assertSame('plain', $ct->subType);
        $this->assertSame('Content-Type', $ct->name());
        $this->assertSame('text/plain', $ct->value());
    }

    public function testWithParams(): void
    {
        $ct = new ContentType('Content-Type', 'text/html', ['charset' => 'utf-8']);
        $this->assertSame('utf-8', $ct->charset());
        $this->assertSame('text/html; charset=utf-8', $ct->value());
    }

    public function testFromRaw(): void
    {
        $ct = ContentType::fromRaw('Content-Type', 'multipart/mixed; boundary="----=_Part_1"');
        $this->assertSame('multipart', $ct->primaryType);
        $this->assertSame('mixed', $ct->subType);
        $this->assertSame('----=_Part_1', $ct->boundary());
        $this->assertTrue($ct->isMultipart());
    }

    public function testIsDefault(): void
    {
        $plain = new ContentType('Content-Type', 'text/plain');
        $this->assertTrue($plain->isDefault());

        $html = new ContentType('Content-Type', 'text/html');
        $this->assertFalse($html->isDefault());
    }

    public function testDefaultFactory(): void
    {
        $ct = ContentType::default();
        $this->assertSame('application', $ct->primaryType);
        $this->assertSame('octet-stream', $ct->subType);
    }

    public function testCaseInsensitiveType(): void
    {
        $ct = new ContentType('Content-Type', 'TEXT/HTML');
        $this->assertSame('text', $ct->primaryType);
        $this->assertSame('html', $ct->subType);
    }

    public function testCaseInsensitiveParams(): void
    {
        $ct = new ContentType('Content-Type', 'text/plain', ['CHARSET' => 'iso-8859-1']);
        $this->assertSame('iso-8859-1', $ct->charset());
    }

    public function testStringable(): void
    {
        $ct = new ContentType('Content-Type', 'text/plain', ['charset' => 'utf-8']);
        $this->assertSame('text/plain; charset=utf-8', (string) $ct);
    }

    public function testSendEncode(): void
    {
        $ct = new ContentType('Content-Type', 'text/plain', ['charset' => 'utf-8']);
        $encoded = $ct->sendEncode();
        $this->assertCount(1, $encoded);
        $this->assertSame('Content-Type: text/plain; charset=utf-8', $encoded[0]);
    }

    public function testHandles(): void
    {
        $this->assertSame(['content-type'], ContentType::handles());
    }

    public function testParamQuotedValues(): void
    {
        $ct = ContentType::fromRaw('Content-Type', 'multipart/mixed; boundary="abc def"');
        $this->assertSame('abc def', $ct->boundary());
    }
}
