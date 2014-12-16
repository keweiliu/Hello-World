<?php

defined('IN_MOBIQUO') or exit;

function ignore_user_func($xmlrpc_params)
{
    $params = php_xmlrpc_decode($xmlrpc_params);

    $bridge = Tapatalk_Bridge::getInstance();
    $visitor = XenForo_Visitor::getInstance();

    if(!isset($visitor['user_id']) || empty($visitor['user_id']))
        get_error('login_required');
        $data = $bridge->_input->filterExternal(array(
            'user_id' => XenForo_Input::UINT,
            'mode' => XenForo_Input::UINT
    ), $params);
    
    $ignoreModel = $bridge->getIgnoreModel();
    if(isset($data['mode']) && $data['mode'] == 0)
    {
        $ignoreModel->unignoreUser($visitor['user_id'], array($data['user_id']));
    }
    else if(isset($data['mode']) && $data['mode'])
    {
        if (!$ignoreModel->canIgnoreUser($visitor['user_id'], array($data['user_id']), $error))
        {
            return $this->responseError($error);
        }
        $ignoreModel->ignoreUsers($visitor['user_id'], array($data['user_id']));
    }
    return xmlresptrue();
}