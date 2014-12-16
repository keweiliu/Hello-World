<?php

defined('IN_MOBIQUO') or exit;

function get_error($error_key, $params = array())
{
    $error_message = (string)new XenForo_Phrase($error_key, $params);

    $r = new xmlrpcresp(
    new xmlrpcval(array(
        'result'        => new xmlrpcval(false, 'boolean'),
        'result_text'   => new xmlrpcval($error_message, 'base64'),
    ),'struct')
    );
    echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n".$r->serialize('UTF-8');
    exit;
}

function get_method_name()
{
    $ver = phpversion();
    if ($ver[0] >= 5) {
        $data = @file_get_contents('php://input');
    } else {
        $data = isset($GLOBALS['HTTP_RAW_POST_DATA']) ? $GLOBALS['HTTP_RAW_POST_DATA'] : '';
    }
    $parsers = php_xmlrpc_decode_xml($data);
    if(is_object($parsers))
    return trim($parsers->methodname);
    else
    {
        if(isset($_POST['method_name']) && !empty($_POST['method_name'])){
            return $_POST['method_name'];
        }else{
            return null;
        }
    }
}

function get_mobiquo_config()
{

    $mobiquo_config = $mobiquo_config = TTConfig::get_config();


    $options = XenForo_Application::get('options');
    $mobiquo_config['guest_okay'] = $options->guest_okay;
    $mobiquo_config['reg_url'] = isset($options->reg_url) && !empty($options->reg_url) ? $options->reg_url: 'index.php?register';
    $mobiquo_config['advanced_delete'] = $options->advanced_delete;

    return $mobiquo_config;
}

function xmlresptrue()
{
    $result = new xmlrpcval(array(
        'result'        => new xmlrpcval(true, 'boolean'),
        'result_text'   => new xmlrpcval('', 'base64')
    ), 'struct');

    return new xmlrpcresp($result);
}

/**
 * For use via preg_replace_callback; makes urls absolute before wrapping them in [url]
 */
function parse_local_link($input){
    return "[URL=".XenForo_Link::convertUriToAbsoluteUri($input[1], true)."]{$input[2]}[/URL]";
}

function xmlresperror($error_message)
{
    $result = new xmlrpcval(array(
        'result'        => new xmlrpcval(false, 'boolean'),
        'result_text'   => new xmlrpcval($error_message, 'base64')
    ), 'struct');

    return new xmlrpcresp($result);
}

function get_forum_icon_url($fid)
{
    $logo_url = '';
    if (file_exists("./forum_icons/$fid.png"))
    {
        $logo_url = FORUM_ROOT."mobiquo/forum_icons/$fid.png";
    }
    else if (file_exists("./forum_icons/$fid.jpg"))
    {
        $logo_url = FORUM_ROOT."mobiquo/forum_icons/$fid.jpg";
    }
    else if (file_exists("./forum_icons/default.png"))
    {
        $logo_url = FORUM_ROOT."mobiquo/forum_icons/default.png";
    }

    return $logo_url;
}

function tp_get_forum_icon($id, $type = 'forum', $lock = false, $new = false)
{
    if (!in_array($type, array('link', 'category', 'forum')))
    $type = 'forum';

    $icon_name = $type;
    if ($type != 'link')
    {
        if ($lock) $icon_name .= '_lock';
        if ($new) $icon_name .= '_new';
    }

    $icon_map = array(
        'category_lock_new' => array('category_lock', 'category_new', 'lock_new', 'category', 'lock', 'new'),
        'category_lock'     => array('category', 'lock'),
        'category_new'      => array('category', 'new'),
        'lock_new'          => array('lock', 'new'),
        'forum_lock_new'    => array('forum_lock', 'forum_new', 'lock_new', 'forum', 'lock', 'new'),
        'forum_lock'        => array('forum', 'lock'),
        'forum_new'         => array('forum', 'new'),
        'category'          => array(),
        'forum'             => array(),
        'lock'              => array(),
        'new'               => array(),
        'link'              => array(),
    );

    $final = !isset($icon_map[$icon_name]) || empty($icon_map[$icon_name]);

    if ($url = tp_get_forum_icon_by_name($id, $icon_name, $final))
    return $url;

    foreach ($icon_map[$icon_name] as $sub_name)
    {
        $final = !isset($icon_map[$sub_name]) || empty($icon_map[$sub_name]);
        if ($url = tp_get_forum_icon_by_name($id, $sub_name, $final))
        return $url;
    }

    return '';
}

function tp_get_forum_icon_by_name($id, $name, $final)
{
    global $boarddir, $boardurl;

    $tapatalk_forum_icon_dir = './forum_icons/';
    $tapatalk_forum_icon_url = FORUM_ROOT.'mobiquo/forum_icons/';

    $filename_array = array(
    $name.'_'.$id.'.png',
    $name.'_'.$id.'.jpg',
    $id.'.png', $id.'.jpg',
    $name.'.png',
    $name.'.jpg',
    );

    foreach ($filename_array as $filename)
    {
        if (file_exists($tapatalk_forum_icon_dir.$filename))
        {
            return $tapatalk_forum_icon_url.$filename;
        }
    }

    if ($final) {
        if (file_exists($tapatalk_forum_icon_dir.'default.png'))
        return $tapatalk_forum_icon_url.'default.png';
        else if (file_exists($tapatalk_forum_icon_dir.'default.jpg'))
        return $tapatalk_forum_icon_url.'default.jpg';
    }

    return '';
}

function mobiquo_iso8601_encode($timestamp)
{
    return date('Ymd\TH:i:sP', $timestamp);
}

function cutstr($string, $length)
{
    if(strlen($string) <= $length) {
        return $string;
    }

    $string = str_replace(array('&amp;', '&quot;', '&lt;', '&gt;'), array('&', '"', '<', '>'), $string);

    $strcut = '';

    $n = $tn = $noc = 0;
    while($n < strlen($string)) {

        $t = ord($string[$n]);
        if($t == 9 || $t == 10 || (32 <= $t && $t <= 126)) {
            $tn = 1; $n++; $noc++;
        } elseif(194 <= $t && $t <= 223) {
            $tn = 2; $n += 2; $noc += 2;
        } elseif(224 <= $t && $t <= 239) {
            $tn = 3; $n += 3; $noc += 2;
        } elseif(240 <= $t && $t <= 247) {
            $tn = 4; $n += 4; $noc += 2;
        } elseif(248 <= $t && $t <= 251) {
            $tn = 5; $n += 5; $noc += 2;
        } elseif($t == 252 || $t == 253) {
            $tn = 6; $n += 6; $noc += 2;
        } else {
            $n++;
        }

        if($noc >= $length) {
            break;
        }

    }
    if($noc > $length) {
        $n -= $tn;
    }

    $strcut = wholeWordTrim($string, $n, 0, "");

    return $strcut;
}

function wholeWordTrim($string, $maxLength, $offset = 0, $elipses = '...')
{
    //TODO: this may need a handler for language independence and some form of error correction for bbcode

    if ($offset)
    {
        $string = preg_replace('/^\S*\s+/s', '', utf8_substr($string, $offset));
    }

    $strLength = utf8_strlen($string);

    if ($maxLength > 0 && $strLength > $maxLength)
    {
        $string = utf8_substr($string, 0, $maxLength);
        $string = strrev(preg_replace('/^\S*\s+/s', '', strrev($string))) . $elipses;
    }

    if ($offset)
    {
        $string = $elipses . $string;
    }

    return $string;
}

function process_page($start_num, $end)
{
    $start = intval($start_num);
    $end = intval($end);
    $start = empty($start) ? 0 : max($start, 0);
    $end = (empty($end) || $end < $start) ? ($start + 19) : max($end, $start);
    if ($end - $start >= 50) {
        $end = $start + 49;
    }
    $limit = $end - $start + 1;
    $page = intval($start/$limit) + 1;

    return array($start, $limit, $page);
}

// redundant? __toString ;)
function get_xf_lang($lang_key, $params = array())
{
    $phrase = new XenForo_Phrase($lang_key, $params);
    return $phrase->render();
}

function get_online_status($user_id)
{
    $bridge = Tapatalk_Bridge::getInstance();
    $sessionModel = $bridge->getSessionModel();
    $userModel = $bridge->getUserModel();

    $bypassUserPrivacy = $userModel->canBypassUserPrivacy();

    $conditions = array(
        'cutOff'            => array('>', $sessionModel->getOnlineStatusTimeout()),
        'getInvisible'      => $bypassUserPrivacy,
        'getUnconfirmed'    => $bypassUserPrivacy,
        'user_id'           => $user_id,
        'forceInclude'      => ($bypassUserPrivacy ? false : XenForo_Visitor::getUserId())
    );

    $onlineUsers = $sessionModel->getSessionActivityRecords($conditions);

    return empty($onlineUsers) ? false : true;
}

function basic_clean($str)
{
    $str = strip_tags($str);
    $str = trim($str);
    return html_entity_decode($str, ENT_QUOTES, 'UTF-8');
}

function get_avatar($user, $size = 'm')
{
    if (isset($user['user_id']) && !empty($user['user_id']) && ((isset($user['gravatar']) && !empty($user['gravatar'])) || (isset($user['avatar_date']) && !empty($user['avatar_date'])))){
        return XenForo_Link::convertUriToAbsoluteUri(XenForo_Template_Helper_Core::callHelper('avatar', array($user, $size)), true);
    }else{
        return '';
    }
}

function get_prefix_name($id)
{
    static $prefixModel;

    if (empty($prefixModel))
    {
        $bridge = Tapatalk_Bridge::getInstance();
        $prefixModel = $bridge->_getPrefixModel();
    }

    $prefix = '';
    if (!empty($id))
    {
        $prefix = new XenForo_Phrase($prefixModel->getPrefixTitlePhraseName($id));
        $prefix = (string)$prefix;
    }

    return $prefix;
}

function get_usertype_by_item($userid = '', $groupid = '', $is_banned = false, $state = '')
{
    if($is_banned)
    return 'banned';
    if($state == 'email_confirm' || $state == 'email_confirm_edit' || $state == 'Email invalid (bounced)')
    return 'inactive';
    if($state == 'moderated')
    return 'unapproved';
    if (empty($groupid))
    {
        if(!empty($userid))
        {
            $bridge = Tapatalk_Bridge::getInstance();
            $userModel = $bridge->getUserModel();
            $user = $userModel->getUserById($userid);
            if($user['is_banned'])
            return 'banned';
            $groupid = $user['display_style_group_id'];
        }
        else
        return ' ';
    }

    if($groupid == 3)
    return 'admin';
    else if($groupid == 4)
    return 'mod';
    else if($groupid == 2)
    return 'normal';
    else if($groupid == 1)
    return ' ';
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
function getContentFromRemoteServer($url, $holdTime = 0, &$error_msg, $method = 'GET', $data = array())
{
    //Validate input.
    $vurl = parse_url($url);
    if ($vurl['scheme'] != 'http' && $vurl['scheme'] != 'https')
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

    $response = '';

    if(!empty($holdTime) && function_exists('file_get_contents') && $method == 'GET')
    {
        $opts = array(
        $vurl['scheme'] => array(
                'method' => "GET",
                'timeout' => $holdTime,
        )
        );

        $context = stream_context_create($opts);
        $response = @file_get_contents($url,false,$context);
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
                $fp = fsockopen($host, 80, $errno, $errstr, 5);

                if(!$fp)
                {
                    $error_msg = 'Error: socket open time out or cannot connet.';
                    return false;
                }

                $data = http_build_query($data, '', '&');

                fputs($fp, "POST $path HTTP/1.1\r\n");
                fputs($fp, "Host: $host\r\n");
                fputs($fp, "Content-type: application/x-www-form-urlencoded\r\n");
                fputs($fp, "Content-length: ". strlen($data) ."\r\n");
                fputs($fp, "Connection: close\r\n\r\n");
                fputs($fp, $data);
                fclose($fp);
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
                $params = array(
                $vurl['scheme'] => array(
                        'method' => 'POST',
                        'content' => http_build_query($data, '', '&'),
                )
                );
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

function mobi_forum_exclude($nodeId, $allNodes, $nodeModel)
{
    if(in_array($nodeId, $allNodes))
    {
        $childNodes = $nodeModel->getChildNodesForNodeIds(array($nodeId));

        foreach($allNodes as $index => $node)
        if($node == $nodeId)
        unset($allNodes[$index]);

        foreach($childNodes as $_nodeid => $_node)
        $allNodes = mobi_forum_exclude($_nodeid, $allNodes, $nodeModel);
    }

    return $allNodes;
}

function mobi_forum_include($nodeId, $allNodes, $nodeModel, $selectedNodes)
{
    if(in_array($nodeId, $allNodes))
    {

        $childNodes = $nodeModel->getChildNodesForNodeIds(array($nodeId));

        if(in_array($nodeId, $allNodes) && !in_array($nodeId, $selectedNodes))
        {

            $selectedNodes[] = $nodeId;
        }

        foreach($childNodes as $_nodeid => $_node)
        {

            $selectedNodes = mobi_forum_include($_nodeid, $allNodes, $nodeModel, $selectedNodes);
        }
    }

    return $selectedNodes;
}

function getEmailFromScription($token, $code, $key)
{
    $boardurl = XenForo_Application::get('options')->boardUrl;
    $verification_url = 'http://directory.tapatalk.com/au_reg_verify.php?token='.$token.'&'.'code='.$code.'&key='.$key.'&url='.$boardurl;
    $response = getContentFromRemoteServer($verification_url, 10, $error);
    if($response)
    $result = json_decode($response, true);
    if(isset($result) && isset($result['result']))
    return $result;
    else
    {
        $data = array(
            'token' => $token,
            'code'  => $code,
            'key'   => $key,
            'url'   => $boardurl,
        );
        $response = getContentFromRemoteServer('http://directory.tapatalk.com/au_reg_verify.php', 10, $error, 'POST', $data);
        if($response)
        $result = json_decode($response, true);
        if(isset($result) && isset($result['result']))
        return $result;
        else
        return 0; //No connection to Tapatalk Server.
    }
}

function loadAPIKey()
{
    global $mobi_api_key;

    if(empty($mobi_api_key))
    {
        $option_key = XenForo_Application::get('options')->tp_push_key;
        if(isset($option_key) && !empty($option_key))
        {
            $mobi_api_key = $option_key;
        }
        else
        {
            $boardurl = XenForo_Application::get('options')->boardUrl;
            $boardurl = urlencode($boardurl);
            $response = getContentFromRemoteServer("http://directory.tapatalk.com/au_reg_verify.php?url=$boardurl", 10, $error);
            if($response)
            {
                $result = @json_decode($response, true);
                if(isset($result['result']) && $result['result'])
                {
                    $mobi_api_key = @$result['api_key'];
                }
            }
        }
    }
    return $mobi_api_key;
}

function keyED($txt,$encrypt_key)
{
    $encrypt_key = md5($encrypt_key);
    $ctr=0;
    $tmp = "";
    for ($i=0;$i<strlen($txt);$i++)
    {
        if ($ctr==strlen($encrypt_key)) $ctr=0;
        $tmp.= substr($txt,$i,1) ^ substr($encrypt_key,$ctr,1);
        $ctr++;
    }
    return $tmp;
}

function encrypt($txt,$key)
{
    srand((double)microtime()*1000000);
    $encrypt_key = md5(rand(0,32000));
    $ctr=0;
    $tmp = "";
    for ($i=0;$i<strlen($txt);$i++)
    {
        if ($ctr==strlen($encrypt_key)) $ctr=0;
        $tmp.= substr($encrypt_key,$ctr,1) .
        (substr($txt,$i,1) ^ substr($encrypt_key,$ctr,1));
        $ctr++;
    }
    return keyED($tmp,$key);
}

function login_user($userId, $new_register = false)
{
    $bridge = Tapatalk_Bridge::getInstance();
    $conversationModel = $bridge->getConversationModel();
    $userModel = $bridge->getUserModel();
    $options = XenForo_Application::get('options');

    XenForo_Model_Ip::log($userId, 'user', $userId, 'login');
    $tapatalk_user_writer = XenForo_DataWriter::create('Tapatalk_DataWriter_TapatalkUser');
    $tapatalk_user_model = $tapatalk_user_writer->getTapatalkUserModel();
    $existing_record = $tapatalk_user_model->getTapatalkUserById($userId);
    if(empty($existing_record))
    {
        $tapatalk_user_writer->set('userid',$userId);
        $tapatalk_user_writer->preSave();
        $tapatalk_user_writer->save();
    }
    else
    {
        $tapatalk_user_writer->setExistingData($existing_record);
        $tapatalk_user_writer->set('updated',gmdate('Y-m-d h:i:s',time()));
        $tapatalk_user_writer->save();
    }


    $userModel->deleteSessionActivity(0, $bridge->_request->getClientIp(false));

    $session = XenForo_Application::get('session');
    $session->changeUserId($userId);
    XenForo_Visitor::setup($userId);

    $visitor = XenForo_Visitor::getInstance();

    $groups = array(
    new xmlrpcval($visitor['user_group_id'], "string")
    );

    if ($visitor['secondary_group_ids'])
    {
        $secondary_groups = explode(",", $visitor['secondary_group_ids']);
        foreach($secondary_groups as $secondary_group_id){
            $groups[] = new xmlrpcval($secondary_group_id, "string");
        }
    }

    // check ban
    $result_text = '';
    $bannedUser = $bridge->getModelFromCache('XenForo_Model_Banning')->getBannedUserById($userId);

    if ($bannedUser)
    {
        if ($bannedUser['user_reason'])
        {
            $result_text = new XenForo_Phrase('you_have_been_banned_for_following_reason_x', array('reason' => $bannedUser['user_reason']));
        }
        else
        {
            $result_text = new XenForo_Phrase('you_have_been_banned');
        }

        if ($bannedUser['end_date'] > XenForo_Application::$time)
        {
            $result_text .= ' ' . new XenForo_Phrase('your_ban_will_be_lifted_on_x', array('date' => XenForo_Locale::dateTime($bannedUser['end_date'])));
        }
    }
    $push_status = array();
    $options = XenForo_Application::get('options');

    //fake push status
    $push_status = array();
    $supported_types = array(
        'conv'       => 'conv',
        'subscribe'=> 'sub',
        'liked'    => 'like',
        'quote'    => 'quote',
        'tag'      => 'tag',
    );
    if(XenForo_Application::get('options')->currentVersionId > 1020069)
    $supported_types['newtopic'] = 'newtopic';
    foreach($supported_types as $support_type)
    $push_status[] = new xmlrpcval(array(
            'name'  => new xmlrpcval($support_type, 'string'),
            'value' => new xmlrpcval(true, 'boolean')
    ), 'struct');

    $postCountdown=0;
    if (!XenForo_Visitor::getInstance()->hasPermission('general', 'bypassFloodCheck')){
        $postCountdown=$options->floodCheckLength;
    }

    $largestDimension = XenForo_Model_Avatar::getSizeFromCode('l');
    $permissions = $visitor->getPermissions();
    if (isset($permissions) && !empty($permissions)){
        $maxFileSize = XenForo_Permission::hasPermission($permissions, 'avatar', 'maxFileSize');
    }
    if (isset($maxFileSize) && !empty($maxFileSize) && $maxFileSize != -1 && $largestDimension > $maxFileSize){
        $largestDimension = $maxFileSize;
    }
    $largestDimension = $largestDimension * 1024;

    $result = array(
        'result'            => new xmlrpcval(true, 'boolean'),
        'result_text'       => new xmlrpcval($result_text, 'base64'),
        'user_id'           => new xmlrpcval($userId, 'string'),
        'username'          => new xmlrpcval($visitor['username'], 'base64'),
        'login_name'        => new xmlrpcval($visitor['username'], 'base64'),
        'email'             => new xmlrpcval($visitor['email'], 'base64'),
        'user_type'         => new xmlrpcval(get_usertype_by_item('', $visitor['display_style_group_id'], $visitor['is_banned'], $visitor['user_state']), 'base64'),
        'icon_url'          => new xmlrpcval(get_avatar($visitor->toArray(), "l"), 'string'),
        'post_count'        => new xmlrpcval(intval($visitor['message_count']), "int"),
        'usergroup_id'      => new xmlrpcval($groups, "array"),
        'can_pm'            => new xmlrpcval(true, "boolean"),
        'can_send_pm'       => new xmlrpcval($conversationModel->canStartConversations($errorPhraseKey), "boolean"),
        'can_moderate'      => new xmlrpcval($visitor['is_moderator'], "boolean"),
        'can_search'        => new xmlrpcval($visitor->canSearch(), "boolean"),
        'can_whosonline'    => new xmlrpcval(true, "boolean"),
        'can_profile'       => new xmlrpcval(true, "boolean"),
        'can_upload_avatar' => new xmlrpcval($visitor->canUploadAvatar(), "boolean"),
        'max_avatar_size'   => new xmlrpcval($largestDimension, "int"),
        'max_avatar_width'  => new xmlrpcval($largestDimension, "int"),
        'max_avatar_height' => new xmlrpcval($largestDimension, "int"),
        'can_report_pm'     => new xmlrpcval(false, 'boolean'),
        'push_type'         => new xmlrpcval($push_status, 'array'),
        'allowed_extensions'=> new xmlrpcval(implode(',',preg_split("/\s+/", trim($options->attachmentExtensions))), 'string'),
        'max_attachment'    => new xmlrpcval($options->attachmentMaxPerMessage ? $options->attachmentMaxPerMessage : 10, "int"),
        'max_attachment_size'=>new xmlrpcval($options->attachmentMaxFileSize ? $options->attachmentMaxFileSize*1024 : 1048576,'int'),
        'ignored_uids'      => new xmlrpcval(isset($visitor['ignored']) && !empty($visitor['ignored']) ? $ignore_users = implode(',', array_keys(unserialize($visitor['ignored']))) : '', 'string'),
        'max_png_size'      => new xmlrpcval($options->attachmentMaxFileSize * 1000, "int"),
        'max_jpg_size'      => new xmlrpcval($options->attachmentMaxFileSize * 1000, "int"),
        'post_countdown'    => new xmlrpcval($postCountdown,"int"),
    );
    if ($new_register !== NULL){
        $result["register"]=new xmlrpcval($new_register, 'boolean');
    }

    return new xmlrpcresp(new xmlrpcval($result, 'struct'));
}

function tt_validate_numeric($numeric){
    return  isset($numeric) && is_numeric($numeric) && !is_array($numeric);
}