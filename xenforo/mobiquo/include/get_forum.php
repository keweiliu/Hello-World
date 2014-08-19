<?php

defined('IN_MOBIQUO') or exit;

if (XenForo_Template_Helper_Core::styleProperty('forumIconReadPath'))
{
    $icon_read =  XenForo_Link::convertUriToAbsoluteUri(XenForo_Template_Helper_Core::styleProperty('forumIconReadPath'), true);
    $icon_unread = XenForo_Link::convertUriToAbsoluteUri(XenForo_Template_Helper_Core::styleProperty('forumIconUnreadPath'), true);
    $icon_link = XenForo_Link::convertUriToAbsoluteUri(XenForo_Template_Helper_Core::styleProperty('linkIconPath'), true);
}
else
{
    $icon_read =   FORUM_ROOT.'mobiquo/forum_icons/forum-read.png';
    $icon_unread = FORUM_ROOT.'mobiquo/forum_icons/forum-unread.png';
    $icon_link =   FORUM_ROOT.'mobiquo/forum_icons/link.png';
}


function get_forum_func($xmlrpc_params)
{
    $bridge = Tapatalk_Bridge::getInstance();
    $nodeModel = $bridge->getNodeModel();
    $params = php_xmlrpc_decode($xmlrpc_params);

    $data = $bridge->_input->filterExternal(array(
            'description' => XenForo_Input::UINT,
            'forumid' => XenForo_Input::STRING,
    ), $params);
    $nodes = $nodeModel->getAllNodes(false, true);
    $nodePermissions = $nodeModel->getNodePermissionsForPermissionCombination();

    $nodeHandlers = $nodeModel->getNodeHandlersForNodeTypes(
        $nodeModel->getUniqueNodeTypeIdsFromNodeList($nodes)
    );

    $nodes = $nodeModel->getViewableNodesFromNodeList($nodes, $nodeHandlers, $nodePermissions);
    $nodes = $nodeModel->mergeExtraNodeDataIntoNodeList($nodes, $nodeHandlers);
    $nodes = $nodeModel->prepareNodesWithHandlers($nodes, $nodeHandlers);

    foreach($nodes as $id => $node)
    {
        if(($node['parent_node_id'] != 0 && !isset($nodes[$node['parent_node_id']])) || !$node['display_in_list'])
            unset($nodes[$id]);
        
        if (!isset($node['hasNew'])) $nodes[$id]['hasNew'] = 0;
    }
    $xml_nodes = new xmlrpcval(array(), 'array');
    $done=array();
    if(isset($data['forumid']) && !empty($data['forumid']))
        $xml_tree = treeBuildForId($data['forumid'], $nodes, $xml_nodes, $done);
    else
        $xml_tree = treeBuild(0, $nodes, $xml_nodes, $done);
    $xml_nodes->addArray($xml_tree);

    return new xmlrpcresp($xml_nodes);
}

function processNode($id, $node, &$nodes)
{
    global $icon_read, $icon_unread, $icon_link;
    $visitor = XenForo_Visitor::getInstance();

    $url = '';
    if($node['node_type_id'] == 'LinkForum'){
        $url = XenForo_Link::convertUriToAbsoluteUri(XenForo_Link::buildPublicLink('link-forums', $node), true);
    }
    
    switch ($node['node_type_id'])
    {
        case 'Category' : $nodeType = 'category'; break;
        case 'LinkForum': $nodeType = 'link'; break;
        default : $nodeType = 'forum'; 
    }
    $icon = tp_get_forum_icon($id, $nodeType, false, ($node['hasNew'] || !$visitor['user_id']) );
    if (empty($icon)) {
        if($node['node_type_id'] == 'LinkForum') {
            $icon = $icon_link;
        } else {
            $icon = ($node['hasNew'] || !$visitor['user_id']) ? $icon_unread : $icon_read;
        }
    }
    if($nodeType == 'forum' && !(XenForo_Application::get('options')->currentVersionId < 1020070))
    {
        $bridge = Tapatalk_Bridge::getInstance();
        $forumModel = $bridge->getForumModel();
        $forum = $forumModel->getForumById($id);
        $is_subscribed = $bridge->getForumWatchModel()->getUserForumWatchByForumId($visitor['user_id'], $id);
        $can_subscribe = $is_subscribed ? false : $forumModel->canWatchForum($forum);
    }
    else
    {
        $is_subscribed = false;
        $can_subscribe = false;
    }
//    $tp_icon = tp_get_forum_icon($id);
    
    $xmlrpc_forum = new xmlrpcval(array(
        'forum_id'      => new xmlrpcval($id, 'string'),
        'forum_name'    => new xmlrpcval($node['title'], 'base64'),
        'description'   => new xmlrpcval($node['description'], 'base64'),
        'parent_id'     => new xmlrpcval($node['parent_node_id'], 'string'),
        'logo_url'      => new xmlrpcval($icon, 'string'),
        'new_post'      => new xmlrpcval(!empty($node['hasNew']), 'boolean'),
        'unread_count'  => new xmlrpcval(!empty($node['hasNew']) ? $node['hasNew'] : 0, 'int'),
        'is_protected'  => new xmlrpcval(false, 'boolean'),
        'url'           => new xmlrpcval($url, 'string'),
        'sub_only'      => new xmlrpcval($node['node_type_id'] == 'Category', 'boolean'),
        'can_subscribe' => new xmlrpcval($can_subscribe, 'boolean'),
        'is_subscribed' => new xmlrpcval($is_subscribed, 'boolean'),
    ), 'struct');

    if ($node['hasNew'] && $node['parent_node_id']) $nodes[$node['parent_node_id']]['hasNew'] += $node['hasNew'];

    return $xmlrpc_forum;
}

function treeBuild($parent_id, &$nodes, &$xml_nodes, &$done)
{
    $newNodes = array();
    foreach($nodes as $id => &$node){
        // not interested in page nodes or nodes from addons etc.
        if(!isset($node['node_type_id']) || ($node['node_type_id'] != 'Forum' && $node['node_type_id'] != 'Category' && $node['node_type_id'] != 'LinkForum'))
            continue;

        if($node['parent_node_id'] === $parent_id && !array_key_exists($id, $done))
        {
            $done[$id] = true;
            $child_nodes = treeBuild($id, $nodes, $xml_nodes, $done);
            $node2 = processNode($id, $node, $nodes);

            if (empty($child_nodes))
            {
                if ($node['node_type_id'] == 'Category') continue;
            }
            else
                $node2->addStruct(array('child' => new xmlrpcval($child_nodes, 'array')));

            $newNodes[]=$node2;

        }
    }

    return $newNodes;
}

function treeBuildForId($parent_id, &$nodes, &$xml_nodes, &$done)
{
    $newNodes = array();
    $child_nodes = array();
    $node2 = processNode($parent_id, $nodes[$parent_id], $nodes);
    foreach($nodes as $id => &$node)
    {
        if(!isset($node['node_type_id']) || ($node['node_type_id'] != 'Forum' && $node['node_type_id'] != 'Category' && $node['node_type_id'] != 'LinkForum'))
            continue;
        if($node['parent_node_id'] == $parent_id)
        {
            $child_nodes[] = processNode($node['node_id'], $node, $nodes);
        }
    }
    $node2->addStruct(array('child' => new xmlrpcval($child_nodes, 'array')));
    $newNodes[] = $node2;

    return $newNodes;
}
function stillHasChildren($id, &$nodes)
{
    foreach($nodes as $node_id => $node){
        if($node['parent_node_id'] === $id /*&& $node_id !== $id && $id !== 0*/) return true;
    }
    
    return false;
}
