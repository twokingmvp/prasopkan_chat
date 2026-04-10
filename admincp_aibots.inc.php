<?php
if(!defined('IN_DISCUZ') || !defined('IN_ADMINCP')) { exit('Access Denied'); }

// รับค่าเมื่อแอดมินกดบันทึก
if(submitcheck('aibotssubmit')) {
    $config = array(
        'enable_ai_bots' => intval($_GET['enable_ai_bots']),
        'gemini_api_key' => trim($_GET['gemini_api_key']),
        'ai_daily_limit' => intval($_GET['ai_daily_limit']),
        'ai_chat_interval' => intval($_GET['ai_chat_interval']),
        'ai_bot_list' => trim($_GET['ai_bot_list']),
        'ai_chat_topic' => trim($_GET['ai_chat_topic']),
        'ai_allowed_forums' => isset($_GET['ai_allowed_forums']) ? $_GET['ai_allowed_forums'] : array()
    );
    // บันทึกลงฐานข้อมูลหลักของบอร์ด
    C::t('common_setting')->update('prasopkan_chat_aibots', $config);
    updatecache('setting');
    cpmsg('อัปเดตการตั้งค่า AI Bots เรียบร้อยแล้ว', 'action=plugins&operation=config&do='.$pluginid.'&identifier=prasopkan_chat&pmod=admincp_aibots', 'succeed');
}

// โหลดค่าเดิมมาแสดงผล
$config = $_G['setting']['prasopkan_chat_aibots'];
if(is_string($config)) { $config = unserialize($config); }

// ถ้ายังไม่มีค่า (โหลดครั้งแรก) ให้ใช้ค่าตั้งต้น
if(empty($config)) {
    $config = array(
        'enable_ai_bots' => 0, 
        'gemini_api_key' => '', 
        'ai_daily_limit' => 50, 
        'ai_chat_interval' => 20, 
        'ai_bot_list' => "แม่บ้านรีวิว|👩‍🍳|เป็นแม่บ้านชอบแต่งบ้านและทำอาหาร มีคนพิมพ์มาถามอะไรให้ตอบสั้นๆ เป็นกันเอง\nทาสแมว|🐱|รักแมวมาก ชอบคุยเรื่องสัตว์เลี้ยง\nช่างไม้ใจดี|🛠️|มีความรู้เรื่องซ่อมบ้าน ช่างสังเกต ชอบให้คำแนะนำสั้นๆ ได้ใจความ", 
        'ai_chat_topic' => 'พูดคุยเรื่องไอเดียแต่งบ้าน ของใช้มินิมอล และไลฟ์สไตล์การใช้ชีวิตประจำวัน', 
        'ai_allowed_forums' => array(1)
    );
}

showformheader('plugins&operation=config&do='.$pluginid.'&identifier=prasopkan_chat&pmod=admincp_aibots');
showtableheader('🤖 การตั้งค่า AI Bots อัจฉริยะ (Gemini API)');

showsetting('เปิดใช้งาน AI คุยกันเอง', 'enable_ai_bots', $config['enable_ai_bots'], 'radio', '', 'เปิดระบบให้หน้าม้า AI พิมพ์คุยโต้ตอบกันในแชทเพื่อสร้างความครึกครื้น');
showsetting('Gemini API Key 🔑', 'gemini_api_key', $config['gemini_api_key'], 'text', '', 'ใส่ API Key ของคุณที่ได้จาก Google AI Studio (ปล่อยว่างไว้หากต้องการปิดระบบ)');
showsetting('ลิมิตข้อความ AI ต่อวัน 💰', 'ai_daily_limit', $config['ai_daily_limit'], 'number', '', 'อนุญาตให้ AI พิมพ์คุยได้สูงสุดกี่ข้อความต่อวัน (ป้องกันการเรียกใช้งานเกินโควต้า)');
showsetting('ความถี่ในการให้ AI คุยกัน (นาที) ⏱️', 'ai_chat_interval', $config['ai_chat_interval'], 'number', '', 'ระยะห่างระหว่างแต่ละข้อความของ AI (แนะนำ 15-30 นาที เพื่อความเนียน)');
showsetting('รายชื่อและบุคลิกของ AI Bots 👥', 'ai_bot_list', $config['ai_bot_list'], 'textarea', '', '<b>รูปแบบ:</b> ชื่อ|ไอคอน|บุคลิก (กด Enter เพื่อขึ้นบรรทัดใหม่สำหรับบอทตัวต่อไป)');
showsetting('หัวข้อสนทนาหลัก (Topic) 💬', 'ai_chat_topic', $config['ai_chat_topic'], 'text', '', 'พิมพ์เรื่องที่คุณอยากให้ AI ชวนคุย (AI จะใช้ข้อมูลนี้เป็นแกนหลักในการหาเรื่องคุย)');

// ดึงรายชื่อห้องทั้งหมดมาให้แอดมินเลือก
require_once libfile('function/forumlist');
$forumselect = '<select name="ai_allowed_forums[]" multiple="multiple" size="10" style="width: 300px;">'.forumselect(FALSE, 0, $config['ai_allowed_forums'], TRUE).'</select>';
showsetting('อนุญาตให้ AI คุยในห้องไหนบ้าง? 🚪', '', '', $forumselect, '', 'เลือกห้องที่ให้ AI โผล่ไปคุย (<b>กด Ctrl ค้างไว้เพื่อเลือกหลายห้อง</b>)');

showsubmit('aibotssubmit');
showtablefooter();
showformfooter();
?>