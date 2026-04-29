<?php

/**
 * Copyright 2000-2026 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author    Anil Madhavapeddy <avsm@horde.org>
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Mime
 */

declare(strict_types=1);

namespace Horde\Mime;

/**
 * MIME type detection utility.
 * Static and stateless — maps extensions to MIME types and vice versa.
 */
final class Magic
{
    /** @var array<string, string>|null */
    private static ?array $map = null;

    /**
     * Convert a file extension to a MIME type.
     *
     * Falls back to 'x-extension/<ext>' for unknown extensions.
     */
    public static function extToMime(string $ext): string
    {
        if ($ext === '') {
            return 'application/octet-stream';
        }

        $ext = strtolower($ext);
        $map = self::loadMap();

        while (!isset($map[$ext])) {
            $pos = strpos($ext, '.');
            if ($pos === false) {
                break;
            }
            $ext = substr($ext, $pos + 1);
        }

        return $map[$ext] ?? 'x-extension/' . $ext;
    }

    /**
     * Convert a filename to a MIME type.
     */
    public static function filenameToMime(string $filename): string
    {
        $map = self::loadMap();
        $pos = strlen($filename) + 1;
        $maxPeriod = (int) ($map['__MAXPERIOD__'] ?? 1);

        for ($i = 0; $i <= $maxPeriod; ++$i) {
            $npos = strrpos(substr($filename, 0, $pos - 1), '.');
            if ($npos === false) {
                break;
            }
            $pos = $npos + 1;
        }

        if ($pos === false || $pos >= strlen($filename)) {
            return 'application/octet-stream';
        }

        $type = self::extToMime(substr($filename, $pos));

        if (str_contains($type, 'x-extension')) {
            return 'application/octet-stream';
        }

        return $type;
    }

    /**
     * Convert a MIME type to a file extension.
     */
    public static function mimeToExt(string $type): ?string
    {
        if ($type === '') {
            return null;
        }

        $type = strtolower($type);
        $map = self::loadMap();

        $key = array_search($type, $map, true);
        if ($key !== false && $key !== '__MAXPERIOD__') {
            return (string) $key;
        }

        $parts = explode('/', $type, 2);
        if (count($parts) === 2) {
            if ($parts[0] === 'x-extension') {
                return $parts[1];
            }
            if (str_starts_with($parts[1], 'x-')) {
                return substr($parts[1], 2);
            }
        }

        return null;
    }

    /**
     * Detect MIME type from a file path using ext-fileinfo.
     */
    public static function analyzeFile(string $path): ?string
    {
        if (!extension_loaded('fileinfo')) {
            return null;
        }

        $res = @finfo_open(FILEINFO_MIME);
        if ($res === false) {
            return null;
        }

        $type = finfo_file($res, $path);
        if ($type === false) {
            return null;
        }

        return self::stripParams(trim($type));
    }

    /**
     * Detect MIME type from byte content using ext-fileinfo.
     */
    public static function analyzeData(string $data): ?string
    {
        if (!extension_loaded('fileinfo')) {
            return null;
        }

        $res = @finfo_open(FILEINFO_MIME);
        if ($res === false) {
            return null;
        }

        $type = finfo_buffer($res, $data);
        if ($type === false) {
            return null;
        }

        return self::stripParams(trim($type));
    }

    /**
     * @return array<string, string>
     */
    private static function loadMap(): array
    {
        if (self::$map === null) {
            $mime_extension_map = null;
            require __DIR__ . '/../data/mime.mapping.php';
            self::$map = $mime_extension_map;
        }

        return self::$map;
    }

    private static function stripParams(string $type): ?string
    {
        foreach ([';', ',', "\0"] as $separator) {
            $pos = strpos($type, $separator);
            if ($pos !== false) {
                $type = rtrim(substr($type, 0, $pos));
            }
        }

        if (preg_match('|^[a-z0-9]+/[.+a-z0-9-]+$|i', $type)) {
            return $type;
        }

        return null;
    }
}
