<?php
abstract class AbstractFunctions{
    const TP_DAFAULT_DIR_NAME = 'mobiquo';
    abstract function getLocalApiKey();
    abstract function getRemoteApiKey();
    abstract function getSystemVersion();
    abstract function getSystemAddonVersion();
    abstract function getLatestPluginVersion();
    abstract function checkTapatalkUserTable();
    abstract function getPushSlug();
    abstract function getTapatalkDirName();
}