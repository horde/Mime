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

use ValueError;

enum TransferEncoding: string
{
    case SevenBit = '7bit';
    case EightBit = '8bit';
    case Binary = 'binary';
    case QuotedPrintable = 'quoted-printable';
    case Base64 = 'base64';

    public function isDefault(): bool
    {
        return $this === self::SevenBit;
    }

    public static function fromString(string $value): self
    {
        $normalized = strtolower(trim($value));

        return self::tryFrom($normalized)
            ?? throw new ValueError(sprintf('"%s" is not a valid transfer encoding', $value));
    }
}
