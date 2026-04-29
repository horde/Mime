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

use Horde\Mime\Encoding\EncodingDetector;
use Horde\Mime\Encoding\TransferEncoder;
use Horde\Mime\Headers\ContentTransferEncoding;
use Horde\Mime\Headers\ContentType;
use Horde\Mime\Headers\HeaderCollection;
use Horde\Mime\Headers\MimeVersion;

/**
 * Serializes an immutable Part tree to RFC 2822/2045 format.
 *
 * This class handles rendering only — it never calls a Transport.
 * The caller is responsible for passing the rendered output to a
 * Mail Transport.
 */
final class MessageRenderer
{
    private const RFC_EOL = "\r\n";
    private const PREAMBLE = 'This is a MIME message.';

    /**
     * Render a complete RFC 2822 message (headers + body).
     */
    public static function render(
        Part $part,
        HeaderCollection $messageHeaders,
        string $eol = self::RFC_EOL,
        int $encodingMask = EncodingDetector::ALLOW_7BIT,
    ): string {
        $headers = self::renderHeaders($part, $messageHeaders, $eol, $encodingMask);
        $body = self::renderBody($part, $eol, $encodingMask);

        return $headers . $eol . $body;
    }

    /**
     * Render only the MIME body (no message-level headers).
     * This is what gets passed to a Transport's $body parameter.
     */
    public static function renderBody(
        Part $part,
        string $eol = self::RFC_EOL,
        int $encodingMask = EncodingDetector::ALLOW_7BIT,
    ): string {
        if ($part->isMultipart()) {
            return self::renderMultipartBody($part, $eol, $encodingMask);
        }

        return self::renderLeafBody($part, $eol, $encodingMask);
    }

    /**
     * Render message-level headers as a string.
     * Ensures MIME-Version and Content-Type are present.
     */
    public static function renderHeaders(
        Part $part,
        HeaderCollection $messageHeaders,
        string $eol = self::RFC_EOL,
        int $encodingMask = EncodingDetector::ALLOW_7BIT,
    ): string {
        $headers = self::prepareMessageHeaders($part, $messageHeaders, $encodingMask);

        return $headers->toString($eol) . $eol;
    }

    /**
     * Export headers as array suitable for Transport::send().
     *
     * @return array<string, string|string[]>
     */
    public static function headersToArray(
        Part $part,
        HeaderCollection $messageHeaders,
        int $encodingMask = EncodingDetector::ALLOW_7BIT,
    ): array {
        $headers = self::prepareMessageHeaders($part, $messageHeaders, $encodingMask);

        return $headers->toArray();
    }

    /**
     * Prepare message-level headers: add MIME-Version, Content-Type from
     * root Part, and Content-Transfer-Encoding for leaf roots.
     */
    private static function prepareMessageHeaders(
        Part $part,
        HeaderCollection $messageHeaders,
        int $encodingMask,
    ): HeaderCollection {
        $headers = $messageHeaders;

        if (!$headers->has('mime-version')) {
            $headers = $headers->with(new MimeVersion('MIME-Version', '1.0'));
        }

        $ct = $part->headers->contentType();
        if ($ct !== null) {
            $headers = $headers->with($ct);
        } else {
            $headers = $headers->with(ContentType::default());
        }

        if (!$part->isMultipart()) {
            $encoding = self::resolveEncoding($part, $encodingMask);
            $headers = $headers->with(
                new ContentTransferEncoding('Content-Transfer-Encoding', $encoding->value),
            );
        }

        return $headers;
    }

    /**
     * Render a multipart body with boundaries.
     */
    private static function renderMultipartBody(
        Part $part,
        string $eol,
        int $encodingMask,
    ): string {
        $boundary = $part->boundary();
        if ($boundary === null) {
            throw new MimeException('Multipart MIME part is missing a boundary parameter.');
        }

        $output = self::PREAMBLE . $eol;

        foreach ($part->children as $child) {
            $output .= $eol . '--' . $boundary . $eol;
            $output .= self::renderPart($child, $eol, $encodingMask);
        }

        $output .= $eol . '--' . $boundary . '--' . $eol;

        return $output;
    }

    /**
     * Render a leaf part body (encode content).
     */
    private static function renderLeafBody(
        Part $part,
        string $eol,
        int $encodingMask,
    ): string {
        $encoding = self::resolveEncoding($part, $encodingMask);

        return TransferEncoder::encode($part->bodyString(), $encoding, $eol);
    }

    /**
     * Render a single Part (headers + body) for inclusion in a multipart message.
     */
    private static function renderPart(
        Part $part,
        string $eol,
        int $encodingMask,
    ): string {
        $headers = $part->headers;

        if ($part->isMultipart()) {
            $headerStr = $headers->toString($eol);
            $body = self::renderMultipartBody($part, $eol, $encodingMask);

            return $headerStr . $eol . $eol . $body;
        }

        $encoding = self::resolveEncoding($part, $encodingMask);

        $headers = $headers->with(
            new ContentTransferEncoding('Content-Transfer-Encoding', $encoding->value),
        );

        $headerStr = $headers->toString($eol);
        $body = TransferEncoder::encode($part->bodyString(), $encoding, $eol);

        return $headerStr . $eol . $eol . $body;
    }

    /**
     * Determine the transfer encoding for a Part.
     *
     * If the Part has an explicit Content-Transfer-Encoding header, use it.
     * Otherwise, detect from body content and recommend based on constraints.
     */
    private static function resolveEncoding(
        Part $part,
        int $encodingMask,
    ): TransferEncoding {
        $explicit = $part->headers->contentTransferEncoding();
        if ($explicit !== null) {
            return $explicit->encoding;
        }

        $detected = EncodingDetector::detect($part->bodyString());

        return EncodingDetector::recommend($detected, $part->primaryType(), $encodingMask);
    }
}
