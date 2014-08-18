<?php
  
defined('IN_MOBIQUO') or exit;

function upload_avatar_func($xmlrpc_params)
{
	$params = php_xmlrpc_decode($xmlrpc_params);    
	
	$bridge = Tapatalk_Bridge::getInstance();
	$visitor = XenForo_Visitor::getInstance();    
	
	$data = $bridge->_input->filter(array(
			'content' => XenForo_Input::STRING,
	));     
	

	if (!$visitor->canUploadAvatar())
	{
		 $bridge->getNoPermissionResponseException();
		 return;
	}

	$avatar = XenForo_Upload::getUploadedFile('upload');

	/* @var $avatarModel XenForo_Model_Avatar */
	$avatarModel = $bridge->getModelFromCache('XenForo_Model_Avatar');

	$avatarData = $avatarModel->uploadAvatar($avatar, $visitor['user_id'], $visitor->getPermissions());


	// merge new data into $visitor, if there is any
	if (isset($avatarData) && is_array($avatarData))
	{
		foreach ($avatarData AS $key => $val)
		{
			$visitor[$key] = $val;
		}
	}
		
	
	$result = new xmlrpcval(array(
		'result'          => new xmlrpcval(true, 'boolean'),
		'result_text'     => new xmlrpcval('', 'base64'),
	), 'struct');

	return new xmlrpcresp($result);
	
}