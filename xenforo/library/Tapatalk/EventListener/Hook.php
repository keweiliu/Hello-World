<?php

class Tapatalk_EventListener_Hook
{
    public static function templateHook ($hookName, &$contents, $hookParams, XenForo_Template_Abstract $template)
    {
        if ($hookName == 'page_container_head')
        {
            $app_kindle_url = XenForo_Application::get('options')->tp_kf_url;
            $app_android_id = XenForo_Application::get('options')->tp_android_url;
            $app_ios_id = XenForo_Application::get('options')->tp_app_ios_id;
            $app_banner_message = XenForo_Application::get('options')->tp_app_banner_msg;
            $app_banner_message = preg_replace('/\r\n/','<br>',$app_banner_message);
            $app_location_url = Tapatalk_EventListener_Hook::get_scheme_url($page_type);
            $is_mobile_skin = false;
            $app_forum_name = XenForo_Application::get('options')->boardTitle;
            $board_url = XenForo_Application::get('options')->boardUrl;
            
            $tapatalk_dir = XenForo_Application::get('options')->tp_directory;  // default as 'mobiquo'
            $tapatalk_dir_url = $board_url.'/'.$tapatalk_dir;
            $api_key = XenForo_Application::get('options')->tp_push_key;
            $app_ads_enable = XenForo_Application::get('options')->full_ads;
            $app_banner_enable = XenForo_Application::get('options')->full_banner;
            $tapatalk_dir_name = XenForo_Application::get('options')->tp_directory;
            if (empty($tapatalk_dir_name)) $tapatalk_dir_name = 'mobiquo';
            $forum_root = dirname(dirname(dirname(dirname(__FILE__))));
            
            if (!function_exists('tt_getenv')){
               include($forum_root.'/'.$tapatalk_dir_name.'/smartbanner/head.inc.php');
            }
            if(isset($app_head_include))
                $contents .= $app_head_include;
        }
        else if($hookName == 'body')
        {
            $contents = '
<!-- Tapatalk Detect body start -->
<style type="text/css">
.ui-mobile [data-role="page"], .ui-mobile [data-role="dialog"], .ui-page 
{
top:auto;
}
</style>
<script type="text/javascript">if(typeof(tapatalkDetect) == "function"){tapatalkDetect()}</script>
<!-- Tapatalk Detect banner body end -->
                '.$contents;
        }
    }
    
    public static function get_scheme_url(&$location)
    {
        $baseUrl = XenForo_Application::get('options')->boardUrl.'?';
        $baseUrl = preg_replace('/https?:\/\//', 'tapatalk://', $baseUrl);
        $visitor = XenForo_Visitor::getInstance();
        $options = XenForo_Application::get('options');
        if($visitor['user_id'] != 0)
            $baseUrl .= 'user_id='.$visitor['user_id'].'&';

        $router = new XenForo_Router();
        $path = $router->getRoutePath(new Zend_Controller_Request_Http());

        $location = 'index';
        $split_rs = preg_split('/\//', $path);
        if(!empty($split_rs) && is_array($split_rs))
        {
            $action = isset($split_rs[0]) && !empty($split_rs[0])?  $split_rs[0] : '';
            $title = isset($split_rs[1]) && !empty($split_rs[1])?  $split_rs[1] : '';
            $other = isset($split_rs[2]) && !empty($split_rs[2])?  $split_rs[2] : '';
            if(!empty($action))
            {

                switch($action)
                {
                    case 'threads':
                        $location = 'topic';
                        $id_name = 'tid';
                        $perPage = $options->messagesPerPage;
                        break;
                    case 'forums':
                        $location = 'forum';
                        $id_name = 'fid';
                        $perPage = $options->discussionsPerPage;
                        break;
                    case 'members':
                        $location = 'profile';
                        $id_name = 'uid';
                        $perPage = $options->membersPerPage;
                        break;
                    case 'conversations':
                        $location = 'message';
                        $id_name = 'mid';
                        $perPage = $options->discussionsPerPage;
                    case 'online':
                        $location = 'online';
                        $perPage = $options->membersPerPage;
                    case 'search':
                        $location = 'search';
                        $perPage = $options->searchResultsPerPage;
                    case 'login':
                        $location = 'login';
                    default:
                        break;
                }

                if(preg_match('/(page=|page-)(\d+)/', $other, $match)){
                    $page = $match[2];
                }else{
                    $page = 1;
                }

                $other_info = '';
                if(!empty($title) && $location != 'index')
                {
                    if(preg_match('/\./',$title,$match))
                    {
                        $departs = preg_split('/\./', $title);
                        if(isset($departs[1]) && !empty($departs[1]))
                        {
                            $other_info .= $id_name.'='.intval($departs[1]);
                        }
                    }
                }
                if (!empty($page)){
                    if(!empty($other_info)){
                        $other_info .= '&';
                    }
                    $other_info .= 'page='.$page.'&perpage='.(intval($perPage) ? intval($perPage) : 20);
                }
            }
        }
        else
        {
            $location = 'index';
        }
        return $baseUrl.'location='.$location.(!empty($other_info) ? '&'.$other_info : '');
    }
}
