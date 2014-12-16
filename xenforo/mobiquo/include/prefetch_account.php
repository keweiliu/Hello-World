<?php

defined('IN_MOBIQUO') or exit;

function prefetch_account_func($xmlrpc_params)
{
    $params = php_xmlrpc_decode($xmlrpc_params);

    $bridge = Tapatalk_Bridge::getInstance();
    $conversationModel = $bridge->getConversationModel();
    $userModel = $bridge->getUserModel();
    $fieldModel = $bridge->_getFieldModel();
    $data = $bridge->_input->filterExternal(array(
        'email' => XenForo_Input::STRING,
    ), $params);
    
    $options = XenForo_Application::get('options');
    $custom_register_fields = array();

    if ($options->get('registrationSetup', 'requireDob')){
        $custom_register_fields[] = new xmlrpcval(array(
            'name'        => new xmlrpcval('birthday','base64'),
            'description' => new xmlrpcval('Birthday','base64'),
            'key'         => new xmlrpcval('birthday','string'),
            'type'        => new xmlrpcval(input,'string'),
            'options'     => new xmlrpcval('','base64'),
            'format'      => new xmlrpcval('nnnn-nn-nn','string'),
        ),'struct');
    }

    if ($options->get('registrationSetup', 'requireLocation')){
            $custom_register_fields[] = new xmlrpcval(array(
            'name'        => new xmlrpcval('location','base64'),
            'description' => new xmlrpcval('location','base64'),
            'key'         => new xmlrpcval('location','string'),
            'type'        => new xmlrpcval('input','string'),
            'options'     => new xmlrpcval('','base64'),
            'format'      => new xmlrpcval('','string'),
        ),'struct');
    }

    $fields = $fieldModel->prepareUserFields($fieldModel->getUserFields());
    
    foreach ( $fields as $key => $value)
    {
        if(!$value['required']) continue;
        
        $field_type="";
        
        switch ($value['field_type'])
        {
            case 'textbox':
                $field_type = 'input';
                break;
            case 'textarea':
                $field_type = 'textarea';
                break;
            case 'select':
                $field_type = 'drop';
                break;
            case 'radio':
                $field_type = 'radio';
                break;
            case 'checkbox':
            case 'multiselect':
                $field_type = 'cbox';
                break;
        }
        
        $format = "";
//      switch ($value['match_type'])
//      {
//          case 'none':
//              $format="";
//              break;
//          case 'regex':
//              $format=$value['match_regex'];
//              break;
//          default:
//              $format=$value['match_type'];
//      }

        $option="";
        $field_choices = unserialize($value['field_choices']);
        foreach ($field_choices as $title => $text){
            $option .= $title.'='.$text.'|';
        }
        $option=substr($option, 0, strlen($option)-1);

        $custom_register_fields[] = new xmlrpcval(array(
            'name'        => new xmlrpcval($value['title']->render(), 'base64'),
            'description' => new xmlrpcval($value['description']->render(), 'base64'),
            'key'         => new xmlrpcval($value['field_id'], 'string'),
            'type'        => new xmlrpcval($field_type, 'string'),
            'options'     => new xmlrpcval($option, 'base64'),
            'format'      => new xmlrpcval($format, 'string'),
        ), 'struct');
    }
    
    if (!empty($data['email'])){
        $user = $userModel->getUserByNameOrEmail($data['email']);
    }
    
    if (!isset($user) || empty($user))
    {
        $result = new xmlrpcval(array(
            'result'                 => new xmlrpcval(false, 'boolean'),
            'custom_register_fields' => new xmlrpcval($custom_register_fields , 'array'),
        ), 'struct');

        return new xmlrpcresp($result);
    }
    
    $result = new xmlrpcval(array(
        'result'        => new xmlrpcval(true, 'boolean'),
        'user_id'       => new xmlrpcval($user['user_id'], 'string'),
        'login_name'    => new xmlrpcval($user['username'], 'base64'),
        'display_name'  => new xmlrpcval($user['username'], 'base64'),
        'avatar'        => new xmlrpcval(get_avatar($user), 'string'),
        'custom_register_fields' => new xmlrpcval($custom_register_fields , 'array'),
    ), 'struct');

    return new xmlrpcresp($result);
}
