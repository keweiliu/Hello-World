<?php

defined('IN_MOBIQUO') or exit;

function save_raw_post_func($xmlrpc_params)
{
    $params = php_xmlrpc_decode($xmlrpc_params);

    $bridge = Tapatalk_Bridge::getInstance();
    $visitor = XenForo_Visitor::getInstance();
    
    $data = $bridge->_input->filterExternal(array(
            'post_id'       => XenForo_Input::UINT,
            'post_title'    => XenForo_Input::STRING,
            'post_content'  => XenForo_Input::STRING,
            'return_html'   => XenForo_Input::UINT,
            'attachment_id_array' => XenForo_Input::ARRAY_SIMPLE,
            'group_id'      => XenForo_Input::STRING,
            'reason'        => XenForo_Input::STRING,
    ), $params);
    
    $postModel = $bridge->getPostModel();
    $threadModel = $bridge->getThreadModel();
    
    $ftpHelper = $bridge->getHelper('ForumThreadPost');
    list($post, $thread, $forum) = $ftpHelper->assertPostValidAndViewable($data['post_id']);
    
    if(!$postModel->canEditPost($post, $thread, $forum, $errorPhraseKey)){
        $bridge->getErrorOrNoPermissionResponseException($errorPhraseKey);
        return;
    }
    
    $data['post_content'] = XenForo_Helper_String::autoLinkBbCode($data['post_content']);
    
    $dw = XenForo_DataWriter::create('XenForo_DataWriter_DiscussionMessage_Post');
    $dw->setExistingData($data['post_id']);
    $dw->set('message', $data['post_content']);
    $dw->setExtraData(XenForo_DataWriter_DiscussionMessage::DATA_ATTACHMENT_HASH,$data['group_id']);
    $dw->setExtraData(XenForo_DataWriter_DiscussionMessage_Post::DATA_FORUM, $forum);
    
    $dw->save();
    $post = $dw->getMergedData();

    $data['watch_thread_state'] = false;
    $bridge->getThreadWatchModel()->setVisitorThreadWatchStateFromInput($thread['thread_id'], $data);
    
    $options = array(
        'states' => array(
            'returnHtml' => (boolean)$data['return_html']
        )
    );
    
    $result = new xmlrpcval(array(
        'result'        => new xmlrpcval(true, 'boolean'),
        'result_text'   => new xmlrpcval('', 'base64'),
        'state'         => new xmlrpcval($threadModel->canViewThread($thread, $forum) ? 0 : 1, 'int'),
        'post_title'    => new xmlrpcval('', 'base64'), //not supported in XenForo
        'post_content'  => new xmlrpcval($bridge->cleanPost($post['message'], $options), 'base64'),
    ), 'struct');
    
    return new xmlrpcresp($result);
}
