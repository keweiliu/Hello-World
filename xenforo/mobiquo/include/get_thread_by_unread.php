<?php

defined('IN_MOBIQUO') or exit;

require_once "include/get_thread_by_post.php";

function get_thread_by_unread_func($xmlrpc_params)
{
    $params = php_xmlrpc_decode($xmlrpc_params);
    $bridge = Tapatalk_Bridge::getInstance();
    $visitor = XenForo_Visitor::getInstance();

    $data = $bridge->_input->filterExternal(array(
        'topic_id' => XenForo_Input::STRING,
        'posts_per_request' => XenForo_Input::UINT,
        'return_html' => XenForo_Input::UINT,
    ), $params);
    $threadId = $data['topic_id'];
    if (preg_match('/^tpann_\d+$/', $threadId))
    {
        $firstUnreadPostId = $data['topic_id'];
    }
    else
    {
        $postModel = $bridge->getPostModel();
    
        $ftpHelper = $bridge->getHelper('ForumThreadPost');
        $threadFetchOptions = array('readUserId' => $visitor['user_id']);
        $forumFetchOptions = array('readUserId' => $visitor['user_id']);
        list($thread, $forum) = $ftpHelper->assertThreadValidAndViewable($data['topic_id'], $threadFetchOptions, $forumFetchOptions);
    
        $firstUnreadPostId = $thread['last_post_id'];
    
        if ($visitor['user_id'])
        {
            $readDate = $bridge->getThreadModel()->getMaxThreadReadDate($thread, $forum);
    
            $fetchOptions = $postModel->getPermissionBasedPostFetchOptions($thread, $forum);
            $firstUnread = $postModel->getNextPostInThread($thread['thread_id'], $readDate, $fetchOptions);
            /*if (!$firstUnread)
            {
                $firstUnread = $postModel->getLastPostInThread($thread['thread_id'], $fetchOptions);
            }*/
    
            if ($firstUnread)
            {
                $firstUnreadPostId = $firstUnread['post_id'];
            }
        }
    }

    return get_thread_by_post_func(new xmlrpcval(array(
        new xmlrpcval($firstUnreadPostId, "string"),
        new xmlrpcval($data['posts_per_request'], 'int'),
        new xmlrpcval(!!$data['return_html'], 'boolean'),
    ), 'array'));
}
