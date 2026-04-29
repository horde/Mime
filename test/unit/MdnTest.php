<?php

declare(strict_types=1);

namespace Horde\Mime\Test;

use Horde\Mime\ComposedMessage;
use Horde\Mime\Headers\HeaderCollection;
use Horde\Mime\Mdn;
use Horde\Mime\MimeException;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(Mdn::class)]
class MdnTest extends TestCase
{
    private function buildOriginalHeaders(): HeaderCollection
    {
        return (new HeaderCollection())
            ->withRaw('From', 'sender@example.com')
            ->withRaw('To', 'recipient@example.com')
            ->withRaw('Subject', 'Original message')
            ->withRaw('Date', 'Mon, 28 Apr 2026 10:00:00 +0000')
            ->withRaw('Message-ID', '<original@example.com>')
            ->withRaw('Return-Path', '<sender@example.com>')
            ->withRaw('Disposition-Notification-To', 'sender@example.com');
    }

    public function testMdnReturnAddress(): void
    {
        $mdn = new Mdn($this->buildOriginalHeaders());

        $addr = $mdn->mdnReturnAddress();
        $this->assertNotNull($addr);
        $this->assertSame('sender@example.com', $addr->first()->bareAddress());
    }

    public function testMdnReturnAddressNullWhenMissing(): void
    {
        $headers = (new HeaderCollection())
            ->withRaw('From', 'sender@example.com');

        $mdn = new Mdn($headers);
        $this->assertNull($mdn->mdnReturnAddress());
    }

    public function testUserConfirmationNotNeededWhenMatch(): void
    {
        $mdn = new Mdn($this->buildOriginalHeaders());

        $this->assertFalse($mdn->userConfirmationNeeded());
    }

    public function testUserConfirmationNeededNoReturnPath(): void
    {
        $headers = (new HeaderCollection())
            ->withRaw('Disposition-Notification-To', 'sender@example.com');

        $mdn = new Mdn($headers);
        $this->assertTrue($mdn->userConfirmationNeeded());
    }

    public function testUserConfirmationNeededMismatch(): void
    {
        $headers = (new HeaderCollection())
            ->withRaw('Return-Path', '<other@example.com>')
            ->withRaw('Disposition-Notification-To', 'sender@example.com');

        $mdn = new Mdn($headers);
        $this->assertTrue($mdn->userConfirmationNeeded());
    }

    public function testGenerateDisplayed(): void
    {
        $mdn = new Mdn($this->buildOriginalHeaders());
        $msg = $mdn->generate(
            manualAction: true,
            manualSending: true,
            type: 'displayed',
            reportingUa: 'test.example.com',
            opts: ['from_addr' => 'recipient@example.com'],
        );

        $this->assertInstanceOf(ComposedMessage::class, $msg);
        $this->assertSame('multipart/report', $msg->part->primaryType() . '/' . $msg->part->subType());
        $this->assertCount(3, $msg->part->children);

        $this->assertSame('text/plain', $msg->part->children[0]->fullType());
        $this->assertSame('message/disposition-notification', $msg->part->children[1]->fullType());
        $this->assertSame('message/rfc822', $msg->part->children[2]->fullType());

        $machineText = $msg->part->children[1]->body;
        $this->assertStringContainsString('Disposition: manual-action/MDN-sent-manually; displayed', $machineText);
        $this->assertStringContainsString('Reporting-UA: test.example.com', $machineText);
        $this->assertStringContainsString('Original-Message-ID: <original@example.com>', $machineText);
        $this->assertStringContainsString('Final-Recipient: rfc822;recipient@example.com', $machineText);

        $this->assertTrue($msg->headers->has('auto-submitted'));
        $this->assertGreaterThan(0, $msg->recipients->count());
    }

    public function testGenerateWithoutMdnHeaderThrows(): void
    {
        $headers = (new HeaderCollection())
            ->withRaw('From', 'sender@example.com');

        $mdn = new Mdn($headers);

        $this->expectException(MimeException::class);
        $mdn->generate(true, true, 'displayed', 'test.local');
    }

    public function testGenerateWithModifiers(): void
    {
        $mdn = new Mdn($this->buildOriginalHeaders());
        $msg = $mdn->generate(
            manualAction: false,
            manualSending: false,
            type: 'displayed',
            reportingUa: 'server.local',
            opts: [],
            modifiers: ['error'],
            errors: ['error' => 'Something went wrong'],
        );

        $machineText = $msg->part->children[1]->body;
        $this->assertStringContainsString('automatic-action/MDN-sent-automatically; displayed/error', $machineText);
        $this->assertStringContainsString('Error: Something went wrong', $machineText);
    }

    public function testAddMdnRequestHeader(): void
    {
        $headers = new HeaderCollection();
        $updated = Mdn::addMdnRequestHeader($headers, 'receipt@example.com');

        $this->assertTrue($updated->has('disposition-notification-to'));
        $this->assertSame('receipt@example.com', $updated->get('disposition-notification-to')->value());
    }

    public function testOriginalBodyIncluded(): void
    {
        $mdn = new Mdn($this->buildOriginalHeaders(), 'Original body text here.');
        $msg = $mdn->generate(
            manualAction: true,
            manualSending: true,
            type: 'displayed',
            reportingUa: 'test.local',
        );

        $originalPart = $msg->part->children[2]->body;
        $this->assertStringContainsString('Original body text here.', $originalPart);
    }
}
