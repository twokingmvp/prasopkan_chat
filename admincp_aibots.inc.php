<?php
if(!defined('IN_DISCUZ') || !defined('IN_ADMINCP')) { exit('Access Denied'); }

$plugin_url = 'plugins&operation=config&do='.$pluginid.'&identifier=prasopkan_chat&pmod=admincp_aibots';

if(submitcheck('aibotssubmit')) {
    $allowed_forums = isset($_GET['ai_allowed_forums']) ? $_GET['ai_allowed_forums'] : array();
    
    // ถ้าระบบติ๊ก 'ห้องพูดคุยทั่วไป' (ค่าเป็น 1) ให้เพิ่ม 1 เข้าไปในอาเรย์
    if(isset($_GET['allow_general_room'])) {
        if(!in_array(1, $allowed_forums)) { $allowed_forums[] = 1; }
    } else {
        if(($key = array_search(1, $allowed_forums)) !== false) { unset($allowed_forums[$key]); }
    }

    $config = array(
        'enable_ai_bots' => intval($_GET['enable_ai_bots']),
        'gemini_api_key' => trim($_GET['gemini_api_key']),
        'ai_daily_limit' => intval($_GET['ai_daily_limit']),
        'ai_chat_interval' => intval($_GET['ai_chat_interval']),
        'ai_bot_list' => trim($_GET['ai_bot_list']),
        'ai_chat_topic' => trim($_GET['ai_chat_topic']),
        'ai_allowed_forums' => $allowed_forums
    );
    
    C::t('common_setting')->update('prasopkan_chat_aibots', $config);
    updatecache('setting');
    cpmsg('อัปเดตการตั้งค่า AI Bots เรียบร้อยแล้ว', 'action='.$plugin_url, 'succeed');
}

$config = $_G['setting']['prasopkan_chat_aibots'];
if(is_string($config)) { $config = unserialize($config); }

if(empty($config)) {
    $config = array(
        'enable_ai_bots' => 0, 
        'gemini_api_key' => '', 
        'ai_daily_limit' => 50, 
        'ai_chat_interval' => 20, 
        'ai_bot_list' => "แม่บ้านรีวิว|👩‍🍳|เป็นแม่บ้านชอบแต่งบ้านและทำอาหาร มีคนพิมพ์มาถามอะไรให้ตอบสั้นๆ เป็นกันเอง\nทาสแมว|🐱|รักแมวมาก ชอบคุยเรื่องสัตว์เลี้ยง\nช่างไม้ใจดี|🛠️|มีความรู้เรื่องซ่อมบ้าน ช่างสังเกต ชอบให้คำแนะนำสั้นๆ ได้ใจความ", 
        'ai_chat_topic' => "ไอเดียแต่งบ้านสไตล์มินิมอล\nรีวิวของใช้ในบ้านที่คุ้มค่า\nการทำความสะอาดและจัดบ้าน\nพูดคุยเรื่องสัตว์เลี้ยงและอาหารแมว\nเรื่องตลกๆ ในชีวิตประจำวัน", 
        'ai_allowed_forums' => array(1)
    );
}

showformheader($plugin_url);
showtableheader('🤖 การตั้งค่า AI Bots อัจฉริยะ (Gemini API)');

showsetting('เปิดใช้งาน AI คุยกันเอง', 'enable_ai_bots', $config['enable_ai_bots'], 'radio', '', 0, 'เปิดระบบให้หน้าม้า AI พิมพ์คุยโต้ตอบกันในแชทเพื่อสร้างความครึกครื้น');
showsetting('Gemini API Key 🔑', 'gemini_api_key', $config['gemini_api_key'], 'text', '', 0, 'ใส่ API Key ของคุณที่ได้จาก Google AI Studio (ปล่อยว่างไว้หากต้องการปิดระบบ)');
showsetting('ลิมิตข้อความ AI ต่อวัน 💰', 'ai_daily_limit', $config['ai_daily_limit'], 'number', '', 0, 'อนุญาตให้ AI พิมพ์คุยได้สูงสุดกี่ข้อความต่อวัน (ป้องกันการเรียกใช้งานเกินโควต้า)');
showsetting('ความถี่ในการให้ AI คุยกัน (นาที) ⏱️', 'ai_chat_interval', $config['ai_chat_interval'], 'number', '', 0, 'ระยะห่างระหว่างแต่ละข้อความของ AI (แนะนำ 15-30 นาที เพื่อความเนียน)');
showsetting('รายชื่อและบุคลิกของ AI Bots 👥', 'ai_bot_list', $config['ai_bot_list'], 'textarea', '', 0, '<b>รูปแบบ:</b> ชื่อ|ไอคอน|บุคลิก (กด Enter เพื่อขึ้นบรรทัดใหม่สำหรับบอทตัวต่อไป)');
showsetting('หัวข้อสนทนาที่อนุญาตให้ AI ชวนคุย (Topics) 💬', 'ai_chat_topic', $config['ai_chat_topic'], 'textarea', '', 0, 'พิมพ์หัวข้อที่อยากให้ AI สุ่มหยิบมาชวนคุย (<b>กด Enter เพื่อขึ้นบรรทัดใหม่</b>)');

$general_room_checked = in_array(1, $config['ai_allowed_forums']) ? 'checked' : '';
$general_room_html = "<label><input type=\"checkbox\" class=\"checkbox\" name=\"allow_general_room\" value=\"1\" $general_room_checked> <b style=\"color:#0056cc;\">ห้องพูดคุยทั่วไป (ห้องหลัก)</b></label><br><br>";

require_once libfile('function/forumlist');
$forumselect = $general_room_html . '<select name="ai_allowed_forums[]" multiple="multiple" size="10" style="width: 300px;">'.forumselect(FALSE, 0, $config['ai_allowed_forums'], TRUE).'</select>';

// 🔥 ใช้ showsetting ส่ง Custom HTML กลับเข้าไป เพื่อให้ Layout ตรงเป๊ะกับช่องอื่น 100%
showsetting('อนุญาตให้ AI คุยในห้องไหนบ้าง? 🚪', '', '', $forumselect, '', 0, 'เลือกห้องที่ให้ AI โผล่ไปคุย (<b>กด Ctrl ค้างไว้เพื่อเลือกหลายห้องของบอร์ด</b>)');

showsubmit('aibotssubmit', 'บันทึกการตั้งค่า (Save Settings)');

showtablefooter();
showformfooter();
?>