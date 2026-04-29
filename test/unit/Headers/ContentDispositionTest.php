<?php

declare(strict_types=1);

namespace Horde\Mime\Test\Headers;

use Horde\Mime\Headers\ContentDisposition;
use PHPUnit\Framework\TestCase;

class ContentDispositionTest extends TestCase
{
    public function testAttachment(): void
    {
        $cd = new ContentDisposition('Content-Disposition', 'attachment', ['filename' => 'test.txt']);
        $this->assertTrue($cd->isAttachment());
        $this->assertFalse($cd->isInline());
        $this->assertSame('test.txt', $cd->filename());
    }

    public function testInline(): void
    {
        $cd = new ContentDisposition('Content-Disposition', 'inline');
        $this->assertTrue($cd->isInline());
        $this->assertFalse($cd->isAttachment());
    }

    public function testIsDefault(): void
    {
        $cd = new ContentDisposition('Content-Disposition', '');
        $this->assertTrue($cd->isDefault());
    }

    public function testFromRaw(): void
    {
        $cd = ContentDisposition::fromRaw('Content-Disposition', 'attachment; filename="report.pdf"; size=12345');
        $this->assertTrue($cd->isAttachment());
        $this->assertSame('report.pdf', $cd->filename());
        $this->assertSame('12345', $cd->param('size'));
    }

    public function testSendEncode(): void
    {
        $cd = new ContentDisposition('Content-Disposition', 'attachment', ['filename' => 'test.txt']);
        $encoded = $cd->sendEncode();
        $this->assertCount(1, $encoded);
        $this->assertStringContainsString('attachment', $encoded[0]);
        $this->assertStringContainsString('filename=test.txt', $encoded[0]);
    }

    public function testHandles(): void
    {
        $this->assertSame(['content-disposition'], ContentDisposition::handles());
    }
}
