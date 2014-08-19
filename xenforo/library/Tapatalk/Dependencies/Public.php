<?php
class Tapatalk_Dependencies_Public extends XenForo_Dependencies_Public
{
	/**
	 * Performs any pre-view rendering setup, such as getting style information and
	 * ensuring the correct data is registered.
	 *
	 * @param XenForo_ControllerResponse_Abstract|null $controllerResponse
	 */
	public function preRenderViewWithDefaultStyle(XenForo_ControllerResponse_Abstract $controllerResponse = null)
	{
		parent::preRenderView($controllerResponse);

		XenForo_Template_Abstract::setLanguageId(XenForo_Phrase::getLanguageId());

		$styles = (XenForo_Application::isRegistered('styles')
			? XenForo_Application::get('styles')
			: XenForo_Model::create('XenForo_Model_Style')->getAllStyles()
		);
		$styleId = XenForo_Application::get('options')->defaultStyleId;

		$style = $styles[$styleId];
		
		if ($style)
		{
			XenForo_Template_Helper_Core::setStyleProperties(unserialize($style['properties']));
			XenForo_Template_Public::setStyleId($style['style_id']);
		}

		// setup the default template params
		if ($style)
		{
			$this->_defaultTemplateParams['visitorStyle'] = $style;
		}
	}
}
?>
