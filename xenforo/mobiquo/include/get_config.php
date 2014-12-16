<?php

defined('IN_MOBIQUO') or exit;

function get_config_func()
{
    global $mobiquo_config;

    $options = XenForo_Application::get('options');
    $bridge = Tapatalk_Bridge::getInstance();
    $permissions = $bridge->getPermissionModel()->getAllGlobalPermissionEntriesForUserCollectionGrouped(1); //guests
    $is_board_active = XenForo_Application::get('originBoardActive');
    $guest_permission = XenForo_Permission::hasPermission($permissions, 'general', 'view');
    $config_list = array(
        'sys_version'    => new xmlrpcval(XenForo_Application::$version, 'string'),
        'guest_okay'     => new xmlrpcval(isset($guest_permission['permission_value']) && $guest_permission['permission_value'] == 'allow', 'boolean'),
        'push'           => new xmlrpcval('1', 'string'),
    );
    if(!$is_board_active)
        $config_list['result_text'] = new xmlrpcval(!isset($options->boardInactiveMessage) || empty($options->boardInactiveMessage) ? 'Sorry, we\'re currently unavailable. Please check back later.' : $options->boardInactiveMessage, 'base64');
    $visitor = XenForo_Visitor::getInstance();
    $config_list['guest_search'] =  new xmlrpcval($visitor->canSearch(), 'string');
    if(isset($options->tp_push_key) && !empty($options->tp_push_key))
        $config_list['api_key'] =  new xmlrpcval(md5($options->tp_push_key), 'string');



    foreach($mobiquo_config as $key => $value){
        if(!array_key_exists($key, $config_list)){
            $config_list[$key] = new xmlrpcval($value, 'string');
        }
    }

    if((string)new XenForo_Phrase('dark_in_tapatalk') == 'dark_in_tapatalk'){
        $result_text = "Tapatalk add-on file 'addon-Tapatalk.xml' was not installed. It may affect some app features in this forum. Please inform the forum admin to complete the installation.";
        $config_list['result_text'] = new xmlrpcval($result_text, 'base64');
    }
    
    $boardTotals = $bridge->getModelFromCache('XenForo_Model_DataRegistry')->get('boardTotals');
    if (!$boardTotals)
    {
        $boardTotals = $bridge->getModelFromCache('XenForo_Model_Counters')->rebuildBoardTotalsCounter();
    }
    $config_list['stats'] = new xmlrpcval(array(
        'topic'    => new xmlrpcval($boardTotals['discussions'], 'int'),
        'messages' => new xmlrpcval($boardTotals['messages'], 'int'),
        'user'     => new xmlrpcval($boardTotals['users'], 'int'),
    ), 'struct');
    $addOnModel = $bridge->getAddOnModel();
    $tapatalk_addon = $addOnModel->getAddOnById('tapatalk');
    $config_version =  trim(str_replace('xf10_', '', $mobiquo_config['version']));
    $is_open = false;
    if(trim($tapatalk_addon['version_string']) == $config_version )
    {
        $is_open = true;
    }
    else
    {
        $result_text = "Tapatalk add-on file 'addon-Tapatalk.xml' was not imported. It may affect some app features in this forum. Please inform the forum admin to complete the installation.";
        $config_list['result_text'] = new xmlrpcval($result_text, 'base64');
    }
    if(!$tapatalk_addon['active'])
    {
        $result_text = "Tapatalk is disabled in this forum, please contact the forum administrator for more.";
        $config_list['result_text'] = new xmlrpcval($result_text, 'base64');
    }
    $config_list['is_open'] = new xmlrpcval(($is_board_active || XenForo_Visitor::getInstance()->get('is_admin')) && $is_open && $tapatalk_addon['active'], 'boolean');
    
    $ads_Dis_group = isset($options->ads_disabled_for_group) && !empty($options->ads_disabled_for_group) ? implode(',', $options->ads_disabled_for_group) : "";
    $config_list['ads_disabled_group'] = new xmlrpcval($ads_Dis_group, 'string');
    $config_list['guest_group_id'] = new xmlrpcval(XenForo_Model_User::$defaultGuestGroupId, 'string');
    $config_list['login_type'] = new xmlrpcval('both', 'string');
    $response = new xmlrpcval($config_list, 'struct');
    
    return new xmlrpcresp($response);
}

