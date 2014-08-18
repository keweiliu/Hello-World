<?php

defined('IN_MOBIQUO') or exit;

function get_quote_conversation_func($xmlrpc_params)
{
    $params = php_xmlrpc_decode($xmlrpc_params);
    
    $bridge = Tapatalk_Bridge::getInstance();
    $conversationModel = $bridge->getConversationModel();
    
    $input = $bridge->_input->filterExternal(array(
        'conversationId'    => XenForo_Input::UINT,
        'messageId'         => XenForo_Input::UINT,
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
    
    $messageId = $input['messageId'];
    $defaultMessage = '';
    if ($messageId)
    {
        if ($message = $conversationModel->getConversationMessageById($messageId))
        {
            if ($message['conversation_id'] != $conversationId)
            {
                get_error('not_possible_to_reply_to_messages_not_same_conversation');
            }
            
            $defaultMessage = $conversationModel->getQuoteForConversationMessage($message);
        }
    }
    
    $result = new xmlrpcval(array(
        'text_body' => new xmlrpcval($defaultMessage, 'base64'),
    ), 'struct');

    return new xmlrpcresp($result);
}
