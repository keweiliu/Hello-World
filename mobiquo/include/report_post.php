<?php


defined('IN_MOBIQUO') or exit;

function report_post_func($xmlrpc_params)
{
	$params = php_xmlrpc_decode($xmlrpc_params);

	$bridge = Tapatalk_Bridge::getInstance();
	$visitor = XenForo_Visitor::getInstance();

	//fake report for guest to fit apple's requirement
	if(empty($visitor['user_id']))
		return xmlresptrue();

	$data = $bridge->_input->filterExternal(array(
			'post_id' => XenForo_Input::UINT,
			'reason' => XenForo_Input::STRING,
	), $params);


	if(empty($data['reason'])){
		$data['reason'] = '';
	}

	$ftpHelper = $bridge->getHelper('ForumThreadPost');
	list($post, $thread, $forum) = $ftpHelper->assertPostValidAndViewable($data['post_id']);

	$reportModel = $bridge->getReportModel();
	if(!$reportModel->reportContent('post', $post, $data['reason'])){
		return xmlresptrue();
	}

	return xmlresptrue();

}