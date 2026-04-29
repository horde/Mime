<?php

declare(strict_types=1);

namespace Horde\Mime\Test;

use Horde\Mime\ComposedMessage;
use Horde\Mime\Headers\HeaderCollection;
use Horde\Mime\Headers\Subject;
use Horde\Mime\MessageBuilder;
use Horde\Mime\Part;
use Horde\Mime\PartBuilder;
use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;

#[CoversClass(MessageBuilder::class)]
#[CoversClass(ComposedMessage::class)]
class MessageBuilderTest extends TestCase
{
    public function testSimpleTextMessage(): void
    {
        $builder = new MessageBuilder();
        $msg = $builder
            ->setFrom('sender@example.com')
            ->setTo('recipient@example.com')
            ->setSubject('Hello')
            ->setBody('This is a test.')
            ->build();

        $this->assertInstanceOf(ComposedMessage::class, $msg);
        $this->assertSame('text/plain', $msg->part->fullType());
        $this->assertSame('This is a test.', $msg->part->body);
        $this->assertTrue($msg->headers->has('from'));
        $this->assertTrue($msg->headers->has('subject'));
        $this->assertTrue($msg->headers->has('date'));
        $this->assertTrue($msg->headers->has('message-id'));
        $this->assertTrue($msg->headers->has('mime-version'));
        $this->assertGreaterThan(0, $msg->recipients->count());
    }

    public function testHtmlOnlyMessage(): void
    {
        $builder = new MessageBuilder();
        $msg = $builder
            ->setTo('user@example.com')
            ->setHtmlBody('<p>Hello</p>')
            ->build();

        $this->assertSame('text/html', $msg->part->fullType());
        $this->assertStringContainsString('<p>Hello</p>', $msg->part->body);
    }

    public function testTextAndHtmlCreatesAlternative(): void
    {
        $builder = new MessageBuilder();
        $msg = $builder
            ->setTo('user@example.com')
            ->setBody('Plain text version')
            ->setHtmlBody('<p>HTML version</p>')
            ->build();

        $this->assertSame('multipart/alternative', $msg->part->fullType());
        $this->assertCount(2, $msg->part->children);
        $this->assertSame('text/plain', $msg->part->children[0]->fullType());
        $this->assertSame('text/html', $msg->part->children[1]->fullType());
    }

    public function testAttachmentsCreateMixed(): void
    {
        $builder = new MessageBuilder();
        $msg = $builder
            ->setTo('user@example.com')
            ->setBody('Message body')
            ->addAttachment('file content', 'report.pdf', 'application/pdf')
            ->build();

        $this->assertSame('multipart/mixed', $msg->part->fullType());
        $this->assertCount(2, $msg->part->children);
        $this->assertSame('text/plain', $msg->part->children[0]->fullType());
        $this->assertSame('application/pdf', $msg->part->children[1]->fullType());
    }

    public function testTextHtmlAndAttachments(): void
    {
        $builder = new MessageBuilder();
        $msg = $builder
            ->setTo('user@example.com')
            ->setBody('Plain')
            ->setHtmlBody('<p>HTML</p>')
            ->addAttachment('data', 'file.txt', 'text/plain')
            ->build();

        $this->assertSame('multipart/mixed', $msg->part->fullType());
        $this->assertCount(2, $msg->part->children);

        $altPart = $msg->part->children[0];
        $this->assertSame('multipart/alternative', $altPart->fullType());
        $this->assertCount(2, $altPart->children);
    }

    public function testRecipientAssemblyFromToCcBcc(): void
    {
        $builder = new MessageBuilder();
        $msg = $builder
            ->setTo('alice@example.com')
            ->setCc('bob@example.com')
            ->setBcc('charlie@example.com')
            ->setBody('Test')
            ->build();

        $bare = $msg->recipients->bareAddresses();
        $this->assertContains('alice@example.com', $bare);
        $this->assertContains('bob@example.com', $bare);
        $this->assertContains('charlie@example.com', $bare);
    }

    public function testBccNotInHeaders(): void
    {
        $builder = new MessageBuilder();
        $msg = $builder
            ->setTo('alice@example.com')
            ->setBcc('secret@example.com')
            ->setBody('Test')
            ->build();

        $this->assertFalse($msg->headers->has('bcc'));
    }

    public function testMandatoryHeadersAdded(): void
    {
        $builder = new MessageBuilder();
        $msg = $builder
            ->setTo('user@example.com')
            ->setBody('Test')
            ->build();

        $this->assertTrue($msg->headers->has('date'));
        $this->assertTrue($msg->headers->has('message-id'));
        $this->assertTrue($msg->headers->has('user-agent'));
        $this->assertTrue($msg->headers->has('mime-version'));
    }

    public function testExistingHeadersNotOverwritten(): void
    {
        $headers = (new HeaderCollection())
            ->withRaw('Message-ID', '<custom@example.com>');

        $builder = new MessageBuilder($headers);
        $msg = $builder
            ->setTo('user@example.com')
            ->setBody('Test')
            ->build();

        $this->assertSame('<custom@example.com>', $msg->headers->get('message-id')->value());
    }

    public function testSetBasePartOverridesComposition(): void
    {
        $custom = PartBuilder::text('Custom part')->build();

        $builder = new MessageBuilder();
        $msg = $builder
            ->setTo('user@example.com')
            ->setBody('This should be ignored')
            ->setBasePart($custom)
            ->build();

        $this->assertSame('Custom part', $msg->part->body);
    }

    public function testAddPart(): void
    {
        $inline = (new PartBuilder())
            ->setContentType('image/png')
            ->setDisposition('inline')
            ->setBody('PNG data')
            ->build();

        $builder = new MessageBuilder();
        $msg = $builder
            ->setTo('user@example.com')
            ->setBody('See attached image.')
            ->addPart($inline)
            ->build();

        $this->assertSame('multipart/mixed', $msg->part->fullType());
        $this->assertSame('image/png', $msg->part->children[1]->fullType());
    }

    public function testRemoveHeader(): void
    {
        $builder = new MessageBuilder();
        $msg = $builder
            ->setTo('user@example.com')
            ->setSubject('Remove me')
            ->removeHeader('Subject')
            ->setBody('Test')
            ->build();

        $this->assertFalse($msg->headers->has('subject'));
    }

    public function testCharsetConversion(): void
    {
        $builder = new MessageBuilder();
        $msg = $builder
            ->setTo('user@example.com')
            ->setCharset('ISO-8859-1')
            ->setBody("\xC3\xA9")
            ->build();

        $this->assertSame('ISO-8859-1', $msg->part->charset());
        $this->assertSame("\xE9", $msg->part->body);
    }

    public function testHtmlToTextConverter(): void
    {
        $builder = new MessageBuilder();
        $msg = $builder
            ->setTo('user@example.com')
            ->setHtmlToTextConverter(fn(string $html) => strip_tags($html))
            ->setHtmlBody('<p>Hello World</p>')
            ->build();

        $this->assertSame('multipart/alternative', $msg->part->fullType());
        $this->assertSame('Hello World', $msg->part->children[0]->body);
    }

    public function testEmptyBodyBuildsEmptyPart(): void
    {
        $builder = new MessageBuilder();
        $msg = $builder
            ->setTo('user@example.com')
            ->build();

        $this->assertSame('text/plain', $msg->part->fullType());
        $this->assertSame('', $msg->part->body);
    }
}
