<?php
  

defined('IN_MOBIQUO') or exit;

function report_pm_func($xmlrpc_params)
{	
	$bridge = Tapatalk_Bridge::getInstance();
	$bridge->responseError(new XenForo_Phrase('dark_not_supported'));
	return;
}