<?php

defined('IN_MOBIQUO') or exit;

function search_topic_func($xmlrpc_params)
{
    $params = php_xmlrpc_decode($xmlrpc_params);

    $bridge = Tapatalk_Bridge::getInstance();
    $visitor = XenForo_Visitor::getInstance();

    if (!$visitor->canSearch())
    {
        $bridge->getNoPermissionResponseException();
        return;
    }

    $userModel = $bridge->getUserModel();
    $threadModel = $bridge->getThreadModel();
    $postModel = $bridge->getPostModel();
    $searchModel = $bridge->getSearchModel();
    $visitorUserId = XenForo_Visitor::getUserId();

    $data = $bridge->_input->filterExternal(array(
            'search_string' => XenForo_Input::STRING,
            'start_num' => XenForo_Input::UINT,
            'last_num' => XenForo_Input::UINT,
            'search_id' => XenForo_Input::UINT,
    ), $params);

    $origKeywords = $data['search_string'];
    $data['search_string'] = XenForo_Helper_String::censorString($data['search_string'], null, '');

    $constraints = array(

    );

    $typeHandler = $searchModel->getSearchDataHandler('post');


    $search = $searchModel->getSearchById($data['search_id']);

    if ($search && $search['user_id'] != XenForo_Visitor::getUserId())
    {
        if ($search['search_query'] === '' || $search['search_query'] !== $data['search_string'])
        {
            $search = false;
            //return $bridge->responseError(new XenForo_Phrase('requested_search_not_found'));
        }
    }

    if(!$search){
        $search = $searchModel->getExistingSearch(
            'post', $data['search_string'], $constraints, 'date', true, $visitorUserId
        );
    }

    if (!$search)
    {
        $searcher = new XenForo_Search_Searcher($searchModel);

        $results = $searcher->searchType(
            $typeHandler, $data['search_string'], $constraints, 'date', true
        );

        if (!$results)
        {
            $errors = $searcher->getErrors();
            if ($errors)
            {
                return $bridge->responseError(reset($errors));
            }
            else
            {
                return $bridge->responseMessage(new XenForo_Phrase('no_results_found'));
            }
        }

        $search = $searchModel->insertSearch(
            $results, 'post', $origKeywords, $constraints, 'date', true
        );
    }

    list($start, $limit) = process_page($data['start_num'], $data['last_num']);

    if (!isset($search['searchResults']))
    {
        $search['searchResults'] = json_decode($search['search_results']);
    }

    $results = array_slice($search['searchResults'], $start, $limit);
    $results = $searchModel->getSearchResultsForDisplay($results);

    $topic_list = array();
    if($results)
    {
        $forums = $bridge->getForumModel()->getForums();
        $threadWatchModel = $bridge->getThreadWatchModel();
        
        foreach($results['results'] as $result)
        {
            $thread = $result['content'];
            $topic_list[] = new xmlrpcval(array(
                'forum_id'          => new xmlrpcval($thread['node_id'], 'string'),
                'forum_name'        => new xmlrpcval($thread['node_title'], 'base64'),
                'topic_id'          => new xmlrpcval($thread['thread_id'], 'string'),
                'topic_title'       => new xmlrpcval($thread['title'], 'base64'),
                'prefix'            => new xmlrpcval(get_prefix_name($thread['prefix_id']), 'base64'),
                'post_id'           => new xmlrpcval($thread['last_post_id'], 'string'),
                'post_author_id'    => new xmlrpcval($thread['user_id'], 'string'),
                'post_author_name'  => new xmlrpcval($thread['username'], 'base64'),
                'user_type'         => new xmlrpcval(get_usertype_by_item('', $thread['display_style_group_id'], $thread['is_banned']), 'base64'),
                'icon_url'          => new xmlrpcval(get_avatar($thread), 'string'),
                'post_time'         => new xmlrpcval(mobiquo_iso8601_encode($thread['post_date']), 'dateTime.iso8601'),
                'timestamp'         => new xmlrpcval($thread['post_date'],'string'),
                'reply_number'      => new xmlrpcval($thread['reply_count'], 'int'),
                'new_post'          => new xmlrpcval($thread['isNew'] > 0, 'boolean'),
                'view_number'       => new xmlrpcval($thread['view_count'], 'int'),
                'short_content'     => new xmlrpcval($bridge->renderPostPreview($thread['message'], 200), 'base64'),
                'like_count'        => new xmlrpcval($thread['first_post_likes'], 'int'),

                'is_closed'         => new xmlrpcval($thread['discussion_open'] == 0, 'boolean'),
                'is_subscribed'     => new xmlrpcval($threadWatchModel->getThreadWatchStateForVisitor($thread['thread_id'], false), 'boolean'),
                'can_subscribe'     => new xmlrpcval(true, 'boolean'), // implied by view permissions
                'can_delete'        => new xmlrpcval($threadModel->canDeleteThread($thread, $forums[$thread['node_id']], 'soft'), 'boolean'),
                'can_close'         => new xmlrpcval($threadModel->canLockUnlockThread($thread, $forums[$thread['node_id']]), 'boolean'),
                'can_approve'       => new xmlrpcval($threadModel->canApproveUnapproveThread($thread, $forums[$thread['node_id']]), 'boolean'),
                'can_rename'        => new xmlrpcval($threadModel->canEditThreadTitle($thread, $forums[$thread['node_id']]), 'boolean'),
                'can_stick'         => new xmlrpcval($threadModel->canStickUnstickThread($thread, $forums[$thread['node_id']]), 'boolean'),
                'can_move'          => new xmlrpcval($threadModel->canMoveThread($thread, $forums[$thread['node_id']]), 'boolean'),
                'is_approved'       => new xmlrpcval(!$thread['isModerated'], 'boolean'),
                'is_deleted'        => new xmlrpcval($thread['isDeleted'], 'boolean'),
                'is_sticky'         => new xmlrpcval($thread['sticky'], 'boolean'),
                'can_ban'           => new xmlrpcval($visitor->hasAdminPermission('ban') && $userModel->couldBeSpammer($thread), 'boolean'),
                'is_ban'            => new xmlrpcval($thread['is_banned'], 'boolean'),
            ), 'struct');
        }
    }

    $result = new xmlrpcval(array(
        'total_topic_num' => new xmlrpcval($search['result_count'], 'int'),
        'search_id'       => new xmlrpcval($search['search_id'], 'string'),
        'topics'          => new xmlrpcval($topic_list, 'array')
    ), 'struct');

    return new xmlrpcresp($result);
}
