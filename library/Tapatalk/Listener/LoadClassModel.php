<?php

class Tapatalk_Listener_LoadClassModel
{
    public static function loadClassListener($class, &$extend)
    {
        $options = XenForo_Application::get('options');

        if ($class == 'XenForo_Model_Node')
        {
            $extend[] = 'Tapatalk_Model_Node';
        }
        elseif ($class == 'XenForo_Model_Search')
        {
            $extend[] = 'Tapatalk_Model_Search';
        }
    }
}
