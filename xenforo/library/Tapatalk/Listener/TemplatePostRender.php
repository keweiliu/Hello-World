<?php

class Tapatalk_Listener_TemplatePostRender
{
    public static function template_post_render($templateName, &$output, array &$containerData, XenForo_Template_Abstract $template) {
        if($templateName == 'online_list')
        {
            $memberListItems = preg_split('/<li class="primaryContent memberListItem">/', $output);
            if(!empty($memberListItems))
            {
                foreach($memberListItems as $key => $memberItem)
                {
                if(preg_match('/\[On Tapatalk\]/', $memberItem))
                    {
                        $memberItem = preg_replace('/\[On Tapatalk\]/', '', $memberItem);
                        $memberItem = preg_replace('/<div class="extra">/', '<div class="extra">
            <img src="mobiquo/forum_icons/tapatalk-online.png">', $memberItem);
                    }
                    else if(preg_match('/\[On BYO\]/', $memberItem))
                    {
                        $memberItem = preg_replace('/\[On BYO\]/', '', $memberItem);
                        $memberItem = preg_replace('/<div class="extra">/', '<div class="extra"> 
            <img src="mobiquo/forum_icons/tapatalk-online.png">', $memberItem);
                    }
                    if($key == 0)
                        $output = $memberItem;
                    else
                        $output .= '<li class="primaryContent memberListItem">'.$memberItem;
                }
            }
        }
        
        if ($templateName == 'thread_view' || $templateName == 'conversation_view')
        {
            $output = preg_replace('/\[emoji(\d+)\]/i', '<img src="https://s3.amazonaws.com/tapatalk-emoji/emoji\1.png" />', $output);
        }
    }

}
