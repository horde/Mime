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

/**
 * Strategy interface for formatting text bodies before MIME assembly.
 *
 * Implementations may apply RFC 3676 format=flowed encoding, line wrapping,
 * or any other text transformation appropriate for message composition.
 */
interface TextFormatter
{
    public function __invoke(string $text, string $charset): TextFormatResult;
}
