<?php
if(!defined('IN_DISCUZ') || !defined('IN_ADMINCP')) {
	exit('Access Denied');
}

$sql = <<<EOF
DROP TABLE IF EXISTS `pre_prasopkan_chat_messages`;
DROP TABLE IF EXISTS `pre_prasopkan_chat_envelopes`;
DROP TABLE IF EXISTS `pre_prasopkan_chat_envlogs`;
DROP TABLE IF EXISTS `pre_prasopkan_chat_reactions`;
DROP TABLE IF EXISTS `pre_prasopkan_chat_typing`;
DROP TABLE IF EXISTS `pre_prasopkan_chat_inventory`;
DROP TABLE IF EXISTS `pre_prasopkan_chat_equipment`;
DROP TABLE IF EXISTS `pre_prasopkan_chat_shop_items`;
EOF;

runquery($sql);
$finish = TRUE;
?>