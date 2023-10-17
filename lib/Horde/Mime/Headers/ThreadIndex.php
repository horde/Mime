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

        return array(Horde_Mime::encode($this->values, $opts['charset']));
    }
}
