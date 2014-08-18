<?php

defined('IN_MOBIQUO') or exit;

function get_unread_topic_func($xmlrpc_params)
{
    $params = php_xmlrpc_decode($xmlrpc_params);
    
    $bridge = Tapatalk_Bridge::getInstance();
    $visitor = XenForo_Visitor::getInstance();
    $threadModel = $bridge->getThreadModel();
    $postModel = $bridge->getPostModel();
    $searchModel = $bridge->getSearchModel();
    
    $data = $bridge->_input->filterExternal(array(
            'start_num' => XenForo_Input::UINT,
            'last_num' => XenForo_Input::UINT
    ), $params);

    list($start, $limit) = process_page($data['start_num'], $data['last_num']);
    
    $limitOptions = array(
        'limit' => XenForo_Application::get('options')->maximumSearchResults
    );

    $threadIds = $threadModel->getUnreadThreadIds($visitor['user_id'], $limitOptions);

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
            'postCountUserId' => $visitor['user_id'], 
            'join' => XenForo_Model_Thread::FETCH_FIRSTPOST | XenForo_Model_Thread::FETCH_USER | XenForo_Model_Thread::FETCH_FORUM
        );
        $threads = $threadModel->getThreadsByIds($threadIds, $threadFetchOptions);
        
        $GLOBALS['orderids'] = array_flip($threadIds);
        uksort($threads, 'temp_cmp');
        
        $forums = $bridge->getForumModel()->getForums();
        
        $userModel = $bridge->getUserModel();
        $threadWatchModel = $bridge->getThreadWatchModel();
        
        foreach($threads as &$thread)
        {
            $threadModel->standardizeViewingUserReferenceForNode($thread['node_id'], $viewingUser, $nodePermissions);
            
            $thread = $threadModel->prepareThread($thread, $forums[$thread['node_id']], $nodePermissions, $viewingUser);
            $lastPost = $postModel->getPostById($thread['last_post_id'], array('join' => XenForo_Model_Post::FETCH_USER));
            $topic_list[] = new xmlrpcval(array(
                'forum_id'          => new xmlrpcval($thread['node_id'], 'string'),
                'forum_name'        => new xmlrpcval($thread['node_title'], 'base64'),
                'topic_id'          => new xmlrpcval($thread['thread_id'], 'string'),
                'topic_title'       => new xmlrpcval($thread['title'], 'base64'),
                'prefix'            => new xmlrpcval(get_prefix_name($thread['prefix_id']), 'base64'),
                'post_author_id'    => new xmlrpcval($lastPost['user_id'], 'string'),
                'post_author_name'  => new xmlrpcval($lastPost['username'], 'base64'),
                'user_type'         => new xmlrpcval(get_usertype_by_item('', $lastPost['display_style_group_id'], $lastPost['is_banned']), 'base64'),
                'icon_url'          => new xmlrpcval(get_avatar($lastPost), 'string'),
                'short_content'     => new xmlrpcval($bridge->renderPostPreview($lastPost['message'], 200), 'base64'),
                'post_time'         => new xmlrpcval(mobiquo_iso8601_encode($lastPost['post_date']), 'dateTime.iso8601'),
                'timestamp'         => new xmlrpcval($lastPost['post_date'],'string'),
                'can_subscribe'     => new xmlrpcval(true, 'boolean'), // implied by view permissions
                'is_subscribed'     => new xmlrpcval($threadWatchModel->getThreadWatchStateForVisitor($thread['thread_id'], false), 'boolean'),
                'is_closed'         => new xmlrpcval($thread['discussion_open'] == 0, 'boolean'),
                'reply_number'      => new xmlrpcval($thread['reply_count'], 'int'),
                'view_number'       => new xmlrpcval($thread['view_count'], 'int'),
                'new_post'          => new xmlrpcval(true, 'boolean'), // can be assumed to be true
                'like_count'        => new xmlrpcval($thread['first_post_likes'], 'int'),
                'can_rename'        => new xmlrpcval($threadModel->canEditThreadTitle($thread, $forums[$thread['node_id']]), 'boolean'),
                'can_delete'        => new xmlrpcval($threadModel->canDeleteThread($thread, $forums[$thread['node_id']], 'soft'), 'boolean'),
                'can_close'         => new xmlrpcval($threadModel->canLockUnlockThread($thread, $forums[$thread['node_id']]), 'boolean'),
                'can_approve'       => new xmlrpcval($threadModel->canApproveUnapproveThread($thread, $forums[$thread['node_id']]), 'boolean'),
                'is_sticky'         => new xmlrpcval($thread['sticky'] ? true : false, 'boolean'),
                'can_stick'         => new xmlrpcval($threadModel->canStickUnstickThread($thread, $forums[$thread['node_id']]), 'boolean'),
                'can_move'          => new xmlrpcval($threadModel->canMoveThread($thread, $forums[$thread['node_id']]), 'boolean'),
                'is_approved'       => new xmlrpcval(!$thread['isModerated'], 'boolean'),
                'is_deleted'        => new xmlrpcval($thread['isDeleted'], 'boolean'),
                'can_ban'           => new xmlrpcval($visitor->hasAdminPermission('ban') && $userModel->couldBeSpammer($thread), 'boolean'),
                'is_ban'            => new xmlrpcval($thread['is_banned'], 'boolean'),
            ), 'struct');
        }
    }
    
    $result = new xmlrpcval(array(
        'total_topic_num' => new xmlrpcval($totalThreads, 'int'),
        'topics'          => new xmlrpcval($topic_list, 'array')
    ), 'struct');


    return new xmlrpcresp($result);
}

function temp_cmp($id_a, $id_b)
{
    return $GLOBALS['orderids'][$id_a] > $GLOBALS['orderids'][$id_b];
}