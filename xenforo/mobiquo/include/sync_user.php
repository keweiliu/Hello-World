<?php

defined('IN_MOBIQUO') or exit;

function sync_user_func(){
    $code = trim($_POST['code']);
    $start = (isset($_POST['start']) && is_numeric($_POST['start']) && intval($_POST['start']) > 0) ? intval($_POST['start']) : 0;
    $limit = (isset($_POST['limit']) && is_numeric($_POST['limit']) && intval($_POST['limit']) > 0) ? intval($_POST['limit']) : 1000;
    $format = trim($_POST['format']);

    $connection = new classFileManagement();
    $response = $connection->actionVerification($code,'sync_user');

    if($response === true)
    {
        $options = XenForo_Application::get('options');
        $api_key = $options->tp_push_key;

        // Get users...
        $users = array();

        $userModel = XenForo_Model::create('XenForo_Model_User');

        $result = $userModel->getUsers(
            array(
                'user_state' => 'valid',
                'is_banned' => 0,
                ),
            array(
                'offset' => $start,
                'limit' => $limit,
                'join' => XenForo_Model_User::FETCH_USER_OPTION,
            )
        );

        foreach ($result as $row)
        {
            $user['uid'] = $row['user_id'];
            $user['username'] = $row['username'];
            $user['encrypt_email'] = base64_encode(encrypt($row['email'],$api_key));
            $user['allow_email'] = $row['receive_admin_email'];
            $languageModel = XenForo_Model::create('XenForo_Model_Language');
            $language = $languageModel->getLanguageById($row['language_id']);
            $user['language'] = (isset($language['title']) && !empty($language['title'])) ? $language['title'] : new XenForo_Phrase('master_language');
            $users[] = $user;
        }
        $data = array(
            'result' => true,
            'users' => $users,
        );
    }
    else
    {
        $data = array(
            'result' => false,
            'result_text' => $response,
        );
    }
    $response = ($format == 'json') ? json_encode($data) : serialize($data);
    echo $response;
    exit;
}