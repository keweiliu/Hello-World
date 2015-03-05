<?php

defined('IN_MOBIQUO') or exit;

class TTConfig{
    public static function get_config(){
        return array(
            'version' => 'xf10_1.1.2',
            'api_level' => 3,

            'mark_forum' => 1,
            'mark_read' => 1,
            'goto_unread' => 1,
            'goto_post' => 1,
            'get_latest_topic' => 1,
            'subscribe_forum' => 0,
            'mod_approve' => 1,
            'mod_delete' => 0,
            'mod_report' => 1,
            'disable_bbcode' => 0,
            'user_id' => 1,
            'no_refresh_on_post' => 1,
            'report_post' => 1,
            'report_pm' => 0,
            'delete_reason' => 1,
            'announcement' => 1,
            'subscribe_load' => 1,
            'multi_quote' => 1,
            'conversation' => 1,
            'upload_avatar' => 1,
            'inbox_stat' => 1,
            'advanced_search' => 1,
            'advanced_online_users' => 1,
            'push_type' => 'quote,sub,conv,like,tag',
            'guest_whosonline' => 1,
            'alert' => 1,
            'get_forum' => 1,
            'prefix_edit' => 1,
            'ban_delete_type' => 'soft_delete',
            'avatar' => 1,
            'user_recommended' => 1,
            'search_user' => 1,
            'mark_pm_unread' => 1,
            'ignore_user' => 1,
            'm_approve' => 1,
            'm_report' => 1,
            'inappsignin' => 1,
            'sign_in' => 1,
            'inappreg' => 1,
            'sso_login' => 1,
            'sso_signin' => 1,
            'sso_register' => 1,
            'native_register' => 1,
            'emoji_support' => 1,
            'unban' => 1,
            'close_report' => 1,
            'advanced_edit' => 1,
            'advanced_merge' => 1,
            'mark_pm_read' => 1,
            'get_contact' => 1,
            'advanced_register' => 1,
            'ban_expires' => 1,
            'sso_activate' => 1,
            'get_topic_participants' => 1,
        );
    }
}