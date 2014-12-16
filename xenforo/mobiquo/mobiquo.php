<?php

$startTime = microtime(true);

define('IN_MOBIQUO', true);

if (isset($_GET['welcome']))
{
    include('./smartbanner/app.php');
    exit;
}

define('MOBIQUO_DEBUG', false);
define('SCRIPT_ROOT', (!isset($_SERVER['SCRIPT_FILENAME']) || empty($_SERVER['SCRIPT_FILENAME'])) ? '../' : dirname(dirname($_SERVER['SCRIPT_FILENAME'])).'/');

if (DIRECTORY_SEPARATOR == '/')
    define('FORUM_ROOT', 'http://'.$_SERVER['HTTP_HOST'].dirname(dirname($_SERVER['SCRIPT_NAME'])).'/');
else
    define('FORUM_ROOT', 'http://'.$_SERVER['HTTP_HOST'].str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))).'/');

if($_REQUEST['method_name'] == 'set_api_key')
{
    if (!isset($_REQUEST['code']) || !isset($_REQUEST['key'])){
        get_error('Parameter Error');
    }
    $_POST['method_name'] = 'set_api_key';
}

if ($_POST['method_name'] == 'sync_user'){
    if (!isset($_POST['code']) || !isset($_POST['format'])){
        get_error('Parameter Error');
    }
}

if($_GET['method_name'] != 'set_api_key' && $_SERVER['REQUEST_METHOD'] == 'GET')
{
    include 'web.php';
}

require_once './config/config.php';
require_once './lib/xmlrpc.inc';
require_once './lib/xmlrpcs.inc';
require_once './lib/classConnection.php';
require_once './lib/classTTSSO.php';
require_once './lib/TTForum.php';

require_once './server_define.php';
require_once './mobiquo_common.php';

require_once SCRIPT_ROOT.'library/XenForo/Autoloader.php';
XenForo_Autoloader::getInstance()->setupAutoloader(SCRIPT_ROOT.'library');

XenForo_Application::initialize(SCRIPT_ROOT.'library', SCRIPT_ROOT);
XenForo_Application::set('page_start_time', $startTime);

@ob_start();

// Make sure deprecated warnings go back off due to XF override
$errorReporting = ini_get('error_reporting') &~ 8096;
@error_reporting($errorReporting);
@ini_set('error_reporting', $errorReporting);
// Hide errors from normal display - will be cleanly output via shutdown function.
// (No need to turn off errors when not debugging like in normal Tapatalk plugins - all are passed through cleanly via XMLRPC result_text.)
@ini_set('display_errors', 0);

// Revert XenForo's error handler also
restore_error_handler();

function shutdown(){
    $error = error_get_last();

    if(!empty($error)){
        switch($error['type']){
            case E_ERROR:
            case E_CORE_ERROR:
            case E_COMPILE_ERROR:
            case E_USER_ERROR:
            case E_PARSE:
                echo "<?xml version=\"1.0\" encoding=\"UTF-8\"?>\n".(xmlresperror("Server error occurred: '{$error['message']} (".basename($error['file']).":{$error['line']})'")->serialize('UTF-8'));
                break;
        }
    }
}
register_shutdown_function('shutdown');

$mobiquo_config = get_mobiquo_config();

$request_method_name = get_method_name();

try
{
    $bridge = Tapatalk_Bridge::getInstance();
    $bridge->setAction($request_method_name);
    $bridge->setUserParams('useragent', $_SERVER['HTTP_USER_AGENT']);
    $bridge->init();
}
catch (XenForo_ControllerResponse_Exception $e)
{
    $controllerResponse = $e->getControllerResponse();

	if ($controllerResponse instanceof XenForo_ControllerResponse_Reroute)
	{
		$errorPhrase = $bridge->responseErrorMessage($controllerResponse);
		if(isset($errorPhrase->errorText)||!empty($errorPhrase->errorText)){
			if ($errorPhrase -> errorText instanceof XenForo_Phrase){
				get_error($errorPhrase->errorText->render());
			}else if (!is_array($errorPhrase->errorText)){
				get_error($errorPhrase->errorText);
			}else{
				get_error('Unknow error');
			}
		}else {
			get_error('Unknow error');
		}
	}
	else if($controllerResponse instanceof XenForo_ControllerResponse_Error)
	{
		get_error(new XenForo_Phrase($controllerResponse->errorText));
	}
	else
	{
		get_error('Unknow error');
	}
}


$visitor = XenForo_Visitor::getInstance();
$user_id = $visitor->getUserId();
date_default_timezone_set($visitor->timezone);


if ($request_method_name && isset($server_param[$request_method_name]))
{
    header('Mobiquo_is_login: ' . ($user_id >= 1 ? 'true' : 'false'));
    if (strpos($request_method_name, 'm_') === 0)
        require('./include/moderation.php');
    else
        if(file_exists('./include/'.$request_method_name.'.php'))
            include('./include/'.$request_method_name.'.php');
}

$rpcServer = new Tapatalk_xmlrpcs($server_param, false);
$rpcServer->setDebug(MOBIQUO_DEBUG ? 3 : 1);
$rpcServer->compress_response = 'true';
$rpcServer->response_charset_encoding = 'UTF-8';

if(isset($_POST['method_name']) && !empty($_POST['method_name'])){
    $xml = new xmlrpcmsg($_POST['method_name']);
    $request = $xml->serialize();
    $response = $rpcServer->service($request);
} else {
    $response = $rpcServer->service();
}

exit;