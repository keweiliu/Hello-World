<?php

defined('IN_MOBIQUO') or exit;

function reply_conversation_func($xmlrpc_params)
{
    $params = php_xmlrpc_decode($xmlrpc_params);
    
    $bridge = Tapatalk_Bridge::getInstance();
    $conversationModel = $bridge->getConversationModel();
    
    $input = $bridge->_input->filterExternal(array(
        'conversationId'    => XenForo_Input::UINT,
        'message'           => XenForo_Input::STRING,
        'subject'           => XenForo_Input::STRING,
        'attachment_id_array' => XenForo_Input::ARRAY_SIMPLE,
        'group_id'          => XenForo_Input::STRING,
    ), $params);
    
    $conversationId = $input['conversationId'];
    $conversation = $conversationModel->getConversationForUser($conversationId, XenForo_Visitor::getUserId());
    if (!$conversation)
    {
        get_error('requested_conversation_not_found');
    }
    if (!$conversationModel->canReplyToConversation($conversation, $errorPhraseKey))
    {
        get_error($errorPhraseKey);
    }
    
    $visitor = XenForo_Visitor::getInstance();
    
    $messageDw = XenForo_DataWriter::create('XenForo_DataWriter_ConversationMessage');
    $messageDw->setExtraData(XenForo_DataWriter_ConversationMessage::DATA_MESSAGE_SENDER, $visitor->toArray());
    $messageDw->setExtraData(XenForo_DataWriter_ConversationMessage::DATA_ATTACHMENT_HASH, $input['group_id']);
    $messageDw->set('conversation_id', $conversation['conversation_id']);
    $messageDw->set('user_id', $visitor['user_id']);
    $messageDw->set('username', $visitor['username']);
    $messageDw->set('message', $input['message']);
    $messageDw->preSave();
    
    if (!$messageDw->hasErrors())
    {
        $bridge->assertNotFlooding('conversation');
    }
    
    $messageDw->save();
    
    $message = $messageDw->getMergedData();
    
    $conversationModel->markConversationAsRead(
        $conversation['conversation_id'], XenForo_Visitor::getUserId(), XenForo_Application::$time
    );
    $result = new xmlrpcval(array(
        'result'        => new xmlrpcval(true, 'boolean'),
        'result_text'   => new xmlrpcval('', 'base64'),
        'msg_id'        => new xmlrpcval($message['message_id'], 'string')
    ), 'struct');
    
    return new xmlrpcresp($result);
}
