<?php

defined('IN_MOBIQUO') or exit;

function get_quote_post_func($xmlrpc_params)
{
	$params = php_xmlrpc_decode($xmlrpc_params);
	$bridge = Tapatalk_Bridge::getInstance();
	$postModel = $bridge->getPostModel();
	
	$data = $bridge->_input->filterExternal(array(
		'post_id'	   => XenForo_Input::STRING,
	), $params);
	
	$ftpHelper = $bridge->getHelper('ForumThreadPost');
	$quote = '';
	$postids = array();
	if(preg_match('/-/',$data['post_id']))
		$postids = preg_split('/-/', $data['post_id']);
	else
		$postids = array($data['post_id']);
	foreach($postids as $postid)
	{
		if(!tt_validate_numeric($postid) || empty($postid)) continue;
		list($post, $thread, $forum) = $ftpHelper->assertPostValidAndViewable($postid,  array(
			'join' => XenForo_Model_Post::FETCH_USER
		));
		$quote .= $postModel->getQuoteTextForPost($post);
	}
	$result = new xmlrpcval(array(
		'post_id'	   => new xmlrpcval(implode('-', $postids)),
		'post_title'	=> new xmlrpcval('', 'base64'),
		'post_content'  => new xmlrpcval($quote, 'base64'),
	), 'struct');

	return new xmlrpcresp($result);
}
