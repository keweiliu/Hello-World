<?php

defined('IN_MOBIQUO') or exit;

function get_thread_func($xmlrpc_params)
{
    $params = php_xmlrpc_decode($xmlrpc_params);
    $bridge = Tapatalk_Bridge::getInstance();
    $visitor = XenForo_Visitor::getInstance();
    $nodeModel = $bridge->getNodeModel();
    $data = $bridge->_input->filterExternal(array(
            'topic_id' => XenForo_Input::STRING,
            'start_num' => XenForo_Input::UINT,
            'last_num' => XenForo_Input::UINT,
            'return_html' => XenForo_Input::UINT
    ), $params);
    $oldTopicId=$data['topic_id'];
    $ftpHelper = $bridge->getHelper('ForumThreadPost');
    $threadFetchOptions = array(
        'readUserId' => $visitor['user_id'],
        'watchUserId' => $visitor['user_id'],
        'join' => XenForo_Model_Thread::FETCH_AVATAR
    );
    $forumFetchOptions = array(
        'readUserId' => $visitor['user_id']
    );
    $threadId = $data['topic_id'];

    $post_list = array();
    
    // get announcement
    if (preg_match('/^tpann_\d+$/', $threadId))
    {
        $notices = array();
        $prefix_id = preg_split('/_/', $threadId);
        $specified_id = $prefix_id[1];
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
                    if (XenForo_Helper_Criteria::userMatchesCriteria($notice['user_criteria'], true, $user))
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

        if(isset($notices[$specified_id]))
        {
            $ann_author = $bridge->getUserModel()->getUserById(1);
            $notice = $notices[$specified_id];
            $xmlrpc_post = array(
                'post_id'         => new xmlrpcval($threadId, 'string'),
                'post_title'       => new xmlrpcval('', 'base64'), //not supported in XenForo
                'post_content'   => new xmlrpcval($bridge->renderPostPreview($notice['message']), 'base64'),
                'post_author_name' => new xmlrpcval(isset($ann_author['username']) ? $ann_author['username'] : 'admin', 'base64'),
                'user_type'     => new xmlrpcval('', 'base64'),
                'is_online'     => new xmlrpcval(false, 'boolean'),
                'can_edit'       => new xmlrpcval('', 'boolean'),
                'icon_url'       => new xmlrpcval('', 'string'),
                'post_time'     => new xmlrpcval('', 'dateTime.iso8601'),
                'timestamp'      => new xmlrpcval('','string'),
                'can_like'       => new xmlrpcval(false, 'boolean'),
                'is_liked'       => new xmlrpcval(false, 'boolean'),
                'like_count'       => new xmlrpcval(0, 'int'),
                'can_upload'       => new xmlrpcval(false, 'boolean'),
                'allow_smilies' => new xmlrpcval(true, 'boolean'), // always true

                'can_delete'        => new xmlrpcval(false, 'boolean'),
                'can_approve'      => new xmlrpcval(false, 'boolean'),
                'can_move'        => new xmlrpcval(false, 'boolean'),
                'is_approved'      => new xmlrpcval(true, 'boolean'),
                'is_deleted'        => new xmlrpcval(false, 'boolean'),
                'can_ban'          => new xmlrpcval(false, 'boolean'),
                'is_ban'            => new xmlrpcval(false, 'boolean'),
            );
            $post_list[] = new xmlrpcval($xmlrpc_post, 'struct');

            $result = array(
                'total_post_num'  => new xmlrpcval(1, 'int'),
                'forum_id'      => new xmlrpcval('', 'string'),
                'forum_name'      => new xmlrpcval('', 'base64'),
                'topic_id'      => new xmlrpcval($threadId, 'string'),
                'topic_title'    => new xmlrpcval($bridge->renderPostPreview($notice['title']), 'base64'),
                'prefix_id'       => new xmlrpcval('' , 'string'),
                'prefix'          => new xmlrpcval('', 'base64'),
                'can_subscribe'   => new xmlrpcval(false, 'boolean'),
                'is_subscribed'   => new xmlrpcval(false, 'boolean'),
                'is_closed'    => new xmlrpcval(false, 'boolean'),
                'can_reply'    => new xmlrpcval(false, 'boolean'),
                'like_count'      => new xmlrpcval(0, 'int'),
                'can_report'    => new xmlrpcval(true,'boolean'),
                'can_delete'        => new xmlrpcval(false, 'boolean'),
                'can_close'      => new xmlrpcval(false, 'boolean'),
                'can_approve'      => new xmlrpcval(false, 'boolean'),
                'can_stick'      => new xmlrpcval(false, 'boolean'),
                'can_move'        => new xmlrpcval(false, 'boolean'),
                'can_merge'        => new xmlrpcval(false, 'boolean'),
                'is_moved'        => new xmlrpcval(false,'boolean'),
                'is_merged'       => new xmlrpcval(false, 'boolean'),
                'real_topic_id'   => new xmlrpcval($threadId, 'string'),
                'is_approved'      => new xmlrpcval(true, 'boolean'),
                'is_deleted'        => new xmlrpcval(false, 'boolean'),
            );
            $result['posts'] = new xmlrpcval($post_list, 'array');
        }
        else
        {
            $result = array(
                'total_post_num'  => new xmlrpcval(0, 'int'),
            );
        }
        $bridge->setUserParams('thread_id', $data['topic_id']);
        return new xmlrpcresp(new xmlrpcval($result, 'struct'));
    }
    list($thread, $forum) = $ftpHelper->assertThreadValidAndViewable($threadId, $threadFetchOptions, $forumFetchOptions);

    $forumModel = $bridge->getForumModel();
    $threadModel = $bridge->getThreadModel();
    $postModel = $bridge->getPostModel();
    
    $isMoved=false;
    $isMerged=false;
    $canMerge=true;
    if ($threadModel->isRedirect($thread))
    {
        $canMerge=false;
        $threadRedirectModel = $bridge->getThreadRedirectModel();
        $newThread = $threadRedirectModel->getThreadRedirectById($thread['thread_id']);
        $redirectKey = $newThread['redirect_key'];
        $parts = preg_split('/-/', $redirectKey);
        $data['topic_id'] = $threadId = $parts[1];
        if (count($parts)<4){
            $isMoved=false;
            $isMerged=true;
        }else{
            $isMoved=true;
            $isMerged=false;
        }
        if(!empty($parts[1]))
            list($thread, $forum) = $ftpHelper->assertThreadValidAndViewable($parts[1], $threadFetchOptions, $forumFetchOptions);
        else
            return xmlresperror(new XenForo_Phrase('dark_thread_is_redirect'));
    }

    list($start, $limit) = process_page($data['start_num'], $data['last_num']);

    $postFetchOptions = $postModel->getPermissionBasedPostFetchOptions($thread, $forum) + array(
        'limit' => $limit,
        'offset' => $start,
        'join' => XenForo_Model_Post::FETCH_USER | XenForo_Model_Post::FETCH_USER_PROFILE,
        'likeUserId' => $visitor['user_id']
    );
    if (!empty($postFetchOptions['deleted']))
    {
        $postFetchOptions['join'] |= XenForo_Model_Post::FETCH_DELETION_LOG;
    }

    $posts = $postModel->getPostsInThread($threadId, $postFetchOptions);
    $the_thread = $threadModel->getThreadById($threadId);
    //bread crumb controller
    $breadcrumb_enable = true;
    $breadcrumblist = array();
    if(isset($the_thread['node_id']) && !empty($the_thread['node_id']) && $breadcrumb_enable)
    {
        $the_node = $nodeModel->getNodeById($the_thread['node_id']);
        if(!empty($the_node))
        {
            $breadcrumblist = $nodeModel->getNodeAncestors($the_node);
            $breadcrumblist[] = $the_node;
        }
    }
    $totalPosts = $the_thread['reply_count'] + 1;

    $posts = $postModel->getAndMergeAttachmentsIntoPosts($posts);

    $inlineModOptions = array();
    $maxPostDate = 0;
    $firstUnreadPostId = 0;

    $deletedPosts = 0;
    $moderatedPosts = 0;

    $permissions = $visitor->getNodePermissions($thread['node_id']);
    foreach ($posts as &$post)
    {
        $postModOptions = $postModel->addInlineModOptionToPost(
            $post, $thread, $forum, $permissions
        );
        $inlineModOptions += $postModOptions;

        $post = $postModel->preparePost($post, $thread, $forum, $permissions);

        if ($post['post_date'] > $maxPostDate)
        {
            $maxPostDate = $post['post_date'];
        }

        if ($post['isDeleted'])
        {
            $deletedPosts++;
        }
        if ($post['isModerated'])
        {
            $moderatedPosts++;
        }

        if (!$firstUnreadPostId && $post['isNew'])
        {
            $firstUnreadPostId = $post['post_id'];
        }
    }
/*
    if ($firstUnreadPostId)
    {
        $requestPaths = XenForo_Application::get('requestPaths');
        $unreadLink = $requestPaths['requestUri'] . '#post-' . $firstUnreadPostId;
    }
    else if ($thread['isNew'])
    {
        $unreadLink = XenForo_Link::buildPublicLink('threads/unread', $thread);
    }
    else
    {
        $unreadLink = '';
    }*/

    if (version_compare(XenForo_Application::$version, '1.0.4', '>'))
        $threadModel->markThreadRead($thread, $forum, $maxPostDate);
    else
        $threadModel->markThreadRead($thread, $forum, $maxPostDate, $visitor['user_id']);

    $threadModel->logThreadView($threadId);

    $defaultOptions = array(
        'states' => array(
            'viewAttachments' => $threadModel->canViewAttachmentsInThread($thread, $forum),
            'returnHtml' => (boolean)$data['return_html']
        )
    );

    $canUpload = $forumModel->canUploadAndManageAttachment($forum, $errorPhraseKey);

    $userModel = $bridge->getUserModel();
    $threadModel->standardizeViewingUserReferenceForNode($thread['node_id'], $viewingUser, $nodePermissions);

    foreach($posts as $id => &$post)
    {
        $attachment_list = array();
        $options = $defaultOptions;

        $lx_info_list = array();
        if(!empty($post['like_users']))
            $like_users = unserialize($post['like_users']);
        
        if(isset($like_users) && !empty($like_users))
        {
            foreach($like_users as $index => $user)
            {
                $lx_info_list[] = new xmlrpcval(array(
                    'userid' => new xmlrpcval($user['user_id'], 'string'),
                    'username' => new xmlrpcval($user['username'], 'base64'),
                    'user_type'  => new xmlrpcval(get_usertype_by_item($user['user_id']), 'base64'),
                ), 'struct');
            }
        }
        
        if(!empty($post['attachments'])){
            $options['states']['attachments'] = $post['attachments'];

            if (stripos($post['message'], '[/attach]') !== false)
            {
                if (preg_match_all('#\[attach(=[^\]]*)?\](?P<id>\d+)\[/attach\]#i', $post['message'], $matches))
                {
                    foreach ($matches['id'] AS $attachId)
                    {
                        unset($post['attachments'][$attachId]);
                    }
                }
            }

            foreach($post['attachments'] as $attachment)
            {
                $type = $attachment['extension'];
                
                switch($attachment['extension']){
                    case 'gif':
                    case 'jpg':
                    case 'png':
                        $type = 'image';
                        break;
                    case 'pdf':
                        $type = 'pdf';
                        break;
                }

                $thumbnail = '';
                if(!empty($attachment['thumbnailUrl']))
                    $thumbnail = XenForo_Link::convertUriToAbsoluteUri($attachment['thumbnailUrl'], true);

                $attachment_list[] = new xmlrpcval(array(
                    'content_type'  => new xmlrpcval($type, 'string'),
                    'thumbnail_url' => new xmlrpcval($thumbnail, 'string'),
                    'url'           => new xmlrpcval(XenForo_Link::convertUriToAbsoluteUri(XenForo_Link::buildPublicLink('attachments', $attachment), true), 'string'),
                    'filename'      => new xmlrpcval($attachment['filename'], 'base64'),
                    'filesize'      => new xmlrpcval($attachment['file_size'], 'int'),
                ), 'struct');
            }
        }
        
        $post['message'] = preg_replace('/\[quote="(.*?), post: (.*?), member: (.*?)"\](.*?)/si', '[quote uid=$3 name="$1" post=$2]$4',$post['message']);
        $xmlrpc_post = array(
            'post_id'           => new xmlrpcval($post['post_id'], 'string'),
            'post_title'        => new xmlrpcval('', 'base64'), //not supported in XenForo
            'post_content'      => new xmlrpcval($bridge->cleanPost($post['message'], $options), 'base64'),
            'post_author_name'  => new xmlrpcval($post['username'], 'base64'),
            'post_author_id'    => new xmlrpcval($post['user_id'], 'string'),
            'user_type'         => new xmlrpcval(get_usertype_by_item('',$post['display_style_group_id'],$post['is_banned']), 'base64'),
            'is_online'         => new xmlrpcval($bridge->isUserOnline($post), 'boolean'),
            'can_edit'          => new xmlrpcval($post['canEdit'], 'boolean'),
            'icon_url'          => new xmlrpcval(get_avatar($post), 'string'),
            'post_time'         => new xmlrpcval(mobiquo_iso8601_encode($post['post_date']), 'dateTime.iso8601'),
            'timestamp'         => new xmlrpcval($post['post_date'],'string'),
            'attachments'       => new xmlrpcval($attachment_list, 'array'),
            'can_like'          => new xmlrpcval($post['canLike'], 'boolean'),
            'is_liked'          => new xmlrpcval($post['like_date'] > 0, 'boolean'),
            'like_count'        => new xmlrpcval($post['likes'], 'int'),
            'can_upload'        => new xmlrpcval($canUpload, 'boolean'),
            'allow_smilies'     => new xmlrpcval(true, 'boolean'), // always true

            'can_delete'        => new xmlrpcval($postModel->canDeletePost($post, $thread, $forum, 'soft', $errorPhraseKey, $nodePermissions, $viewingUser), 'boolean'),
            'can_approve'       => new xmlrpcval($postModel->canApproveUnapprovePost($post, $thread, $forum, $errorPhraseKey, $nodePermissions, $viewingUser), 'boolean'),
            'can_move'          => new xmlrpcval($postModel->canMovePost($post, $thread, $forum, $errorPhraseKey, $nodePermissions, $viewingUser), 'boolean'),
            'is_approved'       => new xmlrpcval(!$post['isModerated'], 'boolean'),
            'is_deleted'        => new xmlrpcval($post['isDeleted'], 'boolean'),
            'can_ban'           => new xmlrpcval($visitor->hasAdminPermission('ban') && $userModel->couldBeSpammer($post), 'boolean'),
            'is_ban'            => new xmlrpcval($post['is_banned'], 'boolean'),
        );
        
        if(!empty($lx_info_list))
            $xmlrpc_post['likes_info'] = new xmlrpcval($lx_info_list, 'array');
        
        if($post['last_edit_user_id'])
        {
            $editName="";
            $editUser=$userModel->getUserById($post['last_edit_user_id']);
            if ($editUser){
                $editName=$editUser['username'];
            }
            $xmlrpc_post['editor_id']   = new xmlrpcval($post['last_edit_user_id'], 'string');
            $xmlrpc_post['editor_name'] = new xmlrpcval($editName, 'base64');
            $xmlrpc_post['edit_time']   = new xmlrpcval($post['last_edit_date'], 'string');
        }
        
        $post_list[] = new xmlrpcval($xmlrpc_post, 'struct');
    }

    $result = array(
        'total_post_num'  => new xmlrpcval($totalPosts, 'int'),
        'forum_id'        => new xmlrpcval($thread['node_id'], 'string'),
        'forum_name'      => new xmlrpcval($forum['title'], 'base64'),
        'topic_id'        => new xmlrpcval($oldTopicId, 'string'),
        'topic_title'     => new xmlrpcval($thread['title'], 'base64'),
        'prefix_id'       => new xmlrpcval($thread['prefix_id'] , 'string'),
        'prefix'          => new xmlrpcval(get_prefix_name($thread['prefix_id']), 'base64'),
        'can_subscribe'   => new xmlrpcval(true, 'boolean'),
        'is_poll'         => new xmlrpcval($thread['discussion_type'] == 'poll', 'boolean'),
        'is_subscribed'   => new xmlrpcval($thread['thread_is_watched'], 'boolean'),
        'is_closed'       => new xmlrpcval($thread['discussion_open'] == 0, 'boolean'),
        'can_reply'       => new xmlrpcval($threadModel->canReplyToThread($thread, $forum, $errorPhraseKey), 'boolean'),
        'like_count'      => new xmlrpcval($thread['first_post_likes'], 'int'),
        'can_report'      => new xmlrpcval(true,'boolean'),
        'can_rename'        => new xmlrpcval($threadModel->canEditThreadTitle($thread, $forum), 'boolean'),
        'can_delete'        => new xmlrpcval($threadModel->canDeleteThread($thread, $forum, 'soft'), 'boolean'),
        'can_close'         => new xmlrpcval($threadModel->canLockUnlockThread($thread, $forum), 'boolean'),
        'can_approve'       => new xmlrpcval($threadModel->canApproveUnapproveThread($thread, $forum), 'boolean'),
        'can_stick'         => new xmlrpcval($threadModel->canStickUnstickThread($thread, $forum), 'boolean'),
        'can_move'          => new xmlrpcval($threadModel->canMoveThread($thread, $forum), 'boolean'),
        'can_merge'         => new xmlrpcval(($canMerge&&$threadModel->canMergeThread($thread, $forum)), 'boolean'),
        'is_moved'        => new xmlrpcval($isMoved,'boolean'),
        'is_merged'       => new xmlrpcval($isMerged, 'boolean'),
        'real_topic_id'   => new xmlrpcval($thread['thread_id'], 'string'),
        'is_approved'       => new xmlrpcval(!$thread['isModerated'], 'boolean'),
        'is_deleted'        => new xmlrpcval($thread['isDeleted'], 'boolean'),
    );
    if(!empty($breadcrumblist) && $breadcrumb_enable)
    {
        $breadcrumbs = array();
        foreach($breadcrumblist as $node)
        {
            $breadcrumbs[] = new xmlrpcval(array(
                'forum_id'      => new xmlrpcval($node['node_id'], 'string'),
                'forum_name'    => new xmlrpcval($node['title'], 'base64'),
                'sub_only'      => new xmlrpcval($node['node_type_id'] == 'Category', 'boolean'),
            ), 'struct');
        }
        if(!empty($breadcrumbs))
            $result['breadcrumb'] = new xmlrpcval($breadcrumbs, 'array');
    }
    if (isset($GLOBALS['POST_POSITION']))
        $result['position'] = new xmlrpcval($GLOBALS['POST_POSITION'], 'int');

    $result['posts'] = new xmlrpcval($post_list, 'array');
    $bridge->setUserParams('thread_id', $data['topic_id']);
    return new xmlrpcresp(new xmlrpcval($result, 'struct'));
}
