<?php
	
defined('IN_MOBIQUO') or exit;

require_once "include/reply_post.php";

function reply_topic_func($xmlrpc_params)
{	
	return reply_post_func($xmlrpc_params);	
}