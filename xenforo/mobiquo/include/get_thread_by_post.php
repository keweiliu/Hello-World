<?php

defined('IN_MOBIQUO') or exit;

require_once "include/get_thread.php";

function get_thread_by_post_func($xmlrpc_params)
{
    $params = php_xmlrpc_decode($xmlrpc_params);
    $bridge = Tapatalk_Bridge::getInstance();
    $visitor = XenForo_Visitor::getInstance();
    
    $data = $bridge->_input->filterExternal(array(
        'post_id' => XenForo_Input::UINT,
        'posts_per_request' => XenForo_Input::UINT,
        'return_html' => XenForo_Input::UINT,
    ), $params);
    
    if(!$data['posts_per_request'])
        $data['posts_per_request'] = 20;
    $threadId = $data['post_id'];
    if (preg_match('/^tpann_\d+$/', $threadId))
    {
        $thread_id = $data['post_id'];
        $page = 1;
        $GLOBALS['POST_POSITION'] = 1;
    }
    else
    {
        $ftpHelper = $bridge->getHelper('ForumThreadPost');
        list($post, $thread, $forum) = $ftpHelper->assertPostValidAndViewable($data['post_id']);
        
        $page = floor($post['position'] / $data['posts_per_request']) + 1;
        
        $GLOBALS['POST_POSITION'] = $post['position'] + 1;
        $thread_id = $thread['thread_id'];
    }
    
    $response = get_thread_func(new xmlrpcval(array(
        new xmlrpcval($thread_id, "string"),
        new xmlrpcval(($page-1) * $data['posts_per_request'], 'int'),
        new xmlrpcval($page * $data['posts_per_request'] - 1, 'int'),
        new xmlrpcval(!!$data['return_html'], 'boolean'),
    ), 'array'));
    
    return $response;
}
