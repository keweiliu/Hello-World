<?php

defined('IN_MOBIQUO') or exit;



function addCustomField($name, $value, &$list){
    $list[] = new xmlrpcval(array(
        'name'  => new xmlrpcval($name, 'base64'),
        'value' => new xmlrpcval($value, 'base64')
    ), 'struct');
}

function get_user_info_func($xmlrpc_params)
{
    $params = php_xmlrpc_decode($xmlrpc_params);

    $bridge = Tapatalk_Bridge::getInstance();
    $visitor = XenForo_Visitor::getInstance();
    $userModel = $bridge->getUserModel();
    $userProfileModel = $bridge->getUserProfileModel();
    $sessionModel = $bridge->getSessionModel();
    $warningModel = $bridge->getWarningModel();

    $custom_fields_list = array();

    $data = $bridge->_input->filterExternal(array(
        'username' => XenForo_Input::STRING,
        'user_id'  => XenForo_Input::UINT,
    ), $params);

    if ($data['user_id']) {
        $user_id = $data['user_id'];
    } elseif ($data['username']) {
        $user = $userModel->getUserByName($data['username']);
        $user_id = $user['user_id'];
    } else {
        $user_id = $visitor['user_id'];
    }

    $userFetchOptions = array(
        'join' => XenForo_Model_User::FETCH_LAST_ACTIVITY
    );
    $user = $bridge->getHelper('UserProfile')->assertUserProfileValidAndViewable($user_id, $userFetchOptions);

    if (!$userProfileModel->canViewFullUserProfile($user, $errorPhraseKey))
    {
        throw $bridge->getErrorOrNoPermissionResponseException($errorPhraseKey);
    }

    if ($user['following'])
    {
        $followingCount = substr_count($user['following'], ',') + 1;
    }
    else
    {
        $followingCount = 0;
    }
    $followersCount = $userModel->countUsersFollowingUserId($user['user_id']);

    $birthday = $userProfileModel->getUserBirthdayDetails($user);
    $age = $userProfileModel->getUserAge($user);

    if(isset($user['custom_title']) && !empty($user['custom_title']))
        addCustomField(new XenForo_Phrase('title'), $user['custom_title'], $custom_fields_list);
    if(isset($user['location']) && !empty($user['location']))
        addCustomField(new XenForo_Phrase('location'), $user['location'], $custom_fields_list);
    if(isset($user['occupation']) && !empty($user['occupation']))
        addCustomField(new XenForo_Phrase('occupation'), $user['occupation'], $custom_fields_list);
    if(isset($user['homepage']) && !empty($user['homepage']))
        addCustomField(new XenForo_Phrase('home_page'), $user['homepage'], $custom_fields_list);
    if(isset($user['gender']) && !empty($user['gender']))
        addCustomField(new XenForo_Phrase('gender'), new XenForo_Phrase($user['gender']), $custom_fields_list);

    if(!empty($birthday))
        addCustomField(new XenForo_Phrase('birthday'), XenForo_Template_Helper_Core::date($birthday['timeStamp'], $birthday['format']).
        (isset($birthday['age']) && !empty($birthday['age']) ? (" (".new XenForo_Phrase('age').": ".$birthday['age'].")") : ""), $custom_fields_list);
    else if(!empty($age))
        addCustomField(new XenForo_Phrase('age'), $age, $custom_fields_list);

    if (version_compare(XenForo_Application::$version, '1.0.4', '>'))
    {
        $fieldModel = $bridge->_getFieldModel();
        $customFields = $fieldModel->prepareUserFields($fieldModel->getUserFields(
            array('profileView' => true),
            array('valueUserId' => $user['user_id'])
        ));
        foreach ($customFields AS $key => $field)
        {
            if (!$field['viewableProfile'] || !$field['hasValue'])
            {
                unset($customFields[$key]);
            }
        }

        $customFieldsGrouped = $fieldModel->groupUserFields($customFields);
        if (!$userProfileModel->canViewIdentities($user))
        {
            $customFieldsGrouped['contact'] = array();
        }

        if (isset($customFieldsGrouped['contact']) && is_array($customFieldsGrouped['contact']))
            foreach($customFieldsGrouped['contact'] as $identity)
                addCustomField($identity['title'], $identity['field_value'], $custom_fields_list);
    }
    else
    {
        if ($userProfileModel->canViewIdentities($user))
        {
            $identities = $userModel->getPrintableIdentityList($user['identities']);
            foreach($identities as $identity)
                addCustomField($identity['title'], $identity['value'], $custom_fields_list);
        }
    }

    addCustomField(new XenForo_Phrase('followers'), $followersCount, $custom_fields_list);
    addCustomField(new XenForo_Phrase('following'), $followingCount, $custom_fields_list);
    addCustomField(new XenForo_Phrase('likes_received'), $user['like_count'], $custom_fields_list);
    addCustomField(new XenForo_Phrase('trophy_points'), $user['trophy_points'], $custom_fields_list);
    if ($userModel->canViewWarnings())
    {
        addCustomField(new XenForo_Phrase('warning_points'), $user['warning_points'], $custom_fields_list);
    }


    $sessionActivity = $sessionModel->getSessionActivityRecords(array(
        'user_id' => $user['user_id']
    ));
    $sessionActivity = $sessionModel->addSessionActivityDetailsToList($sessionActivity);
    $sessionActivity = reset($sessionActivity);

    $activity = new XenForo_Phrase('viewing_forum');
    if(isset($sessionActivity['activityDescription']) && !empty($sessionActivity['activityDescription'])){
        $activity = $sessionActivity['activityDescription'];
        if(isset($sessionActivity['activityItemTitle']) && !empty($sessionActivity['activityItemTitle'])){
            $activity .= " ".$sessionActivity['activityItemTitle'];
        }
    }
    $activity .= " (".XenForo_Template_Helper_Core::dateTime($user['view_date'], 'relative').")";

    $xmlrpc_user_info = new xmlrpcval(array(
        'user_id'            => new xmlrpcval($user['user_id'], 'string'),
        'post_count'         => new xmlrpcval($user['message_count'], 'int'),
        'reg_time'           => new xmlrpcval(mobiquo_iso8601_encode($user['register_date']), 'dateTime.iso8601'),
        'last_activity_time' => new xmlrpcval(mobiquo_iso8601_encode($user['view_date']), 'dateTime.iso8601'),
        'timestamp'          => new xmlrpcval($user['view_date'],'string'),
        'reg_timestamp'      => new xmlrpcval($user['register_date'],'string'),
        'icon_url'           => new xmlrpcval(get_avatar($user, "l"), 'string'),
        'username'           => new xmlrpcval($user['username'], 'base64'),
        'user_type'          => new xmlrpcval(get_usertype_by_item('', $user['display_style_group_id'], $user['is_banned']), 'base64'),
        'display_text'       => new xmlrpcval(basic_clean(XenForo_Template_Helper_Core::helperUserTitle($user)), 'base64'),
        'is_online'          => new xmlrpcval($bridge->isUserOnline($user), 'boolean'),
        'accept_pm'          => new xmlrpcval(true, 'boolean'), //99% sure this is always true
        'current_activity'   => new xmlrpcval($activity, 'base64'),
        'custom_fields_list' => new xmlrpcval($custom_fields_list, 'array'),
        'can_ban'            => new xmlrpcval($visitor->hasAdminPermission('ban') && $userModel->couldBeSpammer($user), 'boolean'),
        'is_ban'             => new xmlrpcval($user['is_banned'], 'boolean'),
    ), 'struct');
    $bridge->setUserParams('user_id',$user['user_id']);
    return new xmlrpcresp($xmlrpc_user_info);


}
