<?php

defined('IN_MOBIQUO') or exit;

function update_signature_func($xmlrpc_params){
    $bridge = Tapatalk_Bridge::getInstance();
    $visitor = XenForo_Visitor::getInstance();
    $params = php_xmlrpc_decode($xmlrpc_params);

    $data = $bridge->_input->filterExternal(array(
            'signature' => XenForo_Input::STRING,
    ), $params);

    if (!$visitor->canEditSignature())
    {
        return new xmlrpcresp($bridge->responseNoPermission());
    }

    $signature = $data['signature'];
    $signature = XenForo_Helper_String::autoLinkBbCode($signature, false);

    /** @var $formatter XenForo_BbCode_Formatter_BbCode_Filter */
    $formatter = XenForo_BbCode_Formatter_Base::create('XenForo_BbCode_Formatter_BbCode_Filter');
    $formatter->configureFromSignaturePermissions($visitor->getPermissions());

    $parser = XenForo_BbCode_Parser::create($formatter);
    $signature = $parser->render($signature);

    if (!$formatter->validateAsSignature($signature, $visitor->getPermissions(), $errors))
    {
        return new xmlrpcresp($bridge->responseError($errors));
    }

    $spamModel = $bridge->getSpamPreventionModel();

    if ($signature && $spamModel->visitorRequiresSpamCheck())
    {
        $spamResult = $spamModel->checkMessageSpam($signature);
        switch ($spamResult)
        {
            case XenForo_Model_SpamPrevention::RESULT_MODERATED:
            case XenForo_Model_SpamPrevention::RESULT_DENIED;
            $spamModel->logSpamTrigger('user_signature', XenForo_Visitor::getUserId());
            return new xmlrpcresp($bridge->responseError(new XenForo_Phrase('your_content_cannot_be_submitted_try_later')));
        }
    }

    $settings = array('signature' => $signature);


    $writer = XenForo_DataWriter::create('XenForo_DataWriter_User');
    $writer->setExistingData(XenForo_Visitor::getUserId());
    $writer->bulkSet($settings);

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
        $errors = (is_array($errors) ? $dwErrors + $errors : $dwErrors);
        return new xmlrpcresp($bridge->responseError($errors));
    }

    $writer->save();

    return xmlresptrue();
}