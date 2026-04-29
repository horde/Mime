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
 * Locates the "primary body" MIME ID within a Part tree.
 *
 * Heuristic: the first text/* leaf at depth 1 (or any depth if root has
 * no MIME ID) that is not an attachment.
 */
final class BodyFinder
{
    /**
     * Find the MIME ID of the primary body part.
     *
     * @param Part        $part    Root part to search.
     * @param string|null $subtype Restrict to a specific text subtype (e.g. 'plain', 'html').
     * @return string|null MIME ID of the body part, or null if not found.
     */
    public function __invoke(Part $part, ?string $subtype = null): ?string
    {
        if ($part->isLeaf() && !$part->isMultipart()) {
            if ($part->primaryType() !== 'text') {
                return null;
            }
            if ($subtype !== null && $part->subType() !== $subtype) {
                return null;
            }
            $disp = $part->contentDisposition();
            if ($disp !== null && $disp->isAttachment()) {
                return null;
            }

            return '1';
        }

        $iterator = $part->iterate(false);

        foreach ($iterator as $id => $child) {
            if ($child->primaryType() !== 'text') {
                continue;
            }

            if ($subtype !== null && $child->subType() !== $subtype) {
                continue;
            }

            $disp = $child->contentDisposition();
            if ($disp !== null && $disp->isAttachment()) {
                continue;
            }

            return $id;
        }

        return null;
    }
}
