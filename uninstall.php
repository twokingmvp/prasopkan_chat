<?php
if(!defined('IN_DISCUZ') || !defined('IN_ADMINCP')) {
	exit('Access Denied');
}

$sql = <<<EOF
DROP TABLE IF EXISTS `pre_prasopkan_chat_messages`;
DROP TABLE IF EXISTS `pre_prasopkan_chat_envelopes`;
DROP TABLE IF EXISTS `pre_prasopkan_chat_envlogs`;
DROP TABLE IF EXISTS `pre_prasopkan_chat_reactions`;
EOF;

runquery($sql);
$finish = TRUE;
?>