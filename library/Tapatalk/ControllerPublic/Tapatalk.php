<?php

class Tapatalk_ControllerPublic_Tapatalk {

    static public function getSessionActivityDetailsForList(array $activities){

        $output = array();

        foreach($activities as $key => $activity)
        {
            $mobile_bbcode = isset($params['useragent']) && $params['useragent'] == 'byo' ? '[On BYO]' : '[On Tapatalk]';
            $phrase_identifier = isset($params['useragent']) && in_array($params['useragent'], array('tapatalk', 'byo')) ? $params['useragent'] : 'tapatalk';
            $format_activity = array( $key => $activity );

            $d_activity = Tapatalk_ControllerPublic_Tapatalk::get_activity($activity['controller_action'], $format_activity, $key);
            if(!empty($d_activity))
            {
                $output[$key] = $d_activity;
            }
            else
            {
               $output[$key] = new XenForo_Phrase($phrase_identifier.'_get_forum_list');
            }
        }
        
        return $output;
    }

    static public function get_activity($action, $activity, $key)
    {
        $d_activity = array();
        $params = $activity[$key]['params'];
        $agentPhrase = isset($params['useragent']) && in_array($params['useragent'], array('tapatalk', 'byo')) ? $params['useragent'] : 'tapatalk';

        switch($action)
        {
            case 'get_topic':
                $xenforo_activity = XenForo_ControllerPublic_Forum::getSessionActivityDetailsForList($activity);
                $d_activity = Tapatalk_ControllerPublic_Tapatalk::mark_mobile_for_activity($agentPhrase.'_get_topic',$xenforo_activity, $key);
                break;

            case 'get_thread':
            case 'get_thread_by_post':
            case 'get_thread_by_unread':
                $xenforo_activity = XenForo_ControllerPublic_Thread::getSessionActivityDetailsForList($activity);
                $d_activity = Tapatalk_ControllerPublic_Tapatalk::mark_mobile_for_activity($agentPhrase.'_get_thread',$xenforo_activity, $key);
                break;

            case 'get_user_info':
                $xenforo_activity = XenForo_ControllerPublic_Member::getSessionActivityDetailsForList($activity);
                $d_activity = Tapatalk_ControllerPublic_Tapatalk::mark_mobile_for_activity($agentPhrase.'_get_user_info',$xenforo_activity, $key);
                break;

            case 'search':
            case 'search_topic':
            case 'search_post':
                $d_activity =  array( new XenForo_Phrase($agentPhrase.'_search'),false,false,false); break;

            case 'get_participated_topic':
            case 'get_unread_topic':
            case 'get_latest_topic':
                $d_activity =  array( new XenForo_Phrase($agentPhrase.'_latest'),false,false,false); break;

            case 'get_subscribed_topic':
                $d_activity =  array( new XenForo_Phrase($agentPhrase.'_favorites'),false,false,false); break;

            case 'get_conversation':
            case 'get_conversations':
            case 'get_forum':
            case 'get_online_users':
                $d_activity =  array( new XenForo_Phrase($agentPhrase.'_'.$action),false,false,false); break;
            default:
                $d_activity =  array( new XenForo_Phrase($agentPhrase.'_get_forum_list'),false,false,false); 
        }
        return $d_activity;
    }
    
    static public function mark_mobile_for_activity($mobile_phrase_key, $activity, $key)
    {
        if(isset($activity[$key]))
        {
            if(is_array($activity[$key]))
            {
                if(isset($activity[$key][0]) && is_object($activity[$key][0]))
                {
                    $activity[$key][0] = new XenForo_Phrase($mobile_phrase_key);
                }
            }
            else
            {
                $activity[$key] = new XenForo_Phrase($mobile_phrase_key);;
            }
        }
        return $activity[$key];
    }
}