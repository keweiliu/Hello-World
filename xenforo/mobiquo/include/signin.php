<?php

defined('IN_MOBIQUO') or exit;

function signin_func($xmlrpc_params)
{
    $params = php_xmlrpc_decode($xmlrpc_params);

    $bridge = Tapatalk_Bridge::getInstance();
    $conversationModel = $bridge->getConversationModel();
    $userModel = $bridge->getUserModel();
    $data = $bridge->_input->filterExternal(array(
        'email'     => XenForo_Input::STRING,
        'token' => XenForo_Input::STRING,
        'code' => XenForo_Input::STRING,
    ), $params);

    $options = XenForo_Application::get('options');

    // Verification
    
    if((!isset($options->tp_push_key) || empty($options->tp_push_key)) && !empty($data['token']))
        return $bridge->responseError('Forum is not configured well, please contact administrator to set up push key for the forum!');
    if(!empty($data['token']))
        $email_response = getEmailFromScription($data['token'], $data['code'], $options->tp_push_key);
    if(!empty($data['token']) && empty($email_response))
        return $bridge->responseError('Failed to connect to tapatalk server, please try again later.');
    if( (!isset($data['email']) || empty($data['email'])) && (!isset($email_response['email']) || empty($email_response['email'])))
        return $bridge->responseError('You need to input an email or use Tapatalk verified email to register.');

    $verified = $email_response['result'] && isset($email_response['email']) && !empty($email_response['email']) && ($email_response['email'] == $data['email']);

    if(!$verified)
       return $bridge->responseError('Tapatalk ID verification failure.');
    

    $user = $userModel->getUserByNameOrEmail($data['email']);
    if(!isset($user['user_id']) || empty($user['user_id']))
        return $bridge->responseError('The user does not exist.');
    $userId = $user['user_id'];
    //are you in my allow user groups?
    if(!empty($options->tp_allowusergroup))
    {
        $currentUser = $userModel->getUserById($userId);
        $allowed_group = !empty($options->tp_allowusergroup) ? explode(",", $options->tp_allowusergroup) : array();
        if(!$userModel->isMemberOfUserGroup($currentUser, $allowed_group))
            return xmlresperror("Sorry, you are not allowed to access this forum via Tapatalk, please contact the forum administrator.");
    }

    XenForo_Model_Ip::log($userId, 'user', $userId, 'login');
    $tapatalk_user_writer = XenForo_DataWriter::create('Tapatalk_DataWriter_TapatalkUser');
    $tapatalk_user_model = $tapatalk_user_writer->getTapatalkUserModel();
    $existing_record = $tapatalk_user_model->getTapatalkUserById($userId);
    if(empty($existing_record))
    {
        $tapatalk_user_writer->set('userid',$userId);
        $tapatalk_user_writer->preSave();
        $tapatalk_user_writer->save();
    }
    else
    {
        $tapatalk_user_writer->setExistingData($existing_record);
        $tapatalk_user_writer->set('updated',gmdate('Y-m-d h:i:s',time()));
        $tapatalk_user_writer->save();
    }


    $userModel->deleteSessionActivity(0, $bridge->_request->getClientIp(false));

    
    $session = XenForo_Application::get('session');
    $session->changeUserId($userId);
    XenForo_Visitor::setup($userId);

    $visitor = XenForo_Visitor::getInstance();

    $groups = array(
        new xmlrpcval($visitor['user_group_id'], "string")
    );

    if ($visitor['secondary_group_ids'])
    {
        $secondary_groups = explode(",", $visitor['secondary_group_ids']);
        foreach($secondary_groups as $secondary_group_id){
            $groups[] = new xmlrpcval($secondary_group_id, "string");
        }
    }

    // check ban
    $result_text = '';
    $bannedUser = $bridge->getModelFromCache('XenForo_Model_Banning')->getBannedUserById($userId);

    if ($bannedUser)
    {
        if ($bannedUser['user_reason'])
        {
            $result_text = new XenForo_Phrase('you_have_been_banned_for_following_reason_x', array('reason' => $bannedUser['user_reason']));
        }
        else
        {
            $result_text = new XenForo_Phrase('you_have_been_banned');
        }

        if ($bannedUser['end_date'] > XenForo_Application::$time)
        {
            $result_text .= ' ' . new XenForo_Phrase('your_ban_will_be_lifted_on_x', array('date' => XenForo_Locale::dateTime($bannedUser['end_date'])));
        }
    }
    $push_status = array();
    $options = XenForo_Application::get('options');
    if($options->enable_push)
    {
        $tapatalk_user_model = $bridge->getTapatalkUserModel();
        $tapa_user_record = $tapatalk_user_model->getTapatalkUserById($userId);
        if($tapa_user_record)
        {
            foreach ($tapa_user_record as $name => $value)
            {
                $display_name = $tapatalk_user_model->getStarndardNameByTableKey($name);
                if($display_name)
                {
                    $push_status[] = new xmlrpcval(array(
                        'name'  => new xmlrpcval($display_name, 'string'),
                        'value' => new xmlrpcval((boolean)$value, 'boolean')
                    ), 'struct');
                }
            }
        }
    }
    $result = new xmlrpcval(array(
        'result'            => new xmlrpcval(true, 'boolean'),
        'result_text'       => new xmlrpcval($result_text, 'base64'),
        'user_id'           => new xmlrpcval($userId, 'string'),
        'username'          => new xmlrpcval($visitor['username'], 'base64'),
        'email'             => new xmlrpcval($visitor['email'], 'base64'),
        'user_type'         => new xmlrpcval(get_usertype_by_item('', $visitor['display_style_group_id'], $visitor['is_banned']), 'base64'),
        'icon_url'          => new xmlrpcval(get_avatar($visitor->toArray(), "l"), 'string'),
        'post_count'        => new xmlrpcval(intval($visitor['message_count']), "int"),
        'usergroup_id'      => new xmlrpcval($groups, "array"),
        'can_pm'            => new xmlrpcval(true, "boolean"),
        'can_send_pm'       => new xmlrpcval($conversationModel->canStartConversations($errorPhraseKey), "boolean"),
        'can_moderate'      => new xmlrpcval($visitor['is_moderator'], "boolean"),
        'can_search'        => new xmlrpcval($visitor->canSearch(), "boolean"),
        'can_whosonline'    => new xmlrpcval(true, "boolean"),
        'can_profile'       => new xmlrpcval(true, "boolean"),
        'can_upload_avatar' => new xmlrpcval($visitor->canUploadAvatar(), "boolean"),
        'can_report_pm'     => new xmlrpcval(false, 'boolean'),
        'push_type'         => new xmlrpcval($push_status, 'array'),
        'max_attachment'    => new xmlrpcval($options->attachmentMaxPerMessage ? $options->attachmentMaxPerMessage : 10, "int"),
        'max_png_size'      => new xmlrpcval($options->attachmentMaxFileSize * 1000, "int"),
        'max_jpg_size'      => new xmlrpcval($options->attachmentMaxFileSize * 1000, "int"),
    ), 'struct');

    return new xmlrpcresp($result);

}