<?php

defined('IN_MOBIQUO') or exit;

function subscribe_topic_func($xmlrpc_params)
{
    $params = php_xmlrpc_decode($xmlrpc_params);
    $bridge = Tapatalk_Bridge::getInstance();
    
    $data = $bridge->_input->filterExternal(array(
            'topic_id' => XenForo_Input::STRING,
            'subscribe_mode' => XenForo_Input::UINT,
    ), $params);

    list($thread, $forum) = $bridge->getHelper('ForumThreadPost')->assertThreadValidAndViewable($data['topic_id']);
    if (!$bridge->getThreadModel()->canWatchThread($thread, $forum))
    {
        return $bridge->responseNoPermission();
    }
    $bridge->getThreadWatchModel()->setThreadWatchState(XenForo_Visitor::getUserId(), $thread['thread_id'], 'watch_no_email'); 
    return xmlresptrue();
    
}
