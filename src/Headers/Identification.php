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

final readonly class Identification implements HeaderElement
{
    use SingleValueHeader;

    public function __construct(
        private string $headerName,
        private string $headerValue,
    ) {}

    public function name(): string
    {
        return $this->headerName;
    }

    public function sendEncode(string $charset = 'UTF-8'): array
    {
        return [$this->headerName . ': ' . $this->headerValue];
    }

    public static function handles(): array
    {
        return ['in-reply-to', 'references'];
    }

    /**
     * @return string[] Extracted message IDs from the header value.
     */
    public function ids(): array
    {
        preg_match_all('/<([^>]+)>/', $this->headerValue, $matches);

        return $matches[1];
    }
}
