<?php
if(!defined('IN_DISCUZ') || !defined('IN_ADMINCP')) {
	exit('Access Denied');
}

// ลบตารางเมื่อถอนการติดตั้ง
$sql = <<<EOF
DROP TABLE IF EXISTS `pre_prasopkan_chat_messages`;
EOF;

runquery($sql);

$finish = TRUE;
?>