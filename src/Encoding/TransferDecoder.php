<?php

/**
 * Copyright 2026 Horde LLC (http://www.horde.org/)
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

namespace Horde\Mime\Encoding;

use Horde\Mime\TransferEncoding;

final class TransferDecoder
{
    public static function decode(string $data, TransferEncoding $encoding): string
    {
        return match ($encoding) {
            TransferEncoding::Base64 => base64_decode($data, true) ?: '',
            TransferEncoding::QuotedPrintable => QuotedPrintable::decode($data),
            default => $data,
        };
    }
}
