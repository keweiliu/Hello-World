<?php

defined('IN_MOBIQUO') or exit;

function reply_post_func($xmlrpc_params)
{

	$params = php_xmlrpc_decode($xmlrpc_params);

	$bridge = Tapatalk_Bridge::getInstance();
	$visitor = XenForo_Visitor::getInstance();    
	
	$data = $bridge->_input->filterExternal(array(
			'forum_id' => XenForo_Input::UINT,
			'topic_id' => XenForo_Input::UINT,
			'subject' => XenForo_Input::STRING,
			'text_body' => XenForo_Input::STRING,
			'attachment_id_array' => XenForo_Input::ARRAY_SIMPLE,
			'group_id' => XenForo_Input::STRING,
			'return_html' => XenForo_Input::UINT,
	), $params);       
	
	$forumModel = $bridge->getForumModel();
	$threadModel = $bridge->getThreadModel();
	$postModel = $bridge->getPostModel();
	
	$ftpHelper = $bridge->getHelper('ForumThreadPost');
	$threadFetchOptions = array('readUserId' => $visitor['user_id']);
	$forumFetchOptions = array('readUserId' => $visitor['user_id']);
	list($thread, $forum) = $ftpHelper->assertThreadValidAndViewable($data['topic_id'], $threadFetchOptions, $forumFetchOptions);
	
	if (!$threadModel->canReplyToThread($thread, $forum, $errorPhraseKey))
	{
		$bridge->getErrorOrNoPermissionResponseException($errorPhraseKey);
		return;
	}

	$data['text_body'] = XenForo_Helper_String::autoLinkBbCode($data['text_body']);

	$writer = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_Post');
	$writer->set('user_id', $visitor['user_id']);
	$writer->set('username', $visitor['username']);
	$writer->set('message', $data['text_body']);
	$writer->set('message_state', $bridge->getPostModel()->getPostInsertMessageState($thread, $forum));
	$writer->set('thread_id', $thread['thread_id']);
	if(!empty($data['group_id']))
		$writer->setExtraData(XenForo_DataWriter_DiscussionMessage::DATA_ATTACHMENT_HASH, $data['group_id']);
	$writer->preSave();

	if (!$writer->hasErrors())
	{
		$bridge->assertNotFlooding('post');
	}

	$writer->save();
	$post = $writer->getMergedData();
	
	$data['watch_thread_state'] = false;
	$bridge->getThreadWatchModel()->setVisitorThreadWatchStateFromInput($thread['thread_id'], $data);
	
	$canViewPost = $postModel->canViewPost($post, $thread, $forum);
	if($canViewPost)
        $threadModel->markThreadRead($thread, $forum, XenForo_Application::$time);

	$postFetchOptions = $postModel->getPermissionBasedPostFetchOptions($thread, $forum) + array(
		'join' => XenForo_Model_Post::FETCH_USER | XenForo_Model_Post::FETCH_USER_PROFILE,
		'likeUserId' => $visitor['user_id']
	);	
	
	$posts = $postModel->getPostsByIds(array($post['post_id']), $postFetchOptions);
	$posts = $postModel->getAndMergeAttachmentsIntoPosts($posts);
	
	$options = array(
		'states' => array(
			'viewAttachments' => $threadModel->canViewAttachmentsInThread($thread, $forum),
			'returnHtml' => (boolean)$data['return_html']
		)
	);
	
	$post = $posts[$post['post_id']];
	$permissions = $visitor->getNodePermissions($thread['node_id']);
	$post = $postModel->preparePost($post, $thread, $forum, $permissions);
		
	$attachment_list = array();
	
	if(!empty($post['attachments'])){
		$options['states']['attachments'] = $post['attachments'];

		if (stripos($post['message'], '[/attach]') !== false)
		{
			if (preg_match_all('#\[attach(=[^\]]*)?\](?P<id>\d+)\[/attach\]#i', $post['message'], $matches))
			{
				foreach ($matches['id'] AS $attachId)
				{
					unset($post['attachments'][$attachId]);
				}
			}
		}
		
		foreach($post['attachments'] as $attachment) {
			
			$type = 'other';
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
			
			$attachment_list[] = new xmlrpcval(array(
				'content_type'  => new xmlrpcval($type, 'string'),
				'thumbnail_url' => new xmlrpcval(XenForo_Link::convertUriToAbsoluteUri($attachment['thumbnailUrl'], true), 'string'),
				'url'           => new xmlrpcval(XenForo_Link::convertUriToAbsoluteUri(XenForo_Link::buildPublicLink('attachments', $attachment), true), 'string'),
			), 'struct');
		}        
	}

	$result = new xmlrpcval(array(
		'result'        => new xmlrpcval(true, 'boolean'),
		'result_text'   => new xmlrpcval('', 'base64'),
		'post_id'       => new xmlrpcval($post['post_id'], 'string'),
		'state'         => new xmlrpcval($threadModel->canViewThread($thread, $forum) ? 0 : 1, 'int'),
		'group_id'      => new xmlrpcval('', 'string'),
		'post_title'       => new xmlrpcval('', 'base64'), //not supported in XenForo
		'post_content'     => new xmlrpcval($bridge->cleanPost($post['message'], $options), 'base64'),
		'post_author_name' => new xmlrpcval($post['username'], 'base64'),
		'user_type'         => new xmlrpcval(get_usertype_by_item('', $post['display_style_group_id'], $post['is_banned']), 'base64'),
		'is_online'        => new xmlrpcval($bridge->isUserOnline($post), 'boolean'),
		'can_edit'         => new xmlrpcval($post['canEdit'], 'boolean'),
		'icon_url'         => new xmlrpcval(get_avatar($post), 'string'),
		'post_time'        => new xmlrpcval(mobiquo_iso8601_encode($post['post_date']), 'dateTime.iso8601'),
		'timestamp'         => new xmlrpcval($post['post_date'],'string'),
		'attachments'      => new xmlrpcval($attachment_list, 'array'),
	), 'struct');
		
	return new xmlrpcresp($result);
}
