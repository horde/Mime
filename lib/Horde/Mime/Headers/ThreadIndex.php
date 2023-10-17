<?php
/**
 * Copyright 2023 Horde LLC (http://www.horde.org/)
 *
 * See the enclosed file LICENSE for license information (LGPL). If you
 * did not receive this file, see http://www.horde.org/licenses/lgpl21.
 *
 * @category  Horde
 * @copyright 2023 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Mime
 */

/**
 * This class represents the (non-standard) ThreadIndex header sent by
 * the desktop MS Outlook products.
 *
 * @author    Michael J. Rubinsky <mrubinsk@horde.org>
 * @category  Horde
 * @copyright 2023 Horde LLC
 * @license   http://www.horde.org/licenses/lgpl21 LGPL 2.1
 * @package   Mime
 */
class Horde_Mime_Headers_ThreadIndex extends Horde_Mime_Headers_Element_Single
{
    const EOL = "\r\n";

    public static function getHandles()
    {
        return array(
            'thread-index'
        );
    }

    /**
     * TODO
     */
    protected function _sendEncode($opts)
    {
        // First try to encode it the standard way. This
        // will catch any cases where the header may not contain
        // the expected base64 encoded string, but rather some
        // other data that would normally get encoded.
        $encoded = Horde_Mime::encode($this->value, $opts['charset']);
        if ($encoded != $this->value) {
            return array($this->value);
        }

        // We didn't encode it already, check for extra-long
        // headers that exceed the 998 character maximum imposed
        // by RFC 2045. The above encoding would not normally catch
        // this sicne the expected data does not contain any whitespace.
        $len = strlen($this->value);
        $crlf = 0;
        $needs_encoding = false;
        for ($i = 0; $i < $len; ++$i) {
            $chr = ord($this->value[$i]);
            switch ($chr) {
            case 0:
                // NULLs not valid here, should have
                // been caught above. Just nuke the header
                // in this case, it's broken.
                return array('');
            case 10:
            case 13:
                $crlf = 0;
                break;
            default:
                if (++$crlf > 998) {
                    $needs_encoding = true;
                    break 2;
                }
                break;
            }
        }

        // If we need encoding, encode it with RFC 2045 mime encoding
        // then fold the resulting lines.
        if ($needs_encoding) {
            $delim = '=?' . $opts['charset'] . '?b?';
            $parts = explode(
                self::EOL,
                rtrim(
                    chunk_split(
                        base64_encode($this->value),
                        /* strlen($delim) + 2 = space taken by MIME
                         * delimiter */
                        intval((75 - strlen($delim) + 2) / 4) * 4
                    )
                )
            );
            $tmp = array();
            foreach ($parts as $val) {
                $tmp[] = $delim . $val . '?=';
            }
            return array(Horde_Mime::encode(implode(' ', $tmp), $opts['charset']));
        }

        return array($this->value);
    }
}
