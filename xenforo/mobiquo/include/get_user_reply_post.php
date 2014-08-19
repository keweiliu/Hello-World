<?php

defined('IN_MOBIQUO') or exit;

function get_user_reply_post_func($xmlrpc_params)
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
        'user_content'      => '',
        'order'             => 'date',
        'group_discussion'  => 0,
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
        $postModel = $bridge->getPostModel();

        foreach($results['results'] as $result)
        {
            $thread = $result['content'];
            $post_id = $result['0'] == 'thread' ? $thread['first_post_id'] : $thread['post_id'];
            $post = $postModel->getPostById($post_id, array('join' => XenForo_Model_Post::FETCH_USER));
            
            $post_list[] = new xmlrpcval(array(
                'forum_id'          => new xmlrpcval($thread['node_id'], 'string'),
                'forum_name'        => new xmlrpcval($thread['node_title'], 'base64'),
                'topic_id'          => new xmlrpcval($thread['thread_id'], 'string'),
                'topic_title'       => new xmlrpcval($thread['title'], 'base64'),
                
                'post_id'           => new xmlrpcval($post_id, 'string'),
                'post_time'         => new xmlrpcval(mobiquo_iso8601_encode($post['post_date']), 'dateTime.iso8601'),
                'timestamp'         => new xmlrpcval($post['post_date'],'string'),
                'short_content'     => new xmlrpcval($bridge->renderPostPreview($post['message'], 200), 'base64'),
                'icon_url'          => new xmlrpcval(get_avatar($post), 'string'),
                'can_ban'           => new xmlrpcval($visitor->hasAdminPermission('ban') && $userModel->couldBeSpammer($post), 'boolean'),
                'reply_number'      => new xmlrpcval($thread['reply_count'], 'int'),
                'view_number'       => new xmlrpcval($thread['view_count'], 'int'),
                'new_post'          => new xmlrpcval($thread['isNew'], 'boolean'),
                'can_delete'        => new xmlrpcval($postModel->canDeletePost($post, $thread, $forums[$thread['node_id']], 'soft', $errorPhraseKey), 'boolean'),
                'can_approve'       => new xmlrpcval($postModel->canApproveUnapprovePost($post, $thread, $forums[$thread['node_id']], $errorPhraseKey), 'boolean'),
                'can_move'          => new xmlrpcval($postModel->canMovePost($post, $thread, $forums[$thread['node_id']], $errorPhraseKey), 'boolean'),
                'is_approved'       => new xmlrpcval(!$thread['isModerated'], 'boolean'),
                'is_deleted'        => new xmlrpcval($thread['isDeleted'], 'boolean'),
            ), 'struct');
        }
    }

    $response = new xmlrpcval($post_list, 'array');

    return new xmlrpcresp($response);
}
