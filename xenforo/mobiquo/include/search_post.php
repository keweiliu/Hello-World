<?php

defined('IN_MOBIQUO') or exit;

function search_post_func($xmlrpc_params)
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
        'group_discussion'  => 0,
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
            'post', $data['search_string'], $constraints, 'date', false, $visitorUserId
        );
    }

    if (!$search)
    {
        $searcher = new XenForo_Search_Searcher($searchModel);

        $results = $searcher->searchType(
            $typeHandler, $data['search_string'], $constraints, 'date', false
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
            $results, 'post', $origKeywords, $constraints, 'date', false
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
    if($results) {
        $threadIds = array();
        foreach($results['results'] as $result){
            if(!empty($result['content']['thread_id']))
                $threadIds[]=$result['content']['thread_id'];
        }
        $threadFetchOptions = array(
            'readUserId' => $visitor['user_id'],
            'watchUserId' => $visitor['user_id'],
            'postCountUserId' => $visitor['user_id'],
            'join' => XenForo_Model_Thread::FETCH_FIRSTPOST | XenForo_Model_Thread::FETCH_USER | XenForo_Model_Thread::FETCH_FORUM
        );

        $threads = $threadModel->getThreadsByIds($threadIds, $threadFetchOptions);

        $forums = $bridge->getForumModel()->getForums();
        $threadsinfo = array();

        foreach($threads as $thread)
        {
            $threadsinfo[$thread['thread_id']] = $thread;
        }

        foreach($results['results'] as $result)
        {
            $post = $result['content'];
            $thread = $threadsinfo[$post['thread_id']];

            $threadModel->standardizeViewingUserReferenceForNode($thread['node_id'], $viewingUser, $nodePermissions);

            $thread = $threadModel->prepareThread($thread, $forums[$thread['node_id']], $nodePermissions, $viewingUser);

            $newTopic = array(
                'forum_id'                      => new xmlrpcval($thread['node_id'], 'string'),
                'forum_name'                    => new xmlrpcval($thread['node_title'], 'base64'),
                'topic_id'                      => new xmlrpcval($thread['thread_id'], 'string'),
                'topic_title'                   => new xmlrpcval($thread['title'], 'base64'),
                
                'post_id'                       => new xmlrpcval($post['post_id']),
                'post_title'                    => new xmlrpcval('', 'base64'), //unsupported
                'post_author_id'                => new xmlrpcval($post['user_id'], 'string'),
                'post_author_name'              => new xmlrpcval($post['username'], 'base64'),
                'user_type'                     => new xmlrpcval(get_usertype_by_item('', $post['display_style_group_id'], $post['is_banned']), 'base64'),
                'is_subscribed'                 => new xmlrpcval((boolean)$thread['thread_is_watched'], 'boolean'),
                'can_subscribe'                 => new xmlrpcval(true, 'boolean'), // implied by view permissions
                'is_closed'                     => new xmlrpcval($post['discussion_open'] == 0, 'boolean'),
                'icon_url'                      => new xmlrpcval(get_avatar($post), 'string'),
                'post_time'                     => new xmlrpcval(mobiquo_iso8601_encode($post['post_date']), 'dateTime.iso8601'),
                'timestamp'                     => new xmlrpcval($post['post_date'],'string'),
                'reply_number'                  => new xmlrpcval($thread['reply_count'], 'int'),
                'new_post'                      => new xmlrpcval(false, 'boolean'),
                'view_number'                   => new xmlrpcval($thread['view_count'], 'int'),
                'short_content'                 => new xmlrpcval($bridge->renderPostPreview($post['message'], 200), 'base64'),

                'can_delete'        => new xmlrpcval($threadModel->canDeleteThread($thread, $forums[$thread['node_id']], 'soft', $errorPhraseKey, $nodePermissions, $viewingUser), 'boolean'),
                'can_approve'       => new xmlrpcval($threadModel->canApproveUnapproveThread($thread, $forums[$thread['node_id']], $errorPhraseKey, $nodePermissions, $viewingUser), 'boolean'),
                'can_move'          => new xmlrpcval($threadModel->canMoveThread($thread, $forums[$thread['node_id']], $errorPhraseKey, $nodePermissions, $viewingUser), 'boolean'),
                'is_approved'       => new xmlrpcval(!$post['isModerated'], 'boolean'),
                'is_deleted'        => new xmlrpcval($post['isDeleted'], 'boolean'),
            );

            // this is not always available
            if(!empty($thread['post_id'])){
                $newTopic['post_id'] = new xmlrpcval($thread['post_id'], 'string');
            }

            $topic_list[] = new xmlrpcval($newTopic, 'struct');
        }
    }

    $result = new xmlrpcval(array(
        'total_post_num'  => new xmlrpcval($search['result_count'], 'int'),
        'search_id'       => new xmlrpcval($search['search_id'], 'string'),
        'posts'          => new xmlrpcval($topic_list, 'array')
    ), 'struct');

    return new xmlrpcresp($result);
}
