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

final class TransferEncoder
{
    public static function encode(
        string $data,
        TransferEncoding $encoding,
        string $eol = "\r\n",
    ): string {
        return match ($encoding) {
            TransferEncoding::Base64 => rtrim(chunk_split(base64_encode($data), 76, $eol)),
            TransferEncoding::QuotedPrintable => QuotedPrintable::encode($data, $eol),
            default => $data,
        };
    }
}
