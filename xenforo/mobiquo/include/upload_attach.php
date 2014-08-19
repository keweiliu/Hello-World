<?php
    
defined('IN_MOBIQUO') or exit;

function upload_attach_func($xmlrpc_params)
{
    $params = php_xmlrpc_decode($xmlrpc_params);
    
    $bridge = Tapatalk_Bridge::getInstance();
    $visitor = XenForo_Visitor::getInstance();
    
    $data = $bridge->_input->filter(array(
        'group_id' => XenForo_Input::STRING,
        'type' => XenForo_Input::STRING,
        'content' => XenForo_Input::STRING,
    ));
    
    $contentType = 'post';
    $contentData=array();
    if(isset($data['type']) && $data['type'] == 'pm'){
        $contentType = 'conversation_message';
        $message_id=$bridge->_input->filterSingle("message_id",XenForo_Input::UINT);
        if (isset($message_id) && !empty($message_id)){
            $contentData['conversation_id'] = $message_id;
        }
    }else {
        $forum_id=$bridge->_input->filterSingle("forum_id", XenForo_Input::UINT);
        if(!isset($forum_id) || empty($forum_id)){
            $forum_id=0;
        }
        $contentData ['node_id'] = $forum_id;
    }
    
    if(empty($data['group_id']))
        $hash = md5(uniqid('', true));
    else
        $hash = $data['group_id'];
    
    $attachmentModel = $bridge->getAttachmentModel();
    
    $attachmentHandler = $attachmentModel->getAttachmentHandler($contentType);
    if (!$attachmentHandler || !$attachmentHandler->canUploadAndManageAttachments($contentData))
    {
         $bridge->getNoPermissionResponseException();
         return;
    }
    
    $attachmentHandler = $attachmentModel->getAttachmentHandler($contentType);
    $contentId = $attachmentHandler->getContentIdFromContentData($contentData);

    $existingAttachments = ($contentId
        ? $attachmentModel->getAttachmentsByContentId($contentType, $contentId)
        : array()
    );

    $maxAttachments = $attachmentHandler->getAttachmentCountLimit();
    if ($maxAttachments !== true)
    {
        $remainingUploads = $maxAttachments - count($existingAttachments);
        if ($remainingUploads <= 0)
        {
            $bridge->responseError(new XenForo_Phrase(
                'you_may_not_upload_more_files_with_message_allowed_x',
                array('total' => $maxAttachments)
            ));
            return;
        }
    }
    
    $attachmentConstraints = $attachmentModel->getAttachmentConstraints();

    $file = XenForo_Upload::getUploadedFile('attachment');
    if (!$file)
    {       
        $bridge->responseError(new XenForo_Phrase('dark_upload_failed'));
        return;
    }

    $file->setConstraints($attachmentConstraints);
    if (!$file->isValid())
    {
        return $bridge->responseError(reset($file->getErrors()));
    }
    $dataId = $attachmentModel->insertUploadedAttachmentData($file, XenForo_Visitor::getUserId());
    $attachmentId = $attachmentModel->insertTemporaryAttachment($dataId, $hash);

    $attachment = $attachmentModel->getAttachmentById($attachmentId);

    
    $result = new xmlrpcval(array(
        'attachment_id'   => new xmlrpcval($attachmentId, 'string'),
        'group_id'        => new xmlrpcval($hash, 'string'),
        'result'          => new xmlrpcval(true, 'boolean'),
        'result_text'     => new xmlrpcval('', 'base64'),
        'file_size'       => new xmlrpcval($attachment['file_size'], 'int'),
    ), 'struct');

    return new xmlrpcresp($result);
}