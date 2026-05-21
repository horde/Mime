<?php

declare(strict_types=1);

namespace Horde\Mime\Test;

use Horde\Mime\Encoding\QuotedPrintable;
use Horde\Mime\Headers\ContentDisposition;
use Horde\Mime\Headers\HeaderCollection;
use Horde\Mime\Mdn;
use Horde\Mime\MimeParser;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

/**
 * Tests using canonical examples from RFC 2045, RFC 2046, RFC 2231, and RFC 3798.
 */
#[CoversClass(MimeParser::class)]
#[CoversClass(QuotedPrintable::class)]
#[CoversClass(ContentDisposition::class)]
#[CoversClass(Mdn::class)]
class RfcCanonicalTest extends TestCase
{
    /**
     * RFC 2045 §6.7 canonical example:
     * "Now's the time for all folk to come to the aid of their country."
     * encoded with a soft line break splitting "folk" onto a new line.
     */
    public function testQpSoftLineBreakFromRfc2045(): void
    {
        $encoded = "Now's the time =\r\nfor all folk to come=\r\n to the aid of their country.";

        $decoded = QuotedPrintable::decode($encoded);

        $this->assertSame(
            "Now's the time for all folk to come to the aid of their country.",
            $decoded,
        );
    }

    /**
     * RFC 2045 §6.7: bytes outside the 7-bit ASCII printable range
     * MUST be represented as "=XX" where XX is the hex value.
     */
    public function testQpHighBytesFromRfc2045(): void
    {
        $input = "\xC3\xBC\xE9";

        $encoded = QuotedPrintable::encode($input, "\r\n");

        $this->assertStringContainsString('=C3=BC', $encoded);
        $this->assertStringContainsString('=E9', $encoded);
    }

    /**
     * RFC 2045 §6.7: encode then decode must be identity for the canonical text.
     */
    public function testQpRoundTripRfc2045Canonical(): void
    {
        $original = "Now's the time for all folk to come to the aid of their country.";

        $encoded = QuotedPrintable::encode($original, "\r\n");
        $decoded = QuotedPrintable::decode($encoded);

        $this->assertSame($original, $decoded);
    }

    /**
     * RFC 2046 §5.1.1 canonical "simple boundary" example:
     * preamble, two text/plain parts separated by "--simple boundary",
     * closing "--simple boundary--", epilogue.
     *
     * The first part has NO Content-Type header — per RFC 2046 §5.1 it
     * defaults to text/plain; charset=us-ascii.
     */
    public function testParseMultipartFromRfc2046(): void
    {
        $msg = implode("\r\n", [
            'MIME-Version: 1.0',
            'Content-Type: multipart/mixed; boundary="simple boundary"',
            '',
            'This is the preamble.  It is to be ignored, though it',
            'is a handy place for composition agents to include an',
            'explanatory note to non-MIME conformant readers.',
            '',
            '--simple boundary',
            '',
            'This is implicitly typed plain US-ASCII text.',
            'It does NOT end with a linebreak.',
            '--simple boundary',
            'Content-type: text/plain; charset=us-ascii',
            '',
            'This is explicitly typed plain US-ASCII text.',
            'It DOES end with a linebreak.',
            '',
            '--simple boundary--',
            '',
            'This is the epilogue.  It is also to be ignored.',
        ]);

        $part = MimeParser::parse($msg);

        $this->assertSame('multipart/mixed', $part->fullType());
        $this->assertCount(2, $part->children);

        $this->assertSame('text/plain', $part->children[0]->fullType());
        $this->assertStringContainsString(
            'This is implicitly typed plain US-ASCII text.',
            $part->children[0]->body,
        );

        $this->assertSame('text/plain', $part->children[1]->fullType());
        $this->assertStringContainsString(
            'This is explicitly typed plain US-ASCII text.',
            $part->children[1]->body,
        );
    }

    /**
     * RFC 2046 §5.1.1: trailing whitespace on boundary lines MUST be ignored.
     */
    public function testParseMultipartBoundaryWhitespace(): void
    {
        $msg = implode("\r\n", [
            'MIME-Version: 1.0',
            'Content-Type: multipart/mixed; boundary="boundary42"',
            '',
            '--boundary42  ',
            'Content-Type: text/plain',
            '',
            'First part.',
            '--boundary42   ',
            'Content-Type: text/plain',
            '',
            'Second part.',
            '--boundary42--  ',
        ]);

        $part = MimeParser::parse($msg);

        $this->assertSame('multipart/mixed', $part->fullType());
        $this->assertCount(2, $part->children);
        $this->assertStringContainsString('First part.', $part->children[0]->body);
        $this->assertStringContainsString('Second part.', $part->children[1]->body);
    }

    /**
     * RFC 2046 §5.1.4: multipart/alternative with text/plain and text/enriched.
     */
    public function testParseMultipartAlternativeFromRfc(): void
    {
        $msg = implode("\r\n", [
            'MIME-Version: 1.0',
            'Content-Type: multipart/alternative; boundary=boundary42',
            '',
            '--boundary42',
            'Content-Type: text/plain; charset=us-ascii',
            '',
            'See the langstroth hive for more.',
            '--boundary42',
            'Content-Type: text/enriched',
            '',
            'See the <bold>langstroth hive</bold> for more.',
            '--boundary42--',
        ]);

        $part = MimeParser::parse($msg);

        $this->assertSame('multipart/alternative', $part->fullType());
        $this->assertCount(2, $part->children);
        $this->assertSame('text/plain', $part->children[0]->fullType());
        $this->assertSame('text/enriched', $part->children[1]->fullType());
        $this->assertStringContainsString('langstroth hive', $part->children[0]->body);
        $this->assertStringContainsString('<bold>langstroth hive</bold>', $part->children[1]->body);
    }

    /**
     * RFC 2231 §4: non-ASCII filename parameter encoded with charset and
     * percent-encoding. Verify the `*=utf-8''...` format.
     */
    public function testRfc2231NonAsciiFilenameRoundTrip(): void
    {
        $original = "Übersicht.pdf";
        $disp = new ContentDisposition('Content-Disposition', 'attachment', ['filename' => $original]);
        $encoded = $disp->sendEncode()[0];

        $this->assertStringContainsString("filename*=utf-8''", $encoded);
        $this->assertStringContainsString('%C3%9C', $encoded);

        preg_match("/filename\*=utf-8''(.+)/", $encoded, $m);
        $this->assertNotEmpty($m);
        $decoded = rawurldecode($m[1]);
        $this->assertSame($original, $decoded);
    }

    /**
     * RFC 2231 §3: parameter values that exceed a single line use
     * continuation with `*0*=`, `*1*=`, etc.
     */
    public function testRfc2231ContinuationLines(): void
    {
        $longFilename = str_repeat('ñ', 50);
        $disp = new ContentDisposition('Content-Disposition', 'attachment', ['filename' => $longFilename]);
        $encoded = $disp->sendEncode()[0];

        $this->assertStringContainsString('filename*0*=', $encoded);
        $this->assertStringContainsString('filename*1*=', $encoded);
    }

    /**
     * RFC 3798 §3: MDN is multipart/report with 3 body parts:
     *   1. text/plain (human-readable)
     *   2. message/disposition-notification (machine-readable)
     *   3. message/rfc822 (original message or headers)
     */
    public function testMdnStructureFromRfc3798(): void
    {
        $headers = (new HeaderCollection())
            ->withRaw('From', 'sender@example.com')
            ->withRaw('To', 'recipient@example.com')
            ->withRaw('Subject', 'Test MDN')
            ->withRaw('Date', 'Mon, 28 Apr 2026 10:00:00 +0000')
            ->withRaw('Message-ID', '<unique-id-1234@example.com>')
            ->withRaw('Return-Path', '<sender@example.com>')
            ->withRaw('Disposition-Notification-To', 'sender@example.com');

        $mdn = new Mdn($headers, "Original message body.");
        $msg = $mdn->generate(
            manualAction: true,
            manualSending: true,
            type: 'displayed',
            reportingUa: 'mail.example.com',
            opts: ['from_addr' => 'recipient@example.com'],
        );

        $this->assertSame('multipart/report', $msg->part->fullType());
        $this->assertCount(3, $msg->part->children);
        $this->assertSame('text/plain', $msg->part->children[0]->fullType());
        $this->assertSame('message/disposition-notification', $msg->part->children[1]->fullType());
        $this->assertSame('message/rfc822', $msg->part->children[2]->fullType());
    }

    /**
     * RFC 3798 §3.2.6: the Disposition field in the machine-readable part
     * MUST follow the grammar: action-mode "/" sending-mode ";" disposition-type.
     */
    public function testMdnDispositionFieldFormat(): void
    {
        $headers = (new HeaderCollection())
            ->withRaw('From', 'sender@example.com')
            ->withRaw('To', 'recipient@example.com')
            ->withRaw('Message-ID', '<test@example.com>')
            ->withRaw('Return-Path', '<sender@example.com>')
            ->withRaw('Disposition-Notification-To', 'sender@example.com');

        $mdn = new Mdn($headers);
        $msg = $mdn->generate(
            manualAction: false,
            manualSending: false,
            type: 'displayed',
            reportingUa: 'mx.example.com',
        );

        $machineText = $msg->part->children[1]->body;

        $this->assertStringContainsString(
            'Disposition: automatic-action/MDN-sent-automatically; displayed',
            $machineText,
        );
        $this->assertStringContainsString('Reporting-UA: mx.example.com', $machineText);
        $this->assertStringContainsString('Original-Message-ID: <test@example.com>', $machineText);
    }
}
