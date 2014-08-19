<?php

defined('IN_MOBIQUO') or exit;

function login_func($xmlrpc_params)
{
    $bridge = Tapatalk_Bridge::getInstance();
    $loginModel = $bridge->getLoginModel();
    $userModel = $bridge->getUserModel();
    $conversationModel = $bridge->getConversationModel();
    $params = php_xmlrpc_decode($xmlrpc_params);
    $options = XenForo_Application::get('options');

    $data = $bridge->_input->filterExternal(array(
            'login' => XenForo_Input::STRING,
            'password' => XenForo_Input::STRING,
            'anonymous' => XenForo_Input::UINT,
            'push' => XenForo_Input::STRING,
    ), $params);

    $userId = $userModel->validateAuthentication($data['login'], $data['password'], $error);

    if (!$userId)
    {
        $error_phrasename = $error->getPhraseName();
        $loginModel->logLoginAttempt($data['login']);
        if($error_phrasename == 'requested_user_x_not_found')
        {
            $result = new xmlrpcval(array(
                'result'        => new xmlrpcval(false, 'boolean'),
                'status'         => new xmlrpcval('2', 'string'),
                'result_text'    => new xmlrpcval($error->__toString(), 'base64'),
            ), 'struct');
            return new xmlrpcresp($result);
        }

        return xmlresperror($error);
    }

    $loginModel->clearLoginAttempts($data['login']);

    return login_user($userId, NULL);
}
