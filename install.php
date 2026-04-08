<?php
if(!defined('IN_DISCUZ') || !defined('IN_ADMINCP')) {
	exit('Access Denied');
}

$sql = <<<EOF
DROP TABLE IF EXISTS `pre_prasopkan_chat_messages`;
CREATE TABLE IF NOT EXISTS `pre_prasopkan_chat_messages` (
  `msg_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `uid` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `username` varchar(15) NOT NULL DEFAULT '',
  `message` text NOT NULL,
  `dateline` int(10) unsigned NOT NULL DEFAULT '0',
  `ip` varchar(45) NOT NULL DEFAULT '',
  `room_id` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`msg_id`),
  KEY `dateline` (`dateline`)
) ENGINE=MyISAM;

DROP TABLE IF EXISTS `pre_prasopkan_chat_envelopes`;
CREATE TABLE IF NOT EXISTS `pre_prasopkan_chat_envelopes` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `msg_id` int(10) unsigned NOT NULL DEFAULT '0',
  `uid` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `total_amount` int(10) unsigned NOT NULL DEFAULT '0',
  `total_count` smallint(6) unsigned NOT NULL DEFAULT '0',
  `remain_amount` int(10) unsigned NOT NULL DEFAULT '0',
  `remain_count` smallint(6) unsigned NOT NULL DEFAULT '0',
  `dateline` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`id`)
) ENGINE=MyISAM;

DROP TABLE IF EXISTS `pre_prasopkan_chat_envlogs`;
CREATE TABLE IF NOT EXISTS `pre_prasopkan_chat_envlogs` (
  `log_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `env_id` int(10) unsigned NOT NULL DEFAULT '0',
  `uid` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `username` varchar(15) NOT NULL DEFAULT '',
  `amount` int(10) unsigned NOT NULL DEFAULT '0',
  `dateline` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`log_id`),
  KEY `env_id` (`env_id`, `uid`)
) ENGINE=MyISAM;

DROP TABLE IF EXISTS `pre_prasopkan_chat_reactions`;
CREATE TABLE IF NOT EXISTS `pre_prasopkan_chat_reactions` (
  `id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `msg_id` int(10) unsigned NOT NULL DEFAULT '0',
  `uid` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `reaction` varchar(20) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  KEY `msg_id` (`msg_id`)
) ENGINE=MyISAM;
EOF;

runquery($sql);
$finish = TRUE;
?>