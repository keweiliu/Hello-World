<?php

class Tapatalk_ViewRenderer_HtmlInternal extends XenForo_ViewRenderer_HtmlPublic
{
	
	public function __construct(XenForo_Dependencies_Abstract $dependencies, Zend_Controller_Response_Http $response, Zend_Controller_Request_Http $request)
	{
		$this->_dependencies = $dependencies;
		$this->_response = $response;
		$this->_request = $request;

		$this->_preloadContainerData();
	}
	
	public function renderError($error)
	{
		return '';
	}
	   
	
}