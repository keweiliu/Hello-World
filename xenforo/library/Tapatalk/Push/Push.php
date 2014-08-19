<?php

class Tapatalk_Push_Push
{
    protected function Tapatalk_Push_Push()
    {
    }

    public static function tt_push_clean($str)
    {
        $str = strip_tags($str);
        $str = trim($str);
        return html_entity_decode($str, ENT_QUOTES, 'UTF-8');
    }

    public static function tapatalk_push_reply($post, $thread)
    {
        if ($post['content_type'] == 'post' && $post['content_id'] && $thread['thread_id'] && (function_exists('curl_init') || ini_get('allow_url_fopen')))
        {
            $tapatalkUser_model = XenForo_Model::create('Tapatalk_Model_TapatalkUser');
            $tapatalk_user = $tapatalkUser_model->getTapatalkUserById($post['alerted_user_id']);
            if(empty($tapatalk_user))
                return false;
            
            if (self::can_view_post($post['content_id'], $post['alerted_user_id']) === false)
                return false;
            
            $title = self::tt_push_clean($thread['title']);
            $author = self::tt_push_clean($post['username']);

            $ttp_data = array(
                'userid'    => $post['alerted_user_id'],
                'type'      => $post['action'],
                'id'        => $thread['thread_id'],
                'subid'     => $post['content_id'],
                'title'     => $title,
                'author'    => $author,
                'dateline'  => $post['event_date'],
            );
            $boardurl = XenForo_Application::get('options')->boardUrl;
            $ttp_post_data = array(
                'url'  => $boardurl,
                'data' => base64_encode(serialize(array($ttp_data))),
            );
            $options = XenForo_Application::get('options');
            if(isset($options->tp_push_key) && !empty($options->tp_push_key))
                $ttp_post_data['key'] = $options->tp_push_key;
            $return_status = self::do_push_request($ttp_post_data);
        }
    }

    public static function tapatalk_push_conv($conver_msg)
    {
        if (!empty($conver_msg['recepients']) && $conver_msg['title'] && (function_exists('curl_init') || ini_get('allow_url_fopen')))
        {
            $tapatalkUser_model = XenForo_Model::create('Tapatalk_Model_TapatalkUser');
            $spcTpUsers = $tapatalkUser_model->getAllPmOpenTapatalkUsersInArray($conver_msg['recepients']);
            $title = Tapatalk_Push_Push::tt_push_clean($conver_msg['title']);
            $author = Tapatalk_Push_Push::tt_push_clean($conver_msg['conv_sender_name']);
            $boardurl = XenForo_Application::get('options')->boardUrl;
            foreach($spcTpUsers as $tpu_id => $tapatalk_user)
            {
                $ttp_data = array(
                    'userid'    => $tpu_id,
                    'type'      => 'conv',
                    'id'        => $conver_msg['conversation_id'],
                    'subid'     => $conver_msg['reply_count']+1,
                    'title'     => $title,
                    'author'    => $author,
                    'dateline'  => time(),
                );

                $ttp_post_data = array(
                    'url'  => $boardurl,
                    'data' => base64_encode(serialize(array($ttp_data))),
                );

            $options = XenForo_Application::get('options');
            if(isset($options->tp_push_key) && !empty($options->tp_push_key))
                $ttp_post_data['key'] = $options->tp_push_key;

            $return_status = self::do_push_request($ttp_post_data);
            }
        }
    }

    public static function can_view_post($post_id, $user_id)
    {
        $userModel = XenForo_Model::create('XenForo_Model_User');
        $user = $userModel->getUserById($user_id);
        if ($user)
        {
            $user = $userModel->prepareUser($user);
            
            $postModel = XenForo_Model::create('XenForo_Model_Post');
            $post = $postModel->getPostById($post_id);
            if ($post)
            {
                $thread_id = $post['thread_id'];
                if ($thread_id)
                {
                    $thread = XenForo_Model::create('XenForo_Model_Thread')->getThreadById($thread_id);
                    if ($thread)
                    {
                        $forum_id = $thread['node_id'];
                        if ($forum_id)
                        {
                            $forumModel = XenForo_Model::create('XenForo_Model_Forum');
                            $forum = $forumModel->getForumById($forum_id, array(
                                'permissionCombinationId' => $user['permission_combination_id']
                            ));
                            if ($forum)
                            {
                                $permissions = XenForo_Permission::unserializePermissions($forum['node_permission_cache']);
                                if ($postModel->canViewPost($post, $thread, $forum, $null, $permissions, $user))
                                {
                                    return true;
                                }
                            }
                        }
                    }
                }
            }
        }
        
        return false;
    }
    
    /**
     * Get content from remote server
     *
     * @param string $url      NOT NULL          the url of remote server, if the method is GET, the full url should include parameters; if the method is POST, the file direcotry should be given.
     * @param string $holdTime [default 0]       the hold time for the request, if holdtime is 0, the request would be sent and despite response.
     * @param string $error_msg                  return error message
     * @param string $method   [default GET]     the method of request.
     * @param string $data     [default array()] post data when method is POST.
     *
     * @exmaple: getContentFromRemoteServer('http://push.tapatalk.com/push.php', 0, $error_msg, 'POST', $ttp_post_data)
     * @return string when get content successfully|false when the parameter is invalid or connection failed.
    */
    public static function getPushContentFromRemoteServer($url, $holdTime = 0, &$error_msg, $method = 'GET', $data = array())
    {
        //Validate input.
        $vurl = parse_url($url);
        if ($vurl['scheme'] != 'http')
        {
            $error_msg = 'Error: invalid url given: '.$url;
            return false;
        }
        if($method != 'GET' && $method != 'POST')
        {
            $error_msg = 'Error: invalid method: '.$method;
            return false;//Only POST/GET supported.
        }
        if($method == 'POST' && empty($data))
        {
            $error_msg = 'Error: data could not be empty when method is POST';
            return false;//POST info not enough.
        }
    
        if(!empty($holdTime) && function_exists('file_get_contents') && $method == 'GET')
        {
            $response = @file_get_contents($url);
        }
        else if (@ini_get('allow_url_fopen'))
        {
            if(empty($holdTime))
            {
                // extract host and path:
                $host = $vurl['host'];
                $path = $vurl['path'];
    
                if($method == 'POST')
                {
                    $fp = @fsockopen($host, 80, $errno, $errstr, 5);
    
                    if(!$fp)
                    {
                        $error_msg = 'Error: socket open time out or cannot connet.';
                        return false;
                    }
    
                    $data =  http_build_query($data);
    
                    fputs($fp, "POST $path HTTP/1.1\r\n");
                    fputs($fp, "Host: $host\r\n");
                    fputs($fp, "Content-type: application/x-www-form-urlencoded\r\n");
                    fputs($fp, "Content-length: ". strlen($data) ."\r\n");
                    fputs($fp, "Connection: close\r\n\r\n");
                    fputs($fp, $data);
                    fclose($fp);
                    return 1;
                }
                else
                {
                    $error_msg = 'Error: 0 hold time for get method not supported.';
                    return false;
                }
            }
            else
            {
                if($method == 'POST')
                {
                    $params = array('http' => array(
                        'method' => 'POST',
                        'content' => http_build_query($data, '', '&'),
                    ));
                    $ctx = stream_context_create($params);
                    $old = ini_set('default_socket_timeout', $holdTime);
                    $fp = @fopen($url, 'rb', false, $ctx);
                }
                else
                {
                    $fp = @fopen($url, 'rb', false);
                }
                if (!$fp)
                {
                    $error_msg = 'Error: fopen failed.';
                    return false;
                }
                ini_set('default_socket_timeout', $old);
                stream_set_timeout($fp, $holdTime);
                stream_set_blocking($fp, 0);
    
                $response = @stream_get_contents($fp);
            }
        }
        elseif (function_exists('curl_init'))
        {
            $ch = curl_init($url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($ch, CURLOPT_HEADER, false);
            if($method == 'POST')
            {
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
            }
            if(empty($holdTime))
            {
                curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 1);
                curl_setopt($ch, CURLOPT_TIMEOUT,1);
            }
            $response = curl_exec($ch);
            curl_close($ch);
        }
        else
        {
            $error_msg = 'CURL is disabled and PHP option "allow_url_fopen" is OFF. You can enable CURL or turn on "allow_url_fopen" in php.ini to fix this problem.';
            return false;
        }
        return $response;
    }

    
    public static function push_slug($push_v, $method = 'NEW')
    {
        if(empty($push_v))
            $push_v = serialize(array());
        $push_v_data = unserialize($push_v);
        $current_time = time();
        if(!is_array($push_v_data))
            return serialize(array(2 => 0, 3 => 'Invalid v data', 5 => 0));
        if($method != 'CHECK' && $method != 'UPDATE' && $method != 'NEW')
            return serialize(array(2 => 0, 3 => 'Invalid method', 5 => 0));
    
        if($method != 'NEW' && !empty($push_v_data))
        {
            $push_v_data[8] = $method == 'UPDATE';
            if($push_v_data[5] == 1)
            {
                if($push_v_data[6] + $push_v_data[7] > $current_time)
                    return $push_v;
                else
                    $method = 'NEW';
            }
        }
    
        if($method == 'NEW' || empty($push_v_data))
        {
            $push_v_data = array();     //Slug
            $push_v_data[0] = 3;        //        $push_v_data['max_times'] = 3;                //max push failed attempt times in period  
            $push_v_data[1] = 300;      //        $push_v_data['max_times_in_period'] = 300;     //the limitation period
            $push_v_data[2] = 1;        //        $push_v_data['result'] = 1;                   //indicate if the output is valid of not
            $push_v_data[3] = '';       //        $push_v_data['result_text'] = '';             //invalid reason
            $push_v_data[4] = array();  //        $push_v_data['stick_time_queue'] = array();   //failed attempt timestamps
            $push_v_data[5] = 0;        //        $push_v_data['stick'] = 0;                    //indicate if push attempt is allowed
            $push_v_data[6] = 0;        //        $push_v_data['stick_timestamp'] = 0;          //when did push be sticked
            $push_v_data[7] = 600;      //        $push_v_data['stick_time'] = 600;             //how long will it be sticked
            $push_v_data[8] = 1;        //        $push_v_data['save'] = 1;                     //indicate if you need to save the slug into db
            return serialize($push_v_data);
        }
    
        if($method == 'UPDATE')
        {
            $push_v_data[4][] = $current_time;
        }
        $sizeof_queue = count($push_v_data[4]);
        
        $period_queue = $sizeof_queue > 1 ? ($push_v_data[4][$sizeof_queue - 1] - $push_v_data[4][0]) : 0;
    
        $times_overflow = $sizeof_queue > $push_v_data[0];
        $period_overflow = $period_queue > $push_v_data[1];
    
        if($period_overflow)
        {
            if(!array_shift($push_v_data[4]))
                $push_v_data[4] = array();
        }
        
        if($times_overflow && !$period_overflow)
        {
            $push_v_data[5] = 1;
            $push_v_data[6] = $current_time;
        }
    
        return serialize($push_v_data);
    }
    
    public static function do_push_request($data, $pushTest = false)
    {
        $push_url = 'http://push.tapatalk.com/push.php';

        $optionModel = XenForo_Model::create('XenForo_Model_Option');

        if($pushTest)
            return self::getPushContentFromRemoteServer($push_url, $pushTest ? 10 : 0, $error, 'POST', $data);
    
        //Initial this key in modSettings

        //Get push_slug from db
        $option = XenForo_Application::get('options');
        $push_slug = $option->push_slug;
        $push_slug = isset($push_slug) && !empty($push_slug) ? $push_slug : 0;

        $slug = $push_slug;
        $slug = self::push_slug($slug, 'CHECK');
        $check_res = unserialize($slug);
    
        //If it is valide(result = true) and it is not sticked, we try to send push
        if($check_res[2] && !$check_res[5])
        {
            //Slug is initialed or just be cleared
            if($check_res[8])
            {
                $optionModel->updateOptions(array('push_slug' => $slug));
            }
    
            //Send push
            $push_resp = self::getPushContentFromRemoteServer($push_url, 0, $error, 'POST', $data);
            if(trim($push_resp) === 'Invalid push notification key') $push_resp = 1;
            if(!is_numeric($push_resp))
            {
                //Sending push failed, try to update push_slug to db
                $slug = self::push_slug($slug, 'UPDATE');
                $update_res = unserialize($slug);
                if($update_res[2] && $update_res[8])
                {
                     $optionModel->updateOptions(array('push_slug' => $slug));
                }
            }
        }
        
        return true;
    }
}
