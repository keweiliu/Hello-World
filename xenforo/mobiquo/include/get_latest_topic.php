<?php

defined('IN_MOBIQUO') or exit;

function get_latest_topic_func($xmlrpc_params)
{
    $params = php_xmlrpc_decode($xmlrpc_params);

    $bridge = Tapatalk_Bridge::getInstance();
    $visitor = XenForo_Visitor::getInstance();
    $threadModel = $bridge->getThreadModel();
    $searchModel = $bridge->getSearchModel();

    $data = $bridge->_input->filterExternal(array(
            'start_num' => XenForo_Input::UINT,
            'last_num' => XenForo_Input::UINT,
    ), $params);
    if(isset($params[2]))
        $data = array_merge($data, $params[2]);
    $nodesLimit = build_node_constraints($data, $bridge);
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
        'moderated' => false,
        'node_id' => $nodesLimit,
    ), $fetchOptions));

    $results = array();
    foreach ($threadIds AS $threadId)
    {
        $results[] = array(XenForo_Model_Search::CONTENT_TYPE => 'thread', XenForo_Model_Search::CONTENT_ID => $threadId);
    }

    $results = $searchModel->getViewableSearchResults($results);

    $totalThreads = count($results);

    $results = array_slice($results, $start, $limit);
    $results = $searchModel->getSearchResultsForDisplay($results);

    $topic_list = array();
    if($results)
    {
        $forums = $bridge->getForumModel()->getForums();
        $nodeModel = $bridge->getNodeModel();
        $nodes = $nodeModel->getAllNodes(false, true);
        
        $userModel = $bridge->getUserModel();
        $threadWatchModel = $bridge->getThreadWatchModel();
        $postModel = $bridge->getPostModel();
        
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
                
                'post_author_id'    => new xmlrpcval($lastPost['user_id'], 'string'),
                'post_author_name'  => new xmlrpcval($lastPost['username'], 'base64'),
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
                'can_subscribe'     => new xmlrpcval(true, 'boolean'), // implied by view permissions
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

function build_node_constraints($data, $bridge)
{
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
            if(!empty($node))
                $search_nodes = mobi_forum_include($node, $search_nodes, $nodeModel, $selected_nodes);
        }
    }
    return array_unique($search_nodes);
}