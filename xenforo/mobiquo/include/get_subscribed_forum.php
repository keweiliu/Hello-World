<?php

defined('IN_MOBIQUO') or exit;


function get_subscribed_forum_func()
{
    if(XenForo_Application::get('options')->currentVersionId < 1020070)
    {
        $result = new xmlrpcval(array(
        'total_forums_num' => new xmlrpcval(0, 'int'),
        'forums'           => new xmlrpcval(array(), 'array')
        ), 'struct');
        
        return new xmlrpcresp($result);
    }
    if (XenForo_Template_Helper_Core::styleProperty('forumIconReadPath'))
    {
        $icon_read =  XenForo_Link::convertUriToAbsoluteUri(XenForo_Template_Helper_Core::styleProperty('forumIconReadPath'), true);
        $icon_unread = XenForo_Link::convertUriToAbsoluteUri(XenForo_Template_Helper_Core::styleProperty('forumIconUnreadPath'), true);
        $icon_link = XenForo_Link::convertUriToAbsoluteUri(XenForo_Template_Helper_Core::styleProperty('linkIconPath'), true);
    }
    else
    {
        $tapatalk_dir_name = XenForo_Application::get('options')->tp_directory;
        if (empty($tapatalk_dir_name)) $tapatalk_dir_name = 'mobiquo';
        $icon_read =   FORUM_ROOT.$tapatalk_dir_name.'/forum_icons/forum-read.png';
        $icon_unread = FORUM_ROOT.$tapatalk_dir_name.'/forum_icons/forum-unread.png';
        $icon_link =   FORUM_ROOT.$tapatalk_dir_name.'/forum_icons/link.png';
    }
    $bridge = Tapatalk_Bridge::getInstance();
    $visitor = XenForo_Visitor::getInstance();
    $forumWatchModel = $bridge->getForumWatchModel();
    $forumModel = $bridge->getForumModel();

    $forumsWatched = $forumWatchModel->getUserForumWatchByUser($visitor['user_id']);
    $forumids = array_keys($forumsWatched);

    $forums = array();
    $forumdetails = $forumModel->getForumsByIds($forumids);
    foreach($forumdetails as $id => $node)
    {
        switch ($node['node_type_id'])
        {
            case 'Category' : $nodeType = 'category'; break;
            case 'LinkForum': $nodeType = 'link'; break;
            default : $nodeType = 'forum'; 
        }
        if(!isset($node['hasNew'])) $node['hasNew'] = 0;
        $icon = tp_get_forum_icon($id, $nodeType, false, ($node['hasNew'] || !$visitor['user_id']) );

        if (empty($icon)) {
            if($node['node_type_id'] == 'LinkForum') {
                $icon = $icon_link;
            } else {
            $icon = ($node['hasNew'] || !$visitor['user_id']) ? $icon_unread : $icon_read;
            }
        }

        $forums[] = new xmlrpcval(array(
            'forum_id'      => new xmlrpcval($node['node_id'], 'string'),
            'forum_name'    => new xmlrpcval($node['title'], 'base64'),
            'icon_url'      => new xmlrpcval($icon, 'string'),
            'new_post'      => new xmlrpcval(!empty($node['hasNew']), 'boolean'),
            'is_protected'  => new xmlrpcval(false, 'boolean'),
        ), 'struct');
    }
    $result = new xmlrpcval(array(
        'total_forums_num' => new xmlrpcval(count($forums), 'int'),
        'forums'           => new xmlrpcval($forums, 'array')
    ), 'struct');

    return new xmlrpcresp($result);
}
