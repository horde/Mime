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
 * Builds a MIME ID → Content-Type map from a Part tree.
 *
 * Returns an associative array mapping each part's MIME ID to its
 * full Content-Type string (e.g. ['1' => 'text/plain', '2' => 'image/png']).
 */
final class ContentTypeMap
{
    /**
     * @return array<string, string>
     */
    public function __invoke(Part $part): array
    {
        $map = [];
        $iterator = $part->iterate();

        foreach ($iterator as $id => $child) {
            $map[$id] = $child->fullType();
        }

        return $map;
    }
}
