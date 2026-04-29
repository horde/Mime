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

use ArrayIterator;
use IteratorAggregate;
use Traversable;

/**
 * Parses multipart/related structures (RFC 2387).
 * Maps Content-ID values to child Part indices.
 */
final class Related implements IteratorAggregate
{
    /** @var array<int, string>  childIndex => CID */
    private array $cids = [];

    private int $startIndex;

    /**
     * @param Part $part  Must be multipart/related.
     * @throws MimeException  If part is not multipart/related.
     */
    public function __construct(private readonly Part $part)
    {
        if ($part->fullType() !== 'multipart/related') {
            throw new MimeException('Part must be of type multipart/related.');
        }

        foreach ($part->children as $index => $child) {
            $cid = $child->contentId();
            if ($cid !== null) {
                $this->cids[$index] = $cid;
            }
        }

        $startCid = $part->contentType()->param('start');
        if ($startCid !== null) {
            $startCid = trim($startCid, '<> ');
            $found = $this->cidSearch($startCid);
            $this->startIndex = $found ?? 0;
        } else {
            $this->startIndex = 0;
        }
    }

    /**
     * Return the child index of the "start" part (the root document).
     */
    public function startIndex(): int
    {
        return $this->startIndex;
    }

    /**
     * Return the start Part.
     */
    public function startPart(): Part
    {
        return $this->part->children[$this->startIndex];
    }

    /**
     * Search for a CID, return the child index or null.
     */
    public function cidSearch(string $cid): ?int
    {
        $index = array_search($cid, $this->cids, true);

        return $index !== false ? $index : null;
    }

    /**
     * Return the Part for a given CID, or null.
     */
    public function cidPart(string $cid): ?Part
    {
        $index = $this->cidSearch($cid);
        if ($index === null) {
            return null;
        }

        return $this->part->children[$index] ?? null;
    }

    /**
     * Replace cid: references in HTML content.
     *
     * @param string   $html      HTML content.
     * @param callable $callback  fn(int $childIndex, string $cid, string $attribute): string
     *                            Returns the replacement URL.
     *
     * @return string  Modified HTML.
     */
    public function cidReplace(string $html, callable $callback): string
    {
        return (string) preg_replace_callback(
            '/\b(src|background)\s*=\s*(["\'])cid:([^"\']+)\2/i',
            function (array $matches) use ($callback): string {
                $attribute = strtolower($matches[1]);
                $cid = $matches[2];
                $cidValue = $matches[3];
                $quote = $matches[2];

                $index = $this->cidSearch($cidValue);
                if ($index === null) {
                    return $matches[0];
                }

                $url = $callback($index, $cidValue, $attribute);

                return $attribute . '=' . $quote . $url . $quote;
            },
            $html,
        );
    }

    /**
     * Iterate over CID mappings (childIndex => cid).
     *
     * @return Traversable<int, string>
     */
    public function getIterator(): Traversable
    {
        return new ArrayIterator($this->cids);
    }
}
