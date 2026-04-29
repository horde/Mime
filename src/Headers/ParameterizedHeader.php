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
