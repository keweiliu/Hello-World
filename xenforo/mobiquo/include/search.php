<?php

defined('IN_MOBIQUO') or exit;

function search_func($xmlrpc_params)
{

    $params = php_xmlrpc_decode($xmlrpc_params);

    $params = $params[0];
    $bridge = Tapatalk_Bridge::getInstance();
    $visitor = XenForo_Visitor::getInstance();

    if (!$visitor->canSearch())
    {
        return new xmlrpcresp($bridge->getNoPermissionResponseException());
    }

    $userModel = $bridge->getUserModel();
    $threadModel = $bridge->getThreadModel();
    $postModel = $bridge->getPostModel();
    $searchModel = $bridge->getSearchModel();
    $visitorUserId = XenForo_Visitor::getUserId();

    //build parameters like xenforo
    $basic_params = array();
    $basic_params[] = @$params['keywords'];
    $basic_params[] = ($params['page']-1) * $params['perpage'];//start
    $last_num = $params['page'] * $params['perpage'] - 1;
    $basic_params[] = $last_num > 0 ? $last_num : 1;//end
    $basic_params[] = isset($params['searchid']) ? $params['searchid'] : '';

    $data = $bridge->_input->filterExternal(array(
            'search_string' => XenForo_Input::STRING,
            'start_num' => XenForo_Input::UINT,
            'last_num' => XenForo_Input::UINT,
            'search_id' => XenForo_Input::UINT,
    ), $basic_params);

    $data = array_merge($data, $params);

    $origKeywords = $data['search_string'];
    $data['search_string'] = XenForo_Helper_String::censorString($data['search_string'], null, '');

    $constraints = build_constraints($data, $bridge);

    $typeHandler = $searchModel->getSearchDataHandler('post');

    //Get search directly by searchid
    $search = isset($data['searchid']) && !empty($data['searchid']) ? $searchModel->getSearchById($data['searchid']) : array();
    if (isset($search) && !empty($search))
    {
        if( $search['user_id'] != XenForo_Visitor::getUserId())
        {
            if ($search['search_query'] === '' || $search['search_query'] !== $data['search_string'])
            {
                $search = false;
            }
        }
        else
        {
            if(!isset($data['showposts']) || ($data['showposts'] !== 0 && $data['showposts'] !== 1))
            {
                if(isset($search['search_constraints']) && !empty($search['search_constraints']))
                {
                    $old_constraints = json_decode($search['search_constraints'], true);
                    $data['showposts'] = (isset($old_constraints['group_discussion']) && $old_constraints['group_discussion'] === 0) ?  1 : 0;
                }
            }
        }
    }
    //Obvious conflict constraints or invalid search user?
    if(isset($constraints['error_code']) && !empty($constraints['error_code']))
        $search['searchResults'] = array();//No results

    if(!$search){
        $search = $searchModel->getExistingSearch(
            'post', $data['search_string'], $constraints, 'date', !$data['showposts'], $visitorUserId
        );
    }

    if (!$search)
    {
        $searcher = new XenForo_Search_Searcher($searchModel);

        $results = $searcher->searchType(
        $typeHandler, $data['search_string'], $constraints, 'date', !$data['showposts']
        );

        if (!$results)
        {
            $errors = $searcher->getErrors();
            if ($errors)
            {
                return new xmlrpcresp($bridge->responseError(reset($errors)));
            }
            else
            {
                return new xmlrpcresp($bridge->responseMessage(new XenForo_Phrase('no_results_found')));
            }
        }

        $search = $searchModel->insertSearch(
        $results, 'post', $origKeywords, $constraints, 'date', !$data['showposts']
        );
    }

    list($start, $limit) = process_page($data['start_num'], $data['last_num']);

    if (!isset($search['searchResults']))
    {
        $search['searchResults'] = json_decode($search['search_results']);
    }

    $results = array_slice($search['searchResults'], $start, $limit);
    $results = $searchModel->getSearchResultsForDisplay($results);

    //Orgnize output
    $return_arr = array();

    if($results)
    {
        $topic_list = array();

        $return_arr['search_id'] = new xmlrpcval($search['search_id'], 'string');

        //Show as posts or topic?
        if(isset($data['showposts']) && !empty($data['showposts']))
        {
            $threadIds = array();
            foreach($results['results'] as $result){
                if(!empty($result['content']['thread_id']))
                $threadIds[]=$result['content']['thread_id'];
            }
            if(isset($data['threadid']) && !empty($data['threadid']))
            $threadIds = array($data['threadid']);
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
                'post_id'                       => new xmlrpcval(!empty($post['post_id']) ? $post['post_id'] : $thread['first_post_id'], 'string'),
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
            $return_arr['total_post_num'] = new xmlrpcval($search['result_count'], 'int');
            $return_arr['posts'] = new xmlrpcval($topic_list, 'array');
        }
        else
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
                'can_stick'         => new xmlrpcval($threadModel->canStickUnstickThread($thread, $forums[$thread['node_id']]), 'boolean'),
                'can_move'          => new xmlrpcval($threadModel->canMoveThread($thread, $forums[$thread['node_id']]), 'boolean'),
                'is_approved'       => new xmlrpcval(!$thread['isModerated'], 'boolean'),
                'is_deleted'        => new xmlrpcval($thread['isDeleted'], 'boolean'),
                'is_sticky'         => new xmlrpcval($thread['sticky'], 'boolean'),
                'can_ban'           => new xmlrpcval($visitor->hasAdminPermission('ban') && $userModel->couldBeSpammer($thread), 'boolean'),
                'is_ban'            => new xmlrpcval($thread['is_banned'], 'boolean'),
                ), 'struct');
            }
            $return_arr['total_topic_num'] = new xmlrpcval($search['result_count'], 'int');
            $return_arr['topics'] = new xmlrpcval($topic_list, 'array');
        }
        
    }
    else
    {
        $return_arr = array(
            'result'            => new xmlrpcval(false, 'boolean'),
            'result_text'       => new xmlrpcval('No result found', 'base64')
        );
    }

    return new xmlrpcresp(new xmlrpcval($return_arr, 'struct'));
}

function build_constraints($data, $bridge)
{
    //build constraints to fit different kind of search condition
    $constraints = array();

    if(isset($data['showposts']) && !empty($data['showposts']))
    $constraints['group_discussion'] = 0;

    if(isset($data['titleonly']) && !empty($data['titleonly']))
    $constraints['title_only'] = $data['titleonly'];

    if(isset($data['userid']) && !empty($data['userid']))
    $constraints['user'][] = $data['userid'];

    if(isset($data['threadid']) && !empty($data['threadid']))
    $constraints['thread'] = $data['threadid'];

    //convert username to userid if username is in search condition
    if(isset($data['searchuser']) && !empty($data['searchuser']))
    {
        $userModel = $bridge->getUserModel();
        $users = $userModel->getUsersByNames(array($data['searchuser']), array(), $notFound);
        if($notFound)
        {
            $constraints['error_code'] = 1;//No such user
            //no result return.
        }else {
            $constraints['user'] = array_keys($users);
        }
    }

    //get all permisson nodes
    $nodeModel = $bridge->getNodeModel();
    $nodes = $nodeModel->getAllNodes(false, true);
    $nodePermissions = $nodeModel->getNodePermissionsForPermissionCombination();
    $nodeHandlers = $nodeModel->getNodeHandlersForNodeTypes(
    $nodeModel->getUniqueNodeTypeIdsFromNodeList($nodes)
    );
    $nodes = $nodeModel->getViewableNodesFromNodeList($nodes, $nodeHandlers, $nodePermissions);
    $nodes = $nodeModel->mergeExtraNodeDataIntoNodeList($nodes, $nodeHandlers);
    $nodes = $nodeModel->prepareNodesWithHandlers($nodes, $nodeHandlers);
    $search_nodes = array_keys($nodes);

    //construct only_in & not_in constraints
    if(isset($data['forumid']) && !empty($data['forumid']))
    {
        $data['only_in'] = array($data['forumid']);
        $data['not_in'] = array();
    }

    if(isset($data['not_in']) && !empty($data['not_in']))
    {
        $data['not_in'] = array_unique($data['not_in']);
        foreach ($data['not_in'] as $index => $node)
        {
            $search_nodes = mobi_forum_exclude($node, $search_nodes, $nodeModel);
        }
    }

    if(isset($data['only_in']) && !empty($data['only_in']))
    {
        $data['only_in'] = array_unique($data['only_in']);
        $selected_nodes = array();
        foreach ($data['only_in'] as $index => $node)
        {
            $search_nodes = mobi_forum_include($node, $search_nodes, $nodeModel, $selected_nodes);
        }
    }

    if(!empty($search_nodes))
        $constraints['node'] = implode(' ', $search_nodes);
    else
        $constraints['error_code'] = 2;//No such forum


    //process search time
    if(isset($data['searchtime']) && !empty($data['searchtime']))
    {
        $newer_than_timestamp = time() - $data['searchtime'];
        $newer_than_date = getdate($newer_than_timestamp);
        $date = $newer_than_date['year']."-".$newer_than_date['mon']."-".$newer_than_date['mday'];
        //XF code from _doClean in Xenforo/Input.php
        if (!$date)
        {
            $date = 0;
        }
        else if (is_string($date))
        {
            $date = trim($date);

            if ($date === strval(intval($date)))
            {
                // date looks like an int, treat as timestamp
                $date = intval($date);
            }
            else
            {
                $tz = (XenForo_Visitor::hasInstance() ? XenForo_Locale::getDefaultTimeZone() : null);

                try
                {
                    $date = new DateTime($date, $tz);
                    if (!empty($filterOptions['dayEnd']))
                    {
                        $date->setTime(23, 59, 59);
                    }

                    $date = $date->format('U');
                }
                catch (Exception $e)
                {
                    $date = 0;
                }
            }
        }

        if (!is_int($date))
        {
            $date = intval($date);
        }
        // XF code end.

        $constraints['date'] = $date;
    }

    return $constraints;
}