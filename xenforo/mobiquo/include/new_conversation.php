<?php

defined('IN_MOBIQUO') or exit;

function new_conversation_func($xmlrpc_params)
{
    $params = php_xmlrpc_decode($xmlrpc_params);
    
    $visitor = XenForo_Visitor::getInstance();
    $bridge = Tapatalk_Bridge::getInstance();
    $conversationModel = $bridge->getConversationModel();
    
    $input = $bridge->_input->filterExternal(array(
        'recipients'    => XenForo_Input::ARRAY_SIMPLE,
        'title'         => XenForo_Input::STRING,
        'message'       => XenForo_Input::STRING,
        'attachment_id_array' =>XenForo_Input::ARRAY_SIMPLE,
        'group_id'      => XenForo_Input::STRING,
    ), $params);
    
    $visitor = XenForo_Visitor::getInstance();

    $conversationDw = XenForo_DataWriter::create('XenForo_DataWriter_ConversationMaster');
    $conversationDw->setExtraData(XenForo_DataWriter_ConversationMaster::DATA_ACTION_USER, $visitor->toArray());
    $conversationDw->set('user_id', $visitor['user_id']);
    $conversationDw->set('username', $visitor['username']);
    $conversationDw->set('title', $input['title']);
    $conversationDw->set('open_invite', 0);
    $conversationDw->set('conversation_open', 1);
    $conversationDw->addRecipientUserNames($input['recipients']); // checks permissions
    
    $messageDw = $conversationDw->getFirstMessageDw();
    $messageDw->set('message', $input['message']);
    $messageDw->setExtraData(XenForo_DataWriter_ConversationMessage::DATA_ATTACHMENT_HASH,$input['group_id']);
    
    $conversationDw->preSave();
    
    if (!$conversationDw->hasErrors())
    {
        $bridge->assertNotFlooding('conversation');
    }
    
    $conversationDw->save();
    $conversation = $conversationDw->getMergedData();
    
    $conversationModel->markConversationAsRead(
        $conversation['conversation_id'], XenForo_Visitor::getUserId(), XenForo_Application::$time
    );
    
    return xmlresptrue();
}
