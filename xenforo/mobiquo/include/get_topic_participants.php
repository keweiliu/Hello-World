<?php

defined('IN_MOBIQUO') or exit;

function get_topic_participants_func($xmlrpc_params){
    $params = php_xmlrpc_decode($xmlrpc_params);
    $bridge = Tapatalk_Bridge::getInstance();
    $visitor = XenForo_Visitor::getInstance();
    $data = $bridge->_input->filterExternal(array(
            'topic_id' => XenForo_Input::UINT,
            'max_num' => XenForo_Input::UINT,
    ), $params);
    if (!isset($data['max_num']) || empty($data['max_num'])){
        $data['max_num'] = 20;
    }

    $forumModel = $bridge->getForumModel();
    $threadModel = $bridge->getThreadModel();
    $postModel = $bridge->getPostModel();
    $userModel = $bridge->getUserModel();
    $mobi_api_key = loadAPIKey();

    $threadFetchOptions = array(
        'readUserId' => $visitor['user_id'],
        'watchUserId' => $visitor['user_id'],
        'join' => XenForo_Model_Thread::FETCH_AVATAR
    );
    $forumFetchOptions = array(
        'readUserId' => $visitor['user_id']
    );

    $ftpHelper = $bridge->getHelper('ForumThreadPost');
    list($thread, $forum) = $ftpHelper->assertThreadValidAndViewable($data['topic_id'], $threadFetchOptions, $forumFetchOptions);
    if ($threadModel->isRedirect($thread))
    {
        $threadRedirectModel = $bridge->getThreadRedirectModel();
        $newThread = $threadRedirectModel->getThreadRedirectById($thread['thread_id']);
        $redirectKey = $newThread['redirect_key'];
        $parts = preg_split('/-/', $redirectKey);
        if (isset($parts[1]) && !empty($parts[1])){
            $data['topic_id'] = $parts[1];
            list($thread, $forum) = $ftpHelper->assertThreadValidAndViewable($parts[1], $threadFetchOptions, $forumFetchOptions);
        }else{
            return xmlresperror(new XenForo_Phrase('dark_thread_is_redirect'));
        }
    }

    $postFetchOptions = $postModel->getPermissionBasedPostFetchOptions($thread, $forum) + array(
        'join' => XenForo_Model_Post::FETCH_USER | XenForo_Model_Post::FETCH_USER_PROFILE,
        'likeUserId' => $visitor['user_id']
    );
    if (isset($postFetchOptions['deleted']) && !empty($postFetchOptions['deleted']))
    {
        $postFetchOptions['join'] |= XenForo_Model_Post::FETCH_DELETION_LOG;
    }
    $posts = $postModel->getPostsInThread($data['topic_id'], $postFetchOptions);
    $post_list = array();
    $xmlrpc_post = array();
    foreach ($posts as $post){
        if (isset($post['user_id']) && !empty($post['user_id']) && !array_key_exists($post['user_id'], $xmlrpc_post)){
            $postAuthor = $userModel->getUserById($post['user_id']);
            $xmlrpc_post[$post['user_id']] = array(
                'user_id'         => new xmlrpcval($post['user_id'], 'string'),
                'username'        => new xmlrpcval($post['username'], 'base64'),
                'icon_url'        => new xmlrpcval(get_avatar($postAuthor), 'string'),
                'enc_email'       => new xmlrpcval(base64_encode(encrypt(trim($postAuthor['email']), $mobi_api_key)), 'string'),
            );
            $post_list[] = new xmlrpcval($xmlrpc_post[$post['user_id']], 'struct');
            if (count($xmlrpc_post) >= $data['max_num']){
                break;
            }
        }

    }
    $result = array(
        'result' => new xmlrpcval(true, 'boolean'),
        'list'   => new xmlrpcval($post_list, 'array'),
    );

    return new xmlrpcresp(new xmlrpcval($result, 'struct'));
}