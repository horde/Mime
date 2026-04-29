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

use Horde\Text\Flowed\TextFlowed;

/**
 * Applies RFC 3676 format=flowed encoding using Horde\Text\Flowed\TextFlowed.
 *
 * Falls back to a no-op (returns text unchanged) if the TextFlowed class
 * is not available at runtime.
 */
final class FlowedFormatter implements TextFormatter
{
    public function __invoke(string $text, string $charset): TextFormatResult
    {
        if (!class_exists(TextFlowed::class)) {
            return new TextFormatResult($text);
        }

        $flowed = new TextFlowed($text, $charset);
        $flowed->setDelSp(true);

        return new TextFormatResult(
            text: $flowed->toFlowed(),
            params: ['format' => 'flowed', 'delsp' => 'yes'],
        );
    }
}
