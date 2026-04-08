<?php
require './source/class/class_core.php';
$discuz = C::app();
$discuz->init();

// 1. ตารางเก็บข้อมูลซองอั่งเปา
$sql1 = <<<EOF
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
EOF;
DB::query($sql1);

// 2. ตารางเก็บประวัติคนกดรับอั่งเปา
$sql2 = <<<EOF
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
EOF;
DB::query($sql2);

echo "<h2 style='color:green;'>อัปเดตฐานข้อมูลสำหรับระบบขั้นสูงสำเร็จ! 🎉</h2>";
echo "สร้างตาราง pre_prasopkan_chat_envelopes และ pre_prasopkan_chat_envlogs เรียบร้อยแล้ว<br><br>";
echo "<a href='index.php'>คลิกที่นี่เพื่อกลับไปหน้าแรก</a>";
?>