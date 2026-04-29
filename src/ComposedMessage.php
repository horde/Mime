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

use Horde\Mail\Rfc822\AddressList;
use Horde\Mime\Headers\HeaderCollection;

/**
 * Value object returned by MessageBuilder.
 * Contains everything needed to render and/or send a message.
 */
final readonly class ComposedMessage
{
    public function __construct(
        /** The root MIME part (body tree). */
        public Part $part,
        /** Message-level headers (From, To, Subject, Date, Message-ID, MIME-Version, etc.). */
        public HeaderCollection $headers,
        /** Complete recipient list (To + Cc + Bcc, deduplicated). */
        public AddressList $recipients,
    ) {}
}
