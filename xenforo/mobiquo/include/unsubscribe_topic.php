<?php

defined('IN_MOBIQUO') or exit;

function unsubscribe_topic_func($xmlrpc_params)
{
    $params = php_xmlrpc_decode($xmlrpc_params);
    $bridge = Tapatalk_Bridge::getInstance();

    $data = $bridge->_input->filterExternal(array(
            'topic_id' => XenForo_Input::UINT,
    ), $params);
    if($data['topic_id'] != 'ALL')
    {
        list($thread, $forum) = $bridge->getHelper('ForumThreadPost')->assertThreadValidAndViewable($data['topic_id']);

        if (!$bridge->getThreadModel()->canWatchThread($thread, $forum))
        {
            return $bridge->responseNoPermission();
        }

        $bridge->getThreadWatchModel()->setThreadWatchState(XenForo_Visitor::getUserId(), $thread['thread_id'], '');

        return xmlresptrue();
    }
    else
    {
        $visitor = XenForo_Visitor::getInstance();
        $threadWatchModel = $bridge->getThreadWatchModel();
        $threadModel = $bridge->getThreadModel();
        $fetchOptions = array(
            'join' => XenForo_Model_Thread::FETCH_FORUM | XenForo_Model_Thread::FETCH_USER,
            'readUserId' => $visitor['user_id'],
            'postCountUserId' => $visitor['user_id'],
            'permissionCombinationId' => $visitor['permission_combination_id'],
        );
        $threads = $threadWatchModel->getThreadsWatchedByUser($visitor['user_id'], false,$fetchOptions);
        $threads = $threadWatchModel->unserializePermissionsInList($threads, 'node_permission_cache');
        $threads = $threadWatchModel->getViewableThreadsFromList($threads);
        // see XenForo_ControllerPublic_Watched::_prepareWatchedThreads
        $threadids = array();
        foreach ($threads AS &$thread)
        {
            if (!$visitor->hasNodePermissionsCached($thread['node_id']))
            {
                $visitor->setNodePermissions($thread['node_id'], $thread['permissions']);
            }

            $thread = $threadModel->prepareThread($thread, $thread);
            $threadids[] = $thread['thread_id'];
        }
        if(!empty($threadids))
        {
            $watch = $threadWatchModel->getUserThreadWatchByThreadIds(XenForo_Visitor::getUserId(), $threadids);
            foreach ($watch AS $threadWatch)
            {
                $dw = XenForo_DataWriter::create('XenForo_DataWriter_ThreadWatch');
                $dw->setExistingData($threadWatch, true);
                $dw->delete();
            }
            return xmlresptrue();
        }
        else
            return xmlresperror("No subscribed topics!");
    }
}
