<?php

defined('IN_MOBIQUO') or exit;

function get_conversations_func($xmlrpc_params)
{
    $params = php_xmlrpc_decode($xmlrpc_params);
    
    list($start, $limit, $page) = process_page($params[0], $params[1]);
    
    $visitor = XenForo_Visitor::getInstance();
    $bridge = Tapatalk_Bridge::getInstance();
    $conversationModel = $bridge->getConversationModel();
    $totalConversations = $conversationModel->countConversationsForUser($visitor['user_id']);
    $unreadConversations = $visitor['conversations_unread'] ? $visitor['conversations_unread'] : 0;
    
    $conversations = $conversationModel->getConversationsForUser($visitor['user_id'], array(), array(
        'page' => $page,
        'perPage' => $limit
    ));
    $conversations = $conversationModel->prepareConversations($conversations);
    
    $conversation_list = array();
    
    foreach($conversations as $conversation)
    {
        $recipients = $conversationModel->getConversationRecipients($conversation['conversation_id']);
        $participants = array();
        foreach($recipients as $uid => $recipient)
        {
            $participants[$uid] = new xmlrpcval(array(
                'username'  => new xmlrpcval($recipient['username'], 'base64'),
                'user_type' => new xmlrpcval(get_usertype_by_item('', $recipient['display_style_group_id'], $recipient['is_banned']), 'base64'),
                'icon_url'  => new xmlrpcval(get_avatar($recipient), 'string'),
            ), 'struct');
        }
        
        $conversation_list[] = new xmlrpcval(array(
            'conv_id'           => new xmlrpcval($conversation['conversation_id'], 'string'),
            'reply_count'       => new xmlrpcval($conversation['reply_count'], 'string'),
            'participant_count' => new xmlrpcval($conversation['recipient_count'], 'string'),
            'start_user_id'     => new xmlrpcval($conversation['user_id'], 'string'),
            'last_user_id'      => new xmlrpcval($conversation['last_message_user_id'], 'string'),
            'last_conv_time'    => new xmlrpcval(mobiquo_iso8601_encode($conversation['last_message_date']), 'dateTime.iso8601'),
            'timestamp'         => new xmlrpcval($conversation['last_message_date'],'string'),
            'start_conv_time'   => new xmlrpcval(mobiquo_iso8601_encode($conversation['start_date']), 'dateTime.iso8601'),
            'conv_subject'      => new xmlrpcval($conversation['title'], 'base64'),
            'participants'      => new xmlrpcval($participants, 'struct'),
            'new_post'          => new xmlrpcval($conversation['isNew'], 'boolean'),
        ), 'struct');
    }
    
    $result = new xmlrpcval(array(
        'result'                => new xmlrpcval(true, 'boolean'),
        'conversation_count'    => new xmlrpcval($totalConversations, 'int'),
        'unread_count'          => new xmlrpcval($unreadConversations, 'int'),
        'can_upload'            => new xmlrpcval(XenForo_Permission::hasPermission($visitor['permissions'], 'conversation', 'uploadAttachment'),'boolean'),
        'list'                  => new xmlrpcval($conversation_list, 'array'),
    ), 'struct');

    return new xmlrpcresp($result);
}
