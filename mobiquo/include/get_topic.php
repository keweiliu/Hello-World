<?php

defined('IN_MOBIQUO') or exit;

function get_topic_func($xmlrpc_params)
{
    $params = php_xmlrpc_decode($xmlrpc_params);

    $bridge = Tapatalk_Bridge::getInstance();
    $visitor = XenForo_Visitor::getInstance();

    $data = $bridge->_input->filterExternal(array(
            'forum_id' => XenForo_Input::UINT,
            'start_num' => XenForo_Input::UINT,
            'last_num' => XenForo_Input::UINT,
            'mode' => XenForo_Input::STRING
    ), $params);

    $ftpHelper = $bridge->getHelper('ForumThreadPost');
    $forumFetchOptions = array('readUserId' => $visitor['user_id']);
    $forum = $ftpHelper->assertForumValidAndViewable($data['forum_id'], $forumFetchOptions);
    $permissions = $visitor->getNodePermissions($forum['node_id']);
    //get announcement
    if($data['mode'] == 'ANN')
    {
        $notices = array();
        $nodeModel = $bridge->getNodeModel();
        $node = $nodeModel->getNodeById($data['forum_id']);
        $ann_author = $bridge->getUserModel()->getUserById(1);
        if(!empty($node))
        {
            if (XenForo_Application::get('options')->enableNotices)
            {
                if (XenForo_Application::isRegistered('notices'))
                {
                    $user = XenForo_Visitor::getInstance()->toArray();

                    $noticeTokens = array(
                        '{name}' => $user['username'] !== '' ? $user['username'] : new XenForo_Phrase('guest'),
                        '{user_id}' => $user['user_id'],
                    );
                    foreach (XenForo_Application::get('notices') AS $noticeId => $notice)
                    {
                        if (XenForo_Helper_Criteria::userMatchesCriteria($notice['user_criteria'], true, $user)
                            && pageMatchesCriteria($notice['page_criteria'],$node, $nodeModel))
                        {
                            $notices[$noticeId] = array(
                                'title' => $notice['title'],
                                'message' => str_replace(array_keys($noticeTokens), $noticeTokens, $notice['message']),
                                'wrap' => $notice['wrap'],
                                'dismissible' => ($notice['dismissible'] && XenForo_Visitor::getUserId())
                            );
                        }
                    }
                }
            }
        }
        $topic_list = array();
        if(!empty($notices))
        {
            foreach($notices as $noticeId => $notice)
            {
                $topic_list[] = new xmlrpcval(array(
                'forum_id'          => new xmlrpcval($data['forum_id'], 'string'),
                'topic_id'          => new xmlrpcval('tpann_'.$noticeId, 'string'),
                'topic_title'       => new xmlrpcval($notice['title'], 'base64'),
                'prefix_id'			=> new xmlrpcval('', 'string'),
                'prefix'            => new xmlrpcval('', 'base64'),
                'topic_author_name' => new xmlrpcval(isset($ann_author['username']) ? $ann_author['username'] : 'admin', 'base64'),
                'user_type'         => new xmlrpcval('', 'base64'),
                'can_subscribe'     => new xmlrpcval(false, 'boolean'),
                'is_subscribed'     => new xmlrpcval(false, 'boolean'),
                'is_closed'         => new xmlrpcval(false, 'boolean'),
                'short_content'     => new xmlrpcval('', 'base64'),
                'icon_url'          => new xmlrpcval(get_avatar($user, "l"), 'string'),
                'last_reply_time'   => new xmlrpcval('', 'dateTime.iso8601'),
                'reply_number'      => new xmlrpcval(0, 'int'),
                'view_number'       => new xmlrpcval(0, 'int'),
                'new_post'          => new xmlrpcval(false, 'boolean'),
                'like_count'        => new xmlrpcval(0, 'int'),
                'can_delete'        => new xmlrpcval(false, 'boolean'),
                'can_close'         => new xmlrpcval(false, 'boolean'),
                'can_approve'       => new xmlrpcval(false, 'boolean'),
                'can_stick'         => new xmlrpcval(false, 'boolean'),
                'can_move'          => new xmlrpcval(false, 'boolean'),
                'can_merge'			=> new xmlrpcval(false, 'boolean'),
                'is_moved'        => new xmlrpcval(false,'boolean'),
                'is_merged'       => new xmlrpcval(false, 'boolean'),
                'real_topic_id'   => new xmlrpcval('tpann_'.$noticeId, 'string'),
                'is_approved'       => new xmlrpcval(true, 'boolean'),
                'is_deleted'        => new xmlrpcval(false, 'boolean'),
                'is_sticky'         => new xmlrpcval(false, 'boolean'),
                'can_ban'           => new xmlrpcval(false, 'boolean'),
                'is_ban'            => new xmlrpcval(false, 'boolean'),
                ), 'struct');
            }
        }

        $result = new xmlrpcval(array(
            'total_topic_num' => new xmlrpcval(count($notices), 'int'),
            'forum_id'        => new xmlrpcval($data['forum_id'], 'string'),
            'forum_name'      => new xmlrpcval(isset($node['title'])? $bridge->renderPostPreview($node['title']) : '' , 'base64'),
            'can_post'        => new xmlrpcval(false, 'boolean'),
            'require_prefix'  => new xmlrpcval(false, 'boolean'),
            'topics'          => new xmlrpcval($topic_list, 'array'),
            'can_upload'      => new xmlrpcval(false, 'boolean'),
            'unread_sticky_count' => new xmlrpcval(false, 'int'),
            //'unread_announce_count' => new xmlrpcval(0, 'int'),
        ), 'struct');
        $bridge->setUserParams('node_id', $data['forum_id']);
        return new xmlrpcresp($result);
    }
    
    $threadModel = $bridge->getThreadModel();
    $forumModel = $bridge->getForumModel();
    $prefixModel = $bridge->_getPrefixModel();
    $userModel = $bridge->getUserModel();

    list($start, $limit) = process_page($data['start_num'], $data['last_num']);

    $threadFetchConditions = $threadModel->getPermissionBasedThreadFetchConditions($forum) + array(
        'sticky' => 1
    );

    $unreadSticky = 0;
    $threads = $threadModel->getStickyThreadsInForum($forum['node_id'], $threadFetchConditions, array(
        'readUserId' => $visitor['user_id'],
        'watchUserId' => $visitor['user_id'],
        'postCountUserId' => $visitor['user_id']
    ));
    foreach ($threads AS &$thread)
    {
        $thread = $threadModel->prepareThread($thread, $forum, $permissions);
        if($thread['isNew'])
            $unreadSticky ++;
    }
    unset($thread);

    $threadFetchConditions['sticky'] = 0;
    $totalThreads = $threadModel->countThreadsInForum($forum['node_id'], $threadFetchConditions);
    $threadFetchOptions = array(
        'limit' => $limit,
        'offset' => $start,

        'join' => XenForo_Model_Thread::FETCH_USER | XenForo_Model_Thread::FETCH_FIRSTPOST,
        'readUserId' => $visitor['user_id'],
        'watchUserId' => $visitor['user_id'],
        'postCountUserId' => $visitor['user_id'],

        'order' => 'last_post_date',
        'orderDirection' => 'desc'
    );


    if($data['mode'] == 'TOP'){
        $threads = $threadModel->getStickyThreadsInForum($forum['node_id'], $threadFetchConditions, $threadFetchOptions);
        $totalThreads = count($threads);
    }else {
        $threads = $threadModel->getThreadsInForum($forum['node_id'], $threadFetchConditions, $threadFetchOptions);
    }

    $inlineModOptions = array();

    foreach ($threads AS &$thread)
    {
        $thread = $threadModel->prepareThread($thread, $forum, $permissions);
    }
    unset($thread);

    $prefixes_list = array();
    $prefixGroups = $prefixModel->getUsablePrefixesInForums($forum['node_id']);
    if (!empty($prefixGroups))
    {
        foreach($prefixGroups as $prefixGroup)
        {
            foreach($prefixGroup['prefixes'] as $prefix)
            {
                $prefix_xmlrpc = new xmlrpcval(array(
                    'prefix_id'           => new xmlrpcval($prefix['prefix_id'], 'string'),
                    'prefix_display_name' => new xmlrpcval(get_prefix_name($prefix['prefix_id']), 'base64'),
                ), 'struct');
                $prefixes_list[] = $prefix_xmlrpc;
            }
        }
    }

    $topic_list = array();
    foreach($threads as $thread)
    {
    		$isMoved=false;
        	$isMerged=false;
        	$canMerge=true;
        	$threadId=$thread['thread_id'];
        	if ($threadModel->isRedirect($thread))
        	{
        		$canMerge=false;
        		$threadRedirectModel = $bridge->getThreadRedirectModel();
        		$newThread = $threadRedirectModel->getThreadRedirectById($thread['thread_id']);
        		$redirectKey = $newThread['redirect_key'];
        		$parts = preg_split('/-/', $redirectKey);
        		$threadId = $parts[1];
        		if (count($parts)<4){
        			$isMoved=false;
        			$isMerged=true;
        		}else{
        			$isMoved=true;
        			$isMerged=false;
        		}
        	}
        $threadModel->standardizeViewingUserReferenceForNode($thread['node_id'], $viewingUser, $nodePermissions);

        $topic_list[] = new xmlrpcval(array(
            'forum_id'          => new xmlrpcval($thread['node_id'], 'string'),
            'topic_id'          => new xmlrpcval($thread['thread_id'], 'string'),
            'topic_title'       => new xmlrpcval($thread['title'], 'base64'),
        	'prefix_id'         => new xmlrpcval($thread['prefix_id'],'string'),
            'prefix'            => new xmlrpcval(get_prefix_name($thread['prefix_id']), 'base64'),
            'topic_author_name' => new xmlrpcval($thread['username'], 'base64'),
            'user_type'         => new xmlrpcval(get_usertype_by_item('', $thread['display_style_group_id'], $thread['is_banned']), 'base64'),
            'can_subscribe'     => new xmlrpcval(true, 'boolean'), // implied by view permissions
            'is_subscribed'     => new xmlrpcval((boolean)$thread['thread_is_watched'], 'boolean'),
            'is_closed'         => new xmlrpcval($thread['discussion_open'] == 0, 'boolean'),
            'short_content'     => new xmlrpcval($bridge->renderPostPreview($thread['message'], 200), 'base64'),
            'icon_url'          => new xmlrpcval(get_avatar($thread), 'string'),
            'last_reply_time'   => new xmlrpcval(mobiquo_iso8601_encode($thread['last_post_date']), 'dateTime.iso8601'),
            'reply_number'      => new xmlrpcval($thread['reply_count'], 'int'),
            'view_number'       => new xmlrpcval($thread['view_count'], 'int'),
            'new_post'          => new xmlrpcval($thread['isNew'], 'boolean'),
            'like_count'        => new xmlrpcval($thread['first_post_likes'], 'int'),

            'can_delete'        => new xmlrpcval($threadModel->canDeleteThread($thread, $forum, 'soft'), 'boolean'),
            'can_close'         => new xmlrpcval($threadModel->canLockUnlockThread($thread, $forum), 'boolean'),
            'can_approve'       => new xmlrpcval($threadModel->canApproveUnapproveThread($thread, $forum), 'boolean'),
            'can_rename'        => new xmlrpcval($threadModel->canEditThreadTitle($thread, $forum), 'boolean'),
            'can_stick'         => new xmlrpcval($threadModel->canStickUnstickThread($thread, $forum), 'boolean'),
            'can_move'          => new xmlrpcval($threadModel->canMoveThread($thread, $forum), 'boolean'),
        	'can_merge'         => new xmlrpcval(($canMerge&&$threadModel->canMergeThread($thread, $forum)), 'boolean'),
        	'is_moved'        => new xmlrpcval($isMoved,'boolean'),
    	    'is_merged'       => new xmlrpcval($isMerged, 'boolean'),
    		'real_topic_id'   => new xmlrpcval($threadId, 'string'),
            'is_approved'       => new xmlrpcval(!$thread['isModerated'], 'boolean'),
            'is_deleted'        => new xmlrpcval($thread['isDeleted'], 'boolean'),
            'is_sticky'         => new xmlrpcval($thread['sticky'], 'boolean'),
            'can_ban'           => new xmlrpcval($visitor->hasAdminPermission('ban') && $userModel->couldBeSpammer($thread), 'boolean'),
            'is_ban'            => new xmlrpcval($thread['is_banned'], 'boolean'),
        ), 'struct');
    }
    $processed_roForums = array();
    $options = XenForo_Application::get('options');
    $readonlyForums = $options->readonlyForums;
    if(!empty($readonlyForums))
    {
        foreach($readonlyForums as $forum_idstr)
        {
            $forum_ids = explode(',', $forum_idstr);
            $processed_roForums = array_merge($processed_roForums, $forum_ids);
        }
    }
    $processed_roForums = array_unique($processed_roForums);
    $result = new xmlrpcval(array(
        'total_topic_num' => new xmlrpcval($totalThreads, 'int'),
        'forum_id'        => new xmlrpcval($forum['node_id'], 'string'),
        'forum_name'      => new xmlrpcval($forum['title'], 'base64'),
        'can_post'        => new xmlrpcval($forumModel->canPostThreadInForum($forum) && !in_array($forum['node_id'], $processed_roForums), 'boolean'),
        'require_prefix'  => new xmlrpcval($forum['require_prefix'], 'boolean'),
        'prefixes'        => new xmlrpcval($prefixes_list, 'array'),
        'topics'          => new xmlrpcval($topic_list, 'array'),
        'can_upload'      => new xmlrpcval($forumModel->canUploadAndManageAttachment($forum, $errorPhraseKey), 'boolean'),
        'unread_sticky_count' => new xmlrpcval($unreadSticky, 'int'),
        //'unread_announce_count' => new xmlrpcval(0, 'int'),
    ), 'struct');
    $bridge->setUserParams('node_id', $data['forum_id']);
    return new xmlrpcresp($result);
}

/**
 *
 * Simulate XenForo_Helper_Criteria but as cannot initilize as Xenforo, we simly match nodes rule.
 */
function pageMatchesCriteria($criteria, $node, $nodeModel)
{
    $breadCrumbs = $nodeModel->getNodeBreadCrumbs($node);
    if (!$criteria = unserializeCriteria($criteria))
    {
        return true;
    }

    foreach ($criteria AS $criterion)
    {
        $data = $criterion['data'];

        switch ($criterion['rule'])
        {
            // browsing within one of the specified nodes
            case 'nodes':
                {

                    if (empty($data['node_ids']))
                    {
                        return false; // no node ids specified
                    }
                    if(is_array($breadCrumbs) && !empty($breadCrumbs) && is_array($data['node_ids']))
                    {
                        foreach ($breadCrumbs as $parent_nodeid => $parent_node)
                        {
                            if(in_array($parent_nodeid, $data['node_ids']))
                                return true;
                        }
                        return false;
                    }
                }
                break;
        }

    }
    return true;
}

function unserializeCriteria($criteria)
{
    if (!is_array($criteria))
    {
        $criteria = @unserialize($criteria);
        if (!is_array($criteria))
        {
            return array();
        }
    }

    return $criteria;
}
