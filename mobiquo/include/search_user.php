<?php

defined('IN_MOBIQUO') or exit;

function search_user_func($xmlrpc_params)
{
    $params = php_xmlrpc_decode($xmlrpc_params);

    $bridge = Tapatalk_Bridge::getInstance();

    $data = $bridge->_input->filterExternal(array(
            'search_key' => XenForo_Input::STRING,
            'page' => XenForo_Input::UINT,
            'perpage' => XenForo_Input::UINT,
    ), $params);

    $total = 0;

    $page = (empty($data['page']) || $data['page'] < 0 )? 0 : $data['page'];
    $perpage = (empty($data['perpage']) || $data['perpage'] < 0)? 50 : $data['perpage'];
    if ($data['search_key'] !== '' && utf8_strlen($data['search_key']) >= 2)
    {
        $user_lists = $bridge->getUserModel()->getUsers(
            array('username' => array($data['search_key'] , 'r'), 'user_state' => 'valid', 'is_banned' => 0),
            array('perPage' => $perpage, 'page' => $page)
        );
        $total = $bridge->getUserModel()->countUsers(array('username' => array($data['search_key'] , 'r'), 'user_state' => 'valid', 'is_banned' => 0));
    }
    else
    {
        $user_lists = array();
    }


    $return_user_lists = array();

    if(!empty($user_lists))
        foreach ($user_lists as $user)
            $return_user_lists[] = new xmlrpcval(array(
                'user_name'     => new xmlrpcval($user['username'], 'base64'),
                'user_id'       => new xmlrpcval($user['user_id'], 'string'),
                'icon_url'      => new xmlrpcval(isset($user['icon_url']) && !empty($user['icon_url']) ? $user['icon_url'] : get_avatar($user), 'string'),
            ), 'struct');

    $suggested_users = new xmlrpcval(array(
        'total' => new xmlrpcval($total, 'int'),
        'list'         => new xmlrpcval($return_user_lists, 'array'),
    ), 'struct');

    return new xmlrpcresp($suggested_users);
}

function add_coversation_users($user_lists)
{
    $bridge = Tapatalk_Bridge::getInstance();
    $conversationModel = $bridge->getConversationModel();
    $visitor = XenForo_Visitor::getInstance();
    $conversation_users = array();

    $conversations = $conversationModel->getConversationsForUser($visitor['user_id'], array(), array(
        'page' => 1,
        'perPage' => 50
    ));
    $conversations = $conversationModel->prepareConversations($conversations);
    
    foreach($conversations as $conversation)
    {
        $recipients = $conversationModel->getConversationRecipients($conversation['conversation_id']);
        $participants = array();
        $rank = time() - $conversation['start_date'] > 30*86400 ? 2 : 10;
        foreach($recipients as $uid => $recipient)
        {
            if($uid == $visitor['user_id'])
                continue;
            $new_user = array(array(
                'user_id' => $uid,
                'username' => $recipient['username'],
                'rank' => $rank,
                'icon_url' => get_avatar($recipient),
                'type' => 'conv',
            ));
            $conversation_users = array_merge($conversation_users, $new_user);
        }
    }
    return array_merge($user_lists, $conversation_users);
}

function add_follow_users($user_lists, $type = 'followed')
{
    $follow_users = array();
    $bridge = Tapatalk_Bridge::getInstance();
    $userModel = $bridge->getUserModel();
    $visitor = XenForo_Visitor::getInstance();
    $users = $type == 'following' ? $userModel->getFollowedUserProfiles($visitor['user_id']) : $userModel->getUsersFollowingUserId($visitor['user_id']);
    $rank = $type == 'followed' ? 1 : 5;
    if(!empty($users))
    {
        foreach($users as $uid => $user)
        {
            $new_user = array(array(
                'user_id' => $uid,
                'username' => $user['username'],
                'rank' => $rank,
                'icon_url' => get_avatar($user),
                'type' => $type,
            ));
            $follow_users = array_merge($follow_users, $new_user);
        }
    }
    return array_merge($user_lists, $follow_users);
}

function add_thread_watch_users($user_lists)
{
    $bridge = Tapatalk_Bridge::getInstance();
    $threadWatchModel = $bridge->getThreadWatchModel();
    $visitor = XenForo_Visitor::getInstance();
    $thread_watch_users = array();
    $fetchOptions = array(
        'join' => XenForo_Model_Thread::FETCH_FORUM | XenForo_Model_Thread::FETCH_USER,
        'readUserId' => $visitor['user_id'],
        'postCountUserId' => $visitor['user_id'],
        'permissionCombinationId' => $visitor['permission_combination_id'],
        'limit' => $limit,
        'offset' => $start,
    );
    $threads = $threadWatchModel->getThreadsWatchedByUser($visitor['user_id'], false,$fetchOptions);
    if(!empty($threads))
    {
        foreach($threads as $thread)
        {
            if($thread['user_id'] == $visitor['user_id'])
                continue;

            $new_user = array(array(
                'user_id' => $thread['user_id'],
                'username' => $thread['username'],
                'rank' => 3,
                'icon_url' => get_avatar($thread),
                'type' => 'thread_watch',
            ));
            $thread_watch_users = array_merge($thread_watch_users, $new_user);
        }
    }
    return array_merge($user_lists, $thread_watch_users);
}

function rank_users($users, $max_num = 50)
{    
    // combine ranks for same user
    $combined_users = array();
    
    foreach($users as $user)
    {
        if(isset($combined_users[$user['user_id']]))
        {
            $combined_users[$user['user_id']]['rank'] += $user['rank'];
        }
        else
        {   
            $combined_users[$user['user_id']] = $user;
        }
    }
    $users = $combined_users;
    
    // sort by rank
    $hash = array();
    
    foreach($users as $user)
    {
        if(isset($hash[$user['rank']]))
            $hash[$user['rank']+1] = $user;
        else
            $hash[$user['rank']] = $user;
    }
    
    krsort($hash);
    
    $users = array();
    $count = 0;
    foreach($hash as $user)
    {
        if($count > $max_num || $count == $max_num)
            break;
        $users[] = $user;
        $count++;
    }
    
    return $users;
}