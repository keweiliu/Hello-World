<?php

class Tapatalk_Option_ListForums
{
    public static function renderOption(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
    {
        $edit_link = $view->createTemplateObject('option_list_option_editlink', array(
            'preparedOption' => $preparedOption,
            'canEditOptionDefinition' => $canEdit
        ));
        
        $nodeModel = XenForo_Model::create('XenForo_Model_Node');
        $forumOptions = self::getNodeOptionsArray($nodeModel->getAllNodes(), $preparedOption['option_value']);
        
        return $view->createTemplateObject('tapatalk_option_multi_forum_select', array(
            'fieldPrefix' => $fieldPrefix,
            'listedFieldName' => $fieldPrefix . '_listed[]',
            'preparedOption' => $preparedOption,
            'formatParams' => $forumOptions,
            'editLink' => $edit_link
        ));
    }
    
    public static function getNodeOptionsArray(array $nodes, $selectedNodeId = array(0))
    {
        $options = array();
        $options[0] = array(
            'value' => 0,
            'label' => 'Show All',
            'selected' => in_array(0, $selectedNodeId) && count($selectedNodeId) == 1,
            'depth' => 0
        );
    
        foreach ($nodes AS $nodeId => $node)
        {
            $node['depth'] += ($nodeId ? 1 : 0);
    
            $options[$nodeId] = array(
                'value' => $nodeId,
                'label' => $node['title'],
                'selected' => in_array($nodeId, $selectedNodeId),
                'depth' => $node['depth']
            );
        }
    
        return $options;
    }
}