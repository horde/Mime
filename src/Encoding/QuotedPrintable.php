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

final class QuotedPrintable
{
    public static function decode(string $data): string
    {
        return quoted_printable_decode($data);
    }

    public static function encode(
        string $text,
        string $eol = "\n",
        int $lineLength = 76,
    ): string {
        $fp = fopen('php://temp', 'r+');
        stream_filter_append(
            $fp,
            'convert.quoted-printable-encode',
            STREAM_FILTER_WRITE,
            [
                'line-break-chars' => $eol,
                'line-length' => $lineLength,
            ],
        );
        fwrite($fp, $text);
        rewind($fp);
        $out = stream_get_contents($fp);
        fclose($fp);

        return $out;
    }
}
