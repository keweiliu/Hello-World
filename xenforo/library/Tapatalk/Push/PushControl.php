<?php

class Tapatalk_Push_PushControl
{
    public static function push_control($class, &$extend)
    {
        $options = XenForo_Application::get('options');

        if ($class == 'XenForo_DataWriter_Alert')
        {
            $extend[] = 'Tapatalk_Push_Alert';
        }
        else if($class == 'XenForo_DataWriter_ConversationMaster')
        {
            $extend[] = 'Tapatalk_Push_Conversation';
        }
//        else if ($class == 'XenForo_DataWriter_Discussion_Thread')
//        {
//            $extend[] = 'Tapatalk_Push_Thread';
//        }
    }
}
