<?php
abstract class AbstractFunctions{
	abstract function getLocalApiKey();
	abstract function getRemoteApiKey();
	abstract function getSystemVersion();
	abstract function getSystemAddonVersion();
	abstract function getLatestPluginVersion();
	abstract function checkTapatalkUserTable();
	abstract function getPushSlug();
}