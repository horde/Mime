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

namespace Horde\Mime\Headers;

use Stringable;

interface HeaderElement extends Stringable
{
    public function name(): string;

    public function value(): string;

    /**
     * @return string[] RFC-encoded header lines ready for transport.
     */
    public function sendEncode(string $charset = 'UTF-8'): array;

    /**
     * @return string[] Lowercase header names this class handles.
     */
    public static function handles(): array;
}
