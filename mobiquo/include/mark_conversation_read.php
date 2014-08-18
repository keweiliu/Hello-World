<?php

defined('IN_MOBIQUO') or exit;

function mark_conversation_read_func($xmlrpc_params)
{
    $bridge = Tapatalk_Bridge::getInstance();
    $visitor = XenForo_Visitor::getInstance();
    $param = php_xmlrpc_decode($xmlrpc_params);
    $conversationModel = $bridge->getConversationModel();
    
    $data = $bridge ->_input ->filterExternal(array(
        'conv_ids' => XenForo_Input::STRING,
    ), $param);
    
    if(empty($visitor['user_id']))
        return xmlresperror(new XenForo_Phrase('requested_member_not_found'));
    
    if(!empty($data['conv_ids']))
    {
        $conversationIds = array_unique(array_map('intval', explode(',', $data['conv_ids'])));
        $conversations = $conversationModel->getConversationsForUserByIds($visitor['user_id'], $conversationIds);
    }
    else
    {
        $conversations = $conversationModel->getConversationsForUser($visitor['user_id'], array('is_unread' => TRUE));
    }
    
    if (is_array($conversations) && count($conversations))
    {
        foreach ($conversations as $conversation){
            $conversationModel->markConversationAsRead($conversation['conversation_id'], $visitor['user_id'], XenForo_Application::$time);
        }
    }
    
    return xmlresptrue();
}

