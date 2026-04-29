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
 * Configuration DTO for the MIME parser.
 */
final readonly class MimeParserConfig
{
    public function __construct(
        /** Treat input as MIME even without MIME-Version header. */
        public bool $forceMime = false,
        /** Skip loading body content; only populate sizeHint. */
        public bool $noBody = false,
    ) {}
}
