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


    $options = XenForo_Application::get('options');

    if(!empty($data['token']))
        $email_response = getEmailFromScription($data['token'], $data['code'], $options->tp_push_key);
    if(!empty($data['token']) && empty($email_response))
        return new xmlrpcresp($bridge->responseError('Failed to connect to tapatalk server, please try again later.'));

    $response_verified = $email_response['result'] && isset($email_response['email']) && !empty($email_response['email']);
    if(!$response_verified)
        return new xmlrpcresp($bridge->responseError(isset($email_response['result_text'])? $email_response['result_text'] : 'Tapatalk ID session expired, please re-login Tapatalk ID and try again, if the problem persist please tell us.'));

    // Sign in logic
    if(!empty($data['email']))
    {
        if($email_response['email'] == $data['email'])
        {
            $user = $userModel->getUserByNameOrEmail($data['email']);
            if(isset($user['user_id']) && !empty($user['user_id']))
            {
                return login_user($user['user_id']);
            }
            else
            {
                if(empty($bridge->mobiquo_configuration['sso_signin'])) 
                    return new xmlrpcresp($bridge->responseError('Application Error : social sign in is not supported currently.'));
                if(!function_exists('register_user'))
                {
                    if(file_exists('include/register.php'))
                        include('include/register.php');
                    else
                        return new xmlrpcresp($bridge->responseError('File Missing Error : Missing include/register.php'));
                }
                if(!empty($data['username']))
                {
                    $user = $userModel->getUserByNameOrEmail($data['username']);
                    $username_exist = isset($user['user_id']) && !empty($user['user_id']);
                    
                    //gavatar?
                    $set_gravatar = false;
                    if(isset($email_response['avatar_url'])&& !empty($email_response['avatar_url']))
                        if(preg_match('/gravatar\.com\/avatar/', $email_response['avatar_url']))
                            if ($options->gravatarEnable)
                                $set_gravatar = true;

                    $reg_response = register_user($data, false, $set_gravatar, $email_response['profile']);

                    if(is_array($reg_response))
                    {
                        list($user, $result_text) = $reg_response;
        
                        if($user['user_id'] != 0) 
                        {
                            // register succeed, try to add custom avatar
                            if(isset($email_response['profile']['avatar_url'])&& !empty($email_response['profile']['avatar_url']) && !$set_gravatar)
                            {
                                try
                                {
                                    $avatarModel = $bridge->getModelFromCache('XenForo_Model_Avatar');
                                    $avatarData = $avatarModel->applyAvatar($user['user_id'], $email_response['profile']['avatar_url']);
                                }
                                catch(Exception $e){}
                            }
                            // set status message
                            if(isset($email_response['profile']['status_message'])&& !empty($email_response['profile']['status_message']))
                            {
                                try
                                {
                                    $date = XenForo_Application::$time;
                                    $writer = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_ProfilePost');
                                    $writer->set('user_id', $user['user_id']);
                                    $writer->set('username', $user['username']);
                                    $writer->set('message', $email_response['profile']['status_message']);
                                    $writer->set('profile_user_id', $user['user_id']);
                                    $writer->set('post_date', $date);
                                    $writer->set('message_state', 'visible');
                                    if (!$writer->hasErrors())
                                        $writer->save();
                                }
                                catch(Exception $e){}
                            }
                            return login_user($user['user_id'], true); // login if registered
                        }
                        else
                        {
                            return error_status($username_exist ? '1' : '0', $result_text);
                        }
                    }
                    else
                    {
                        $result_text = (string) $reg_response;
                        return error_status($username_exist ? '1' : '0', $result_text);
                    }
                }
                else
                {
                    return error_status(2);
                }
            }
        }
        else
        {
            return error_status(3);
        }
    }
    else if(!empty($data['username']))
    {
        $user = $userModel->getUserByNameOrEmail($data['username']);

        if(isset($user['user_id']) && !empty($user['user_id']) && $user['email'] == $email_response['email'])
        {
            return login_user($user['user_id']);
        }
        else
        {
            return error_status(3);
        }
    }
    else
    {
        return new xmlrpcresp($bridge->responseError('Application Error : either email or username should provided.'));
    }

}

function error_status($status = 0, $result_text = '')
{
    $result = new xmlrpcval(array(
        'result'        => new xmlrpcval(false, 'boolean'),
        'status'        => new xmlrpcval($status, 'string'),
        'result_text'   => new xmlrpcval($result_text, 'base64'),
    ), 'struct');

    return new xmlrpcresp($result);
}
