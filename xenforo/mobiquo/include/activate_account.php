<?php

defined('IN_MOBIQUO') or exit;

function activate_account_func($xmlrpc_params){
    $params = php_xmlrpc_decode($xmlrpc_params);
    $bridge = Tapatalk_Bridge::getInstance();
    $data = $bridge->_input->filterExternal(array(
        'email'     => XenForo_Input::STRING,
        'token' => XenForo_Input::STRING,
        'code' => XenForo_Input::STRING,
    ), $params);

    $userModel = $bridge->getUserModel();
    $user = $userModel->getUserByEmail($data['email']);
    if (empty($user)){
        return errorStatus(1);//account does not exist
    }

    if(empty($data['token'])){
        return errorStatus(4);//tapatalk authorization verify failed
    }

    $options = XenForo_Application::get('options');
    $email_response = getEmailFromScription($data['token'], $data['code'], $options->tp_push_key);

    if (empty($email_response)){
        return errorStatus(4);//tapatalk authorization verify failed
    }
    if ($email_response['result'] === false && isset($email_response['inactive']) && !empty($email_response['inactive'])){
        return errorStatus(2);//tapatalk id is not active
    }

    if(!$email_response['result'] || !isset($email_response['email']) || empty($email_response['email'])){
        return errorStatus(4);//tapatalk authorization verify failed
    }

    if ($email_response['email'] != $data['email']){
        return errorStatus(3);//account does not match
    }

    try {
        $dw = XenForo_DataWriter::create('XenForo_DataWriter_User');
        $dw->setExistingData($user['user_id']);
        if ($dw->get('user_state') == 'email_confirm' || $dw->get('user_state') == 'email_confirm_edit')
        {
            // don't log when changing from initial confirm state as it creates a lot of noise
            $dw->setOption(XenForo_DataWriter_User::OPTION_LOG_CHANGES, false);
            $dw->advanceRegistrationUserState();
            $dw->save();

            $confirmationModel = $bridge->getUserConfirmationModel();
            @$confirmationModel->deleteUserConfirmationRecord($user['user_id'], 'email');

            $user = $dw->getMergedData();
            // log the IP of the user
            XenForo_Model_Ip::log($user['user_id'], 'user', $user['user_id'], 'account-confirmation');
        }
         
    }catch (Exception $e){
        return errorStatus(5, $e->getMessage());
    }
    return new xmlrpcresp(new xmlrpcval(array('result' => new xmlrpcval(true, 'boolean')), 'struct'));
}

function errorStatus($status = 0, $result_text = '')
{
    $xmlrpcResult = array(
        'result' => new xmlrpcval(false, 'boolean'),
        'status' => new xmlrpcval($status, 'string'),
    );
    if (!empty($result_text)){
        $xmlrpcResult['result_text'] = new xmlrpcval($result_text, 'base64');
    }
    $result = new xmlrpcval($xmlrpcResult, 'struct');
    return new xmlrpcresp($result);
}