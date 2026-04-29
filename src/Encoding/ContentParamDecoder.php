<?php

/**
 * Copyright 2014-2026 Horde LLC (http://www.horde.org/)
 *
 * Decoding parsing code adapted from rfc822-parser.c (Dovecot 2.2.13)
 *   Original code released under LGPL-2.1
 *   Copyright (c) 2002-2014 Timo Sirainen <tss@iki.fi>
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @author    Timo Sirainen <tss@iki.fi>
 * @author    Michael Slusarz <slusarz@horde.org>
 * @category  Horde
 * @copyright 2002-2015 Timo Sirainen
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Mime
 */

declare(strict_types=1);

namespace Horde\Mime\Encoding;

use Horde\Mail\Rfc822\ParseException;
use Horde\Mail\Rfc822\Rfc822CharacterRules;
use Horde\Mail\Rfc822\ValidationMode;

final class ContentParamDecoder
{
    use Rfc822CharacterRules;

    private array $activeComments = [];

    private function validationMode(): ValidationMode
    {
        return ValidationMode::Lenient;
    }

    /**
     * @return array<string, string>
     */
    public function decode(string $data): array
    {
        $out = [];

        $this->data = $data;
        $this->dataLen = strlen($data);
        $this->ptr = 0;

        while ($this->curr() !== false) {
            $this->skipLwsp();

            $param = null;
            $this->parseMimeToken($param);

            if ($param === null || $this->curr() !== '=') {
                break;
            }

            ++$this->ptr;
            $this->skipLwsp();

            $value = '';

            if ($this->curr() === '"') {
                try {
                    $this->traitParseQuotedString($value);
                } catch (ParseException) {
                    break;
                }
            } else {
                $this->parseMimeToken($value);
                if ($value === null) {
                    break;
                }
            }

            $out[$param] = $value;

            $this->skipLwsp();
            if ($this->curr() !== ';') {
                break;
            }

            ++$this->ptr;
        }

        return $out;
    }

    public static function isAtextNonTspecial(string $c): bool
    {
        $ord = ord($c);

        return match ($ord) {
            34, 40, 41, 44, 47, 58, 59, 60, 61, 62, 63, 64, 91, 92, 93 => false,
            default => ($ord > 32) && ($ord < 127),
        };
    }

    private function parseMimeToken(?string &$str): void
    {
        for ($i = $this->ptr, $size = strlen($this->data); $i < $size; ++$i) {
            if (!self::isAtextNonTspecial($this->data[$i])) {
                break;
            }
        }

        if ($i === $this->ptr) {
            $str = null;
        } else {
            $str = substr($this->data, $this->ptr, $i - $this->ptr);
            $this->ptr += ($i - $this->ptr);
            $this->skipLwsp();
        }
    }
}
