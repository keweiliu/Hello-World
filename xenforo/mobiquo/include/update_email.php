<?php


defined('IN_MOBIQUO') or exit;

require_once "include/register.php";

function update_email_func($xmlrpc_params)
{
    $bridge = Tapatalk_Bridge::getInstance();
    $userModel = $bridge->getUserModel();
    $params = php_xmlrpc_decode($xmlrpc_params);
    $visitor = XenForo_Visitor::getInstance();
    $data = $bridge->_input->filterExternal(array(
            'password' => XenForo_Input::STRING,
            'email' => XenForo_Input::STRING,
    ), $params);

    $options = XenForo_Application::get('options');

    if(isset($visitor['is_admin']) && $visitor['is_admin'])
        return xmlresperror('Sorry, you are administrator of this forum,please try to get password via browser!');

    if(empty($data['password']) || empty($data['email']))
        return xmlresperror('Password/Email could not be empty!');

    if(!isset($visitor['username']) || empty($visitor['username']))
        return $bridge->responseError('You are not logged in.');


    $visitor = XenForo_Visitor::getInstance();

    $auth = $userModel->getUserAuthenticationObjectByUserId($visitor['user_id']);
    if (!$auth)
    {
        return $bridge->responseError('You have no permissions to perform this action.');
    }

    if (!$auth->hasPassword())
    {
        unset($data['email']);
    }

    if (isset($data['email']) && $data['email'] !== $visitor['email'])
    {
        $auth = $userModel->getUserAuthenticationObjectByUserId($visitor['user_id']);
        if (!$auth->authenticate($visitor['user_id'], $data['password']))
        {
            return $bridge->responseError(new XenForo_Phrase('your_existing_password_is_not_correct'));
        }
    }
    //modify data
    $data['receive_admin_email'] = $visitor['receive_admin_email'];
    $data['email_on_conversation'] = $visitor['email_on_conversation'];
    $data['allow_send_personal_conversation'] = $visitor['allow_send_personal_conversation'];
    unset($data['password']);

    $writer = XenForo_DataWriter::create('XenForo_DataWriter_User');
    $writer->setExistingData(XenForo_Visitor::getUserId());
    $writer->bulkSet($data);

    if ($writer->isChanged('email')
        && XenForo_Application::get('options')->get('registrationSetup', 'emailConfirmation')
        && !$writer->get('is_moderator')
        && !$writer->get('is_admin')
    )
    {
        switch ($writer->get('user_state'))
        {
            case 'moderated':
            case 'email_confirm':
                $writer->set('user_state', 'email_confirm');
                break;

            default:
                $writer->set('user_state', 'email_confirm_edit');
        }
    }

    $writer->preSave();

    if ($dwErrors = $writer->getErrors())
    {
        $error_message = '';
        foreach($dwErrors as $error)
        {
            $error_message .= (string) $error;
        }
        if(empty($error_message))
            $error_message = 'Register failed for unkown reason!';
        return $bridge->responseError($error_message);
    }

    $writer->save();

    $user = $writer->getMergedData();
    if ($writer->isChanged('email')
        && ($user['user_state'] == 'email_confirm_edit' || $user['user_state'] == 'email_confirm')
    )
    {
        $userConfModel = $bridge->getUserConfirmationModel();
        $userConfModel->sendEmailConfirmation($user);
        return new xmlrpcresp(new xmlrpcval(array(
                'result'            => new xmlrpcval(1, 'boolean'),
                'result_text'       => new xmlrpcval(new XenForo_Phrase('your_account_must_be_reconfirmed'), 'base64'),
            ), 'struct'));
    }
    else
    {
        return new xmlrpcresp(new xmlrpcval(array(
                'result'            => new xmlrpcval(1, 'boolean'),
                'result_text'       => new xmlrpcval('', 'base64'),
            ), 'struct'));
    }


}