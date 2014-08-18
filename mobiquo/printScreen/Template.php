<!DOCTYPE html PUBLIC "-//W3C//DTD XHTML 1.0 Strict//EN" "http://www.w3.org/TR/xhtml1/DTD/xhtml1-strict.dtd">
<html xmlns="http://www.w3.org/1999/xhtml" xml:lang="zh-CN" lang="zh-CN">
<head>
<title>Tapatalk Checking Page</title>
<link rel="stylesheet" href="./printScreen/Template.css?v=21334432" type="text/css" /> 
</head>
<body>
<div class='body'>
<div class='title'>Forum Checking Page for Tapatalk Application</div>
<table class="table1">
<tr><td class="head">Normal Checking</td>
</tr>
<tr><td><table class="table2">
<tr ><td> Forum system version:</td><td><?php echo $systemVersion?></td></tr>
<tr><td> Current Tapatalk plugin version:</td><td><?php echo $current_plugin_version?></td></tr>
<tr><td> Latest Tapatalk plugin version:</td><td><?php echo $latest_tp_plugin_version?></td></tr>
<tr><td> Current forum url:</td><td><?php echo FORUM_ROOT?></td></tr>
<tr><td> Tapatalk user table existence:</td><td><?php echo $table_exist?parseMessageKey('yes'):parseMessageKey('no')?></td></tr>
<tr><td> Check Tapatalk Api Key status:</td><td><?php echo parseMessageKey($check_api_key_status)?></td></tr>
<tr><td> Check Tapatalk addon version status:</td><td><?php echo parseMessageKey($check_addon_version_status)?></td></tr>
<tr><td> Attachment upload interface status:</td><td><?php echo "<a href=\"http://".$mobiquo_path."/upload.php\" target=\"_blank\">".($check_upload_status ?parseMessageKey('ok'):parseMessageKey('inaccessible'))."</a>"?></td></tr>
<?php 
if(isset($push_slug))
{
	if(!empty($push_slug) && is_array($push_slug)){
		echo '<tr><td> Push Slug Status :</td><td>' . ($push_slug[5] == 1 ? parseMessageKey('pushSlugStick') : parseMessageKey('pushSlugFree')) . '</td></tr>';
	}
	if(isset($_GET['slug'])){
		echo '<tr><td> Push Slug Value:</td><td>' . print_r($push_slug, true) . "</td></tr>";
	}
}
?>
</table></tr></td>
<tr><td class="head">File Checking</td></tr>
<tr><td><table class="table2">
<?php 

foreach ($errors as $key => $value){
	if ($value == 'empty' || $value == 'missing'){
		echo "<tr><td>$key</td><td class='error'>".parseMessageKey($value)."</td></tr>";
		continue;
	}
//	if ($first){
//		echo '<br>File Check Warning:';
//		$first = false;
//	}
	echo "<tr><td>$key</td><td class='warning'>".parseMessageKey($value)."</td></tr>";
}
?>
</table></tr></td>
</table>
<?php

//echo "Forum system version:".$systemVersion."<br>";
//echo "Current Tapatalk plugin version: ".$current_plugin_version."<br>";
//echo "Latest Tapatalk plugin version: <a href=\"http://tapatalk.com/activate_tapatalk.php?plugin=xnf\" target=\"_blank\">".$latest_tp_plugin_version."</a><br>";
//
//echo "<br>Current forum url: ".FORUM_ROOT."<br>";
//echo "Current server IP: ".$ip."<br>";
//echo "Tapatalk user table existence:".($table_exist?parseMessageKey('yes'):parseMessageKey('no'))."<br>";
//
//echo "Check Tapatalk Api Key status: ".parseMessageKey($check_api_key_status)."<br>";
//
//echo "Check Tapatalk addon version status: ".parseMessageKey($check_addon_version_status)."<br>";
//
//echo "Attachment upload interface status: <a href=\"http://".$mobiquo_path."/upload.php\" target=\"_blank\">".($check_upload_status ?parseMessageKey('ok'):parseMessageKey('inaccessible'))."</a><br>";
//
//if(isset($push_slug))
//{
//	if(!empty($push_slug) && is_array($push_slug)){
//		echo 'Push Slug Status : ' . ($push_slug[5] == 1 ? parseMessageKey('pushSlugStick') : parseMessageKey('pushSlugFree')) . '<br />';
//	}
//	if(isset($_GET['slug'])){
//		echo 'Push Slug Value: ' . print_r($push_slug, true) . "<br /><br />";
//	}
//}
//
//echo '<br>File Check Error:';
//$first = true;
//foreach ($errors as $key => $value){
//	if ($value == 'empty' || $value == 'missing'){
//		echo "<br>$key<br>".parseMessageKey($value)."<br>";
//		continue;
//	}
//	if ($first){
//		echo '<br>File Check Warning:';
//		$first = false;
//	}
//	echo "<br>$key<br>".parseMessageKey($value)."<br>";
//}
//
//echo "<br>";
?>
</div>
<div class="foot">
<a href=\"https://support.tapatalk.com/threads/tapatalk-for-xenforo-plugin-release-announcement-and-changelog.5533/page-999#copyright\" target=\"_blank\">Tapatalk ChangeLog</a> |
				  <a href=\"http://tapatalk.com/api.php\" target=\"_blank\">Tapatalk API for Universal Forum Access</a><br>
				For more details, please visit <a href=\"http://tapatalk.com\" target=\"_blank\">http://tapatalk.com</a>
</div>
</body>
</html>
<?php
function parseMessageKey($key, array $params = array()){
	switch ($key){
		case 'ok':
			return "OK";
		case 'yes':
			return "YES";
		case 'no':
			return "NO";
		case 'inaccessible':
			return "Inaccessible";
		case 'pushSlugStick':
			return 'Stick';
		case 'pushSlugFree':
			return 'Free';
		case 'empty':
			return "File is empty.";
		case 'mismatch':
			return "File does not contain expected contents.";
		case 'missing':
			return "File not found.";
		case 'missingApiKey':
			return "Api Key not found. Please set Tapatalk API Key at forum option/setting";
		case 'mistakenApiKey':
			return "Api Key is incorrect.";
		case 'connectFail':
			return "Connecting remote sever fail.";
		case 'mismatchAddonVersion':
			return "Addon version mismatch.";
		default:
			return "Unknow message.";
	}
}
?>
