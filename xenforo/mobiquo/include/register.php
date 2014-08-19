<?php

defined('IN_MOBIQUO') or exit;

function register_func($xmlrpc_params)
{

    $bridge = Tapatalk_Bridge::getInstance();
    $userModel = $bridge->getUserModel();
    $params = php_xmlrpc_decode($xmlrpc_params);

    $options = XenForo_Application::get('options');
    $data = $bridge->_input->filterExternal(array(
        'username'  => XenForo_Input::STRING,
        'password'  => XenForo_Input::STRING,
        'email'     => XenForo_Input::STRING,
        'token'     => XenForo_Input::STRING,
        'code'      => XenForo_Input::STRING,
        'custom_register_fields' =>XenForo_Input::ARRAY_SIMPLE,
    ), $params);
    
    if(empty($bridge->mobiquo_configuration['native_register'])) 
        return new xmlrpcresp($bridge->responseError('Application Error : social sign in is not supported currently.'));
    
    if(isset($options->tp_push_key)&& !empty($options->tp_push_key) && !empty($data['token']))
    {
        if(empty($bridge->mobiquo_configuration['sso_register'])) 
            return new xmlrpcresp($bridge->responseError('Application Error : social sign in is not supported currently.'));
        
        $email_response = getEmailFromScription($data['token'], $data['code'], $options->tp_push_key);
        if(!empty($data['token']) && empty($email_response))
            return new xmlrpcresp($bridge->responseError('Failed to connect to tapatalk server, please try again later.'));
        
        if((!isset($data['email']) || empty($data['email'])) && (!isset($email_response['email']) || empty($email_response['email'])))
            return new xmlrpcresp($bridge->responseError('You need to input an email or use Tapatalk verified email to register.'));
    }
    
    $need_email_verification = isset($email_response['result']) && $email_response['result'] && isset($email_response['email']) && !empty($email_response['email']) && ($email_response['email'] == $data['email']) ? false : true;
    $reg_response = register_user($data, $need_email_verification);
    
    if(is_array($reg_response))
    {
        list($user, $result_text) = $reg_response;
        $result = new xmlrpcval(array(
            'result'        => new xmlrpcval( $user['user_id'] != 0 , 'boolean'),
            'result_text'   => new xmlrpcval($result_text, 'base64'),
        ), 'struct');
    }
    else
    {
        $result_text = (string) $reg_response;
        $result = new xmlrpcval(array(
            'result'        => new xmlrpcval(false, 'boolean'),
            'result_text'   => new xmlrpcval($result_text, 'base64'),
        ), 'struct');

    }
    return new xmlrpcresp($result);
}

function register_user($data, $need_email_verification = false, $gravatar = false, $profile = array())
{
    $bridge = Tapatalk_Bridge::getInstance();

    //Xenforo Validate fields
    $v_datas = array();
    foreach($data as $key => $value)
    {
        if($key == 'token'|| $key == 'code' || $key == 'password' || $key=='custom_register_fields')
            continue;
        $v_datas[$key] = array(
            'name' => $key,
            'value' => $value,
        );
    }
    $options = array(XenForo_DataWriter_User::OPTION_ADMIN_EDIT => true);
    foreach($v_datas as $field_name => $v_data)
    {
        $v_data = array_merge(array('existingDataKey' => 0), $v_data);
        $vwriter = XenForo_DataWriter::create('XenForo_DataWriter_User');
        if (!empty($v_data['existingDataKey']) || $v_data['existingDataKey'] === '0')
        {
            $vwriter->setExistingData($v_data['existingDataKey']);
        }

        foreach ($options AS $key => $value)
        {
            $vwriter->setOption($key, $value);
        }
        $vwriter->set($v_data['name'], $v_data['value']);

        if ($errors = $vwriter->getErrors())
        {
           return ($errors[$field_name]);
        }
    }
    //apply profile
    if(isset($profile['birthday']) && !empty($profile['birthday']))
        $birthday = preg_split('/-/', $profile['birthday']);

    //user state
    $xf_options = XenForo_Application::get('options');
    $xf_reg_option = $xf_options->registrationSetup;
    if(empty($xf_reg_option['emailConfirmation']) && empty($xf_reg_option['moderation']))
        $user_state = 'valid';
    else if(!empty($xf_reg_option['emailConfirmation']) && empty($xf_reg_option['moderation']))
        $user_state = $need_email_verification ? 'email_confirm' : 'valid';
    else if(empty($xf_reg_option['emailConfirmation']) && !empty($xf_reg_option['moderation']))
        $user_state = 'moderated';
    else
        $user_state = $need_email_verification ? 'email_confirm' : 'moderated';

    $gender='';
    if(isset($profile['gender']) && !empty($profile['gender'])){
        if($profile['gender']=='male'||$profile['gender']=='female'){
            $gender=$profile['gender'];
        }
    }
    
    $extra_data = array(
        'user_group_id' => isset($xf_options->tapatalk_reg_ug) && intval($xf_options->tapatalk_reg_ug) ? intval($xf_options->tapatalk_reg_ug) : XenForo_Model_User::$defaultRegisteredGroupId,
        'user_state' => $user_state,
        'is_discouraged' => '0',
        'gender' => $gender,
        'dob_day' => isset($birthday[2]) && $birthday[2]>=1 && $birthday[2]<=31 ? $birthday[2] : '0',
        'dob_month' => isset($birthday[1]) && $birthday[2]>=1 && $birthday[2]<=12 ? $birthday[1] : '0',
        'dob_year' => isset($birthday[0]) ? $birthday[0] : '0',
        'location' => isset($profile['location']) && !empty($profile['location']) ? $profile['location'] : '',
        'occupation' => '',
        'custom_title' => '',
        'homepage' => isset($profile['link']) && !empty($profile['link']) ? $profile['link'] : '',
        'about' => isset($profile['description']) && !empty($profile['description']) ? $profile['description'] : '',
        'signature' => isset($profile['signature']) && !empty($profile['signature']) ? $profile['signature'] : '',
        'message_count' => '0',
        'like_count' => '0',
        'trophy_points' => '0',
        'style_id' => '0',
        'language_id' => XenForo_Visitor::getInstance()->get('language_id'),
        'timezone' => empty(XenForo_Application::get('options')->guestTimeZone)? 'Europe/London' : XenForo_Application::get('options')->guestTimeZone,
        'content_show_signature' => '1',
        'enable_rte' => '1',
        'visible' => '1',
        'receive_admin_email' => '1',
        'show_dob_date' => '1',
        'show_dob_year' => '1',
        'allow_view_profile' => 'everyone',
        'allow_post_profile' => 'members',
        'allow_send_personal_conversation' => 'members',
        'allow_view_identities' => 'everyone',
        'allow_receive_news_feed' => 'everyone',
        'gravatar' => $gravatar ? $data['email'] : '',
    );
    $data = array_merge($extra_data,$data);

    $writer = XenForo_DataWriter::create('XenForo_DataWriter_User');
    $writer->setOption(XenForo_DataWriter_User::OPTION_ADMIN_EDIT, false);
    $password = $data['password'];
    $customFields=$data['custom_register_fields'];
    unset($data['password']);
    unset($data['token']);
    unset($data['code']);
    unset($data['custom_register_fields']);
    $writer->bulkSet($data);
    if ($password !== '')
    {
        $writer->setPassword($password);
    }
    
    $fieldModel=$bridge->_getFieldModel();
    $userFields=$fieldModel->getUserFields(array('registration' => true));
    $customFieldsShown=array_keys($userFields);
    foreach ($customFields as $key=>$value)
    {
        if (!empty($userFields[$key]))
        {
            switch ($userFields[$key]['field_type'])
            {
                case 'textbox':
                case 'textarea':
                    break;
                case 'select':
                case 'radio':
                    if (is_array($customFields[$key])){
                        $customFields[$key]=reset(array_keys($customFields[$key]));
                    }
                    break;
                case 'checkbox':
                    foreach ($customFields[$key] as $key2=>$value2){
                        $customFields[$key][$key2]=$key2;
                    }
                    break;
                case 'multiselect':
                    foreach ($customFields[$key] as $key2=>$value2){
                        $customFields[$key][]=$key2;
                        unset($customFields[$key][$key2]);
                    }
                    break;
            }
        }
    }
    $writer->setCustomFields($customFields, $customFieldsShown);
    
    $writer->preSave();

    $errors = $writer->getErrors();
    if($errors)
    {
        if (is_array($errors)){
            $error=reset($errors);
        }else {
            $error=$errors;
        }
        if ($error instanceof XenForo_Phrase){
            $error_message=$error->render();
        }else{
            $error_message = (string) $error[0];
        }
        if(empty($error_message))
            $error_message = 'Register failed for unknown reason!';
        return $bridge->responseError($error_message)->errorText;
    }

    $writer->save();
    
    $user = $writer->getMergedData();
    $userConfirmModel = $bridge->getUserConfirmationModel();
    XenForo_Model_Ip::log($user['user_id'], 'user', $user['user_id'], 'register');
    $result_text="";
    if($user_state == 'email_confirm' && $user['user_id'] != 0)
    {
        $userConfirmModel->sendEmailConfirmation($user);
        $result_text = (string) new XenForo_Phrase('your_account_is_currently_awaiting_confirmation_confirmation_sent_to_x', array('email' => $data['email']));
    }
    else if($user_state == 'moderated' && $user['user_id'] != 0)
    {
        $result_text = (string) new XenForo_Phrase('thanks_for_registering_registration_must_be_approved');
    }
    else if($user['user_id'] == 0)
    {
        $result_text = isset($email_response['result_text']) && !empty($email_response['result_text']) ? $email_response['result_text'] : '';
    }

    return array($user, $result_text);
}
