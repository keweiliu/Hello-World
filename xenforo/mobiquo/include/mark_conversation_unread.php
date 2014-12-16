<?php

defined('IN_MOBIQUO') or exit;

function mark_conversation_unread_func($xmlrpc_params)
{
    $bridge = Tapatalk_Bridge::getInstance();
    $visitor = XenForo_Visitor::getInstance();
    $params = php_xmlrpc_decode($xmlrpc_params);

    $data = $bridge->_input->filterExternal(array(
            'conv_id' => XenForo_Input::UINT,
    ), $params);
    $conversationId = $data['conv_id'];

    if ($userId === null)
    {
        $userId = XenForo_Visitor::getUserId();
        if(empty($userId))
            return xmlresperror();
    }

    $conversationModel = $bridge->getConversationModel();

    $fetchOptions = array();

    $conversation = $conversationModel->getConversationForUser($conversationId, $userId, $fetchOptions);
    if (!$conversation)
    {
        return xmlresperror(new XenForo_Phrase('requested_conversation_not_found'));
    }

    $conversation =  $conversationModel->prepareConversation($conversation);
    if (!$conversation['isNew'])
    {
        $conversationModel->markConversationAsUnread($conversationId, $visitor->user_id);
        return xmlresptrue();
    }
    else
    {
        return xmlresperror();
    }
}
