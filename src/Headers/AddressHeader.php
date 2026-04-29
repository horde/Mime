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

use Horde\Mail\Rfc822\AddressList;
use Horde\Mail\Rfc822\Rfc822Parser;

trait AddressHeader
{
    public function addressList(): AddressList
    {
        $parser = new Rfc822Parser();

        return $parser->parseAddressList($this->value());
    }
}
