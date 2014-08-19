<?php
	
defined('IN_MOBIQUO') or exit;

function get_id_by_url_func($xmlrpc_params)
{
	$params = php_xmlrpc_decode($xmlrpc_params);
	$bridge = Tapatalk_Bridge::getInstance();    
	$visitor = XenForo_Visitor::getInstance();
	
	$url = $bridge->_input->filterSingleExternal(XenForo_Input::STRING, $params);
		
	$url = str_ireplace("index.php?", "", $url);
	
	$request = new Zend_Controller_Request_Http($url);
	$request->setBasePath($bridge->_request->getBasePath());     
			
	$routeMatch = $bridge->getDependencies()->route($request);
	
	$result = array();
	
	switch($routeMatch->getControllerName()){
		case "XenForo_ControllerPublic_Thread":
			if($request->getParam('thread_id'))
				$result['topic_id'] = new xmlrpcval((int)$request->getParam('thread_id'), 'int');				
			break;
		case "XenForo_ControllerPublic_Forum":
			if($request->getParam('node_id'))
				$result['forum_id'] = new xmlrpcval((int)$request->getParam('node_id'), 'int');                
			break;
		case "XenForo_ControllerPublic_Post":
			if($request->getParam('post_id'))
				$result['post_id'] = new xmlrpcval((int)$request->getParam('post_id'), 'int');                
			break;
	}
	
	if(empty($result)){		
		$bridge->getErrorOrNoPermissionResponseException(new XenForo_Phrase('dark_unknown_url'));
		return;
	}
	
	return new xmlrpcresp(new xmlrpcval($result, 'struct'));
}