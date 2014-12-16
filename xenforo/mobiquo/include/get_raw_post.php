<?php

defined('IN_MOBIQUO') or exit;

function get_raw_post_func($xmlrpc_params)
{
	$params = php_xmlrpc_decode($xmlrpc_params);
	$bridge = Tapatalk_Bridge::getInstance();
	$postModel = $bridge->getPostModel();
	
	$data = $bridge->_input->filterExternal(array(
		'post_id'       => XenForo_Input::UINT,
	), $params);
	
	$ftpHelper = $bridge->getHelper('ForumThreadPost');
	list($post, $thread, $forum) = $ftpHelper->assertPostValidAndViewable($data['post_id']);
	
	if(!$postModel->canEditPost($post, $thread, $forum, $errorPhraseKey)){
		$bridge->getErrorOrNoPermissionResponseException($errorPhraseKey);
		return;
	}
		
	$accachmentParams = $bridge->getForumModel()->getAttachmentParams($forum,array('post_id'=>$post['post_id']));
	$posts=$postModel->getAndMergeAttachmentsIntoPosts(array($post["post_id"]=>$post));
	$post=$posts[$post["post_id"]];
	$attachment_list = array();
	if(isset($post['attachments']) && !empty($post['attachments'])){
		foreach($post['attachments'] as $attachment) {

			$type = $attachment['extension'];

			switch($attachment['extension']){
				case 'gif':
				case 'jpg':
				case 'png':
					$type = 'image';
					break;
				case 'pdf':
					$type = 'pdf';
					break;
			}
			$thumbnail = '';
			if(isset($attachment['thumbnailUrl']) && !empty($attachment['thumbnailUrl']))
			$thumbnail = XenForo_Link::convertUriToAbsoluteUri($attachment['thumbnailUrl'], true);

			$attachment_list[] = new xmlrpcval(array(
                	"attachment_id"	=>	new xmlrpcval($attachment['attachment_id'], 'string'),
                    'content_type'  => new xmlrpcval($type, 'string'),
                    'thumbnail_url' => new xmlrpcval($thumbnail, 'string'),
                    'url'           => new xmlrpcval(XenForo_Link::convertUriToAbsoluteUri(XenForo_Link::buildPublicLink('attachments', $attachment), true), 'string'),
                    'filename'      => new xmlrpcval($attachment['filename'], 'base64'),
                    'filesize'      => new xmlrpcval($attachment['file_size'], 'int'),
			), 'struct');
		}
	}
	$result = new xmlrpcval(array(
		'post_id'       => new xmlrpcval($post['post_id'], 'string'),
		'post_title'    => new xmlrpcval('', 'base64'),
		'post_content'  => new xmlrpcval($post['message'], 'base64'),
		"show_reason"   => new xmlrpcval(false, 'boolean'),
		"edit_reason"	=> new xmlrpcval(null, 'base64'),
		"group_id"		=> new xmlrpcval($accachmentParams['hash'], 'string'),
		"attachments"	=> new xmlrpcval($attachment_list,'array'),
	), 'struct');
	
	return new xmlrpcresp($result);
}
