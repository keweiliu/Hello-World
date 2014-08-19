<?php

defined('IN_MOBIQUO') or exit;

function update_push_status_func($xmlrpc_params)
{
    $bridge = Tapatalk_Bridge::getInstance();
    $params = php_xmlrpc_decode($xmlrpc_params);
    $data = $bridge->_input->filterExternal(array(
            'pushparam' => XenForo_Input::ARRAY_SIMPLE,
            'login' => XenForo_Input::STRING,
            'password' => XenForo_Input::STRING,
    ), $params);
    $visitor = XenForo_Visitor::getInstance();
    $loginModel = $bridge->getLoginModel();
    $userModel = $bridge->getUserModel();
    $user_id = $userModel->getUserByName($data['login']);
    $options = XenForo_Application::get('options');


    $allow_update = false;
    if(isset($data['login']) && !empty($data['login']))
    {
        $user = $userModel->getUserByName($data['login']);
        if($user['user_id'] == $visitor['user_id'])
        {
            $userId = $user['user_id'];
            $allow_update = true;
        }
        else if(!empty($data['password']))
        {
            $userId = $userModel->validateAuthentication($data['login'], $data['password'], $error);
            if(empty($userId)) return xmlresperror('Incorrect username and password!');
            $allow_update = !empty($userId);
        }
    }
    else if(!empty($visitor['user_id']))
    {
        $allow_update = true;
    }

    if($allow_update)
    {
        $push_data = array(
            'url'  => $options->boardUrl,
            'key'  => (!empty($options->tp_push_key) ? $options->tp_push_key : ''),
            'uid'  => $user_id['user_id'],
            'data' => base64_encode(serialize($data['pushparam'])),
        );
        
        $url = 'https://directory.tapatalk.com/au_update_push_setting.php';
        getContentFromRemoteServer($url, 0, $error_msg, 'POST', $push_data);
        return xmlresptrue();
    }
    else
        return xmlresperror('Incorrect username and password!');
}