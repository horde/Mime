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

namespace Horde\Mime\Headers;

trait ParameterizedHeader
{
    public function value(): string
    {
        if (empty($this->params)) {
            return $this->baseValue;
        }

        $parts = [$this->baseValue];
        foreach ($this->params as $key => $val) {
            $parts[] = $key . '=' . self::quoteParamValue($val);
        }

        return implode('; ', $parts);
    }

    /**
     * Encode parameters per RFC 2231 for wire transmission.
     * Handles non-ASCII values (charset encoding) and long values (continuation).
     *
     * @return string The base value followed by encoded parameters.
     */
    public function encodedValue(string $charset = 'UTF-8'): string
    {
        if (empty($this->params)) {
            return $this->baseValue;
        }

        $encoded = [];
        foreach ($this->params as $name => $val) {
            $encoded = array_merge($encoded, self::encodeRfc2231Param($name, $val, $charset));
        }

        $parts = [$this->baseValue];
        foreach ($encoded as $key => $val) {
            $parts[] = $key . '=' . $val;
        }

        return implode('; ', $parts);
    }

    public function __toString(): string
    {
        return $this->value();
    }

    public function param(string $name): ?string
    {
        $lower = strtolower($name);
        foreach ($this->params as $key => $val) {
            if (strtolower($key) === $lower) {
                return $val;
            }
        }

        return null;
    }

    /**
     * @return array<string, string>
     */
    public function params(): array
    {
        return $this->params;
    }

    private static function quoteParamValue(string $value): string
    {
        if ($value === '') {
            return '""';
        }

        if (preg_match('/^[a-zA-Z0-9!#$&+\-.^_`|~]+$/', $value)) {
            return $value;
        }

        return '"' . addcslashes($value, '"\\') . '"';
    }

    /**
     * Encode a single parameter per RFC 2231.
     *
     * @return array<string, string> Encoded key => value pairs.
     */
    private static function encodeRfc2231Param(string $name, string $val, string $charset): array
    {
        $needsEncoding = false;
        for ($i = 0, $len = strlen($val); $i < $len; $i++) {
            $ord = ord($val[$i]);
            if ($ord > 127) {
                $needsEncoding = true;
                break;
            }
        }

        if (!$needsEncoding) {
            return [$name => self::quoteParamValue($val)];
        }

        $encoded = strtolower($charset) . "''" . rawurlencode($val);

        $preLen = strlen($name) + 3;

        if (($preLen + strlen($encoded)) <= 75 || $name === 'boundary') {
            return [$name . '*' => $encoded];
        }

        $lines = [];
        $curr = 0;
        while ($encoded !== '') {
            $chunk = 75 - $preLen - strlen((string) $curr);
            $pos = min($chunk, strlen($encoded));

            if ($pos < strlen($encoded) && $pos > 2) {
                for ($i = 0; $i <= 2; $i++) {
                    if ($encoded[$pos - 1 - $i] === '%') {
                        $pos = $pos - 1 - $i;
                        break;
                    }
                }
            }

            $lines[] = substr($encoded, 0, $pos);
            $encoded = substr($encoded, $pos);
            if ($encoded === false) {
                $encoded = '';
            }
            $curr++;
        }

        $out = [];
        foreach ($lines as $i => $line) {
            $out[$name . '*' . $i . '*'] = $line;
        }

        return $out;
    }

    /**
     * @return array{0: string, 1: array<string, string>}
     */
    public static function parseParameterized(string $raw): array
    {
        $parts = explode(';', $raw, 2);
        $baseValue = trim($parts[0]);

        if (!isset($parts[1])) {
            return [$baseValue, []];
        }

        $params = [];
        $remainder = trim($parts[1]);

        while ($remainder !== '') {
            if (!preg_match('/^\s*([^\s=]+)\s*=\s*/A', $remainder, $m)) {
                break;
            }

            $key = $m[1];
            $remainder = substr($remainder, strlen($m[0]));

            if ($remainder !== '' && $remainder[0] === '"') {
                $value = '';
                $remainder = substr($remainder, 1);
                while ($remainder !== '') {
                    if ($remainder[0] === '\\' && strlen($remainder) > 1) {
                        $value .= $remainder[1];
                        $remainder = substr($remainder, 2);
                    } elseif ($remainder[0] === '"') {
                        $remainder = substr($remainder, 1);
                        break;
                    } else {
                        $value .= $remainder[0];
                        $remainder = substr($remainder, 1);
                    }
                }
            } else {
                $end = strpos($remainder, ';');
                if ($end === false) {
                    $value = trim($remainder);
                    $remainder = '';
                } else {
                    $value = trim(substr($remainder, 0, $end));
                    $remainder = substr($remainder, $end);
                }
            }

            $params[$key] = $value;

            $remainder = ltrim($remainder, "; \t");
        }

        return [$baseValue, $params];
    }
}
