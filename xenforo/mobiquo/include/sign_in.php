<?php

defined('IN_MOBIQUO') or exit;

function sign_in_func($xmlrpc_params)
{
    $params = php_xmlrpc_decode($xmlrpc_params);

    $bridge = Tapatalk_Bridge::getInstance();
    $conversationModel = $bridge->getConversationModel();
    $userModel = $bridge->getUserModel();

    $data = $bridge->_input->filterExternal(array(
        'token' => XenForo_Input::STRING,
        'code' => XenForo_Input::STRING,
        'email'     => XenForo_Input::STRING,
        'username'     => XenForo_Input::STRING,
        'password'     => XenForo_Input::STRING,
        'custom_register_fields' =>XenForo_Input::ARRAY_SIMPLE,
    ), $params);

    $sso = new TTSSOBase(new TTForum());
    $sso->signIn($data);
    if ($sso->result === FALSE){
        $errors = $sso->errors;
        $result = array(
            'result' => new xmlrpcval(false, 'boolean'),
            'result_text' => new xmlrpcval(isset($errors[0]) && !empty($errors[0]) ? $errors[0] : '', 'base64'),
        );
        return new xmlrpcresp(new xmlrpcval($result, 'struct'));
    }
    return $sso->result;
}
