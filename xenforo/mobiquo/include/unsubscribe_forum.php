<?php

defined('IN_MOBIQUO') or exit;

function unsubscribe_forum_func($xmlrpc_params)
{
    if(XenForo_Application::get('options')->currentVersionId < 1020070)
    {
        $params = php_xmlrpc_decode($xmlrpc_params);
        return xmlresperror(new XenForo_Phrase('dark_forum_subscriptions_not_supported'));
    }
    $params = php_xmlrpc_decode($xmlrpc_params);
    $visitor = XenForo_Visitor::getInstance();
    $bridge = Tapatalk_Bridge::getInstance();
    $forumWatchModel = $bridge->getForumWatchModel();


    $data = $bridge->_input->filterExternal(array(
            'node_id' => XenForo_Input::UINT,
    ), $params);

    $forumId = $data['node_id'];
    $userId = $visitor['user_id'];

    $sendAlert = true;
    $sendEmail = false;
    $unwatch = true;

    if($forumId == 'ALL')
    {
        $forumWatchModel->setForumWatchStateForAll(
            XenForo_Visitor::getUserId(), ''
        );
        
        return xmlresptrue();
    }
    
    $forum = $bridge->getHelper('ForumThreadPost')->assertForumValidAndViewable(
        $forumId,
        array(
            'readUserId' => $userId,
            'watchUserId' => $userId
        )
    );
    $forumId = $forum['node_id'];
    if (!$bridge->getForumModel()->canWatchForum($forum))
    {
        return $bridge->responseNoPermission();
    }
    if ($unwatch)
    {
        $notifyOn = 'delete';
    }
    else
    {
        $notifyOn = 'thread';//we only notify new thread
        if ($notifyOn)
        {
            if ($forum['allowed_watch_notifications'] == 'none')
            {
                $notifyOn = '';
            }
            else if ($forum['allowed_watch_notifications'] == 'thread' && $notifyOn == 'message')
            {
                $notifyOn = 'thread';
            }
        }
    }
    $forumWatchModel->setForumWatchState(
        XenForo_Visitor::getUserId(), $forumId,
        $notifyOn, $sendAlert, $sendEmail
    );
    
    return xmlresptrue();
}
