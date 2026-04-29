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

namespace Horde\Mime;

use Stringable;

final readonly class MimeId implements Stringable
{
    public function __construct(
        public string $id,
    ) {}

    public function down(bool $noRfc822 = false): ?self
    {
        return $this->idArithmetic($this->id, 'down', $noRfc822);
    }

    public function next(): ?self
    {
        return $this->idArithmetic($this->id, 'next', false);
    }

    public function prev(): ?self
    {
        return $this->idArithmetic($this->id, 'prev', false);
    }

    public function up(bool $noRfc822 = false): ?self
    {
        return $this->idArithmetic($this->id, 'up', $noRfc822);
    }

    public function isChild(self|string $other): bool
    {
        $otherId = ($other instanceof self) ? $other->id : $other;

        return str_starts_with($otherId . '.', $this->id . '.');
    }

    public function __toString(): string
    {
        return $this->id;
    }

    private function idArithmetic(string $id, string $action, bool $noRfc822): ?self
    {
        $pos = strrpos($id, '.');
        $end = ($pos === false) ? $id : substr($id, $pos + 1);

        switch ($action) {
            case 'down':
                if ($end === '0') {
                    if ($noRfc822) {
                        return null;
                    }
                    $result = $id . '.1';
                } else {
                    $result = $noRfc822
                        ? ($id . '.1')
                        : ($id . '.0');
                }
                return new self($result);

            case 'next':
                ++$end;
                $result = ($pos === false) ? (string) $end : (substr($id, 0, $pos + 1) . $end);
                return new self($result);

            case 'prev':
                $endInt = (int) $end;
                if ($endInt <= 0) {
                    return null;
                }
                --$endInt;
                if ($endInt <= 0) {
                    return null;
                }
                $result = ($pos === false) ? (string) $endInt : (substr($id, 0, $pos + 1) . $endInt);
                return new self($result);

            case 'up':
                if ($pos === false) {
                    return ($end === '0') ? null : new self('0');
                }
                $parent = substr($id, 0, $pos);
                if (!$noRfc822 && $end === '0') {
                    return null;
                }
                if ($end === '1') {
                    $parentEnd = strrpos($parent, '.');
                    $parentLast = ($parentEnd === false) ? $parent : substr($parent, $parentEnd + 1);
                    if ($parentLast === '0') {
                        return new self($parent);
                    }
                }
                return new self($parent);
        }

        return null;
    }
}
