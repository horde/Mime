<?php

/**
 * Copyright 2015-2026 Horde LLC (http://www.horde.org/)
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

use Countable;
use Iterator;

/**
 * Depth-first iterator over an immutable Part tree.
 * Computes MIME IDs from tree position during traversal.
 *
 * @implements Iterator<string, Part>
 */
final class PartIterator implements Iterator, Countable
{
    /** @var list<array{Part, int, string}> Stack: [parent, nextChildIndex, idPrefix] */
    private array $stack = [];

    private ?Part $current = null;
    private ?string $currentKey = null;

    public function __construct(
        private readonly Part $root,
        private readonly bool $includeSelf = true,
    ) {}

    public function current(): ?Part
    {
        return $this->current;
    }

    public function key(): ?string
    {
        return $this->currentKey;
    }

    public function next(): void
    {
        if ($this->current === null) {
            return;
        }

        $part = $this->current;
        $children = $part->children;

        if (!empty($children)) {
            if ($part->primaryType() === 'message' && $part->subType() === 'rfc822') {
                $this->pushRfc822Children($part, $this->currentKey);
            } else {
                $this->pushRegularChildren($part, $this->currentKey);
            }
        }

        $this->advance();
    }

    public function rewind(): void
    {
        $this->stack = [];
        $this->current = null;
        $this->currentKey = null;

        if ($this->root->isLeaf() && !$this->root->isMultipart()) {
            if ($this->includeSelf) {
                $this->current = $this->root;
                $this->currentKey = '1';
            }
            return;
        }

        if ($this->includeSelf) {
            $this->current = $this->root;

            if ($this->root->primaryType() === 'message' && $this->root->subType() === 'rfc822') {
                $this->currentKey = '1.0';
            } else {
                $this->currentKey = '0';
            }

            return;
        }

        if ($this->root->primaryType() === 'message' && $this->root->subType() === 'rfc822') {
            $this->pushRfc822Children($this->root, '1.0');
        } else {
            $this->pushRegularChildren($this->root, '');
        }

        $this->advance();
    }

    public function valid(): bool
    {
        return $this->current !== null;
    }

    public function count(): int
    {
        $count = 0;
        foreach ($this as $_) {
            ++$count;
        }
        $this->rewind();

        return $count;
    }

    public function currentId(): ?MimeId
    {
        return $this->currentKey !== null ? new MimeId($this->currentKey) : null;
    }

    private function pushRegularChildren(Part $parent, string $parentId): void
    {
        $prefix = ($parentId === '' || $parentId === '0') ? '' : $parentId . '.';

        $this->stack[] = [$parent, 0, $prefix];
    }

    private function pushRfc822Children(Part $parent, string $parentId): void
    {
        $children = $parent->children;
        if (empty($children)) {
            return;
        }

        $child = $children[0];
        $baseId = rtrim($parentId, '0');
        if ($baseId !== '' && str_ends_with($baseId, '.')) {
            $baseId = substr($baseId, 0, -1);
        }

        if (empty($child->children) && $child->primaryType() !== 'multipart') {
            $this->stack[] = [$parent, 0, $baseId . ($baseId !== '' ? '.' : '')];
        } else {
            $this->stack[] = [$parent, 0, $baseId . ($baseId !== '' ? '.' : '')];
        }
    }

    private function advance(): void
    {
        while (!empty($this->stack)) {
            $top = &$this->stack[count($this->stack) - 1];
            $parent = $top[0];
            $index = $top[1];
            $prefix = $top[2];

            if ($index < $parent->childCount()) {
                $top[1]++;
                $child = $parent->child($index);
                $childId = $prefix . ($index + 1);

                if ($child->primaryType() === 'message' && $child->subType() === 'rfc822' && !$child->isLeaf()) {
                    $this->current = $child;
                    $this->currentKey = $childId;
                    return;
                }

                if ($child->isMultipart()) {
                    $this->current = $child;
                    $this->currentKey = $childId;
                    return;
                }

                $this->current = $child;
                $this->currentKey = $childId;
                return;
            }

            array_pop($this->stack);
        }

        $this->current = null;
        $this->currentKey = null;
    }
}
