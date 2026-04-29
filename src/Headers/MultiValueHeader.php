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

trait MultiValueHeader
{
    public function value(): string
    {
        return implode(', ', $this->headerValues);
    }

    public function __toString(): string
    {
        return $this->value();
    }

    /**
     * @return string[]
     */
    public function values(): array
    {
        return $this->headerValues;
    }
}
