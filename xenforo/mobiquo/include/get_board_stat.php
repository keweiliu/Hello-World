<?php

defined('IN_MOBIQUO') or exit;

function get_board_stat_func() 
{
    $bridge = Tapatalk_Bridge::getInstance();
    
    $boardTotals = $bridge->getModelFromCache('XenForo_Model_DataRegistry')->get('boardTotals');
    if (!$boardTotals)
    {
        $boardTotals = $bridge->getModelFromCache('XenForo_Model_Counters')->rebuildBoardTotalsCounter();
    }
    
    $visitor = XenForo_Visitor::getInstance();
    
    $sessionModel = $bridge->getModelFromCache('XenForo_Model_Session');
    
    $onlineUsers = $sessionModel->getSessionActivityQuickList(
        $visitor->toArray(),
        array('cutOff' => array('>', $sessionModel->getOnlineStatusTimeout())),
        ($visitor['user_id'] ? $visitor->toArray() : null)
    );
    
    $board_stat = array(
        'total_threads' => new xmlrpcval($boardTotals['discussions'], 'int'),
        'total_posts'   => new xmlrpcval($boardTotals['messages'], 'int'),
        'total_members' => new xmlrpcval($boardTotals['users'], 'int'),
        'guest_online'  => new xmlrpcval($onlineUsers['guests'], 'int'),
        'total_online'  => new xmlrpcval($onlineUsers['total'], 'int'),
    );
    
    $response = new xmlrpcval($board_stat, 'struct');
    
    return new xmlrpcresp($response);
}
