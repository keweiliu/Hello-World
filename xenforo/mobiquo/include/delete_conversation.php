<?php

defined('IN_MOBIQUO') or exit;

function delete_conversation_func($xmlrpc_params)
{
    $params = php_xmlrpc_decode($xmlrpc_params);

    $bridge = Tapatalk_Bridge::getInstance();
    $conversationModel = $bridge->getConversationModel();
    
    $input = $bridge->_input->filterExternal(array(
        'conversationId'    => XenForo_Input::STRING,
        'mode'              => XenForo_Input::UINT,
    ), $params);
    
    $conversationId = $input['conversationId'];
    $conversation = $conversationModel->getConversationForUser($conversationId, XenForo_Visitor::getUserId());
    if (!$conversation)
    {
        get_error('requested_conversation_not_found');
    }
    
    $deleteType = (isset($input['mode']) && $input['mode'] == 2) ? 'delete_ignore' : 'delete';
    
    $conversationModel->deleteConversationForUser(
        $conversationId, XenForo_Visitor::getUserId(), $deleteType
    );
    
    return xmlresptrue();
}
