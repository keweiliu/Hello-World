<?php
	
defined('IN_MOBIQUO') or exit;

function remove_attachment_func($xmlrpc_params)
{
	$params = php_xmlrpc_decode($xmlrpc_params);
	
	$bridge = Tapatalk_Bridge::getInstance();
	$visitor = XenForo_Visitor::getInstance();
	
	$data = $bridge->_input->filterExternal(array(
			'attachment_id' => XenForo_Input::UINT,
			'forum_id' => XenForo_Input::UINT,
			'group_id' => XenForo_Input::STRING,
			'post_id' => XenForo_Input::UINT,
	), $params);
	
	$attachment = $bridge->getAttachmentModel()->getAttachmentById($data['attachment_id']);
	if (!$attachment)
	{
		$bridge->responseError(new XenForo_Phrase('requested_attachment_not_found'));
		return;
	}
		
	if (!$bridge->getAttachmentModel()->canDeleteAttachment($attachment, $data['group_id']))
	{
		 $bridge->getNoPermissionResponseException();
		 return;
	}

	$dw = XenForo_DataWriter::create('XenForo_DataWriter_Attachment');
	$dw->setExistingData($attachment, true);
	$dw->delete();
	
	return xmlresptrue();
}