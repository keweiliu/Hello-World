<?php

class Tapatalk_Option_ReadonlyForums
{
    public static function renderOption(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
    {
        $edit_link = $view->createTemplateObject('option_list_option_editlink', array(
            'preparedOption' => $preparedOption,
            'canEditOptionDefinition' => $canEdit
        ));
        $nodeModel = XenForo_Model::create('XenForo_Model_Node');
        $nodeHighrrr = $nodeModel->getNodeHierarchy();
        $option_values = $nodeModel->getAllNodes(true);
        foreach($option_values as $idx => $nodedetail)
            $option_values[$idx] = array($idx);
        $option_values = Tapatalk_Option_ReadonlyForums::generateValue($option_values, $nodeHighrrr);
        $forumOptions = Tapatalk_Option_ReadonlyForums::getNodeOptionsArray($nodeModel->getAllNodes(), $option_values, $preparedOption['option_value']);
        
        return $view->createTemplateObject('tapatalk_option_multi_forum_select', array(
            'fieldPrefix' => $fieldPrefix,
            'listedFieldName' => $fieldPrefix . '_listed[]',
            'preparedOption' => $preparedOption,
            'formatParams' => $forumOptions,
            'editLink' => $edit_link
        ));
    }
    
    public static function getNodeOptionsArray(array $nodes, $option_values,  $selectedNodeId = array(0))
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
            $option_value = is_array($option_values[$nodeId])? implode(',', $option_values[$nodeId]) : $option_values[$nodeId];
            $options[$nodeId] = array(
                'value' => $option_value,
                'label' => $node['title'],
                'selected' => in_array($nodeId, $selectedNodeId),
                'depth' => $node['depth']
            );
        }
        return $options;
    }
    
    public static function generateValue($option_values, $nodeHighrrr)
    {
        foreach($nodeHighrrr as $index => $nodes)
        {
            if($index == 0)
                continue;
            if(isset($option_values[$index]))
            {
                if(!empty($nodes))
                {
                    $higher_ids = array();
                    foreach($option_values as $option => $values)
                    {
                        if(!is_numeric($option))
                            continue;
                        if(is_array($values))
                        {
                            if(in_array($index, $values))
                                $higher_ids= array_unique(array_merge($higher_ids,$values));
                        }
                        else if(strval($values) == strval($index))
                        {
                            $higher_ids[] = $values;
                        }
                    }
                    foreach($nodes as $idx => $node)
                    {
                        if(!in_array($idx,$option_values[$index]))
                            $option_values[$index][] = $idx;
                        foreach($higher_ids as $id)
                        {
                            if(!in_array($idx,$option_values[$id]))
                                $option_values[$id][] = $idx;//add this node id to all its' father or father's father node id
                        }
                    }
                }
            }
        }
        return $option_values;
    }
}