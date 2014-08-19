<?php

defined('IN_MOBIQUO') or exit;

function get_online_users_func($xmlrpc_params)
{
    $params = php_xmlrpc_decode($xmlrpc_params);
    $bridge = Tapatalk_Bridge::getInstance();
    $visitor = XenForo_Visitor::getInstance();
    $sessionModel = $bridge->getSessionModel();

    $bypassUserPrivacy = $bridge->getUserModel()->canBypassUserPrivacy();

    $data = $bridge->_input->filterExternal(array(
            'page' => XenForo_Input::UINT,
            'perpage' => XenForo_Input::UINT,
            'id' => XenForo_Input::STRING,
            'area' => XenForo_Input::STRING
    ), $params);

    if (!empty($data['id']))
    {
        $online_users = new xmlrpcval(array(
            'member_count' => new xmlrpcval(0, 'int'),
            'guest_count'  => new xmlrpcval(0, 'int'),
            'list'         => new xmlrpcval(array(), 'array'),
        ), 'struct');
    }
    else
    {
        $conditions = array(
            'cutOff' => array('>', $sessionModel->getOnlineStatusTimeout()),
            'getInvisible' => $bypassUserPrivacy,
            'getUnconfirmed' => $bypassUserPrivacy,
            'userLimit'      => 'registered',
            // allow force including of self, even if invisible
            'forceInclude' => ($bypassUserPrivacy ? false : XenForo_Visitor::getUserId())
        );
        
        $onlineUsers = $sessionModel->getSessionActivityRecords($conditions, array(
            'perPage' => $data['perpage'] ? $data['perpage'] : 200,
            'page' => $data['page'] ? $data['page'] : 1,
            'join' => XenForo_Model_Session::FETCH_USER,
            'order' => 'view_date'
        ));
    
        $onlineUsers = $sessionModel->addSessionActivityDetailsToList($onlineUsers);
    
        $user_lists = array();
    
        foreach($onlineUsers as $id => $user)
        {
            $activity = new XenForo_Phrase('viewing_forum');
            if(!empty($user['activityDescription'])){
                $activity = $user['activityDescription'];
                if(!empty($user['activityItemTitle'])){
                    $activity .= " ".$user['activityItemTitle'];
                }
            }
            $activity = preg_replace('/\[On Tapatalk\]/', '', $activity);
            $activity = preg_replace('/\[On BYO\]/', '', $activity);
            if(strpos($user['params'], 'tapatalk') !== false)
                $from = 'tapatalk';
            else if(strpos($user['params'], 'byo') !== false)
                $from = 'byo';
            else
                $from = 'browser';
            $activity .= " (".XenForo_Template_Helper_Core::dateTime($user['view_date'], 'relative').")";
            $user_lists[] = new xmlrpcval(array(
                'user_name'     => new xmlrpcval($user['username'], 'base64'),
                'user_id'       => new xmlrpcval($user['user_id'], 'string'),
                'user_type'     => new xmlrpcval(get_usertype_by_item('', $user['display_style_group_id'], $user['is_banned']), 'base64'),
                'display_text'  => new xmlrpcval($activity, 'base64'),
                'from'          => new xmlrpcval($from,'string'),
                'icon_url'      => new xmlrpcval(get_avatar($user), 'string'),
            ), 'struct');
        }
    
        $onlineTotals = $sessionModel->getSessionActivityQuickList(
            $visitor->toArray(),
            array('cutOff' => array('>', $sessionModel->getOnlineStatusTimeout())),
            ($visitor['user_id'] ? $visitor->toArray() : null)
        );
        //scrolling page of online users will result duplicate because Xenforo itself do it the same.
        $online_users = new xmlrpcval(array(
            'member_count' => new xmlrpcval($onlineTotals['members'] != count($user_lists) ? $onlineTotals['members'] : count($user_lists) , 'int'),
            'guest_count'  => new xmlrpcval($onlineTotals['guests'], 'int'),
            'list'         => new xmlrpcval($user_lists, 'array'),
        ), 'struct');
    }

    return new xmlrpcresp($online_users);
}
