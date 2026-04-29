<?php

/**
 * Copyright 1999-2026 Horde LLC (http://www.horde.org/)
 *
 * This file contains code adapted from PEAR's PHP_Compat library (v1.6.0a3).
 *   http://pear.php.net/package/PHP_Compat
 * This code was released under the LGPL 2.1
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author    Chuck Hagenbuch <chuck@horde.org>
 * @author    Aidan Lister <aidan@php.net>
 * @author    Michael Slusarz <slusarz@horde.org>
 * @author    Michael Wallner <mike@php.net>
 * @category  Horde
 * @copyright 2004-2007 Aidan Lister <aidan@php.net>, Arpad Ray <arpad@php.net>
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Mime
 */

declare(strict_types=1);

namespace Horde\Mime\Encoding;

use ArrayIterator;
use Countable;
use IteratorAggregate;
use Traversable;

/**
 * @implements IteratorAggregate<int, UudecodeEntry>
 */
final readonly class Uudecode implements Countable, IteratorAggregate
{
    public const REGEX = "/begin ([0-7]{3}) (.+)\r?\n(.+)\r?\nend/Us";

    /** @var list<UudecodeEntry> */
    public array $entries;

    public function __construct(string $input)
    {
        $entries = [];

        if (preg_match_all(self::REGEX, $input, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $v) {
                $entries[] = new UudecodeEntry(
                    self::uudecode($v[3]),
                    $v[2],
                    $v[1],
                );
            }
        }

        $this->entries = $entries;
    }

    public function first(): ?UudecodeEntry
    {
        return $this->entries[0] ?? null;
    }

    public function count(): int
    {
        return count($this->entries);
    }

    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->entries);
    }

    private static function uudecode(string $input): string
    {
        $decoded = '';

        foreach (explode("\n", $input) as $line) {
            $c = count($bytes = unpack('c*', substr(trim($line, "\r\n\t"), 1)));

            while ($c % 4) {
                $bytes[++$c] = 0;
            }

            foreach (array_chunk($bytes, 4) as $b) {
                $b0 = ($b[0] === 0x60) ? 0 : $b[0] - 0x20;
                $b1 = ($b[1] === 0x60) ? 0 : $b[1] - 0x20;
                $b2 = ($b[2] === 0x60) ? 0 : $b[2] - 0x20;
                $b3 = ($b[3] === 0x60) ? 0 : $b[3] - 0x20;

                $b0 <<= 2;
                $b0 |= ($b1 >> 4) & 0x03;
                $b1 <<= 4;
                $b1 |= ($b2 >> 2) & 0x0F;
                $b2 <<= 6;
                $b2 |= $b3 & 0x3F;

                $decoded .= pack('c*', $b0, $b1, $b2);
            }
        }

        return rtrim($decoded, "\0");
    }
}
