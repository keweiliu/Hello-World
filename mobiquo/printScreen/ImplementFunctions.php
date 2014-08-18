<?php
require_once 'Tools.php';
require_once 'AbstractFunctions.php';
if (!defined('SCRIPT_ROOT')){
	define('SCRIPT_ROOT', empty($_SERVER['SCRIPT_FILENAME']) ? '../../' : dirname(dirname($_SERVER['SCRIPT_FILENAME'])).'/');
}

require_once SCRIPT_ROOT.'library/XenForo/Autoloader.php';
XenForo_Autoloader::getInstance()->setupAutoloader(SCRIPT_ROOT.'library');
XenForo_Application::initialize(SCRIPT_ROOT.'library', SCRIPT_ROOT);

class ImplementsFunctions extends AbstractFunctions{
	public function getLocalApiKey(){
		return XenForo_Application::get('options')->tp_push_key;
	}

	public function getRemoteApiKey(){
		$mobi_api_key="";
		$boardurl = XenForo_Application::get('options')->boardUrl;
		$boardurl = urlencode($boardurl);
		$response = Tools::getContentFromRemoteServer("http://directory.tapatalk.com/au_reg_verify.php?url=$boardurl", 10, $error);
		if($response)
		{
			$result = json_decode($response, true);
			if(isset($result) && isset($result['result']) && isset($result['api_key']))
			{
				$mobi_api_key = @$result['api_key'];
			}
		}
		return $mobi_api_key;
	}

	public function getSystemVersion(){
		return XenForo_Application::$version;
	}

	public function getSystemAddonVersion(){
		$addonModel = XenForo_Model::create('XenForo_Model_AddOn');
		$tapatalk = $addonModel -> getAddOnById('tapatalk');
		return "xf10_".$tapatalk['version_string'];
	}

	public function getLatestPluginVersion(){
		$tp_lst_pgurl = 'https://tapatalk.com/v.php?sys=xf10&link';

		$res =  Tools::getContentFromRemoteServer($tp_lst_pgurl, 10, $error);
		return 'xf10_'.$res;
	}

	public function checkTapatalkUserTable(){
		try{
			$tapatalk_user_model = XenForo_Model::create('Tapatalk_Model_TapatalkUser');
			return true;
		}catch(Exception $e)
		{
			return false;
		}
	}

	public function getPushSlug(){
		$options = XenForo_Application::get('options');
		return isset($options->push_slug)?unserialize($options->push_slug) : null;
	}
}