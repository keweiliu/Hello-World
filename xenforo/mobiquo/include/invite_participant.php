<?php

defined('IN_MOBIQUO') or exit;

function invite_participant_func($xmlrpc_params)
{
    $params = php_xmlrpc_decode($xmlrpc_params);
    
    $bridge = Tapatalk_Bridge::getInstance();
    $conversationModel = $bridge->getConversationModel();
    
    $input = $bridge->_input->filterExternal(array(
        'recipients'     => XenForo_Input::ARRAY_SIMPLE,
        'conversationId' => XenForo_Input::UINT,
    ), $params);
    
    $conversationId = $input['conversationId'];
    $conversation = $conversationModel->getConversationForUser($conversationId, XenForo_Visitor::getUserId());
    if (!$conversation)
    {
        get_error('requested_conversation_not_found');
    }
    
    if (empty($input['recipients']))
    {
        get_error('The following recipients could not be found:""');
    }
    
    if (!$conversationModel->canInviteUsersToConversation($conversation, $errorPhraseKey))
    {
        if(empty($errorPhraseKey))
            $errorPhraseKey = 'You are not allowed to invite users in this conversation.';
        get_error($errorPhraseKey);
    }
    $conversationDw = XenForo_DataWriter::create('XenForo_DataWriter_ConversationMaster');
    $conversationDw->setExistingData($conversationId);
    $conversationDw->setExtraData(XenForo_DataWriter_ConversationMaster::DATA_ACTION_USER, XenForo_Visitor::getInstance()->toArray());
    $conversationDw->addRecipientUserNames($input['recipients']);
    $conversationDw->save();
    
    return xmlresptrue();
}
