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

use Horde_Mail_Rfc822;

final class ContentParamDecoder extends Horde_Mail_Rfc822
{
    /**
     * @return array<string, string>
     */
    public function decode(string $data): array
    {
        $out = [];

        $this->_data = $data;
        $this->_datalen = strlen($data);
        $this->_ptr = 0;

        while ($this->_curr() !== false) {
            $this->_rfc822SkipLwsp();

            $this->_rfc822ParseMimeToken($param);

            if (is_null($param) || ($this->_curr() !== '=')) {
                break;
            }

            ++$this->_ptr;
            $this->_rfc822SkipLwsp();

            $value = '';

            if ($this->_curr() === '"') {
                try {
                    $this->_rfc822ParseQuotedString($value);
                } catch (\Horde_Mail_Exception $e) {
                    break;
                }
            } else {
                $this->_rfc822ParseMimeToken($value);
                if (is_null($value)) {
                    break;
                }
            }

            $out[$param] = $value;

            $this->_rfc822SkipLwsp();
            if ($this->_curr() !== ';') {
                break;
            }

            ++$this->_ptr;
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

    protected function _rfc822ParseMimeToken(?string &$str): void
    {
        for ($i = $this->_ptr, $size = strlen($this->_data); $i < $size; ++$i) {
            if (!self::isAtextNonTspecial($this->_data[$i])) {
                break;
            }
        }

        if ($i === $this->_ptr) {
            $str = null;
        } else {
            $str = substr($this->_data, $this->_ptr, $i - $this->_ptr);
            $this->_ptr += ($i - $this->_ptr);
            $this->_rfc822SkipLwsp();
        }
    }
}
