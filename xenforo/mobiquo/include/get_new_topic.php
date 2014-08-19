<?php

defined('IN_MOBIQUO') or exit;

function get_new_topic_func($xmlrpc_params)
{
    $params = php_xmlrpc_decode($xmlrpc_params);    
    
    $bridge = Tapatalk_Bridge::getInstance();
    $visitor = XenForo_Visitor::getInstance();    
    $threadModel = $bridge->getThreadModel();
    $searchModel = $bridge->getSearchModel();
    
    $data = $bridge->_input->filterExternal(array(
            'start_num' => XenForo_Input::UINT,
            'last_num' => XenForo_Input::UINT
    ), $params);       

    list($start, $limit) = process_page($data['start_num'], $data['last_num']);
    
    $limitOptions = array(
        'limit' => XenForo_Application::get('options')->maximumSearchResults
    );
    
    $fetchOptions = $limitOptions + array(
        'order' => 'last_post_date',
        'orderDirection' => 'desc',
    );

    $threadIds = array_keys($threadModel->getThreads(array(
        'last_post_date' => array('>', XenForo_Application::$time - 86400 * 3),
        'deleted' => false,
        'moderated' => false
    ), $fetchOptions));

    $results = array();
    foreach ($threadIds AS $threadId)
    {
        $results[] = array(XenForo_Model_Search::CONTENT_TYPE => 'thread', XenForo_Model_Search::CONTENT_ID => $threadId);
    }

    $results = $searchModel->getViewableSearchResults($results);
    
    $totalThreads = count($results);
    
    $results = array_slice($results,  $start, $limit);
    $results = $searchModel->getSearchResultsForDisplay($results);
    
    $topic_list = array();
    if($results) {
        $threadIds = array();
        foreach($results['results'] as $result){
            $threadIds[]=$result['content']['thread_id'];
        }
        $threadFetchOptions = array(
            'readUserId' => $visitor['user_id'],
            'watchUserId' => $visitor['user_id'],
            'postCountUserId' => $visitor['user_id'], 
            'join' => XenForo_Model_Thread::FETCH_FIRSTPOST | XenForo_Model_Thread::FETCH_USER | XenForo_Model_Thread::FETCH_FORUM
        );
        $threads = $threadModel->getThreadsByIds($threadIds, $threadFetchOptions);
        foreach($threads as &$thread){
            $thread = $threadModel->prepareThread($thread, $thread);
        }
        
        $forums = $bridge->getForumModel()->getForums();
        
        $userModel = $bridge->getUserModel();
        $threads = array_reverse($threads);
    
        foreach($threads as &$thread)
        {
            $threadModel->standardizeViewingUserReferenceForNode($thread['node_id'], $viewingUser, $nodePermissions);
            
            $thread = $threadModel->prepareThread($thread, $forums[$thread['node_id']], $nodePermissions, $viewingUser);
            $topic_list[] = new xmlrpcval(array(            
                'forum_id'          => new xmlrpcval($thread['node_id'], 'string'),
                'forum_name'        => new xmlrpcval($thread['node_title'], 'base64'),
                'topic_id'          => new xmlrpcval($thread['thread_id'], 'string'),
                'topic_title'       => new xmlrpcval($thread['title'], 'base64'),
                'post_author_name'  => new xmlrpcval($thread['username'], 'base64'),
                'user_type'         => new xmlrpcval(get_usertype_by_item('', $thread['display_style_group_id'], $thread['is_banned']), 'base64'),
                'can_subscribe'     => new xmlrpcval(true, 'boolean'), // implied by view permissions
                'is_subscribed'     => new xmlrpcval((boolean)$thread['thread_is_watched'], 'boolean'),
                'is_closed'         => new xmlrpcval($thread['discussion_open'] == 0, 'boolean'),
                'short_content'     => new xmlrpcval($bridge->renderPostPreview($thread['message'], 200), 'base64'),
                'icon_url'          => new xmlrpcval(get_avatar($thread), 'string'),
                'post_time'         => new xmlrpcval(mobiquo_iso8601_encode($thread['last_post_date']), 'dateTime.iso8601'),
                'timestamp'         => new xmlrpcval($thread['last_post_date'],'string'),
                'reply_number'      => new xmlrpcval($thread['reply_count'], 'int'),
                'view_number'       => new xmlrpcval($thread['view_count'], 'int'),
                'new_post'          => new xmlrpcval($thread['isNew'], 'boolean'), 
                'like_count'        => new xmlrpcval($thread['first_post_likes'], 'int'),
                'can_delete'        => new xmlrpcval($threadModel->canDeleteThread($thread, $forums[$thread['node_id']], 'soft'), 'boolean'),
                'can_close'         => new xmlrpcval($threadModel->canLockUnlockThread($thread, $forums[$thread['node_id']]), 'boolean'),
                'can_approve'       => new xmlrpcval($threadModel->canApproveUnapproveThread($thread, $forums[$thread['node_id']]), 'boolean'),
                'is_sticky'         => new xmlrpcval($thread['sticky'] ? true : false, 'boolean'),
                'can_stick'         => new xmlrpcval($threadModel->canStickUnstickThread($thread, $forums[$thread['node_id']]), 'boolean'),
                'can_move'          => new xmlrpcval($threadModel->canMoveThread($thread, $forums[$thread['node_id']]), 'boolean'),
                'can_rename'        => new xmlrpcval($threadModel->canEditThreadTitle($thread, $forums[$thread['node_id']]), 'boolean'),
                'is_approved'       => new xmlrpcval(!$thread['isModerated'], 'boolean'),
                'is_deleted'        => new xmlrpcval($thread['isDeleted'], 'boolean'),
                'can_ban'           => new xmlrpcval($visitor->hasAdminPermission('ban') && $userModel->couldBeSpammer($thread), 'boolean'),
                'is_ban'            => new xmlrpcval($thread['is_banned'], 'boolean'),
            ), 'struct');
        }
    }
    
    $result = new xmlrpcval($topic_list, 'array');

    return new xmlrpcresp($result);
}