<?php

class Tapatalk_Model_Search extends XFCP_Tapatalk_Model_Search
{
    public function getViewableSearchResultData(array $resultsGrouped, array $handlers, $prepareData = true, array $viewingUser = null)
    {
        $this->standardizeViewingUserReference($viewingUser);

        $dataGrouped = array();
        
        foreach ($handlers AS $contentType => $handler)
        {
            if (!isset($resultsGrouped[$contentType]))
            {
                continue;
            }

            $dataResults = $handler->getDataForResults($resultsGrouped[$contentType], $viewingUser, $resultsGrouped);
            
            // add by tapatalk
            if (defined('IN_MOBIQUO'))
            {
                $options = XenForo_Application::get('options');
                $hideForums = $options->hideForums;
                foreach ($dataResults as $dataId => $data)
                {
                    if (in_array($data['node_id'], $hideForums))
                        unset($dataResults[$dataId]);
                }
            }
            
            foreach ($dataResults AS $dataId => $data)
            {
                if (!$handler->canViewResult($data, $viewingUser))
                {
                    unset($dataResults[$dataId]);
                    continue;
                }

                if ($prepareData)
                {
                    $dataResults[$dataId] = $handler->prepareResult($data, $viewingUser);
                }
            }

            $dataGrouped[$contentType] = $dataResults;
        }

        return $dataGrouped;
    }
}