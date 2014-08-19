<?php

class Tapatalk_Option_adsDisabledForGroup
{
    public static function renderOption(XenForo_View $view, $fieldPrefix, array $preparedOption, $canEdit)
    {
        $edit_link = $view->createTemplateObject('option_list_option_editlink', array(
            'preparedOption' => $preparedOption,
            'canEditOptionDefinition' => $canEdit
        ));
        
        $GroupModel = XenForo_Model::create('XenForo_Model_UserGroup');
        $forumOptions = Tapatalk_Option_adsDisabledForGroup::getGroupOptionsArray($GroupModel->getAllUserGroupTitles(), $preparedOption['option_value']);
        
        return $view->createTemplateObject('tapatalk_option_multi_group_select', array(
            'fieldPrefix' => $fieldPrefix,
            'listedFieldName' => $fieldPrefix . '_listed[]',
            'preparedOption' => $preparedOption,
            'formatParams' => $forumOptions,
            'editLink' => $edit_link
        ));
    }
    
    public static function getGroupOptionsArray(array $Groups, $GroupTitle = array(0))
    {
        $options = array();
        $options[0] = array(
            'value' => 0,
            'label' => 'Show All',
            'selected' => in_array(0, $GroupTitle) && count($GroupTitle) == 1,
        );
        foreach ($Groups AS $GroupId => $Title)
        {
            $options[$GroupId] = array(
                'value' => $GroupId,
                'label' => $Title,
                'selected' => in_array($GroupId, $GroupTitle),
            );
        }
    
        return $options;
    }
}