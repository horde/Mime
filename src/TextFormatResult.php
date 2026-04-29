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
 * Result DTO returned by TextFormatter implementations.
 *
 * @param string               $text   The (possibly reformatted) text.
 * @param array<string,string> $params Extra Content-Type parameters to set (e.g. format, delsp).
 */
final readonly class TextFormatResult
{
    public function __construct(
        public string $text,
        public array $params = [],
    ) {}
}
