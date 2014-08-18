<?php

error_reporting(E_ALL & ~E_NOTICE);
define('IN_MOBIQUO', 1);

define('SCRIPT_ROOT', empty($_SERVER['SCRIPT_FILENAME']) ? '../' : dirname(dirname($_SERVER['SCRIPT_FILENAME'])).'/');
if (DIRECTORY_SEPARATOR == '/')
    define('FORUM_ROOT', 'http://'.$_SERVER['HTTP_HOST'].dirname(dirname($_SERVER['SCRIPT_NAME'])).'/');
else
    define('FORUM_ROOT', 'http://'.$_SERVER['HTTP_HOST'].str_replace('\\', '/', dirname(dirname($_SERVER['SCRIPT_NAME']))).'/');
require_once './lib/xmlrpc.inc';
require_once './lib/xmlrpcs.inc';
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

try
{
	$bridge = Tapatalk_Bridge::getInstance();
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

XenForo_Application::autoload('Xenforo_Model_User');
$userModel = $bridge->getUserModel();
if (isset($_GET['user_id']))
{
    $uid = intval($_GET['user_id']);
    $user = $userModel->getUserById($uid);
}
elseif (isset($_GET['username']))
{
    $user = $userModel->getUserByName(base64_decode($_GET['username']));
}
else
{
    exit;
}

$url = get_avatar($user, "l");
if(!empty($url))
    header("Location: $url", 0, 303);