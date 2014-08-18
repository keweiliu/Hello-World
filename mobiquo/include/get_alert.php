<?php

defined('IN_MOBIQUO') or exit;

function get_alert_func($xmlrpc_params)
{
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

    $params = php_xmlrpc_decode($xmlrpc_params);

    $bridge = Tapatalk_Bridge::getInstance();
    $visitor = XenForo_Visitor::getInstance();

    if(!$bridge->assertLoggedIn()) return;


    $data = $bridge->_input->filterExternal(array(
            'start' => XenForo_Input::UINT,
            'perpage' => XenForo_Input::UINT
    ), $params);
    $page = (isset($data['start']) && $data['start'] > 0) ? $data['start'] : 1;
    $perpage = (isset($data['perpage']) && $data['perpage'] > 0) ? $data['perpage'] : 20;
    $start = ($page-1)*$perpage + 1;
    $alertModel = $bridge->getAlertModel();
    $userModel = $bridge->getUserModel();
//    $fetchOptions = array(
//        'page'     => $page,
//        'perPage'  => $perpage+1
//    );
    $alertResults = $alertModel->getAlertsForUser(
        $visitor['user_id'],
        XenForo_Model_Alert::FETCH_MODE_ALL
    );
    $total_num = 0;
    // super dirty hax
    $derpView = new XenForo_ViewPublic_Base(
     new Tapatalk_ViewRenderer_HtmlInternal($bridge->getDependencies(), new Zend_Controller_Response_Http(), $bridge->_request),
     new Zend_Controller_Response_Http());
    $processedAlertNum = 0;
    $rt_alerts = array();
    
	if ($visitor['alerts_unread'])
	{
		$alertModel->markAllAlertsReadForUser($visitor['user_id']);
	}
    
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
        $processedAlertNum++;

        if(($processedAlertNum < $start) || ($processedAlertNum - $start > $perpage -1))
        {
            $total_num ++;
            continue;
        }

        if($alert['tp_type'] == 'sub')
            if($alert['content']['post_id'] == $alert['content']['first_post_id'])
                $alert['tp_type'] = 'newtopic';

        $message = sprintf($alert_format[$alert['tp_type']], $alert['user']['username'], basic_clean($alert['content']['title']));

        $rt_alert = array(
                'user_id'       => new xmlrpcval($alert['user']['user_id'], 'string'),
                'username'      => new xmlrpcval($alert['user']['username'], 'base64'),
                'user_type'     => new xmlrpcval(get_usertype_by_item($alert['user']['user_id']), 'base64'),
                'icon_url'      => new xmlrpcval(get_avatar($alert['user']), 'string'),
                'message'       => new xmlrpcval($message, 'base64'),
                'timestamp'     => new xmlrpcval($alert['event_date'], 'string'),
                'content_type'  => new xmlrpcval($alert['tp_type'], 'string'),
                'content_id'    => new xmlrpcval($alert['content']['post_id'], 'string'),
            );
        $rt_alerts[] = new xmlrpcval($rt_alert, 'struct');
        $total_num ++;
    }
    $return_data = array(
        'total' => new xmlrpcval($total_num, 'int'),
        'items' => new xmlrpcval($rt_alerts, 'array')
    );
    return new xmlrpcresp(new xmlrpcval($return_data, 'struct'));
}