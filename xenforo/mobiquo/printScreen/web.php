<?php
if (function_exists('set_magic_quotes_runtime'))
@set_magic_quotes_runtime(0);
ini_set('max_execution_time', '120');
error_reporting(0);
//define('CWD1', (($getcwd = getcwd()) ? $getcwd : '.'));

if (!defined('SCRIPT_ROOT')){
	define('SCRIPT_ROOT', empty($_SERVER['SCRIPT_FILENAME']) ? '../../' : dirname(dirname($_SERVER['SCRIPT_FILENAME'])).'/');
}

require_once 'Tools.php';


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

$mobiquo_config = Tools::get_mobiquo_config();
$current_plugin_version = $mobiquo_config['version'];
Tools::print_screen($current_plugin_version);
exit;




