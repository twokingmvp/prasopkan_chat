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

DROP TABLE IF EXISTS `pre_prasopkan_chat_typing`;
CREATE TABLE IF NOT EXISTS `pre_prasopkan_chat_typing` (
  `room_id` int(10) unsigned NOT NULL DEFAULT '0',
  `uid` mediumint(8) unsigned NOT NULL DEFAULT '0',
  `username` varchar(15) NOT NULL DEFAULT '',
  `dateline` int(10) unsigned NOT NULL DEFAULT '0',
  PRIMARY KEY (`room_id`, `uid`)
) ENGINE=MyISAM;

DROP TABLE IF EXISTS `pre_prasopkan_chat_inventory`;
CREATE TABLE IF NOT EXISTS `pre_prasopkan_chat_inventory` (
  `uid` mediumint(8) unsigned NOT NULL,
  `item_key` varchar(50) NOT NULL,
  `dateline` int(10) unsigned NOT NULL,
  PRIMARY KEY (`uid`, `item_key`)
) ENGINE=MyISAM;

DROP TABLE IF EXISTS `pre_prasopkan_chat_equipment`;
CREATE TABLE IF NOT EXISTS `pre_prasopkan_chat_equipment` (
  `uid` mediumint(8) unsigned NOT NULL,
  `name_style` varchar(50) NOT NULL DEFAULT '',
  `badge` varchar(50) NOT NULL DEFAULT '',
  `bubble_skin` varchar(50) NOT NULL DEFAULT '',
  PRIMARY KEY (`uid`)
) ENGINE=MyISAM;

DROP TABLE IF EXISTS `pre_prasopkan_chat_shop_items`;
CREATE TABLE IF NOT EXISTS `pre_prasopkan_chat_shop_items` (
  `item_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
  `item_type` varchar(20) NOT NULL,
  `item_key` varchar(50) NOT NULL,
  `name` varchar(100) NOT NULL,
  `price` int(10) unsigned NOT NULL DEFAULT '0',
  `css` text NOT NULL,
  `icon` varchar(50) NOT NULL DEFAULT '',
  `displayorder` smallint(6) NOT NULL DEFAULT '0',
  PRIMARY KEY (`item_id`),
  UNIQUE KEY `item_key` (`item_key`)
) ENGINE=MyISAM;

INSERT INTO `pre_prasopkan_chat_shop_items` (`item_type`, `item_key`, `name`, `price`, `css`, `icon`, `displayorder`) VALUES
('name_style', 'ns_gold', 'สีทองพรีเมียม', 500, 'background: linear-gradient(90deg, #d4af37, #ffdf00); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-weight: bold;', '', 1),
('name_style', 'ns_fire', 'เปลวไฟเดือด', 800, 'color: #ff4500; text-shadow: 0 0 5px #ff8c00; font-weight: bold;', '', 2),
('name_style', 'ns_rainbow', 'สีรุ้งนีออน', 1500, 'background: linear-gradient(90deg, #ff0000, #ff7f00, #ffff00, #00ff00, #0000ff, #4b0082, #9400d3); -webkit-background-clip: text; -webkit-text-fill-color: transparent; font-weight: bold; animation: pk-hue 3s infinite linear;', '', 3),
('name_style', 'ns_lightning', 'เทพสายฟ้า ⚡', 2500, 'background: linear-gradient(90deg, #00f2fe 0%, #4facfe 100%); -webkit-background-clip: text; -webkit-text-fill-color: transparent; text-shadow: 0px 0px 8px rgba(0,242,254,0.8); font-weight: bold; animation: pk-flash 1.5s infinite;', '', 4),
('name_style', 'ns_matrix', 'เดอะเมทริกซ์ 💻', 1000, 'color: #0f0; background: #000; padding: 0 5px; border-radius: 3px; font-family: monospace; letter-spacing: 1px;', '', 5),
('badge', 'bd_cat', 'ทาสแมว', 300, '', '🐱', 6),
('badge', 'bd_dog', 'ทาสหมา', 300, '', '🐶', 7),
('badge', 'bd_vip', 'มงกุฎ VIP', 2000, '', '👑', 8),
('badge', 'bd_rich', 'มหาเศรษฐี', 5000, '', '💎', 9),
('badge', 'bd_star', 'ซุปตาร์', 3000, '', '🌟', 10),
('badge', 'bd_devil', 'ยมทูต', 1500, '', '😈', 11),
('bubble_skin', 'bb_pink', 'พาสเทลชมพู', 600, 'background: #ffe4e1; border: 1px solid #ffb6c1; color: #d81b60;', '', 12),
('bubble_skin', 'bb_dark', 'ดาร์กโหมด 🌙', 600, 'background: #222; border: 1px solid #444; color: #ddd;', '', 13),
('bubble_skin', 'bb_neon', 'นีออนไซเบอร์ ⚡', 2500, 'background: #0d0d1a; border: 1px solid #00f2fe; color: #00f2fe; box-shadow: 0 0 10px rgba(0,242,254,0.5);', '', 14),
('bubble_skin', 'bb_gold', 'กรอบทองคำ 🏆', 3000, 'background: linear-gradient(135deg, #fff9c4, #ffeb3b); border: 1px solid #fbc02d; color: #b71c1c; font-weight: 500;', '', 15),
('gacha', 'gc_all', 'กล่องสุ่มมหาเทพ (สุ่มทุกอย่าง)', 1500, 'all', '🎁', 16),
('gacha', 'gc_name', 'กล่องสุ่มสีชื่อ (ลุ้นแรร์)', 800, 'name_style', '🎨', 17),
('gacha', 'gc_badge', 'กล่องสุ่มป้ายฉายา', 500, 'badge', '📛', 18),
('gacha', 'gc_bubble', 'กล่องสุ่มกรอบแชท', 1200, 'bubble_skin', '💬', 19);

EOF;

runquery($sql);
$finish = TRUE;
?>