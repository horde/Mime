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

use Horde\Mime\Encoding\TransferDecoder;
use Horde\Mime\Headers\ContentDescription;
use Horde\Mime\Headers\ContentDisposition;
use Horde\Mime\Headers\ContentId;
use Horde\Mime\Headers\ContentLanguage;
use Horde\Mime\Headers\ContentTransferEncoding;
use Horde\Mime\Headers\ContentType;
use Horde\Mime\Headers\HeaderCollection;

final class PartBuilder
{
    private HeaderCollection $headers;

    /** @var string|resource */
    private mixed $body = '';

    private ?TransferEncoding $bodyEncoding = null;

    /** @var list<Part|self> */
    private array $children = [];

    private ?int $sizeHint = null;

    /** @var array<string,mixed> */
    private array $metadata = [];

    public function __construct(?HeaderCollection $headers = null)
    {
        $this->headers = $headers ?? new HeaderCollection();
    }

    public function setContentType(string $type, array $params = []): self
    {
        $this->headers = $this->headers->with(
            new ContentType('Content-Type', $type, $params),
        );

        return $this;
    }

    public function setCharset(string $charset): self
    {
        $ct = $this->headers->contentType();
        if ($ct !== null) {
            $params = $ct->params();
            $params['charset'] = $charset;
            $this->headers = $this->headers->with(
                new ContentType('Content-Type', $ct->primaryType . '/' . $ct->subType, $params),
            );
        } else {
            $this->setContentType('text/plain', ['charset' => $charset]);
        }

        return $this;
    }

    public function setDisposition(string $disposition, array $params = []): self
    {
        $this->headers = $this->headers->with(
            new ContentDisposition('Content-Disposition', $disposition, $params),
        );

        return $this;
    }

    public function setFilename(string $filename): self
    {
        $disp = $this->headers->contentDisposition();
        $dispParams = $disp !== null ? $disp->params() : [];
        $dispParams['filename'] = $filename;
        $dispBase = $disp !== null && !$disp->isDefault()
            ? ($disp->isInline() ? 'inline' : 'attachment')
            : 'attachment';

        $this->headers = $this->headers->with(
            new ContentDisposition('Content-Disposition', $dispBase, $dispParams),
        );

        $ct = $this->headers->contentType();
        if ($ct !== null) {
            $ctParams = $ct->params();
            $ctParams['name'] = $filename;
            $this->headers = $this->headers->with(
                new ContentType('Content-Type', $ct->primaryType . '/' . $ct->subType, $ctParams),
            );
        }

        return $this;
    }

    public function setTransferEncoding(TransferEncoding $encoding): self
    {
        $this->headers = $this->headers->with(
            new ContentTransferEncoding('Content-Transfer-Encoding', $encoding->value),
        );

        return $this;
    }

    public function setDescription(string $description): self
    {
        $this->headers = $this->headers->with(
            new ContentDescription('Content-Description', $description),
        );

        return $this;
    }

    public function setContentId(?string $cid = null): self
    {
        $this->headers = $this->headers->with(
            $cid !== null
                ? new ContentId('Content-ID', $cid)
                : ContentId::create(),
        );

        return $this;
    }

    public function setLanguage(string ...$languages): self
    {
        $this->headers = $this->headers->with(
            new ContentLanguage('Content-Language', implode(', ', $languages)),
        );

        return $this;
    }

    public function setHeader(string $name, string $value): self
    {
        $this->headers = $this->headers->withRaw($name, $value);

        return $this;
    }

    public function setHeaders(HeaderCollection $headers): self
    {
        $this->headers = $headers;

        return $this;
    }

    /**
     * @param string|resource $body
     */
    public function setBody(mixed $body, ?TransferEncoding $encoding = null): self
    {
        $this->body = $body;
        $this->bodyEncoding = $encoding;

        return $this;
    }

    public function setSizeHint(int $bytes): self
    {
        $this->sizeHint = $bytes;

        return $this;
    }

    public function addChild(Part|self $child): self
    {
        $this->children[] = $child;

        return $this;
    }

    public function setChildren(Part|self ...$children): self
    {
        $this->children = array_values($children);

        return $this;
    }

    public function setMetadata(string $key, mixed $value): self
    {
        $this->metadata[$key] = $value;

        return $this;
    }

    public function build(): Part
    {
        $body = $this->resolveBody();
        $children = $this->buildChildren();
        $headers = $this->ensureBoundary($this->headers, $children);

        return new Part($headers, $body, $children, $this->sizeHint, $this->metadata);
    }

    public static function text(
        string $body,
        string $subType = 'plain',
        string $charset = 'utf-8',
    ): self {
        return (new self())
            ->setContentType('text/' . $subType, ['charset' => $charset])
            ->setBody($body);
    }

    public static function html(string $body, string $charset = 'utf-8'): self
    {
        return self::text($body, 'html', $charset);
    }

    /**
     * @param string|resource $body
     */
    public static function attachment(
        mixed $body,
        string $filename,
        string $mimeType = 'application/octet-stream',
    ): self {
        return (new self())
            ->setContentType($mimeType)
            ->setDisposition('attachment', ['filename' => $filename])
            ->setBody($body);
    }

    public static function multipart(string $subType = 'mixed', Part|self ...$children): self
    {
        $builder = (new self())->setContentType('multipart/' . $subType);

        foreach ($children as $child) {
            $builder->addChild($child);
        }

        return $builder;
    }

    private function resolveBody(): string
    {
        $data = $this->body;

        if (is_resource($data)) {
            rewind($data);
            $data = stream_get_contents($data);
        }

        if ($data === '' || $this->bodyEncoding === null) {
            return $data;
        }

        return TransferDecoder::decode($data, $this->bodyEncoding);
    }

    /**
     * @return list<Part>
     */
    private function buildChildren(): array
    {
        $built = [];

        foreach ($this->children as $child) {
            $built[] = ($child instanceof self) ? $child->build() : $child;
        }

        return $built;
    }

    private function ensureBoundary(HeaderCollection $headers, array $children): HeaderCollection
    {
        if (empty($children)) {
            return $headers;
        }

        $ct = $headers->contentType();
        if ($ct === null || !$ct->isMultipart()) {
            return $headers;
        }

        if ($ct->boundary() !== null) {
            return $headers;
        }

        $params = $ct->params();
        $params['boundary'] = '=_' . bin2hex(random_bytes(12));

        return $headers->with(
            new ContentType('Content-Type', $ct->primaryType . '/' . $ct->subType, $params),
        );
    }
}
