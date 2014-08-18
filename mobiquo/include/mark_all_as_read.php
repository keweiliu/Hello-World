<?php

defined('IN_MOBIQUO') or exit;

function mark_all_as_read_func($xmlrpc_params)
{
	$bridge = Tapatalk_Bridge::getInstance();
	$visitor = XenForo_Visitor::getInstance();
	$params = php_xmlrpc_decode($xmlrpc_params);
	
	$forum = null;
	if (isset($params[0])) {		
		$ftpHelper = $bridge->getHelper('ForumThreadPost');
		$forum = $ftpHelper->assertForumValidAndViewable(
			$params[0], array('readUserId' => $visitor['user_id'])
		);
	}
	
	
	$bridge->getForumModel()->markForumTreeRead($forum, XenForo_Application::$time);
	
	return xmlresptrue();
}
