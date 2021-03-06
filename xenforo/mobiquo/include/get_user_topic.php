<?php

defined('IN_MOBIQUO') or exit;

function get_user_topic_func($xmlrpc_params)
{
    $params = php_xmlrpc_decode($xmlrpc_params);

    $bridge = Tapatalk_Bridge::getInstance();
    $visitor = XenForo_Visitor::getInstance();

    $userModel = $bridge->getUserModel();
    $threadModel = $bridge->getThreadModel();
    $postModel = $bridge->getPostModel();
    $searchModel = $bridge->getSearchModel();
    $visitorUserId = XenForo_Visitor::getUserId();

    $data = $bridge->_input->filterExternal(array(
        'username'  => XenForo_Input::STRING,
        'user_id'   => XenForo_Input::UINT,
    ), $params);

    if (isset($data['user_id']) && !empty($data['user_id']))
    {
        $requestuser = $userModel->getUserById($data['user_id']);
        $data['username'] = $requestuser['username'];
    }

    $input = array(
        'type'              => 'post',
        'keywords'          => '',
        'title_only'        => 0,
        'date'              => 0,
        'users'             => $data['username'],
        'nodes'             => array(),
        'child_nodes'       => 1,
        'user_content'      => 'thread',
        'order'             => 'date',
        'group_discussion'  => 1,
    );

    $constraints = $searchModel->getGeneralConstraintsFromInput($input, $errors);
    if ($errors)
    {
        return $bridge->responseError($errors);
    }

    $search = $searchModel->getExistingSearch(
        $input['type'], $input['keywords'], $constraints, $input['order'], $input['group_discussion'], $visitorUserId
    );

    if (!$search)
    {
        $searcher = new XenForo_Search_Searcher($searchModel);

        $typeHandler = $searchModel->getSearchDataHandler('post');

        if ($typeHandler)
        {
            $results = $searcher->searchType(
                $typeHandler, $input['keywords'], $constraints, $input['order'], $input['group_discussion']
            );
        }
        else
        {
            $results = $searcher->searchGeneral($input['keywords'], $constraints, $input['order']);
        }

        if (!$results)
        {
            $errors = $searcher->getErrors();
            if ($errors)
            {
                return $bridge->responseError(reset($errors));
            }
            else
            {
                return new xmlrpcresp(new xmlrpcval(array(), 'array'));
            }
        }
        $search = $searchModel->insertSearch(
            $results, $input['type'], '', $constraints, $input['order'], $input['group_discussion']
        );
    }

    if (!isset($search['searchResults']))
    {
        $search['searchResults'] = json_decode($search['search_results']);
    }

    $results = array_slice($search['searchResults'], 0, 50);
    $results = $searchModel->getSearchResultsForDisplay($results);

    $topic_list = array();
    if($results)
    {
        $forums = $bridge->getForumModel()->getForums();
        $threadWatchModel = $bridge->getThreadWatchModel();

        foreach($results['results'] as $result)
        {
            $thread = $result['content'];
            $lastPost = $postModel->getPostById($thread['last_post_id'], array('join' => XenForo_Model_Post::FETCH_USER));
            $topic_list[] = new xmlrpcval(array(
                'forum_id'          => new xmlrpcval($thread['node_id'], 'string'),
                'forum_name'        => new xmlrpcval($thread['node_title'], 'base64'),
                'topic_id'          => new xmlrpcval($thread['thread_id'], 'string'),
                'topic_title'       => new xmlrpcval($thread['title'], 'base64'),
                'prefix'            => new xmlrpcval(get_prefix_name($thread['prefix_id']), 'base64'),
                
          'last_reply_author_id'    => new xmlrpcval($lastPost['user_id'], 'string'),
          'last_reply_author_name'  => new xmlrpcval($lastPost['username'], 'base64'),
                'user_type'         => new xmlrpcval(get_usertype_by_item('', $lastPost['display_style_group_id'], $lastPost['is_banned']), 'base64'),
                'icon_url'          => new xmlrpcval(get_avatar($lastPost), 'string'),
                'short_content'     => new xmlrpcval($bridge->renderPostPreview($lastPost['message'], 200), 'base64'),
                'post_time'         => new xmlrpcval(mobiquo_iso8601_encode($lastPost['post_date']), 'dateTime.iso8601'),
                'timestamp'         => new xmlrpcval($lastPost['post_date'],'string'),
                'reply_number'      => new xmlrpcval($thread['reply_count'], 'int'),
                'view_number'       => new xmlrpcval($thread['view_count'], 'int'),
                'new_post'          => new xmlrpcval($thread['isNew'], 'boolean'),
                'like_count'        => new xmlrpcval($thread['first_post_likes'], 'int'),
                'is_closed'         => new xmlrpcval($thread['discussion_open'] == 0, 'boolean'),
                'can_subscribe'     => new xmlrpcval(true, 'boolean'),
                'is_subscribed'     => new xmlrpcval($threadWatchModel->getThreadWatchStateForVisitor($thread['thread_id'], false), 'boolean'),
                'can_delete'        => new xmlrpcval($threadModel->canDeleteThread($thread, $forums[$thread['node_id']], 'soft'), 'boolean'),
                'can_close'         => new xmlrpcval($threadModel->canLockUnlockThread($thread, $forums[$thread['node_id']]), 'boolean'),
                'can_approve'       => new xmlrpcval($threadModel->canApproveUnapproveThread($thread, $forums[$thread['node_id']]), 'boolean'),
                'is_sticky'         => new xmlrpcval($thread['sticky'] ? true : false, 'boolean'),
                'can_stick'         => new xmlrpcval($threadModel->canStickUnstickThread($thread, $forums[$thread['node_id']]), 'boolean'),
                'can_rename'        => new xmlrpcval($threadModel->canEditThreadTitle($thread, $forums[$thread['node_id']]), 'boolean'),
                'can_move'          => new xmlrpcval($threadModel->canMoveThread($thread, $forums[$thread['node_id']]), 'boolean'),
                'is_approved'       => new xmlrpcval(!$thread['isModerated'], 'boolean'),
                'is_deleted'        => new xmlrpcval($thread['isDeleted'], 'boolean'),
                'can_ban'           => new xmlrpcval($visitor->hasAdminPermission('ban') && $userModel->couldBeSpammer($thread), 'boolean'),
                'is_ban'            => new xmlrpcval($thread['is_banned'], 'boolean'),
            ), 'struct');
        }
    }

    $response = new xmlrpcval($topic_list, 'array');

    return new xmlrpcresp($response);
}
