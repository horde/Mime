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

final readonly class ContentType implements HeaderElement
{
    use ParameterizedHeader;
    use MimeHeaderTrait;

    public string $primaryType;
    public string $subType;

    /**
     * @param array<string, string> $params
     */
    public function __construct(
        private string $headerName,
        private string $baseValue,
        private array $params = [],
    ) {
        $lower = strtolower($this->baseValue);
        $slash = strpos($lower, '/');
        if ($slash !== false) {
            $this->primaryType = substr($lower, 0, $slash);
            $this->subType = substr($lower, $slash + 1);
        } else {
            $this->primaryType = $lower;
            $this->subType = '';
        }
    }

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
        return ['content-type'];
    }

    public function charset(): ?string
    {
        return $this->param('charset');
    }

    public function boundary(): ?string
    {
        return $this->param('boundary');
    }

    public function isMultipart(): bool
    {
        return $this->primaryType === 'multipart';
    }

    public function isDefault(): bool
    {
        return $this->primaryType === 'text' && $this->subType === 'plain';
    }

    public static function default(): self
    {
        return new self('Content-Type', 'application/octet-stream');
    }

    public static function fromRaw(string $headerName, string $raw): self
    {
        [$base, $params] = self::parseParameterized($raw);

        return new self($headerName, $base, $params);
    }
}
