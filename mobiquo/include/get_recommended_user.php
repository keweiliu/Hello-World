<?php

defined('IN_MOBIQUO') or exit;

function get_recommended_user_func($xmlrpc_params)
{    
    $mobi_api_key = loadAPIKey();
    $visitorUserId = XenForo_Visitor::getUserId();
    
    $total = 0;
    $return_user_lists = array();
    if(!empty($mobi_api_key) && !empty($visitorUserId))
    {
        $params = php_xmlrpc_decode($xmlrpc_params);
    
        $bridge = Tapatalk_Bridge::getInstance();
    
        $data = $bridge->_input->filterExternal(array(
                'page' => XenForo_Input::UINT,
                'perpage' => XenForo_Input::UINT,
                'mode' => XenForo_Input::UINT,
        ), $params);
        
        $user_lists = array();
        $user_lists = add_watched_your_thread_users($user_lists);
        $user_lists = add_coversation_users($user_lists);
        $user_lists = add_follow_users($user_lists, 'followed');
        $user_lists = add_follow_users($user_lists, 'following');
        $user_lists = add_thread_watch_users($user_lists);
        $user_lists = rank_users($user_lists);
        
        $page = isset($data['page']) && !empty($data['page']) ? $data['page'] : 1;
        $perpage = isset($data['perpage']) && !empty($data['perpage']) ? $data['perpage'] : 20;
        $start = ($page-1) * $perpage;
        $end = $start + $perpage;
    
        if(!empty($user_lists))
        {
            $num_track = 0;
            foreach ($user_lists as $user)
            {
                if(isset($data['mode']) && $data['mode'] == 2)
                {
                    $tapatalk_user_model = $bridge->getTapatalkUserModel();
                    $is_tapa_user = $tapatalk_user_model->getTapatalkUserById($user['user_id']);
                    if($is_tapa_user) continue;
                }
                if($user['user_id'] == 0) continue;
                if($num_track > $start - 1 && $num_track < $end)
                    $return_user_lists[] = new xmlrpcval(array(
                        'username'      => new xmlrpcval($user['username'], 'base64'),
                        'user_id'       => new xmlrpcval($user['user_id'], 'string'),
                        'icon_url'      => new xmlrpcval(isset($user['icon_url']) && !empty($user['icon_url']) ? $user['icon_url'] : get_avatar($user), 'string'),
                        'enc_email'     => new xmlrpcval(base64_encode(encrypt(trim($user['email']), $mobi_api_key)), 'string'),
                        'type'          => new xmlrpcval($user['type'], 'string'),
                    ), 'struct');
                $num_track ++;
            }
        }
    }
    $total = count($return_user_lists);
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
                'email'  => $recipient['email'],
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
                'email'  => $user['email'],
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
        'limit' => 50,
        'offset' => 0,
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
                'email'  => $thread['email'],
                'rank' => 3,
                'icon_url' => get_avatar($thread),
                'type' => 'thread_watch',
            ));
            $thread_watch_users = array_merge($thread_watch_users, $new_user);
        }
    }
    return array_merge($user_lists, $thread_watch_users);
}

function add_watched_your_thread_users($user_lists)
{
    $bridge = Tapatalk_Bridge::getInstance();
    $visitor = XenForo_Visitor::getInstance();
    $results = XenForo_Search_SourceHandler_Abstract::getDefaultSourceHandler()->executeSearchByUserId(
        $visitor['user_id'], 0, 50
    );
    $user_thread = array();
    foreach($results as $result)
        if($result[0] == 'thread')
            $user_thread[] = $result[1];
    $threadWatchModel = $bridge->getThreadWatchModel();
    $threadModel = $bridge->getThreadModel();
    $watchThreadUsers = array();
    foreach($user_thread as $threadId)
    {
        $thread = $threadModel->getThreadById($threadId);
        $watched_users = $threadWatchModel->getUsersWatchingThread($thread['thread_id'], $thread['node_id']);
        foreach($watched_users as $watch_user)
        {
            $new_user = array(array(
                'user_id' => $watch_user['user_id'],
                'username' => $watch_user['username'],
                'email'  => $watch_user['email'],
                'rank' => 3,
                'icon_url' => get_avatar($watch_user),
                'type' => 'watched_thread',
            ));
            $watchThreadUsers = array_merge($watchThreadUsers, $new_user);
        }
    }
    return array_merge($user_lists, $watchThreadUsers);
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

