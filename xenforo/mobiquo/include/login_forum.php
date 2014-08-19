<?php

defined('IN_MOBIQUO') or exit;

function login_forum_func($xmlrpc_params)
{
	$params = php_xmlrpc_decode($xmlrpc_params);
	
	return xmlresperror(new XenForo_Phrase('dark_passworded_forums_not_supported'));
}
