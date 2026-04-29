<?php

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Mime
 */

declare(strict_types=1);

namespace Horde\Mime;

use Horde\Mail\Rfc822\AddressList;
use Horde\Mail\Rfc822\Rfc822Parser;
use Horde\Mime\Headers\Addresses;
use Horde\Mime\Headers\DateHeader;
use Horde\Mime\Headers\HeaderCollection;
use Horde\Mime\Headers\MessageId;
use Horde\Mime\Headers\MimeVersion;
use Horde\Mime\Headers\Subject;
use Horde\Mime\Headers\UserAgent;

/**
 * High-level message composition API.
 *
 * Builds a MIME Part tree and message-level headers from user-facing inputs.
 * Does NOT send — returns a ComposedMessage for the caller to render and dispatch.
 */
final class MessageBuilder
{
    private HeaderCollection $headers;

    private ?Part $textBody = null;

    private ?Part $htmlBody = null;

    /** @var list<Part> */
    private array $attachments = [];

    private ?Part $basePart = null;

    private string $charset = 'UTF-8';

    private ?string $bcc = null;

    /** @var callable|null */
    private mixed $htmlToTextConverter = null;

    private TextFormatter $textFormatter;

    public function __construct(?HeaderCollection $headers = null, ?TextFormatter $textFormatter = null)
    {
        $this->headers = $headers ?? new HeaderCollection();
        $this->textFormatter = $textFormatter ?? new FlowedFormatter();
    }

    public function setFrom(string $from): self
    {
        $this->headers = $this->headers->withRaw('From', $from);

        return $this;
    }

    public function setTo(string $to): self
    {
        $this->headers = $this->headers->withRaw('To', $to);

        return $this;
    }

    public function setCc(string $cc): self
    {
        $this->headers = $this->headers->withRaw('Cc', $cc);

        return $this;
    }

    /**
     * Set BCC recipients. These are included in the recipient list
     * but stripped from rendered message headers.
     */
    public function setBcc(string $bcc): self
    {
        $this->bcc = $bcc;

        return $this;
    }

    public function setReplyTo(string $replyTo): self
    {
        $this->headers = $this->headers->withRaw('Reply-To', $replyTo);

        return $this;
    }

    public function setSubject(string $subject): self
    {
        $this->headers = $this->headers->with(new Subject('Subject', $subject));

        return $this;
    }

    public function setCharset(string $charset): self
    {
        $this->charset = $charset;

        return $this;
    }

    public function addHeader(string $name, string $value): self
    {
        $this->headers = $this->headers->withRaw($name, $value);

        return $this;
    }

    public function removeHeader(string $name): self
    {
        $this->headers = $this->headers->without($name);

        return $this;
    }

    /**
     * Set the plaintext message body.
     *
     * @param string      $text     UTF-8 encoded text.
     * @param string|null $charset  Target charset for the part (default: builder charset).
     * @param bool        $flowed   Apply RFC 3676 format=flowed encoding via the TextFormatter.
     */
    public function setBody(string $text, ?string $charset = null, bool $flowed = false): self
    {
        $charset ??= $this->charset;
        $encoded = $this->convertCharset($text, $charset);

        if ($flowed) {
            $result = ($this->textFormatter)($encoded, $charset);
            $params = array_merge(['charset' => $charset], $result->params);

            $this->textBody = (new PartBuilder())
                ->setContentType('text/plain', $params)
                ->setBody($result->text)
                ->build();
        } else {
            $this->textBody = PartBuilder::text($encoded, 'plain', $charset)->build();
        }

        return $this;
    }

    /**
     * Set the HTML message body.
     *
     * If an htmlToTextConverter is set, a text/plain alternative is
     * auto-generated.
     *
     * @param string      $html     UTF-8 encoded HTML.
     * @param string|null $charset  Target charset for the part.
     */
    public function setHtmlBody(string $html, ?string $charset = null): self
    {
        $charset ??= $this->charset;
        $encoded = $this->convertCharset($html, $charset);
        $this->htmlBody = PartBuilder::html($encoded, $charset)->build();

        if ($this->htmlToTextConverter !== null && $this->textBody === null) {
            $plainText = ($this->htmlToTextConverter)($html);
            $plainEncoded = $this->convertCharset($plainText, $charset);
            $this->textBody = PartBuilder::text($plainEncoded, 'plain', $charset)->build();
        }

        return $this;
    }

    /**
     * Set a callback for converting HTML to plain text.
     * Used by setHtmlBody() to auto-generate a text/plain alternative.
     *
     * @param callable(string): string $converter
     */
    public function setHtmlToTextConverter(?callable $converter): self
    {
        $this->htmlToTextConverter = $converter;

        return $this;
    }

    /**
     * Add an attachment from raw content.
     */
    public function addAttachment(
        string $content,
        string $filename,
        string $mimeType = 'application/octet-stream',
    ): self {
        $this->attachments[] = PartBuilder::attachment($content, $filename, $mimeType)->build();

        return $this;
    }

    /**
     * Add an attachment from a file path.
     */
    public function addAttachmentFromFile(
        string $path,
        ?string $filename = null,
        ?string $mimeType = null,
    ): self {
        $content = file_get_contents($path);
        if ($content === false) {
            throw new MimeException('Cannot read file: ' . $path);
        }

        $filename ??= basename($path);
        $mimeType ??= Magic::analyzeFile($path) ?? Magic::filenameToMime($filename);

        $this->attachments[] = PartBuilder::attachment($content, $filename, $mimeType)->build();

        return $this;
    }

    /**
     * Add an already-built Part as a child/attachment.
     */
    public function addPart(Part $part): self
    {
        $this->attachments[] = $part;

        return $this;
    }

    /**
     * Override the entire MIME tree with a pre-built Part.
     * When set, text/html/attachment composition is skipped.
     */
    public function setBasePart(Part $part): self
    {
        $this->basePart = $part;

        return $this;
    }

    /**
     * Build the composed message.
     *
     * Adds mandatory headers, assembles the MIME tree, and computes recipients.
     */
    public function build(): ComposedMessage
    {
        $part = $this->buildPartTree();
        $headers = $this->buildHeaders();
        $recipients = $this->buildRecipients();

        return new ComposedMessage($part, $headers, $recipients);
    }

    private function buildPartTree(): Part
    {
        if ($this->basePart !== null) {
            return $this->basePart;
        }

        $body = $this->buildBodyPart();
        if (empty($this->attachments)) {
            return $body;
        }

        return PartBuilder::multipart('mixed', $body, ...$this->attachments)->build();
    }

    private function buildBodyPart(): Part
    {
        if ($this->textBody !== null && $this->htmlBody !== null) {
            return PartBuilder::multipart(
                'alternative',
                $this->textBody,
                $this->htmlBody,
            )->build();
        }

        if ($this->htmlBody !== null) {
            return $this->htmlBody;
        }

        if ($this->textBody !== null) {
            return $this->textBody;
        }

        return PartBuilder::text('', 'plain', $this->charset)->build();
    }

    private function buildHeaders(): HeaderCollection
    {
        $headers = $this->headers;

        if (!$headers->has('mime-version')) {
            $headers = $headers->with(MimeVersion::create());
        }

        if (!$headers->has('date')) {
            $headers = $headers->with(DateHeader::create());
        }

        if (!$headers->has('message-id')) {
            $headers = $headers->with(MessageId::create());
        }

        if (!$headers->has('user-agent')) {
            $headers = $headers->with(UserAgent::create());
        }

        return $headers;
    }

    private function buildRecipients(): AddressList
    {
        $parser = new Rfc822Parser();
        $recipients = new AddressList();

        foreach (['to', 'cc'] as $field) {
            $header = $this->headers->get($field);
            if ($header !== null) {
                $list = $parser->parseAddressList($header->value());
                $recipients->add(...$list->baseElements());
            }
        }

        if ($this->bcc !== null && $this->bcc !== '') {
            $list = $parser->parseAddressList($this->bcc);
            $recipients->add(...$list->baseElements());
        }

        $recipients = $recipients->unique();

        return $recipients;
    }

    private function convertCharset(string $text, string $charset): string
    {
        if (strcasecmp($charset, 'UTF-8') === 0) {
            return $text;
        }

        $converted = @mb_convert_encoding($text, $charset, 'UTF-8');
        if ($converted === false) {
            return $text;
        }

        return $converted;
    }
}
