<?php
if (function_exists('set_magic_quotes_runtime'))
@set_magic_quotes_runtime(0);
ini_set('max_execution_time', '120');
error_reporting(0);

define('CWD1', (($getcwd = getcwd()) ? $getcwd : '.'));

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

$mobiquo_config = get_mobiquo_config();
$current_plugin_version = $mobiquo_config['version'];
print_screen($current_plugin_version);
exit;



function print_screen($current_plugin_version)
{
	$latest_tp_plugin_version = 'xf10_'.get_latest_plugin_version();
	$mobiquo_path = get_path();
	$check_upload_status = file_get_contents("http://".$mobiquo_path."/upload.php?checkAccess");
	$check_push_status = file_get_contents("http://".$mobiquo_path."/push.php?checkAccess");
    
	echo "Forum XMLRPC Interface for Tapatalk Application<br><br>";
	echo "Forum system version:".XenForo_Application::$version."<br>";
	echo "Current Tapatalk plugin version: ".$current_plugin_version."<br>";

	echo "Latest Tapatalk plugin version: <a href=\"http://tapatalk.com/activate_tapatalk.php?plugin=xnf\" target=\"_blank\">".$latest_tp_plugin_version."</a><br>";

	echo "Attachment upload interface status: <a href=\"http://".$mobiquo_path."/upload.php\" target=\"_blank\">".($check_upload_status ? 'OK' : 'Inaccessible')."</a><br>";

	echo "Push notification interface status: <a href=\"http://".$mobiquo_path."/push.php\" target=\"_blank\">".($check_push_status ? 'OK' : 'Inaccessible')."</a><br>";

	echo "<br>
<a href=\"http://tapatalk.com/api.php\" target=\"_blank\">Tapatalk API for Universal Forum Access</a><br>
For more details, please visit <a href=\"http://tapatalk.com\" target=\"_blank\">http://tapatalk.com</a>";
}



function get_latest_plugin_version()
{
	$tp_lst_pgurl = 'http://api.tapatalk.com/v.php?sys=xf10&link';

	$res =  getContentFromRemoteServer($tp_lst_pgurl, 10, $error);
	return $res;
}


function get_path()
{
	$path =  '../';

	if (isset($_SERVER['SCRIPT_NAME']) && !empty($_SERVER['SCRIPT_NAME']) && isset($_SERVER['HTTP_HOST']) && !empty($_SERVER['HTTP_HOST']))
	{
		$path = $_SERVER['HTTP_HOST'];
		$path .= dirname($_SERVER['SCRIPT_NAME']);
	}
	return $path;
}