<?php
class Tapatalk_Model_TapatalkUser extends XenForo_Model
{
    /**
    * Get only one row using the data passed. 
    */
    public function getTapatalkUserById($userid)
    {
        return $this->_getDb()->fetchRow('
            SELECT * FROM xf_tapatalk_users WHERE userid = ?', $userid);
    }
 
    /**
    * Get all the rows of our table.
    *
    */
    public function getAllTapatalkUser()
    {
        return $this->fetchAllKeyed('SELECT * FROM xf_tapatalk_users ORDER BY userid DESC', 'userid');
    }

    public function getAllPmOpenTapatalkUsersInArray($user_ids)
    {
        $search_users="";
        if(!is_array($user_ids)){
            return array();
        }else{
            $search_users = implode(',', array_map('intval',$user_ids));
        }
        return $this->fetchAllKeyed('SELECT userid FROM xf_tapatalk_users WHERE userid in ('.$search_users.') ORDER BY userid DESC', 'userid');
    }

    public function getPushTypeOpenTapatalkUsers($user_id, $action)
    {
        $action_columnames = array(
            'sub' => 'subscribe',
            'quote' => 'quote',
            'like' => 'liked',
            'tag' => 'tag',
        );
        if(!isset($action_columnames[$action]) || empty($action_columnames[$action]))
            return array();

        return $this->fetchAllKeyed('SELECT userid FROM xf_tapatalk_users WHERE '.$action_columnames[$action].' = 1 AND userid = ? ', 'userid', $user_id);
    }
    
    public function getDisplayNameByTableKey($key)
    {
        $display_key_map = array(
            'conv'     => 'Conversation push',
            'pm'       => 'PM push',
            'subscribe'=> 'Subscription topic push',
            'liked'    => 'Likes push',
            'quote'    => 'Quotes push',
            'newtopic' => 'Subscription forum push',
            'tag'      => 'Mention push',
            'announcement'      => 'Announcement push',
        );
        return isset($display_key_map[$key])? $display_key_map[$key]: '';
    }
    
    public function getStarndardNameByTableKey($key)
    {
        $starndard_key_map = array(
            'conv'     => 'conv',
            'pm'       => 'conv',
            'subscribe'=> 'sub',
            'liked'    => 'like',
            'quote'    => 'quote',
            'newtopic' => 'newtopic',
            'tag'      => 'tag',
//            'announcement'      => 'ann',
        );
        return isset($starndard_key_map[$key])? $starndard_key_map[$key]: '';
    }
}
?>