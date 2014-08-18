<?php

defined('IN_MOBIQUO') or exit;

function get_contact_func($xmlrpc_params)
{
    $params = php_xmlrpc_decode($xmlrpc_params);
    $mobi_api_key = loadAPIKey();

    $bridge = Tapatalk_Bridge::getInstance();

    $data = $bridge->_input->filterExternal(array(
        'user_id' => XenForo_Input::UINT,
    ), $params);
    
    if ($data['user_id'] && $mobi_api_key)
    {
        $userModel = $bridge->getUserModel();
        $user = $bridge->getHelper('UserProfile')->assertUserProfileValidAndViewable($data['user_id']);
        if ($user['receive_admin_email'])
        {
            $suggested_users = new xmlrpcval(array(
                'result'        => new xmlrpcval(true, 'boolean'),
                'user_id'       => new xmlrpcval($user['user_id'], 'string'),
                'display_name'  => new xmlrpcval($user['username'], 'base64'),
                'enc_email'     => new xmlrpcval(base64_encode(encrypt(trim($user['email']), $mobi_api_key)), 'string'),
            ), 'struct');
            
            return new xmlrpcresp($suggested_users);
        }
    }
    
    $suggested_users = new xmlrpcval(array(
        'result' => new xmlrpcval(false, 'boolean'),
    ), 'struct');

    return new xmlrpcresp($suggested_users);
}

