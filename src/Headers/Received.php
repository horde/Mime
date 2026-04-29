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

final readonly class Received implements HeaderElement
{
    use MultiValueHeader;

    /**
     * @param string[] $headerValues
     */
    public function __construct(
        private string $headerName,
        private array $headerValues = [],
    ) {}

    public function name(): string
    {
        return $this->headerName;
    }

    public function sendEncode(string $charset = 'UTF-8'): array
    {
        return array_map(
            fn(string $v) => $this->headerName . ': ' . $v,
            $this->headerValues,
        );
    }

    public static function handles(): array
    {
        return ['received'];
    }

    public function withValue(string $value): self
    {
        return new self($this->headerName, [...$this->headerValues, $value]);
    }
}
