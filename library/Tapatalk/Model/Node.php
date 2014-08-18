<?php

class Tapatalk_Model_Node extends XFCP_Tapatalk_Model_Node
{
    public function getNodePermissionsForPermissionCombination($permissionCombinationId = null)
    {
        $data = parent::getNodePermissionsForPermissionCombination($permissionCombinationId);

        // add by tapatalk
        if (defined('IN_MOBIQUO'))
        {
            $options = XenForo_Application::get('options');
            $hideForums = $options->hideForums;
            foreach ($hideForums as $fid)
            {
                $data[$fid]['view'] = '';
            }
        }

        return $data;
    }
}