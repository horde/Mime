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

use Horde\Mime\Encoding\TransferDecoder;
use Horde\Mime\Headers\HeaderCollection;

/**
 * Parses raw RFC 2822/2045 message text into an immutable Part tree.
 *
 * This is the inverse of MessageRenderer.
 */
final class MimeParser
{
    public const NESTING_LIMIT = 100;

    /**
     * Parse a raw RFC 2822 message into a Part tree.
     */
    public static function parse(string $text, ?MimeParserConfig $config = null): Part
    {
        $config ??= new MimeParserConfig();
        $text = self::normalizeEol($text);

        $headerEnd = self::findHeaderEnd($text);
        $headerText = substr($text, 0, $headerEnd);
        $body = ($headerEnd < strlen($text))
            ? substr($text, $headerEnd + 2)
            : '';

        return self::getStructure($headerText, $body, $config, 0);
    }

    /**
     * Extract raw header or body text of a specific MIME part by ID.
     *
     * @param string $text  Full MIME message.
     * @param string $type  'header' or 'body'.
     * @param string $id    MIME ID (e.g. '0', '1', '2.1').
     */
    public static function getRawPartText(string $text, string $type, string $id): ?string
    {
        $text = self::normalizeEol($text);
        $headerEnd = self::findHeaderEnd($text);

        if ($id === '0') {
            if ($type === 'header') {
                return self::restoreEol(substr($text, 0, $headerEnd));
            }

            return self::restoreEol(
                ($headerEnd < strlen($text))
                    ? substr($text, $headerEnd + 2)
                    : '',
            );
        }

        $headerText = substr($text, 0, $headerEnd);
        $body = ($headerEnd < strlen($text))
            ? substr($text, $headerEnd + 2)
            : '';

        $headers = HeaderCollection::parse($headerText);
        $ct = $headers->contentType();

        if ($ct !== null && strtolower($ct->primaryType) === 'message' && strtolower($ct->subType) === 'rfc822') {
            return self::getRawPartText($body, $type, $id);
        }

        $boundary = $ct?->boundary();
        if ($boundary === null) {
            return null;
        }

        return self::navigateToId($body, $boundary, $id, $type);
    }

    private static function getStructure(
        string $headerText,
        string $body,
        MimeParserConfig $config,
        int $level,
    ): Part {
        $headers = HeaderCollection::parse($headerText);

        if (!$config->forceMime && !$headers->has('mime-version') && $level === 0) {
            return self::buildLeafPart($headers, $body, $config, 'text/plain');
        }

        $ct = $headers->contentType();
        $primaryType = $ct !== null ? strtolower($ct->primaryType) : 'text';
        $subType = $ct !== null ? strtolower($ct->subType) : 'plain';

        if ($primaryType === 'multipart') {
            return self::parseMultipart($headers, $body, $config, $level, $subType);
        }

        if ($primaryType === 'message' && $subType === 'rfc822') {
            return self::parseMessageRfc822($headers, $body, $config, $level);
        }

        return self::buildLeafPart($headers, $body, $config);
    }

    private static function parseMultipart(
        HeaderCollection $headers,
        string $body,
        MimeParserConfig $config,
        int $level,
        string $subType,
    ): Part {
        $ct = $headers->contentType();
        $boundary = $ct?->boundary();

        if ($boundary === null) {
            return self::buildLeafPart($headers, $body, $config);
        }

        if ($level >= self::NESTING_LIMIT) {
            return self::buildLeafPart($headers, $body, $config);
        }

        $segments = self::findBoundaries($body, 0, $boundary);

        $defaultType = ($subType === 'digest') ? 'message/rfc822' : 'text/plain';
        $children = [];

        foreach ($segments as $segment) {
            $segText = substr($body, $segment['start'], $segment['length']);
            $segHeaderEnd = self::findHeaderEnd($segText);
            $segHeader = substr($segText, 0, $segHeaderEnd);
            $segBody = ($segHeaderEnd < strlen($segText))
                ? substr($segText, $segHeaderEnd + 2)
                : '';

            $childHeaders = HeaderCollection::parse($segHeader);

            if (!$childHeaders->has('content-type')) {
                $childHeaders = $childHeaders->withRaw('Content-Type', $defaultType);
            }

            $childConfig = new MimeParserConfig(forceMime: true, noBody: $config->noBody);
            $children[] = self::getStructure($segHeader, $segBody, $childConfig, $level + 1);
        }

        return new Part(
            headers: $headers,
            children: $children,
        );
    }

    private static function parseMessageRfc822(
        HeaderCollection $headers,
        string $body,
        MimeParserConfig $config,
        int $level,
    ): Part {
        if ($level >= self::NESTING_LIMIT) {
            return self::buildLeafPart($headers, $body, $config);
        }

        $innerConfig = new MimeParserConfig(forceMime: $config->forceMime, noBody: $config->noBody);
        $child = self::parse($body, $innerConfig);

        return new Part(
            headers: $headers,
            children: [$child],
        );
    }

    private static function buildLeafPart(
        HeaderCollection $headers,
        string $body,
        MimeParserConfig $config,
        ?string $defaultType = null,
    ): Part {
        if ($defaultType !== null && !$headers->has('content-type')) {
            $headers = $headers->withRaw('Content-Type', $defaultType);
        }

        if ($config->noBody) {
            return new Part(
                headers: $headers,
                sizeHint: strlen($body),
            );
        }

        $cte = $headers->contentTransferEncoding();
        $encoding = $cte !== null ? $cte->encoding : TransferEncoding::SevenBit;
        $decoded = TransferDecoder::decode($body, $encoding);

        return new Part(
            headers: $headers,
            body: $decoded,
        );
    }

    /**
     * Find all boundary segments within text.
     *
     * @return list<array{start: int, length: int}>
     */
    private static function findBoundaries(string $text, int $pos, string $boundary): array
    {
        $marker = '--' . $boundary;
        $markerLen = strlen($marker);
        $textLen = strlen($text);
        $segments = [];
        $starts = [];

        $searchPos = $pos;
        while (($found = strpos($text, $marker, $searchPos)) !== false) {
            if ($found !== 0 && $text[$found - 1] !== "\n") {
                $searchPos = $found + $markerLen;
                continue;
            }

            $afterMarker = $found + $markerLen;

            if ($afterMarker < $textLen && $text[$afterMarker] === '-'
                && ($afterMarker + 1) < $textLen && $text[$afterMarker + 1] === '-') {
                $starts[] = ['pos' => $found, 'end' => true];
                break;
            }

            $contentStart = $afterMarker;
            if ($contentStart < $textLen && $text[$contentStart] === "\r") {
                $contentStart++;
            }
            if ($contentStart < $textLen && $text[$contentStart] === "\n") {
                $contentStart++;
            }

            $starts[] = ['pos' => $found, 'start' => $contentStart, 'end' => false];
            $searchPos = $contentStart;
        }

        for ($i = 0, $count = count($starts); $i < $count; $i++) {
            if ($starts[$i]['end']) {
                break;
            }

            $segStart = $starts[$i]['start'];

            if ($i + 1 < $count) {
                $segLength = $starts[$i + 1]['pos'] - $segStart;
                if ($segLength > 0 && $text[$segStart + $segLength - 1] === "\n") {
                    $segLength--;
                }
                if ($segLength > 0 && $text[$segStart + $segLength - 1] === "\r") {
                    $segLength--;
                }
            } else {
                $segLength = $textLen - $segStart;
            }

            if ($segLength > 0) {
                $segments[] = ['start' => $segStart, 'length' => $segLength];
            }
        }

        return $segments;
    }

    private static function findHeaderEnd(string $text): int
    {
        $pos = strpos($text, "\n\n");
        if ($pos !== false) {
            return $pos;
        }

        return strlen($text);
    }

    private static function normalizeEol(string $text): string
    {
        return str_replace("\r\n", "\n", $text);
    }

    private static function restoreEol(string $text): string
    {
        $text = str_replace("\r\n", "\n", $text);

        return str_replace("\n", "\r\n", $text);
    }

    /**
     * Navigate multipart boundaries to find a specific MIME ID.
     */
    private static function navigateToId(
        string $body,
        string $boundary,
        string $id,
        string $type,
    ): ?string {
        $parts = explode('.', $id);
        $index = (int) array_shift($parts) - 1;
        $remainingId = implode('.', $parts);

        $segments = self::findBoundaries($body, 0, $boundary);
        if (!isset($segments[$index])) {
            return null;
        }

        $segText = substr($body, $segments[$index]['start'], $segments[$index]['length']);

        if ($remainingId === '') {
            $headerEnd = self::findHeaderEnd($segText);
            if ($type === 'header') {
                return self::restoreEol(substr($segText, 0, $headerEnd));
            }

            return self::restoreEol(
                ($headerEnd < strlen($segText))
                    ? substr($segText, $headerEnd + 2)
                    : '',
            );
        }

        $headerEnd = self::findHeaderEnd($segText);
        $segHeader = substr($segText, 0, $headerEnd);
        $segBody = ($headerEnd < strlen($segText))
            ? substr($segText, $headerEnd + 2)
            : '';

        $headers = HeaderCollection::parse($segHeader);
        $ct = $headers->contentType();

        if ($ct !== null && strtolower($ct->primaryType) === 'message' && strtolower($ct->subType) === 'rfc822') {
            return self::getRawPartText($segBody, $type, $remainingId);
        }

        $childBoundary = $ct?->boundary();
        if ($childBoundary === null) {
            return null;
        }

        return self::navigateToId($segBody, $childBoundary, $remainingId, $type);
    }
}
