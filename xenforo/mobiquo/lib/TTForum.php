<?php
defined('IN_MOBIQUO') or exit;

class TTForum implements TTSSOForumInterface{
    public function getUserByEmail($email)
    {
        $bridge = Tapatalk_Bridge::getInstance();
        $userModel = $bridge->getUserModel();
        return $userModel->getUserByEmail($email);
    }

    public function getUserByName($username)
    {
        $bridge = Tapatalk_Bridge::getInstance();
        $userModel = $bridge->getUserModel();
        return $userModel->getUserByName($username);
    }

    public function validateUsernameHandle($username)
    {
        $options = array(XenForo_DataWriter_User::OPTION_ADMIN_EDIT => true);

        $v_data = array(
                'name' => 'username',
                'value' => $username,
        );
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

        if ($error = $vwriter->getErrors()){
            return false;
        }
        return true;
    }

    public function validatePasswordHandle($password)
    {
        $options = array(XenForo_DataWriter_User::OPTION_ADMIN_EDIT => true);

        $v_data = array(
                'name' => 'password',
                'value' => $password,
        );
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

        if ($error = $vwriter->getErrors()){
            return false;
        }
        return true;
    }

    public function createUserHandle($email, $username, $password, $verified, $custom_register_fields, $profile, &$errors)
    {
        $bridge = Tapatalk_Bridge::getInstance();

        //Xenforo Validate fields
        $v_datas = array(
            'username' => array(
                'name' => 'username',
                'value' => $username,
        ),
            'email' => array(
                'name' => 'email',
                'value' => $email,
        ),
        );
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

            $error = $vwriter->getErrors();
            if (!empty($error)){
                if (is_array($error)){
                    foreach ($error as $key => $value){
                        if ($value instanceof XenForo_Phrase){
                            $errors[] = $value->render();
                        }else{
                            $errors[] = $error;
                        }
                    }
                }else{
                    if ($error instanceof XenForo_Phrase){
                        $errors[] = $error->render();
                    }else{
                        $errors[] = $error;
                    }
                }
            }
        }
        //apply profile
        $xf_options = XenForo_Application::get('options');
        if(isset($custom_register_fields['birthday']) && !empty($custom_register_fields['birthday'])){
            $birthday = preg_split('/-/', $custom_register_fields['birthday']);
            unset($custom_register_fields['birthday']);
        }

        $requireDob = $xf_options->get('registrationSetup', 'requireDob');
        $requireLocation =  $xf_options->get('registrationSetup', 'requireLocation');
        if(isset($custom_register_fields['location']) && !empty($custom_register_fields['location'])){
            $location = $custom_register_fields['location'];
            unset($custom_register_fields['location']);
        }else if (isset($profile['location']) && !empty($profile['location'])){
            $location = $custom_register_fields['location'];
        }

        //user state
        $xf_reg_option = $xf_options->registrationSetup;
        $email_confirmation = $xf_reg_option['emailConfirmation'];
        $moderation = $xf_reg_option['moderation'];
        if(empty($email_confirmation) && empty($moderation)){
            $user_state = 'valid';
        }else if(!empty($email_confirmation) && empty($moderation)){
            $user_state = $verified ? 'valid' : 'email_confirm';
        }else if(empty($email_confirmation) && !empty($moderation)){
            $user_state = ($verified && isset($xf_options->auto_approval_tp_user) && $xf_options->auto_approval_tp_user) ? 'valid' : 'moderated';
        }else{
            $user_state = $verified ? ((isset($xf_options->auto_approval_tp_user) && $xf_options->auto_approval_tp_user == true) ? 'valid' : 'moderated') : 'email_confirm';
        }

        $gender='';
        if(isset($profile['gender']) && !empty($profile['gender'])){
            if($profile['gender']=='male'||$profile['gender']=='female'){
                $gender=$profile['gender'];
            }
        }

        $userGroupModel = $bridge->getUserGroupModel();
        $userGroups = $userGroupModel->getAllUserGroups();
        $tapatalk_reg_ug = $xf_options->tapatalk_reg_ug;
        if (!array_key_exists($tapatalk_reg_ug, $userGroups)){
            $tapatalk_reg_ug = 0;
        }

        //indicate if it can use gravatar
        $gravatar = false;
        if(isset($profile['avatar_url'])&& !empty($profile['avatar_url'])){
            if(preg_match('/gravatar\.com\/avatar/', $profile['avatar_url'])){
                if ($xf_options->gravatarEnable){
                    $gravatar = true;
                }
            }
        }

        $data = array(
        'username' => $username,
        'email' => $email,
        'user_group_id' => XenForo_Model_User::$defaultRegisteredGroupId,
        'secondary_group_ids' => $tapatalk_reg_ug,
        'user_state' => $user_state,
        'is_discouraged' => '0',
        'gender' => $gender,
        'dob_day' => isset($birthday[2]) && $birthday[2]>=0 && $birthday[2]<=31 ? $birthday[2] : ($requireDob ? '01' : '0'),
        'dob_month' => isset($birthday[1]) && $birthday[2]>=0 && $birthday[2]<=12 ? $birthday[1] : ($requireDob ? '01' : '0'),
        'dob_year' => isset($birthday[0]) ? $birthday[0] : ($requireDob ? '1971' : '0'),
        'location' => isset($location) && !empty($location) ? $location : '',
        'occupation' => '',
        'custom_title' => '',
        'homepage' => isset($profile['link']) && !empty($profile['link']) ? $profile['link'] : '',
        'about' => isset($profile['description']) && !empty($profile['description']) ? $profile['description'] : '',
        'signature' => isset($profile['signature']) && !empty($profile['signature']) ? $profile['signature'] : '',
        'message_count' => '0',
        'like_count' => '0',
        'trophy_points' => '0',
        'style_id' => '0',
        'language_id' => '1',
        'timezone' => (!isset($xf_options->guestTimeZone) || empty($xf_options->guestTimeZone)) ? 'Europe/London' : $xf_options->guestTimeZone,
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
        'gravatar' => $gravatar ? $email : '',
        );

        $writer = XenForo_DataWriter::create('XenForo_DataWriter_User');
        $writer->setOption(XenForo_DataWriter_User::OPTION_ADMIN_EDIT, false);

        $writer->bulkSet($data);
        if ($password !== '')
        {
            $writer->setPassword($password);
        }

        $fieldModel=$bridge->_getFieldModel();
        $userFields=$fieldModel->getUserFields(array('registration' => true));
        $customFieldsShown=array_keys($userFields);
        foreach ($custom_register_fields as $key=>$value){
            if (isset($userFields[$key]) && !empty($userFields[$key])){
                switch ($userFields[$key]['field_type']){
                    case 'textbox':
                    case 'textarea':
                        break;
                    case 'select':
                    case 'radio':
                        if (is_array($custom_register_fields[$key])){
                            $custom_register_fields[$key]=reset(array_keys($custom_register_fields[$key]));
                        }
                        break;
                    case 'checkbox':
                        foreach ($custom_register_fields[$key] as $key2=>$value2){
                            $custom_register_fields[$key][$key2]=$key2;
                        }
                        break;
                    case 'multiselect':
                        foreach ($custom_register_fields[$key] as $key2=>$value2){
                            $custom_register_fields[$key][]=$key2;
                            unset($custom_register_fields[$key][$key2]);
                        }
                        break;
                }
            }
        }
        $writer->setCustomFields($custom_register_fields, $customFieldsShown);

        $writer->preSave();

        $error = $writer->getErrors();
        if($error)
        {
            if (is_array($error)){
                foreach ($error as $key => $value){
                    if ($value instanceof XenForo_Phrase){
                        $errors[] = $value->render();
                    }else{
                        $errors[] = $error;
                    }
                }
            }else {
                if ($error instanceof XenForo_Phrase){
                    $errors[] = $error->render();
                }else{
                    $errors[] = $error;
                }
            }

            return null;
        }

        $writer->save();

        $user = $writer->getMergedData();
        $userConfirmModel = $bridge->getUserConfirmationModel();
        XenForo_Model_Ip::log($user['user_id'], 'user', $user['user_id'], 'register');
        $result_text="";
        if($user_state == 'email_confirm' && $user['user_id'] != 0)
        {
            $userConfirmModel->sendEmailConfirmation($user);
            $errors[] = (string) new XenForo_Phrase('your_account_is_currently_awaiting_confirmation_confirmation_sent_to_x', array('email' => $data['email']));
        }
        else if($user_state == 'moderated' && $user['user_id'] != 0)
        {
            $errors[] = (string) new XenForo_Phrase('thanks_for_registering_registration_must_be_approved');
        }

        return $user;
    }

    public function loginUserHandle($userInfo, $register)
    {
        return login_user($userInfo['user_id'], $register);
    }

    public function getAPIKey(){
        return XenForo_Application::get('options')->tp_push_key;
    }

    public function getForumUrl(){
        return XenForo_Application::get('options')->boardUrl;
    }

    function getEmailByUserInfo($userInfo){
        return $userInfo['email'];
    }
}