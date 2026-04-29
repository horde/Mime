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

use Horde\Mail\Rfc2047;

final readonly class ContentDescription implements HeaderElement
{
    use SingleValueHeader;
    use MimeHeaderTrait;

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
        return [$this->headerName . ': ' . Rfc2047::encode($this->headerValue, $charset)];
    }

    public static function handles(): array
    {
        return ['content-description'];
    }
}
