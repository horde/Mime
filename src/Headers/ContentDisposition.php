<?php

/**
 * Copyright 2012-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Mime
 */

declare(strict_types=1);

namespace Horde\Mime\Headers;

final readonly class ContentDisposition implements HeaderElement
{
    use ParameterizedHeader;
    use MimeHeaderTrait;

    /**
     * @param array<string, string> $params
     */
    public function __construct(
        private string $headerName,
        private string $baseValue,
        private array $params = [],
    ) {}

    public function name(): string
    {
        return $this->headerName;
    }

    public function sendEncode(string $charset = 'UTF-8'): array
    {
        return [$this->headerName . ': ' . $this->encodedValue($charset)];
    }

    public static function handles(): array
    {
        return ['content-disposition'];
    }

    public function isInline(): bool
    {
        return strtolower($this->baseValue) === 'inline';
    }

    public function isAttachment(): bool
    {
        return strtolower($this->baseValue) === 'attachment';
    }

    public function isDefault(): bool
    {
        return $this->baseValue === '';
    }

    public function filename(): ?string
    {
        return $this->param('filename');
    }

    public static function fromRaw(string $headerName, string $raw): self
    {
        [$base, $params] = self::parseParameterized($raw);

        return new self($headerName, $base, $params);
    }
}
