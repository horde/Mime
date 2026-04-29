<?php

/**
 * Copyright 2014-2026 Horde LLC (http://www.horde.org/)
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

final class EncodingDetector
{
    public const ALLOW_7BIT = 1;
    public const ALLOW_8BIT = 2;
    public const ALLOW_BINARY = 4;

    public static function detect(string $data): TransferEncoding
    {
        if ($data === '') {
            return TransferEncoding::SevenBit;
        }

        if (str_contains($data, "\0")) {
            return TransferEncoding::Binary;
        }

        $is8bit = false;
        $lineLength = 0;

        for ($i = 0, $len = strlen($data); $i < $len; ++$i) {
            $ord = ord($data[$i]);

            if ($ord === 10 || $ord === 13) {
                $lineLength = 0;
                continue;
            }

            if (++$lineLength > 998) {
                return TransferEncoding::Binary;
            }

            if ($ord > 127) {
                $is8bit = true;
            }
        }

        return $is8bit ? TransferEncoding::EightBit : TransferEncoding::SevenBit;
    }

    public static function recommend(
        TransferEncoding $detected,
        string $primaryType,
        int $allowedMask = self::ALLOW_7BIT,
    ): TransferEncoding {
        if ($detected === TransferEncoding::Binary) {
            if ($allowedMask & self::ALLOW_BINARY) {
                return TransferEncoding::Binary;
            }

            return TransferEncoding::Base64;
        }

        if ($detected === TransferEncoding::EightBit) {
            if ($allowedMask & self::ALLOW_8BIT) {
                return TransferEncoding::EightBit;
            }

            if ($primaryType === 'text') {
                return TransferEncoding::QuotedPrintable;
            }

            return TransferEncoding::Base64;
        }

        return TransferEncoding::SevenBit;
    }
}
