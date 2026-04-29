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

use Horde\Mime\TransferEncoding;

final readonly class ContentTransferEncoding implements HeaderElement
{
    use SingleValueHeader;
    use MimeHeaderTrait;

    public TransferEncoding $encoding;

    public function __construct(
        private string $headerName,
        private string $headerValue,
    ) {
        $lower = strtolower(trim($this->headerValue));
        $this->encoding = TransferEncoding::tryFrom($lower) ?? TransferEncoding::SevenBit;
    }

    public function name(): string
    {
        return $this->headerName;
    }

    public function sendEncode(string $charset = 'UTF-8'): array
    {
        return [$this->headerName . ': ' . $this->encoding->value];
    }

    public function isDefault(): bool
    {
        return $this->encoding->isDefault();
    }

    public static function handles(): array
    {
        return ['content-transfer-encoding'];
    }
}
