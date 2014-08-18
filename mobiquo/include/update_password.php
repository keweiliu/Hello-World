<?php


defined('IN_MOBIQUO') or exit;

require_once "include/register.php";

function update_password_func($xmlrpc_params)
{
    $bridge = Tapatalk_Bridge::getInstance();
    $userModel = $bridge->getUserModel();
    $params = php_xmlrpc_decode($xmlrpc_params);
    $visitor = XenForo_Visitor::getInstance();
    $data = $bridge->_input->filterExternal(array(
            'password' => XenForo_Input::STRING,
            'token' => XenForo_Input::STRING,
            'code' => XenForo_Input::STRING,
    ), $params);

    $options = XenForo_Application::get('options');
    if(!isset($options->tp_push_key) || empty($options->tp_push_key))
        $bridge->responseError('Sorry, this community has not yet full configured to work with Tapatalk, this feature has been disabled.');

    if(isset($visitor['is_admin']) && $visitor['is_admin'])
        return xmlresperror('Sorry, for security reason you are not allowed to reset password using Tapatalk.');

    if(isset($data['code']) && !empty($data['code']))
    {
        //token and code validation
        $email_response = getEmailFromScription($data['token'], $data['code'], $options->tp_push_key);
        if(empty($email_response))
            return $bridge->responseError('Failed to connect to tapatalk server, please try again later.');
        
        $data['email'] = (!isset($email_response['email']) || empty($email_response['email'])) ? '': $email_response['email'];
        if(empty($data['email']))
            return $bridge->responseError($email_response['result_text']);
        $userFromEmail = $userModel->getUserByEmail($data['email']);
        if(empty($userFromEmail))
            return $bridge->responseError('Sorry, no such email user found.');
        $new_password = $data['password'];
    }
    else
    {
        $old_password = $data['password'];
        $new_password = $data['token'];
        if(!isset($visitor['username']) || empty($visitor['username']))
            return $bridge->responseError('You are not logged in.');
        $userId = $userModel->validateAuthentication($visitor['username'], $old_password, $error);
        if (!$userId)
        {
            return xmlresperror($error);
        }
    }

    $writer = XenForo_DataWriter::create('XenForo_DataWriter_User');
    if (isset($visitor['user_id']) && $visitor['user_id'])
    {
        $writer->setExistingData($visitor['user_id']);
    }else if(isset($userFromEmail['user_id']) && $userFromEmail['user_id'])
    {
        $writer->setExistingData($userFromEmail['user_id']);
    }
    $writer->setOption(XenForo_DataWriter_User::OPTION_ADMIN_EDIT, true);
    if ($new_password !== '')
    {
        $writer->setPassword($new_password);
    }
    else
    {
        return $bridge->responseError('password cannot be empty');
    }
    $writer->save();
    if ($errors = $writer->getErrors())
    {
        return $bridge->responseError('Update failed.');
    }

    return new xmlrpcresp(new xmlrpcval(array(
            'result'            => new xmlrpcval(1, 'boolean'),
            'result_text'       => new xmlrpcval('', 'base64'),
        ), 'struct'));
}