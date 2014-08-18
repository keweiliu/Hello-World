<?php
  
defined('IN_MOBIQUO') or exit;

function authorize_user_func($xmlrpc_params){
	
	$bridge = Tapatalk_Bridge::getInstance();
	$loginModel = $bridge->getLoginModel();
	$userModel = $bridge->getUserModel();
	$params = php_xmlrpc_decode($xmlrpc_params);
	
	$data = $bridge->_input->filterExternal(array(
			'login' => XenForo_Input::STRING,
			'password' => XenForo_Input::STRING
	), $params);
	
	$userId = $userModel->validateAuthentication($data['login'], $data['password'], $error);
	
	if (!$userId)
	{
		$loginModel->logLoginAttempt($data['login']);
		return new xmlrpcresp(new xmlrpcval(array('authorize_result' => new xmlrpcval(false, 'boolean')), 'struct'));
	}
	
	
	$loginModel->clearLoginAttempts($data['login']);
	
	XenForo_Model_Ip::log($userId, 'user', $userId, 'login');
	
	$userModel->deleteSessionActivity(0, $bridge->_request->getClientIp(false));
	
	$session = XenForo_Application::get('session');
	$session->changeUserId($userId);
	XenForo_Visitor::setup($userId);	
	
	return new xmlrpcresp(new xmlrpcval(array('authorize_result' => new xmlrpcval(true, 'boolean')), 'struct'));    
}