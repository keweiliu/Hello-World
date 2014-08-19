<?php

defined('IN_MOBIQUO') or exit;

function get_inbox_stat_func()
{
    $visitor = XenForo_Visitor::getInstance();
    $inbox_unread_count = $visitor['conversations_unread'] ? $visitor['conversations_unread'] : 0;
	$bridge = Tapatalk_Bridge::getInstance();
	$threadWatchModel = $bridge->getThreadWatchModel();
	$visitor = XenForo_Visitor::getInstance();

	$newThreads = $threadWatchModel->getThreadsWatchedByUser($visitor['user_id'], true, array(
		'join' => XenForo_Model_Thread::FETCH_FORUM | XenForo_Model_Thread::FETCH_USER,
		'readUserId' => $visitor['user_id'],
		'postCountUserId' => $visitor['user_id'],
		'permissionCombinationId' => $visitor['permission_combination_id'],
	));

	$newThreads = $threadWatchModel->unserializePermissionsInList($newThreads, 'node_permission_cache');
	$newThreads = $threadWatchModel->getViewableThreadsFromList($newThreads);
	$sub_threads_num = count($newThreads);

	$result = new xmlrpcval(array(
		'inbox_unread_count' => new xmlrpcval($inbox_unread_count, 'int'),
		'subscribed_topic_unread_count' => new xmlrpcval($sub_threads_num, 'int'),
	), 'struct');

	return new xmlrpcresp($result);
}
