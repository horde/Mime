<?php
/**
 *
 */
class Horde_Mime_Headers_ThreadIndex extends Horde_Mime_Headers_Element_Single
{
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
        $encoded = Horde_Mime::encode($this->value, $opts['charset']);

        if ($encoded != $this->value) {
            return array($this->value);
        }

        $len = strlen($this->value);
        $crlf = 0;
        $needs_encoding = false;
        for ($i = 0; $i < $len; ++$i) {
            $chr = ord($this->value[$i]);
            switch ($chr) {
            case 0:
                // NULLs not valid here, should have
                // been caught above.
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

            return trim(implode(' ', $tmp));
        }

        return array($this->value);
    }
}
