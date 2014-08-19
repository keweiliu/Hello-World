<?php

defined('IN_MOBIQUO') or exit;

function logout_user_func()
{
	$bridge = Tapatalk_Bridge::getInstance();
	
	// remove an admin session if we're logged in as the same person
	if (XenForo_Visitor::getInstance()->get('is_admin'))
	{
		$adminSession = new XenForo_Session(array('admin' => true));
		$adminSession->start();
		if ($adminSession->get('user_id') == XenForo_Visitor::getUserId())
		{
			$adminSession->delete();
		}
	}

	$bridge->getSessionModel()->processLastActivityUpdateForLogOut(XenForo_Visitor::getUserId());

	XenForo_Application::get('session')->delete();
	XenForo_Helper_Cookie::deleteAllCookies(
		array('session'),
		array('user' => array('httpOnly' => false))
	);

	XenForo_Visitor::setup(0);

	
	return xmlresptrue();
}
