<?php

class Tapatalk_Push_Conversation extends XFCP_Tapatalk_Push_Conversation
{

	/**
	 * Post-save handling.
	 */
	protected function _postSave()
	{
		parent::_postSave();
		//Tapatalk add
		$conversationModel = $this->_getConversationModel();

		$conver_msg = $this->getMergedData();

		$participated_members = $conversationModel->getConversationRecipients($conver_msg['conversation_id']);
		$recepients = array();
		$visitor = XenForo_Visitor::getInstance();
		$current_user = $visitor->toArray();
		$conver_msg['conv_sender_id'] = !empty($current_user['user_id']) ? $current_user['user_id'] : $conver_msg['last_message_user_id'];
		$conver_msg['conv_sender_name'] = !empty($current_user['username']) ? $current_user['username'] : $conver_msg['last_message_username'];
		foreach($participated_members as $mem_id => $member)
		{
			if($mem_id == $conver_msg['conv_sender_id']) continue;
			if($member['recipient_state'] != 'active') continue;
			$recepients[] = $mem_id;
		}
		$conver_msg['recepients'] = $recepients;
		XenForo_Application::autoload('Tapatalk_Push_Push');
		Tapatalk_Push_Push::tapatalk_push_conv($conver_msg);
	}

}