<?php

defined('IN_MOBIQUO') or exit;

require_once "include/register.php";

function forget_password_func($xmlrpc_params)
{
    $bridge = Tapatalk_Bridge::getInstance();
    $userModel = $bridge->getUserModel();
    $userConfirmModel = $bridge->getUserConfirmationModel();
    $params = php_xmlrpc_decode($xmlrpc_params);

    $data = $bridge->_input->filterExternal(array(
            'username' => XenForo_Input::STRING,
            'token' => XenForo_Input::STRING,
            'code' => XenForo_Input::STRING,
    ), $params);
    $options = XenForo_Application::get('options');

    $user = $userModel->getUserByName($data['username']);
    $verified = false;
    $result_text = new XenForo_Phrase('invalid_username');
    if(isset($data['token']) && !empty($data['token']))
    {
        if(isset($options->tp_push_key) &&!empty($options->tp_push_key))
        {
            $email_response = getEmailFromScription($data['token'], $data['code'], $options->tp_push_key);
            if(empty($email_response))
                return $bridge->responseError('Failed to connect to tapatalk server, please try again later.');
        }
    }
    
    $data['email'] = (!isset($email_response['email']) || empty($email_response['email'])) ? '': $email_response['email'];

    if(!empty($user))
    {
        $result_text = 'Validate successfully!';
        if(isset($user['email']) && $user['email'] == $data['email'])
        {
            $verified = true;
            if(isset($user['is_admin']) && $user['is_admin'])
            {
                $result = false;
                $result_text = 'Sorry, you are administrator of this forum,please try to get password via browser!';
            }

        }
        else
        {
            $res = $userConfirmModel->sendPasswordResetRequest($user);
            $result_text = new XenForo_Phrase('your_password_has_been_reset');
            if(!$res)
                $result_text = isset($email_response['result_text']) ? $email_response['result_text'] :'Failed to send confirmation email.';
        }
    }
    else
        $res = false;
    return new xmlrpcresp(new xmlrpcval(array(
            'result'            => new xmlrpcval(isset($res) ? $res : true, 'boolean'),
            'result_text'       => new xmlrpcval($result_text, 'base64'),
            'verified'          => new xmlrpcval($verified, 'boolean'),
        ), 'struct'));
}