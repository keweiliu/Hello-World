<?php

defined('IN_MOBIQUO') or exit;

function get_dashboard_func($xmlrpc_params)
{
	$params = php_xmlrpc_decode($xmlrpc_params);

	$bridge = Tapatalk_Bridge::getInstance();
	$visitor = XenForo_Visitor::getInstance();

	if(!$bridge->assertLoggedIn()) return;


	$data = $bridge->_input->filterExternal(array(
			'mark_unread' => XenForo_Input::UINT
	), $params);

	$alertModel = $bridge->getAlertModel();
	$userModel = $bridge->getUserModel();
    $fetchOptions = array(
        'limit' => 49,
        'offset' => 0
    );
	$alertResults = $alertModel->getAlertsForUser(
		$visitor['user_id'],
		XenForo_Model_Alert::FETCH_MODE_ALL,
		$fetchOptions
	);
	if ($visitor['alerts_unread'] && $data['mark_unread'])
	{
		$alertModel->markAllAlertsReadForUser($visitor['user_id']);
	}
	$newAlerts = 0;

	$alerts = array();
	$feeds = array();
	$likes = array();

	// super dirty hax
	$derpView = new XenForo_ViewPublic_Base(
	 new Tapatalk_ViewRenderer_HtmlInternal($bridge->getDependencies(), new Zend_Controller_Response_Http(), $bridge->_request),
	 new Zend_Controller_Response_Http());

        $alert_format = array(
            'sub'       => '%s replied to "%s"',
            'like'      => '%s liked your post in thread "%s"',
            'thank'     => '%s thanked your post in thread "%s"',
            'quote'     => '%s quoted your post in thread "%s"',
            'tag'       => '%s mentioned you in thread "%s"',
            'newtopic'  => '%s started a new thread "%s"',
            'pm'        => '%s sent you a message "%s"',
            'ann'       => '%sNew Announcement "%s"',
        );
        foreach($alertResults['alerts'] as $id => $alert)
        {
             $allow_action = array(
                'insert'      => 'sub',
                'watch_reply' => 'sub',
                'quote'       => 'quote',
                'tag'         => 'tag',
                'like'        => 'like',
                'sub'         => 'sub',
                'insert_attachment'=> 'sub',
            );

            if (!isset($allow_action[$alert['action']])) 
            {
                continue;
            }

            $alert['tp_type'] = $allow_action[$alert['action']];
                
            if (!isset($alert_format[$alert['tp_type']])) 
            {
                continue;
            }

            if($alert['tp_type'] == 'sub')
                if($alert['content']['post_id'] == $alert['content']['first_post_id'])
                    $alert['tp_type'] = 'newtopic';
    
            $message = sprintf($alert_format[$alert['tp_type']], $alert['user']['username'], basic_clean($alert['content']['title']));

		if($alert['unviewed'])
			$newAlerts ++;

		$newAlert = array(
			'new_alert'  => new xmlrpcval($alert['unviewed'], 'boolean'),
			//'icon_url' =>  new xmlrpcval(get_avatar($alert['user']), 'string'),
			//'post_id' => new xmlrpcval($alert['content']['post_id'], 'string'),
			//'topic_id' => new xmlrpcval($alert['content']['thread_id'], 'string'),
			//'username' => new xmlrpcval($alert['user']['username'], 'base64'),
			'message' => new xmlrpcval($message, 'base64'),
			'short_content' => new xmlrpcval($message, 'base64'),
			'post_time'         => new xmlrpcval(mobiquo_iso8601_encode($alert['event_date']), 'dateTime.iso8601'),
			'timestamp'         => new xmlrpcval($alert['event_date'],'string'),
		);

		if(!empty($alert['content']['post_id']))
			$newAlert['post_id'] = new xmlrpcval($alert['content']['post_id'], 'string');
		if(!empty($alert['content']['topic_id']))
			$newAlert['topic_id'] = new xmlrpcval($alert['content']['topic_id'], 'string');
		if(!empty($alert['user'])){
			$newAlert['icon_url'] = new xmlrpcval(get_avatar($alert['user']), 'string');
			$newAlert['username'] = new xmlrpcval($alert['user']['username'], 'base64');
		}

		$alerts[] = new xmlrpcval($newAlert, 'struct');
		}
/*
	if (XenForo_Application::get('options')->enableNewsFeed){
		$feedResults = $bridge->getNewsFeedModel()->getNewsFeedForUser($visitor->toArray());

		$userIds = array();
		foreach($feedResults['newsFeed'] as $feed){
			$userIds[] = $feed['user']['user_id'];
		}
		$users = $userModel->getUsersByIds($userIds);

		foreach($feedResults['newsFeed'] as $id => $feed){
			$feedResults['newsFeed'][$id]['template'] = $feedResults['newsFeedHandlers'][$feed['news_feed_handler_class']]->renderHtml($feed, $derpView);
			$html = $feedResults['newsFeed'][$id]['template']->render();

			preg_match('/<h3 class=".*?(?:description|title).*?">(.*?)<\/h3>(?:\s+<p class=".*?snippet.*?">(.*?)<\/p>)?/is', $html, $matches);

			if(empty($matches[2]))
				$matches[2] = $matches[1];

			$message = $matches[1];
			$message = preg_replace('/<a href=".*?members\/.*?" class=".*?username.*?">(.*?)<\/a>/i', '[USERNAME]$1[/USERNAME]', $message);
			$message = preg_replace_callback('/<a href="(profile-posts.*?)".*?>(.*?)<\/a>/i', 'parse_local_link', $message); //since the next one catches this
			$message = preg_replace('/<a href=".*?posts\/.*?".*?>(.*?)<\/a>/i', '[POST]$1[/POST]', $message);
			$message = preg_replace('/<a href=".*?threads\/.*?".*?>(.*?)<\/a>/i', '[TOPIC]$1[/TOPIC]', $message);
			$message = preg_replace('/<a href="http:\/\/(.*?)".*?>(.*?)<\/a>/i', '[URL=$1]$2[/URL]', $message);
			$message = preg_replace_callback('/<a href="(.*?)".*?>(.*?)<\/a>/i', 'parse_local_link', $message);
			$message = basic_clean($message);

			$shortContent = trim(strip_tags($matches[2]));

			$newFeed = array(
				//'icon_url' =>  new xmlrpcval(get_avatar($feed['user']), 'string'),
				//'post_id' => new xmlrpcval($feed['content']['post_id'], 'string'),
				//'topic_id' => new xmlrpcval($feed['content']['thread_id'], 'string'),
				//'username' => new xmlrpcval($feed['user']['username'], 'base64'),
				'message' => new xmlrpcval($message, 'base64'),
				'short_content' => new xmlrpcval($shortContent, 'base64'),
				'post_time' => new xmlrpcval(mobiquo_iso8601_encode($feed['event_date']), 'dateTime.iso8601'),
				'timestamp'         => new xmlrpcval($feed['event_date'],'string'),
			);

			if(!empty($feed['content']['post_id']))
				$newFeed['post_id'] = new xmlrpcval($feed['content']['post_id'], 'string');
			if(!empty($feed['content']['topic_id']))
				$newFeed['topic_id'] = new xmlrpcval($feed['content']['topic_id'], 'string');
			if(!empty($feed['user']['user_id']) && !empty($users[$feed['user']['user_id']])){
				$newFeed['icon_url'] = new xmlrpcval(get_avatar($users[$feed['user']['user_id']]), 'string');
				$newFeed['username'] = new xmlrpcval($users[$feed['user']['user_id']]['username'], 'base64');
			}

			$feeds[] = new xmlrpcval($newFeed, 'struct');
		}
	}
*/
	$likeModel = $bridge->getLikeModel();

	$totalLikes = $likeModel->countLikesForContentUser($visitor['user_id']);
	$likeResults = $likeModel->getLikesForContentUser($visitor['user_id'], array(
		'page' => 1,
		'perPage' => 25
	));
	$likeResults = $likeModel->addContentDataToLikes($likeResults);

	/*
	$derpView->setParams(array(
		'selectedGroup' => $selectedGroup,
		'selectedLink' => $selectedLink,
		'selectedKey' => "$selectedGroup/$selectedLink",
	));*/

	$userIds = array();
	foreach($likeResults as $item){
		$userIds[] = $item['like_user_id'];
	}
	$users = $userModel->getUsersByIds($userIds);

	foreach($likeResults as $id => &$item){

		$item['listTemplate'] = $derpView->createTemplateObject($item['listTemplateName'], array(
			'item' => $item,
			/*'user' => array(
				'user_id' => $item['user_id'],
				'username' => $item['username'],
			),*/
			'user' => $users[$item['like_user_id']],
			'content' => $item['content']
		));

		$item['template'] = $derpView->createTemplateObject('news_feed_item', array(
			'itemTemplate' => $item['listTemplate'],
			'itemDate' => $item['like_date'],
		));

		$html = $item['template']->render();
		preg_match('/<h3 class=".*?(?:description|title).*?">(.*?)<\/h3>\s+<p class=".*?snippet.*?">(.*?)<\/p>/is', $html, $matches);

		$message = $matches[1];
		$message = preg_replace('/<a href=".*?members\/.*?" class=".*?username.*?">(.*?)<\/a>/i', '[USERNAME]$1[/USERNAME]', $message);
		$message = preg_replace_callback('/<a href="(profile-posts.*?)".*?>(.*?)<\/a>/i', 'parse_local_link', $message); //since the next one catches this
		$message = preg_replace('/<a href=".*?posts\/.*?".*?>(.*?)<\/a>/i', '[POST]$1[/POST]', $message);
		$message = preg_replace('/<a href=".*?threads\/.*?".*?>(.*?)<\/a>/i', '[TOPIC]$1[/TOPIC]', $message);
		$message = preg_replace('/<a href="http:\/\/(.*?)".*?>(.*?)<\/a>/i', '[URL=$1]$2[/URL]', $message);
		$message = preg_replace_callback('/<a href="(.*?)".*?>(.*?)<\/a>/i', 'parse_local_link', $message);
		$message = basic_clean($message);

		$shortContent = trim(strip_tags($matches[2]));

		$likes[] = new xmlrpcval(array(
			'icon_url' =>  new xmlrpcval(get_avatar($users[$item['like_user_id']]), 'string'),
			'post_id' => new xmlrpcval(isset($item['content']['post_id']) ? $item['content']['post_id'] : '', 'string'),
			'topic_id' => new xmlrpcval(isset($item['content']['thread_id']) ? $item['content']['thread_id'] : '', 'string'),
			'username' => new xmlrpcval($users[$item['like_user_id']]['username'], 'base64'),
			'message' => new xmlrpcval($message, 'base64'),
			'short_content' => new xmlrpcval($shortContent, 'base64'),
			'post_time' => new xmlrpcval(mobiquo_iso8601_encode($item['like_date']), 'dateTime.iso8601'),
			'timestamp'         => new xmlrpcval($item['like_date'],'string'),
		), 'struct');
	}

	$result = new xmlrpcval(array(
		'total_likes'     => new xmlrpcval($totalLikes, 'int'),
		'new_alerts'      => new xmlrpcval($newAlerts, 'int'),
		'alerts'          => new xmlrpcval($alerts, 'array'),
		'feed'            => new xmlrpcval($feeds, 'array'),
		'likes'           => new xmlrpcval($likes, 'array'),
	), 'struct');

	return new xmlrpcresp($result);

}
