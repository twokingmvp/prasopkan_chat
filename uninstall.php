<?php
if(!defined('IN_DISCUZ') || !defined('IN_ADMINCP')) {
	exit('Access Denied');
}

// ลบตารางทั้งหมดของปลั๊กอินแชท
$sql = <<<EOF
DROP TABLE IF EXISTS `pre_prasopkan_chat_messages`;
DROP TABLE IF EXISTS `pre_prasopkan_chat_envelopes`;
DROP TABLE IF EXISTS `pre_prasopkan_chat_envlogs`;
EOF;

runquery($sql);

$finish = TRUE;
?>