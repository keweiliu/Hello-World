<?php

error_reporting(E_ALL & ~E_NOTICE);
define('IN_MOBIQUO', 1);
if(isset($_GET['checkAccess']))
{
    echo "yes";
    exit;
}
define('SCRIPT_ROOT', empty($_SERVER['SCRIPT_FILENAME']) ? '../' : dirname(dirname($_SERVER['SCRIPT_FILENAME'])).'/');
if (DIRECTORY_SEPARATOR == '/')
    define('FORUM_ROOT', 'http://'.$_SERVER['HTTP_HOST'].dirname(dirname($_SERVER['SCRIPT_NAME'])).'/');
else
    define('FORUM_ROOT', 'http://'.$_SERVER['HTTP_HOST'].str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))).'/');
require_once './mobiquo_common.php';
require_once SCRIPT_ROOT.'library/XenForo/Autoloader.php';
XenForo_Autoloader::getInstance()->setupAutoloader(SCRIPT_ROOT.'library');
XenForo_Application::initialize(SCRIPT_ROOT.'library', SCRIPT_ROOT);


// Make sure deprecated warnings go back off due to XF override
$errorReporting = ini_get('error_reporting') &~ 8096;
@error_reporting($errorReporting);
@ini_set('error_reporting', $errorReporting);
// Hide errors from normal display - will be cleanly output via shutdown function.
// (No need to turn off errors when not debugging like in normal Tapatalk plugins - all are passed through cleanly via XMLRPC result_text.)
ini_set('display_errors', 0);
//
// Revert XenForo's error handler also
restore_error_handler();

if (isset($_GET['checkip']))
{
    print do_post_request(array('ip' => 1), true);
}
else
{
    $output = 'Tapatalk Push Notification Status Monitor<br><br>';
    $output .= 'Push notification test: <b>';
    $options = XenForo_Application::get('options');

    if(isset($options->tp_push_key) && !empty($options->tp_push_key))
    {
        $push_key = $options->tp_push_key;
        $return_status = do_post_request(array('test' => 1, 'key' => $push_key), true);
        if ($return_status === '1')
            $output .= 'Success</b>';
        else
            $output .= 'Failed</b><br />'.$return_status;
    }
    else
    {
        $output .= 'Failed</b><br /> Please set Tapatalk API Key at forum option/setting<br />';
    }
    $ip =  do_post_request(array('ip' => 1), true);
    
    $bridge = Tapatalk_Bridge::getInstance();
    $bridge->setAction('');
    $bridge->init();
    $table_exist = false;
    try{
        $tapatalk_user_model = $bridge->getModelFromCache('Tapatalk_Model_TapatalkUser');
        $table_exist = true;
    }catch(Exception $e)
    {
        $table_exist = false;
    }
    
    $output .="<br>Current forum url: ".FORUM_ROOT."<br>";
    $output .="Current server IP: ".$ip."<br>";
    $output .="Tapatalk user table existence:".($table_exist ? "Yes" : "No")."<br>";

    if(isset($options->push_slug))
    {
        $push_slug = unserialize($options->push_slug);
        if(!empty($push_slug) && is_array($push_slug))
            $output .= 'Push Slug Status : ' . ($push_slug[5] == 1 ? 'Stick' : 'Free') . '<br />';
        if(isset($_GET['slug']))
            $output .= 'Push Slug Value: ' . print_r(unserialize($options->push_slug), true) . "<br /><br />";
    }
    $output .="<br>
<a href=\"http://tapatalk.com/api.php\" target=\"_blank\">Tapatalk API for Universal Forum Access</a> | <a href=\"http://tapatalk.com/build.php\" target=\"_blank\">Build Your Own</a><br>
For more details, please visit <a href=\"http://tapatalk.com\" target=\"_blank\">http://tapatalk.com</a>";
    echo $output;
}

function do_post_request($data, $pushTest = false)
{
    $push_url = 'http://push.tapatalk.com/push.php';
    $res =  getContentFromRemoteServer($push_url, $pushTest ? 10 : 0, $error,'POST',$data);
    return $res;
}
