<?php

defined('IN_MOBIQUO') or exit;

function get_conversation_func($xmlrpc_params)
{
    $params = php_xmlrpc_decode($xmlrpc_params);

    $bridge = Tapatalk_Bridge::getInstance();
    $conversationModel = $bridge->getConversationModel();
    
    $input = $bridge->_input->filterExternal(array(
        'conversationId'    => XenForo_Input::STRING,
        'start_num'         => XenForo_Input::UINT,
        'last_num'          => XenForo_Input::UINT,
        'return_html'       => XenForo_Input::UINT,
    ), $params);
    
    $conversationId = $input['conversationId'];
    list($start, $limit, $page) = process_page($input['start_num'], $input['last_num']);
    
    
    $conversation = $conversationModel->getConversationForUser($conversationId, XenForo_Visitor::getUserId());
    if (!$conversation)
    {
        get_error('requested_conversation_not_found');
    }
    
    $conversation = $conversationModel->prepareConversation($conversation);
    
    $recipients = $conversationModel->getConversationRecipients($conversationId);
    $messages = $conversationModel->getConversationMessages($conversationId, array(
        'perPage' => $limit,
        'page' => $page,
    ));
    
    $maxMessageDate = $conversationModel->getMaximumMessageDate($messages);
    if ($maxMessageDate > $conversation['last_read_date'])
    {
        $conversationModel->markConversationAsRead(
            $conversationId, XenForo_Visitor::getUserId(), $maxMessageDate, $conversation['last_message_date']
        );
    }
    
    $messages = $conversationModel->prepareMessages($messages, $conversation);
    
    $viewParams = array(
        'conversation' => $conversation,
        'recipients' => $recipients,
        
        'canEditConversation' => $conversationModel->canEditConversation($conversation),
        'canReplyConversation' => $conversationModel->canReplyToConversation($conversation),
        'canInviteUsers' => $conversationModel->canInviteUsersToConversation($conversation),
        
        'messages' => $messages,
        'lastMessage' => end($messages),
        'page' => $page,
        'messagesPerPage' => $limit,
        'totalMessages' => $conversation['reply_count'] + 1
    );
    
    $participants = array();

    foreach($recipients as $uid => $recipient)
    {
        $participants[$uid] = new xmlrpcval(array(
            'username'  => new xmlrpcval($recipient['username'], 'base64'),
            'user_type' => new xmlrpcval(get_usertype_by_item('', $recipient['display_style_group_id'], $recipient['is_banned']), 'base64'),
            'icon_url'  => new xmlrpcval(get_avatar($recipient), 'string'),
        ), 'struct');
    }
    
    $defaultOptions = array(
        'states' => array(
            'viewAttachments' => false,
            'returnHtml' => (boolean)$input['return_html']
        )
    );
    $messages = $conversationModel->getAndMergeAttachmentsIntoConversationMessages($messages);

    $message_list = array();
    foreach($messages as $message)
    {
        global $attachs_info;
        $attachs_info = array();
        $attachment_list = array();
        if(isset($message['attachments']) && !empty($message['attachments']) && is_array($message['attachments']))
        {
            foreach($message['attachments'] as $attachment) {
    
                $type = $attachment['extension'];
                
                switch($attachment['extension']){
                    case 'gif':
                    case 'jpg':
                    case 'png':
                        $type = 'image';
                        break;
                    case 'pdf':
                        $type = 'pdf';
                        break;
                }
                $thumbnail = '';
                if(!empty($attachment['thumbnailUrl']))
                    $thumbnail = XenForo_Link::convertUriToAbsoluteUri($attachment['thumbnailUrl'], true);
                $attach_url = XenForo_Link::convertUriToAbsoluteUri(XenForo_Link::buildPublicLink('attachments', $attachment), true);
                $attachs_info[$attachment['attachment_id']] =  array(
                   'id' => $attachment['attachment_id'],
                   'thumbnail' => $thumbnail,
                   'url' => $attach_url,
                   'type' => $type,
                  );
                $attachment_list[] = new xmlrpcval(array(
                    'content_type'  => new xmlrpcval($type, 'string'),
                    'thumbnail_url' => new xmlrpcval($thumbnail, 'string'),
                    'url'           => new xmlrpcval($attach_url, 'string'),
                    'filename'      => new xmlrpcval($attachment['filename'], 'base64'),
                    'filesize'      => new xmlrpcval($attachment['file_size'], 'int'),
                ), 'struct');
            }
        }
        
        $message['message'] = preg_replace_callback('/\[attach=full\](.*?)\[\/attach\]/si',create_function(
                '$matches',
                'global $attachs_info;
                 if(isset($attachs_info[$matches[1]]) && $attachs_info[$matches[1]][\'type\'] == \'image\')
                    return \'[img]\'.$attachs_info[$matches[1]][\'url\'].\'[/img]\';
                 else
                    return \'[attach=full]\'.$matches[1].\'[/attach]\';'), 
                $message['message']);
        $message['message'] = preg_replace_callback('/\[attach\](.*?)\[\/attach\]/si',create_function(
                '$matches',
                'global $attachs_info;
                 if(isset($attachs_info[$matches[1]]) && $attachs_info[$matches[1]][\'type\'] == \'image\')
                    return \'[img]\'.(isset($attachs_info[$matches[1]][\'thumbnail\'])? $attachs_info[$matches[1]][\'thumbnail\'] : $attachs_info[$matches[1]][\'url\']).\'[/img]\';
                 else
                    return \'[attach]\'.$matches[1].\'[/attach]\';'), 
                $message['message']);

        $message_list[] = new xmlrpcval(array(
            'msg_id'        => new xmlrpcval($message['message_id'], 'string'),
            'msg_content'   => new xmlrpcval($bridge->cleanPost($message['message'], $defaultOptions), 'base64'),
            'msg_author_id' => new xmlrpcval($message['user_id'], 'string'),
            'is_unread'     => new xmlrpcval($message['isNew'], 'boolean'),
            'is_online'     => new xmlrpcval(get_online_status($message['user_id']), 'boolean'),
            'has_left'      => new xmlrpcval($recipients[$message['user_id']]['recipient_state'] != 'active', 'boolean'),
            'post_time'     => new xmlrpcval(mobiquo_iso8601_encode($message['message_date']), 'dateTime.iso8601'),
            'timestamp'     => new xmlrpcval($message['message_date'],'string'),
            'new_post'      => new xmlrpcval($message['isNew'], 'boolean'),
            'attachments'      => new xmlrpcval($attachment_list, 'array'),
        ), 'struct');
    }
    
    $viewingUser = XenForo_Visitor::getInstance()->toArray();

    $result = new xmlrpcval(array(
        'conv_id'           => new xmlrpcval($conversation['conversation_id'], 'string'),
        'conv_title'        => new xmlrpcval($conversation['title'], 'base64'),
        'participant_count' => new xmlrpcval($conversation['recipient_count'], 'int'),
        'total_message_num' => new xmlrpcval($conversation['reply_count'] + 1, 'int'),
        'can_invite'        => new xmlrpcval($viewParams['canInviteUsers'], 'boolean'),
        'can_edit'          => new xmlrpcval($viewParams['canEditConversation'], 'boolean'),
        'can_close'         => new xmlrpcval($viewParams['canEditConversation'], 'boolean'),
        'is_close'          => new xmlrpcval(empty($conversation['conversation_open']), 'boolean'),
        'can_upload'        => new xmlrpcval(XenForo_Permission::hasPermission($viewingUser['permissions'], 'conversation', 'uploadAttachment'), 'boolean'),
        'participants'      => new xmlrpcval($participants, 'struct'),
        'list'              => new xmlrpcval($message_list, 'array'),
    ), 'struct');

    return new xmlrpcresp($result);
}
