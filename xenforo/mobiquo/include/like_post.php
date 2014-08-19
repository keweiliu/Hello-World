<?php
  
defined('IN_MOBIQUO') or exit;

function like_post_func($xmlrpc_params)
{
	$params = php_xmlrpc_decode($xmlrpc_params);
	$bridge = Tapatalk_Bridge::getInstance();    
	$visitor = XenForo_Visitor::getInstance();
	
	$data = $bridge->_input->filterExternal(array(
			'post_id' => XenForo_Input::UINT
	), $params);       
	
	$postId = $data['post_id'];	
	
	$ftpHelper = $bridge->getHelper('ForumThreadPost');
	list($post, $thread, $forum) = $ftpHelper->assertPostValidAndViewable($postId);

	if (!$bridge->getPostModel()->canLikePost($post, $thread, $forum, $errorPhraseKey))
	{
		$bridge->getErrorOrNoPermissionResponseException($errorPhraseKey);
		return;
	}

	$likeModel = $bridge->getLikeModel();

	$existingLike = $likeModel->getContentLikeByLikeUser('post', $postId, XenForo_Visitor::getUserId());

	if ($existingLike)
	{  
		// It's a mobile app - let's do the sensible thing and stay silent here.
	}
	else
	{
		$latestUsers = $likeModel->likeContent('post', $postId, $post['user_id']);
	}
	
	return xmlresptrue();
}