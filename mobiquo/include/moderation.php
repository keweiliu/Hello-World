<?php

defined('IN_MOBIQUO') or exit;

// TOPIC ACTION

function m_stick_topic_func($xmlrpc_params)
{
    $params = php_xmlrpc_decode($xmlrpc_params);

    $bridge = Tapatalk_Bridge::getInstance();
    $inlineModThreadModel = $bridge->getInlineModThreadModel();
    
    $input = $bridge->_input->filterExternal(array(
        'threadId'  => XenForo_Input::STRING,
        'mode'      => XenForo_Input::UINT,
    ), $params);
    
    $threadIds = array_unique(array_map('intval', explode(',', $input['threadId'])));
    if ($input['mode'] == 1)
        $result = $inlineModThreadModel->stickThreads($threadIds, array(), $errorPhraseKey);
    else
        $result = $inlineModThreadModel->unstickThreads($threadIds, array(), $errorPhraseKey);
    
    $response = new xmlrpcval(array(
        'result'        => new xmlrpcval($result, 'boolean'),
        'result_text'   => new xmlrpcval(get_xf_lang($errorPhraseKey), 'base64')
    ), 'struct');
    
    return new xmlrpcresp($response);
}

function m_rename_topic_func($xmlrpc_params)
{
    $params = php_xmlrpc_decode($xmlrpc_params);
    $bridge = Tapatalk_Bridge::getInstance();

    $input = $bridge->_input->filterExternal(array(
        'topic_id'  => XenForo_Input::STRING,
        'title'      => XenForo_Input::STRING,
        'prefix_id'  => XenForo_Input::STRING,
    ), $params);

    $visitor = XenForo_Visitor::getInstance();
    $threadId = $input['topic_id'];
    unset($input['topic_id']);

    $threadModel = $bridge->getThreadModel();
    $ftpHelper = $bridge->getHelper('ForumThreadPost');
    list($thread, $forum) = $ftpHelper->assertThreadValidAndViewable($threadId);
    $permissions = $visitor->getNodePermissions($forum['node_id']);
    
    if (!$threadModel->canEditThreadTitle($thread, $forum, $errorPhraseKey))
    {
        return new xmlrpcval(array(
            'result'        => new xmlrpcval(false, 'boolean'),
            'result_text'   => new xmlrpcval(empty($errorPhraseKey) ? 'You have no permissions to perform this action' : $errorPhraseKey, 'base64')
        ), 'struct');
    }
    
    if(isset($input['prefix_id']) && !empty($input['prefix_id']))
    {
        $prefix_valid = true;
        $prefixModel = $bridge->getPrefixModel();
        if(!$prefixModel->verifyPrefixIsUsable($input['prefix_id'], $forum['node_id']))
        {
            $prefix_valid = $input['prefix_id'] = 0;
            if(isset($thread['prefix_id']) && !empty($thread['prefix_id']))
                $input['prefix_id'] = $thread['prefix_id'];
        }
    }
    else
    {
        $input['prefix_id'] = $thread['prefix_id'];
    }
    
    // TODO: check prefix requirements?

    $dw = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread');
    $dw->setExistingData($threadId);
    $dw->bulkSet($input);
    $dw->setExtraData(XenForo_DataWriter_Discussion_Thread::DATA_FORUM, $forum);
    $dw->save();

    //Update thread moderation log.
    $newData = $dw->getMergedNewData();
    if ($newData && empty($errorPhraseKey))
    {
        $oldData = $dw->getMergedExistingData();
        $basicLog = array();

        foreach ($newData AS $key => $value)
        {
            $oldValue = (isset($oldData[$key]) ? $oldData[$key] : '-');
            switch ($key)
            {
                case 'title':
                    XenForo_Model_Log::logModeratorAction(
                        'thread', $thread, 'title', array('old' => $oldValue)
                    );
                    break;

                case 'prefix_id':
                    if ($oldValue)
                    {
                        $phrase = new XenForo_Phrase('thread_prefix_' . $oldValue);
                        $oldValue = $phrase->render();
                    }
                    else
                    {
                        $oldValue = '-';
                    }
                    XenForo_Model_Log::logModeratorAction(
                        'thread', $thread, 'prefix', array('old' => $oldValue)
                    );
                    break;

                default:
                    if (!in_array($key, $skip))
                    {
                        $basicLog[$key] = $oldValue;
                    }
            }
        }

        if ($basicLog)
        {
            XenForo_Model_Log::logModeratorAction('thread', $thread, 'edit', $basicLog);
        }
    }
    $response = new xmlrpcval(array(
        'result'        => new xmlrpcval(empty($errorPhraseKey), 'boolean'),
        'result_text'   => new xmlrpcval(empty($errorPhraseKey) ? (!isset($prefix_valid)? '' : ($prefix_valid ? '': 'Changes have been saved while prefix is not valid')): get_xf_lang($errorPhraseKey), 'base64')
    ), 'struct');
    
    return new xmlrpcresp($response);
}

function m_close_topic_func($xmlrpc_params)
{
    $params = php_xmlrpc_decode($xmlrpc_params);

    $bridge = Tapatalk_Bridge::getInstance();
    $inlineModThreadModel = $bridge->getInlineModThreadModel();
    
    $input = $bridge->_input->filterExternal(array(
        'threadId'  => XenForo_Input::STRING,
        'mode'      => XenForo_Input::UINT,
    ), $params);
    
    $threadIds = array_unique(array_map('intval', explode(',', $input['threadId'])));
    if ($input['mode'] == 2)
        $result = $inlineModThreadModel->lockThreads($threadIds, array(), $errorPhraseKey);
    else
        $result = $inlineModThreadModel->unlockThreads($threadIds, array(), $errorPhraseKey);
    
    $response = new xmlrpcval(array(
        'result'        => new xmlrpcval($result, 'boolean'),
        'result_text'   => new xmlrpcval(get_xf_lang($errorPhraseKey), 'base64')
    ), 'struct');
    
    return new xmlrpcresp($response);
}

function m_delete_topic_func($xmlrpc_params)
{
    $params = php_xmlrpc_decode($xmlrpc_params);

    $bridge = Tapatalk_Bridge::getInstance();
    $inlineModThreadModel = $bridge->getInlineModThreadModel();
    
    $input = $bridge->_input->filterExternal(array(
        'threadId'  => XenForo_Input::STRING,
        'mode'      => XenForo_Input::UINT,
        'reason'    => XenForo_Input::STRING,
    ), $params);
    
    $threadIds = array_unique(array_map('intval', explode(',', $input['threadId'])));
    
    $options = array(
        'deleteType' => ($input['mode'] == 2 ? 'hard' : 'soft'),
        'reason' => $input['reason'],
    );
    
    $result = $inlineModThreadModel->deleteThreads($threadIds, $options, $errorPhraseKey);
    
    $response = new xmlrpcval(array(
        'result'        => new xmlrpcval($result, 'boolean'),
        'result_text'   => new xmlrpcval(get_xf_lang($errorPhraseKey), 'base64')
    ), 'struct');
    
    return new xmlrpcresp($response);
}

function m_undelete_topic_func($xmlrpc_params)
{
    $params = php_xmlrpc_decode($xmlrpc_params);

    $bridge = Tapatalk_Bridge::getInstance();
    $inlineModThreadModel = $bridge->getInlineModThreadModel();
    
    $input = $bridge->_input->filterExternal(array(
        'threadId'  => XenForo_Input::STRING,
        'reason'    => XenForo_Input::STRING,
    ), $params);
    
    $threadIds = array_unique(array_map('intval', explode(',', $input['threadId'])));
    $result = $inlineModThreadModel->undeleteThreads($threadIds, array(), $errorPhraseKey);
    
    $response = new xmlrpcval(array(
        'result'        => new xmlrpcval($result, 'boolean'),
        'result_text'   => new xmlrpcval(get_xf_lang($errorPhraseKey), 'base64')
    ), 'struct');
    
    return new xmlrpcresp($response);
}

function m_move_topic_func($xmlrpc_params)
{
    $params = php_xmlrpc_decode($xmlrpc_params);

    $bridge = Tapatalk_Bridge::getInstance();
    $inlineModThreadModel = $bridge->getInlineModThreadModel();
    $nodeModel = $bridge->getNodeModel();
    
    $input = $bridge->_input->filterExternal(array(
        'threadId'  => XenForo_Input::STRING,
        'node_id'   => XenForo_Input::STRING,
    ), $params);
    
    $threadIds = array_unique(array_map('intval', explode(',', $input['threadId'])));
    
    $viewableNodes = $nodeModel->getViewableNodeList();
    if (!isset($viewableNodes[$input['node_id']])) {
        get_error('requested_forum_not_found');
    }
    $options = array('redirect' => 1, 'redirectExpiry' => time() + 86400);
    
    $result = $inlineModThreadModel->moveThreads($threadIds, $input['node_id'], $options, $errorPhraseKey);
    
    $response = new xmlrpcval(array(
        'result'        => new xmlrpcval($result, 'boolean'),
        'result_text'   => new xmlrpcval(get_xf_lang($errorPhraseKey), 'base64')
    ), 'struct');
    
    return new xmlrpcresp($response);
}

function m_merge_topic_func($xmlrpc_params)
{
    $params = php_xmlrpc_decode($xmlrpc_params);

    $bridge = Tapatalk_Bridge::getInstance();
    $inlineModThreadModel = $bridge->getInlineModThreadModel();
    $nodeModel = $bridge->getNodeModel();
    
    $input = $bridge->_input->filterExternal(array(
        'threadId'          => XenForo_Input::STRING,
        'target_thread_id'  => XenForo_Input::STRING,
        'redirect'          => XenForo_Input::UINT,
    ), $params);
    
    $threadId = array_unique(array_map('intval', explode(',', $input['threadId'])));
    $threadIds = array($threadId, $input['target_thread_id']);
    $options = array('redirect' => false);
    
    if ($input['redirect']){
        $redirect_ttl_value=1;
        $redirect_ttl_unit='days';
        $expiryDate = strtotime('+' . $redirect_ttl_value . ' ' . $redirect_ttl_unit);
        $options = array('redirect' => true , 'redirectExpiry' => $expiryDate);
    }
    
    $targetThread = $inlineModThreadModel->mergeThreads($threadIds, $input['target_thread_id'], $options, $errorPhraseKey);
    
    $response = new xmlrpcval(array(
        'result'        => new xmlrpcval($targetThread ? true : false, 'boolean'),
        'result_text'   => new xmlrpcval(get_xf_lang($errorPhraseKey), 'base64')
    ), 'struct');
    
    return new xmlrpcresp($response);
}

function m_approve_topic_func($xmlrpc_params)
{
    $params = php_xmlrpc_decode($xmlrpc_params);

    $bridge = Tapatalk_Bridge::getInstance();
    $inlineModThreadModel = $bridge->getInlineModThreadModel();
    
    $input = $bridge->_input->filterExternal(array(
        'threadId'  => XenForo_Input::STRING,
        'mode'      => XenForo_Input::UINT,
    ), $params);
    
    $threadIds = array_unique(array_map('intval', explode(',', $input['threadId'])));
    if ($input['mode'] == 1)
        $result = $inlineModThreadModel->approveThreads($threadIds, array(), $errorPhraseKey);
    else
        $result = $inlineModThreadModel->unapproveThreads($threadIds, array(), $errorPhraseKey);
    
    $response = new xmlrpcval(array(
        'result'        => new xmlrpcval($result, 'boolean'),
        'result_text'   => new xmlrpcval(get_xf_lang($errorPhraseKey), 'base64')
    ), 'struct');
    
    return new xmlrpcresp($response);
}


// POST ACTION

function m_delete_post_func($xmlrpc_params)
{
    $params = php_xmlrpc_decode($xmlrpc_params);

    $bridge = Tapatalk_Bridge::getInstance();
    $inlineModPostModel = $bridge->getInlineModPostModel();
    
    $input = $bridge->_input->filterExternal(array(
        'postId'    => XenForo_Input::STRING,
        'mode'      => XenForo_Input::UINT,
        'reason'    => XenForo_Input::STRING,
    ), $params);
    
    $postIds = array_unique(array_map('intval', explode(',', $input['postId'])));
    
    $options = array(
        'deleteType' => ($input['mode'] == 2 ? 'hard' : 'soft'),
        'reason' => $input['reason'],
    );
    
    $result = $inlineModPostModel->deletePosts($postIds, $options, $errorPhraseKey);
    
    $response = new xmlrpcval(array(
        'result'        => new xmlrpcval($result, 'boolean'),
        'result_text'   => new xmlrpcval(get_xf_lang($errorPhraseKey), 'base64')
    ), 'struct');
    
    return new xmlrpcresp($response);
}

function m_undelete_post_func($xmlrpc_params)
{
    $params = php_xmlrpc_decode($xmlrpc_params);

    $bridge = Tapatalk_Bridge::getInstance();
    $inlineModPostModel = $bridge->getInlineModPostModel();
    
    $input = $bridge->_input->filterExternal(array(
        'postId'    => XenForo_Input::STRING,
        'reason'    => XenForo_Input::STRING,
    ), $params);
    
    $postIds = array_unique(array_map('intval', explode(',', $input['postId'])));
    $result = $inlineModPostModel->undeletePosts($postIds, array(), $errorPhraseKey);
    
    $response = new xmlrpcval(array(
        'result'        => new xmlrpcval($result, 'boolean'),
        'result_text'   => new xmlrpcval(get_xf_lang($errorPhraseKey), 'base64')
    ), 'struct');
    
    return new xmlrpcresp($response);
}

function m_move_post_func($xmlrpc_params)
{
    $params = php_xmlrpc_decode($xmlrpc_params);

    $bridge = Tapatalk_Bridge::getInstance();
    $inlineModPostModel = $bridge->getInlineModPostModel();
    $nodeModel = $bridge->getNodeModel();
    
    $input = $bridge->_input->filterExternal(array(
        'postId'    => XenForo_Input::STRING,
        'threadId'  => XenForo_Input::STRING,
        'title'  => XenForo_Input::STRING,
        'node_id'   => XenForo_Input::STRING,
    ), $params);
    
    $postIds = array_unique(array_map('intval', explode(',', $input['postId'])));
    
    $viewableNodes = $nodeModel->getViewableNodeList();
    if (!isset($viewableNodes[$input['node_id']])) {
        get_error('requested_forum_not_found');
    }
    $options = array(
        'threadNodeId' => $input['node_id'],
        'threadTitle' => $input['title']
    );
    
    $newThread = $inlineModPostModel->movePosts($postIds, $options, $errorPhraseKey);
    
    $response = new xmlrpcval(array(
        'result'        => new xmlrpcval($newThread ? true : false, 'boolean'),
        'result_text'   => new xmlrpcval(get_xf_lang($errorPhraseKey), 'base64')
    ), 'struct');
    
    return new xmlrpcresp($response);
}

function m_approve_post_func($xmlrpc_params)
{
    $params = php_xmlrpc_decode($xmlrpc_params);

    $bridge = Tapatalk_Bridge::getInstance();
    $inlineModPostModel = $bridge->getInlineModPostModel();
    
    $input = $bridge->_input->filterExternal(array(
        'postId'    => XenForo_Input::STRING,
        'mode'      => XenForo_Input::UINT,
    ), $params);
    
    $postIds = array_unique(array_map('intval', explode(',', $input['postId'])));
    if ($input['mode'] == 1)
        $result = $inlineModPostModel->approvePosts($postIds, array(), $errorPhraseKey);
    else
        $result = $inlineModPostModel->unapprovePosts($postIds, array(), $errorPhraseKey);
    
    $response = new xmlrpcval(array(
        'result'        => new xmlrpcval($result, 'boolean'),
        'result_text'   => new xmlrpcval(get_xf_lang($errorPhraseKey), 'base64')
    ), 'struct');
    
    return new xmlrpcresp($response);
}

function m_delete_post_by_user_func($xmlrpc_params)
{
    $params = php_xmlrpc_decode($xmlrpc_params);
    
    $bridge = Tapatalk_Bridge::getInstance();
    $userModel = $bridge->getUserModel();
    
    $input = $bridge->_input->filterExternal(array(
        'userId'    => XenForo_Input::STRING,
        'reason'    => XenForo_Input::STRING,
    ), $params);
    
    $userId = $input['userId'];
    $user = $userModel->getUserById($userId, array('join' => XenForo_Model_User::FETCH_LAST_ACTIVITY));
    if (!$user)
    {
        get_error('requested_member_not_found');
    }
    
    if (!$userModel->couldBeSpammer($user, $errorKey))
    {
        get_error($errorKey);
    }
    
    $options = array(
        'action_threads'  => 1,
        'delete_messages' => 1,
        'ban_user'        => 0,
        'check_ips'       => 0,
        'email_user'      => 0,
        'email'           => '',
    );
    
    $spamCleanerModel = $bridge->getSpamCleanerModel();
    
    if (!$log = $spamCleanerModel->cleanUp($user, $options, $log, $errorKey))
    {
        get_error($errorKey);
    }
    
    return xmlresptrue();
}

function m_ban_user_func($xmlrpc_params)
{
    $params = php_xmlrpc_decode($xmlrpc_params);
    $visitor = XenForo_Visitor::getInstance();
    $bridge = Tapatalk_Bridge::getInstance();
    $userModel = $bridge->getUserModel();
    
    $input = $bridge->_input->filterExternal(array(
        'userName'  => XenForo_Input::STRING,
        'mode'      => XenForo_Input::UINT,
        'reason'    => XenForo_Input::STRING,
        'end_date'  => XenForo_Input::UINT,
    ), $params);
    
    $userName = $input['userName'];
    $user = $userModel->getUserByName($userName, array('join' => XenForo_Model_User::FETCH_LAST_ACTIVITY));
    if (!$user)
    {
        get_error('requested_member_not_found');
    }
    if (!$visitor->hasAdminPermission('ban'))
    {
        get_error('security_error_occurred');
    }
    if (!$userModel->couldBeSpammer($user, $errorKey))
    {
        get_error($errorKey);
    }
    $input['user_id'] = $user['user_id'];
    if ($ban = $bridge->getBanningModel()->getBannedUserById($user['user_id']))
	{
		$existing = true;
	}
	else
	{
	    $existing = false;
	}
    if (!$userModel->ban($input['user_id'], $input['end_date'], $input['reason'], $existing, $errorKey))
	{
		get_error($errorKey);
	}
    $options = array(
        'action_threads'  => $input['mode'] == 2 ? 1 : 0,
        'delete_messages' => $input['mode'] == 2 ? 1 : 0,
        'email_user'      => 0,
        'email'           => '',
    );
    
    $spamCleanerModel = $bridge->getSpamCleanerModel();
    
    if (!$log = $spamCleanerModel->cleanUp($user, $options, $log, $errorKey))
    {
        get_error($errorKey);
    }
    
    return xmlresptrue();
}

function m_unban_user_func($xmlrpc_params){
    $visitor = XenForo_Visitor::getInstance();
    $visitor_permissions = $visitor->getPermissions();
    $params = php_xmlrpc_decode($xmlrpc_params);    
    $bridge = Tapatalk_Bridge::getInstance();
    $userModel = $bridge->getUserModel();
    
    $input = $bridge->_input->filterExternal(array("user_id"=>XenForo_Input::STRING),$params);
    $userId = $input['user_id'];
    $user = $userModel->getUserById($userId, array('join' => XenForo_Model_User::FETCH_LAST_ACTIVITY));
    if (!$user)
    {
        get_error('requested_member_not_found');
    }    
    if (!$visitor->hasAdminPermission('ban'))
    {
        get_error('security_error_occurred');
    }
    
    if (!$user['is_banned']){
        get_error('existing_data_required_data_writer_not_found');
    }
    $userModel->liftBan($userId);
    return xmlresptrue();
}

// Moderation Queue

function m_get_moderate_topic_func($xmlrpc_params)
{
    $params = php_xmlrpc_decode($xmlrpc_params);
    
    $bridge = Tapatalk_Bridge::getInstance();
    $moderationQueueModel = $bridge->getModerationQueueModel();
    
    $queue = $moderationQueueModel->getModerationQueueEntries();
    foreach($queue as $key => $value)
        if ($value['content_type'] != 'thread')
            unset($queue[$key]);
    
    $datas = $moderationQueueModel->getVisibleModerationQueueEntriesForUser($queue);
    
    $total_topic_num = count($datas);
    $topics = array();
    $threadModel = $bridge->getThreadModel();
    $fetchOptions = array(
        'join' => XenForo_Model_Thread::FETCH_USER | XenForo_Model_Thread::FETCH_FORUM,
    );
    
    $forums = $bridge->getForumModel()->getForums();
    $visitor = XenForo_Visitor::getInstance();
    $userModel = $bridge->getUserModel();
    
    foreach($datas as $data)
    {
        $thread = $threadModel->getThreadById($data['content_id'], $fetchOptions);
        $thread = $threadModel->prepareThread($thread, $forums[$thread['node_id']]);
        
        $starter_avatar = get_avatar($thread);
        $topics[] = new xmlrpcval(array(
            'forum_id'      => new xmlrpcval($thread['node_id'], 'string'),
            'forum_name'    => new xmlrpcval($thread['node_title'], 'base64'),
            'topic_id'      => new xmlrpcval($thread['thread_id'], 'string'),
            'topic_title'   => new xmlrpcval($thread['title'], 'base64'),
            'prefix'        => new xmlrpcval(get_prefix_name($thread['prefix_id']), 'base64'),
        'topic_author_name' => new xmlrpcval($thread['username'], 'base64'),
            'icon_url'      => new xmlrpcval($starter_avatar, 'string'),
            'post_time'     => new xmlrpcval(mobiquo_iso8601_encode($thread['post_date']), 'dateTime.iso8601'),
            'timestamp'     => new xmlrpcval($thread['post_date'],'string'),
            'short_content' => new xmlrpcval($bridge->renderPostPreview($data['content']['message'], 200), 'base64'),
            
            'new_post'      => new xmlrpcval($thread['isNew'] ? true : false, 'boolean'),
            'reply_number'  => new xmlrpcval(intval($thread['reply_count']), 'int'),
            'view_number'   => new xmlrpcval(intval($thread['view_count']), 'int'),
            
            'is_deleted'    => new xmlrpcval($thread['isDeleted'], 'boolean'),
            'can_delete'    => new xmlrpcval($threadModel->canDeleteThread($thread, $forums[$thread['node_id']]), 'boolean'),
            'is_closed'     => new xmlrpcval($thread['discussion_open'] == 0, 'boolean'),
            'can_close'     => new xmlrpcval($threadModel->canLockUnlockThread($thread, $forums[$thread['node_id']]), 'boolean'),
            'is_approved'   => new xmlrpcval(!$thread['isModerated'], 'boolean'),
            'can_approve'   => new xmlrpcval($threadModel->canApproveUnapproveThread($thread, $forums[$thread['node_id']]), 'boolean'),
            'is_sticky'     => new xmlrpcval($thread['sticky'] ? true : false, 'boolean'),
            'can_stick'     => new xmlrpcval($threadModel->canStickUnstickThread($thread, $forums[$thread['node_id']]), 'boolean'),
            'can_move'      => new xmlrpcval($threadModel->canMoveThread($thread, $forums[$thread['node_id']]), 'boolean'),
            'can_ban'       => new xmlrpcval($visitor->hasAdminPermission('ban') && $userModel->couldBeSpammer($thread), 'boolean'),
            'is_ban'        => new xmlrpcval($thread['is_banned'], 'boolean'),
        ), 'struct');
    }
    
    $result = new xmlrpcval(array(
        'total_topic_num'   => new xmlrpcval($total_topic_num, 'int'),
        'topics'            => new xmlrpcval($topics, 'array'),
    ), 'struct');

    return new xmlrpcresp($result);
}

function m_get_moderate_post_func($xmlrpc_params)
{
    $params = php_xmlrpc_decode($xmlrpc_params);
    
    $bridge = Tapatalk_Bridge::getInstance();
    $moderationQueueModel = $bridge->getModerationQueueModel();
    
    $queue = $moderationQueueModel->getModerationQueueEntries();
    foreach($queue as $key => $value)
        if ($value['content_type'] != 'post')
            unset($queue[$key]);
    
    $datas = $moderationQueueModel->getVisibleModerationQueueEntriesForUser($queue);
    
    $total_post_num = count($datas);
    $posts = array();
    $postModel = $bridge->getPostModel();
    $fetchOptions = array(
        'join' => XenForo_Model_Post::FETCH_USER | XenForo_Model_Post::FETCH_FORUM,
    );
    
    $forums = $bridge->getForumModel()->getForums();
    $visitor = XenForo_Visitor::getInstance();
    $userModel = $bridge->getUserModel();
    
    foreach($datas as $data)
    {
        $post = $postModel->getPostById($data['content_id'], $fetchOptions);
        
        $starter_avatar = get_avatar($post);
        $posts[] = new xmlrpcval(array(
            'forum_id'      => new xmlrpcval($post['node_id'], 'string'),
            'forum_name'    => new xmlrpcval($post['node_title'], 'base64'),
            'topic_id'      => new xmlrpcval($post['thread_id'], 'string'),
            'topic_title'   => new xmlrpcval($post['title'], 'base64'),
            'post_id'       => new xmlrpcval($post['post_id'], 'string'),
            'post_title'    => new xmlrpcval($post['title'], 'base64'),
            'post_author_name'  => new xmlrpcval($post['username'], 'base64'),
            'icon_url'          => new xmlrpcval($starter_avatar, 'string'),
            'post_time'         => new xmlrpcval(mobiquo_iso8601_encode($post['post_date']), 'dateTime.iso8601'),
            'timestamp'         => new xmlrpcval($post['post_date'],'string'),
            'short_content'     => new xmlrpcval($bridge->renderPostPreview($data['content']['message'], 200), 'base64'),
            
            'reply_number'  => new xmlrpcval(intval($post['reply_count']), 'int'),
            'view_number'   => new xmlrpcval(intval($post['view_count']), 'int'),
            
            'is_closed'     => new xmlrpcval($post['discussion_open'] == 0, 'boolean'),
            
            'is_deleted'        => new xmlrpcval($postModel->isDeleted($post), 'boolean'),
            'can_delete'        => new xmlrpcval($postModel->canDeletePost($post, $post, $forums[$post['node_id']]), 'boolean'),
            'is_approved'       => new xmlrpcval(!$postModel->isModerated($post), 'boolean'),
            'can_approve'       => new xmlrpcval($postModel->canApproveUnapprovePost($post, $post, $forums[$post['node_id']]), 'boolean'),
            'can_move'          => new xmlrpcval($postModel->canMovePost($post, $post, $forums[$post['node_id']]), 'boolean'),
            'can_ban'           => new xmlrpcval($visitor->hasAdminPermission('ban') && $userModel->couldBeSpammer($post), 'boolean'),
            'is_ban'            => new xmlrpcval($post['is_banned'], 'boolean'),
        ), 'struct');
    }
    
    $result = new xmlrpcval(array(
        'total_post_num'    => new xmlrpcval($total_post_num, 'int'),
        'posts'             => new xmlrpcval($posts, 'array'),
    ), 'struct');

    return new xmlrpcresp($result);
}

function m_get_report_post_func($xmlrpc_params)
{
    $params = php_xmlrpc_decode($xmlrpc_params);
    
    $bridge = Tapatalk_Bridge::getInstance();
    $reportModel = $bridge->getReportModel();
    
    $activeReports = $reportModel->getActiveReports();
    
    if (XenForo_Application::isRegistered('reportCounts'))
    {
        $reportCounts = XenForo_Application::get('reportCounts');
        if (count($activeReports) != $reportCounts['activeCount'])
        {
            $reportModel->rebuildReportCountCache(count($activeReports));
        }
    }
    
    $reports = $reportModel->getVisibleReportsForUser($activeReports);
    
    $session = XenForo_Application::get('session');
    $sessionReportCounts = $session->get('reportCounts');
    
    if (!is_array($sessionReportCounts) || $sessionReportCounts['total'] != count($reports))
    {
        $sessionReportCounts = $reportModel->getSessionCountsForReports($reports, XenForo_Visitor::getUserId());
        $sessionReportCounts['lastBuildDate'] = XenForo_Application::$time;
        $session->set('reportCounts', $sessionReportCounts);
    }
    
    $forums = $bridge->getForumModel()->getForums();
    $visitor = XenForo_Visitor::getInstance();
    $userModel = $bridge->getUserModel();
    $postModel = $bridge->getPostModel();
    $fetchOptions = array(
        'join' => XenForo_Model_Post::FETCH_USER | XenForo_Model_Post::FETCH_FORUM,
    );
    $report_list = array();
    foreach($reports as $rid => $report)
    {
		$comment = end($reportModel->getReportComments($report['report_id']));
        $post = $postModel->getPostById($report['content_id'], $fetchOptions);
        
        if (empty($post)) {
            unset($post[$rid]);
            continue;
        }
        
        $starter_avatar = get_avatar($report);
        $report_list[] = new xmlrpcval(array(
            'forum_id'      => new xmlrpcval($report['extraContent']['node_id'], 'string'),
            'forum_name'    => new xmlrpcval($report['extraContent']['node_title'], 'base64'),
            'topic_id'      => new xmlrpcval($report['extraContent']['thread_id'], 'string'),
            'topic_title'   => new xmlrpcval($report['extraContent']['thread_title'], 'base64'),
            'post_id'       => new xmlrpcval($report['content_id'], 'string'),
            'post_title'    => new xmlrpcval($report['extraContent']['thread_title'], 'base64'),
            'post_author_name'  => new xmlrpcval($report['username'], 'base64'),
            'icon_url'          => new xmlrpcval($starter_avatar, 'string'),
            'post_time'         => new xmlrpcval(mobiquo_iso8601_encode($report['first_report_date']), 'dateTime.iso8601'),
            'timestamp'         => new xmlrpcval($report['first_report_date'],'string'),
            'short_content'     => new xmlrpcval($bridge->renderPostPreview($report['extraContent']['message'], 200), 'base64'),
            
            'reply_number'  => new xmlrpcval(intval($post['reply_count']), 'int'),
            'view_number'   => new xmlrpcval(intval($post['view_count']), 'int'),
            
            'is_closed'     => new xmlrpcval($post['discussion_open'] == 0, 'boolean'),
            
            'is_deleted'        => new xmlrpcval($postModel->isDeleted($post), 'boolean'),
            'can_delete'        => new xmlrpcval($postModel->canDeletePost($post, $post, $forums[$post['node_id']]), 'boolean'),
            'is_approved'       => new xmlrpcval(!$postModel->isModerated($post), 'boolean'),
            'can_approve'       => new xmlrpcval($postModel->canApproveUnapprovePost($post, $post, $forums[$post['node_id']]), 'boolean'),
            'can_move'          => new xmlrpcval($postModel->canMovePost($post, $post, $forums[$post['node_id']]), 'boolean'),
            'can_ban'           => new xmlrpcval($visitor->hasAdminPermission('ban') && $userModel->couldBeSpammer($report), 'boolean'),
            'is_ban'            => new xmlrpcval($report['is_banned'], 'boolean'),
            'reported_by_id'    => new xmlrpcval($report['last_modified_user_id'], 'string'),
            'reported_by_name'  => new xmlrpcval($report['last_modified_username'], 'base64'),
            'report_reason'     => new xmlrpcval(isset($comment['message'])?$comment['message']:"", 'base64'),
        ), 'struct');
    }
    
    $result = new xmlrpcval(array(
        'total_report_num'    => new xmlrpcval(count($reports), 'int'),
        'reports'             => new xmlrpcval($report_list, 'array'),
    ), 'struct');

    return new xmlrpcresp($result);
}

function m_close_report_func($xmlrpc_params){
    $visitor = XenForo_Visitor::getInstance();
    
    if (!$visitor->get("is_admin")&&!$visitor->hasAdminPermission("user")){
        get_error('security_error_occurred');
    }
    $params= php_xmlrpc_decode($xmlrpc_params);
    $bridge=Tapatalk_Bridge::getInstance();

    $input=$bridge->_input->filterExternal(array("post_id"=>XenForo_Input::STRING), $params);

    if ($visitor->hasPermission("profilePost", "post"))
    $reportModel=$bridge->getReportModel();
    $report=$reportModel->getReportByContent("post",$input['post_id']);
    if(!$report){
        get_error('requested_report_not_found');
    }
    if (!$reportModel->canUpdateReport($report))
    {
        get_error('you_can_no_longer_update_this_report');
    }
    $dw = XenForo_DataWriter::create('XenForo_DataWriter_ReportComment');
    $dw->bulkSet(array(
            'report_id' => $report['report_id'],
            'user_id' => $visitor['user_id'],
            'username' => $visitor['username'],
            'message' => '',
            'state_change' => 'resolved'
    ));
    $dw->save();
    
    $dw = XenForo_DataWriter::create('XenForo_DataWriter_Report');
    $dw->setExistingData($report, true);
    $dw->set('report_state', 'resolved');
    $dw->save();
    return xmlresptrue();
}