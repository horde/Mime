<?php

declare(strict_types=1);

namespace Horde\Mime\Test;

use Horde\Mime\Headers\ContentDisposition;
use Horde\Mime\Headers\ContentType;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(ContentType::class)]
#[CoversClass(ContentDisposition::class)]
class Rfc2231Test extends TestCase
{
    public function testAsciiParamNotEncoded(): void
    {
        $ct = new ContentType('Content-Type', 'text/plain', ['charset' => 'utf-8']);
        $encoded = $ct->sendEncode()[0];

        $this->assertSame('Content-Type: text/plain; charset=utf-8', $encoded);
    }

    public function testAsciiParamWithSpecialCharsQuoted(): void
    {
        $ct = new ContentType('Content-Type', 'multipart/mixed', ['boundary' => '=_boundary with spaces']);
        $encoded = $ct->sendEncode()[0];

        $this->assertStringContainsString('boundary="=_boundary with spaces"', $encoded);
    }

    public function testNonAsciiParamEncoded(): void
    {
        $disp = new ContentDisposition('Content-Disposition', 'attachment', ['filename' => "Ü bersicht.pdf"]);
        $encoded = $disp->sendEncode()[0];

        $this->assertStringContainsString('filename*=', $encoded);
        $this->assertStringContainsString("utf-8''", $encoded);
        $this->assertStringContainsString('%C3%9C', $encoded);
    }

    public function testNonAsciiParamDecodable(): void
    {
        $original = "Ünïcödé naïve.txt";
        $disp = new ContentDisposition('Content-Disposition', 'attachment', ['filename' => $original]);
        $encoded = $disp->sendEncode()[0];

        preg_match("/filename\*=utf-8''(.+)/", $encoded, $m);
        $this->assertNotEmpty($m);
        $decoded = rawurldecode($m[1]);
        $this->assertSame($original, $decoded);
    }

    public function testLongParamContinuation(): void
    {
        $longName = str_repeat('ä', 50);
        $disp = new ContentDisposition('Content-Disposition', 'attachment', ['filename' => $longName]);
        $encoded = $disp->sendEncode()[0];

        $this->assertStringContainsString('filename*0*=', $encoded);
        $this->assertStringContainsString('filename*1*=', $encoded);
    }

    public function testBoundaryNeverContinued(): void
    {
        $longBoundary = str_repeat('a', 70);
        $ct = new ContentType('Content-Type', 'multipart/mixed', ['boundary' => $longBoundary]);
        $encoded = $ct->sendEncode()[0];

        $this->assertStringNotContainsString('boundary*0', $encoded);
        $this->assertStringContainsString('boundary=' . $longBoundary, $encoded);
    }
}
