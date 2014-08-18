<?php

defined('IN_MOBIQUO') or exit;

function new_topic_func($xmlrpc_params)
{
	$params = php_xmlrpc_decode($xmlrpc_params);

	$bridge = Tapatalk_Bridge::getInstance();
	$visitor = XenForo_Visitor::getInstance();

	$data = $bridge->_input->filterExternal(array(
			'forum_id' => XenForo_Input::UINT,
			'subject' => XenForo_Input::STRING,
			'message' => XenForo_Input::STRING,
			'prefix_id' => XenForo_Input::STRING,
			'attachment_id_array' => XenForo_Input::ARRAY_SIMPLE,
			'group_id' => XenForo_Input::STRING,
	), $params);


	$ftpHelper = $bridge->getHelper('ForumThreadPost');
	$forum = $ftpHelper->assertForumValidAndViewable($data['forum_id']);

	$forumModel = $bridge->getForumModel();
	$threadModel = $bridge->getThreadModel();
	$prefixModel = $bridge->_getPrefixModel();

	$forumId = $forum['node_id'];

	// glitchy
	if (!$forumModel->canPostThreadInForum($forum, $errorPhraseKey))
	{
		$bridge->getErrorOrNoPermissionResponseException($errorPhraseKey);
		return;
	}

	$data['message'] = XenForo_Helper_String::autoLinkBbCode($data['message']);

	if (!$prefixModel->verifyPrefixIsUsable($data['prefix_id'], $forumId))
	{
		$data['prefix_id'] = 0; // not usable, just blank it out
	}

	$writer = XenForo_DataWriter::create('XenForo_DataWriter_Discussion_Thread');
	$writer->set('user_id', $visitor['user_id']);
	$writer->set('username', $visitor['username']);
	$writer->set('title', $data['subject']);
	$writer->set('node_id', $forumId);
	$writer->set('prefix_id', $data['prefix_id']);

	$writer->set('discussion_state', $bridge->getModelFromCache('XenForo_Model_Post')->getPostInsertMessageState(array(), $forum));

	$postWriter = $writer->getFirstMessageDw();
	if(!empty($data['group_id']))
		$postWriter->setExtraData(XenForo_DataWriter_DiscussionMessage::DATA_ATTACHMENT_HASH, $data['group_id']);
	$postWriter->set('message', $data['message']);

	$writer->preSave();
	if (!$writer->hasErrors()){
		$bridge->assertNotFlooding('post');
	}

	// glitchy
	$writer->save();

	$thread = $writer->getMergedData();

	$data['watch_thread_state'] = false;
	$bridge->getThreadWatchModel()->setVisitorThreadWatchStateFromInput($thread['thread_id'], $data);

	if (version_compare(XenForo_Application::$version, '1.0.4', '>'))
	    $threadModel->markThreadRead($thread, $forum, XenForo_Application::$time);
	else
	    $threadModel->markThreadRead($thread, $forum, XenForo_Application::$time, $visitor['user_id']);

	$result = new xmlrpcval(array(
		'result'        => new xmlrpcval(true, 'boolean'),
		'result_text'   => new xmlrpcval('', 'base64'),
		'topic_id'      => new xmlrpcval($thread['thread_id'], 'string'),
		'state'         => new xmlrpcval($threadModel->canViewThread($thread, $forum) ? 0 : 1, 'int'),
	), 'struct');

/*    $forum_id = $params[0];
	$subject  = $params[1];
	$message  = $params[2];
	$prefix_id  = $params[3];
	$attachment_ids  = $params[4];

	// post the new topic and get the new topic id
	$tid = '123';
	$result = true;
	$action_message = '';
	// state value as 1 means it need approve before publishing
	$state = 0;

	$result = new xmlrpcval(array(
		'result'        => new xmlrpcval($result, 'boolean'),
		'result_text'   => new xmlrpcval($action_message, 'base64'),
		'topic_id'      => new xmlrpcval($tid, 'string'),
		'state'         => new xmlrpcval($state, 'int'),
	), 'struct');*/

	return new xmlrpcresp($result);
}
