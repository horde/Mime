<?php

/**
 * Copyright 1999-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author    Chuck Hagenbuch <chuck@horde.org>
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Mime
 */

declare(strict_types=1);

namespace Horde\Mime;

use Horde\Mime\Headers\ContentDisposition;
use Horde\Mime\Headers\ContentType;
use Horde\Mime\Headers\HeaderCollection;
use Horde\Stream\StreamInterface;

final readonly class Part
{
    /**
     * @param HeaderCollection         $headers   MIME headers.
     * @param string|StreamInterface   $body      Decoded (binary) body content.
     * @param list<Part>               $children  Child parts for multipart.
     * @param int|null                 $sizeHint  External size hint in bytes.
     * @param array<string,mixed>      $metadata  Arbitrary metadata.
     */
    public function __construct(
        public HeaderCollection $headers = new HeaderCollection(),
        public string|StreamInterface $body = '',
        public array $children = [],
        public ?int $sizeHint = null,
        public array $metadata = [],
    ) {}

    public function contentType(): ContentType
    {
        return $this->headers->contentType() ?? ContentType::default();
    }

    public function primaryType(): string
    {
        return $this->contentType()->primaryType;
    }

    public function subType(): string
    {
        return $this->contentType()->subType;
    }

    public function fullType(): string
    {
        $ct = $this->contentType();

        return $ct->primaryType . '/' . $ct->subType;
    }

    public function charset(): ?string
    {
        $charset = $this->contentType()->charset();
        if ($charset !== null) {
            return $charset;
        }

        if ($this->primaryType() === 'text') {
            return 'us-ascii';
        }

        return null;
    }

    public function boundary(): ?string
    {
        return $this->contentType()->boundary();
    }

    public function isMultipart(): bool
    {
        return $this->contentType()->isMultipart();
    }

    public function contentDisposition(): ?ContentDisposition
    {
        return $this->headers->contentDisposition();
    }

    public function transferEncoding(): TransferEncoding
    {
        return $this->headers->contentTransferEncoding()?->encoding
            ?? TransferEncoding::SevenBit;
    }

    public function description(): ?string
    {
        $h = $this->headers->get('content-description');

        return $h !== null ? $h->value() : null;
    }

    public function filename(): ?string
    {
        $disp = $this->contentDisposition();
        if ($disp !== null) {
            $fn = $disp->filename();
            if ($fn !== null) {
                return $fn;
            }
        }

        return $this->contentType()->param('name');
    }

    public function contentId(): ?string
    {
        $h = $this->headers->get('content-id');
        if ($h === null) {
            return null;
        }

        return trim($h->value(), '<>');
    }

    public function hasBody(): bool
    {
        if ($this->body instanceof StreamInterface) {
            return $this->body->length() !== 0;
        }

        return $this->body !== '';
    }

    /**
     * Get body content as a string.
     * If body is a stream, reads and returns its full contents.
     */
    public function bodyString(): string
    {
        if ($this->body instanceof StreamInterface) {
            $this->body->rewind();

            return $this->body->substring();
        }

        return $this->body;
    }

    public function bodySize(): int
    {
        if ($this->body instanceof StreamInterface) {
            return $this->body->length();
        }

        if ($this->body !== '') {
            return strlen($this->body);
        }

        return $this->sizeHint ?? 0;
    }

    /**
     * Whether the body is a stream.
     */
    public function isStream(): bool
    {
        return $this->body instanceof StreamInterface;
    }

    public function childCount(): int
    {
        return count($this->children);
    }

    public function child(int $index): ?self
    {
        return $this->children[$index] ?? null;
    }

    public function isLeaf(): bool
    {
        return empty($this->children);
    }

    public function metadata(string $key): mixed
    {
        return $this->metadata[$key] ?? null;
    }

    public function withHeaders(HeaderCollection $headers): self
    {
        return new self($headers, $this->body, $this->children, $this->sizeHint, $this->metadata);
    }

    public function withBody(string|StreamInterface $body): self
    {
        return new self($this->headers, $body, $this->children, $this->sizeHint, $this->metadata);
    }

    public function withChildren(array $children): self
    {
        return new self($this->headers, $this->body, $children, $this->sizeHint, $this->metadata);
    }

    public function withChild(self $child): self
    {
        $children = $this->children;
        $children[] = $child;

        return new self($this->headers, $this->body, $children, $this->sizeHint, $this->metadata);
    }

    public function withoutChild(int $index): self
    {
        $children = $this->children;
        unset($children[$index]);

        return new self($this->headers, $this->body, array_values($children), $this->sizeHint, $this->metadata);
    }

    public function withMetadata(string $key, mixed $value): self
    {
        $metadata = $this->metadata;
        $metadata[$key] = $value;

        return new self($this->headers, $this->body, $this->children, $this->sizeHint, $metadata);
    }

    public function withSizeHint(?int $sizeHint): self
    {
        return new self($this->headers, $this->body, $this->children, $sizeHint, $this->metadata);
    }

    public function iterate(bool $includeSelf = true): PartIterator
    {
        return new PartIterator($this, $includeSelf);
    }
}
