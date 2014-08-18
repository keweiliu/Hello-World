<?php

class Tapatalk_Push_Thread extends XFCP_Tapatalk_Push_Thread
{
    protected function _save()
    {
        parent::_save();
        //temporary solution, any ideas?
        $GLOBALS['tap_is_new_thread'] = 1;
    }
}
