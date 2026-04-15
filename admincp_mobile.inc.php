<?php
if(!defined('IN_DISCUZ') || !defined('IN_ADMINCP')) { exit('Access Denied'); }

$plugin_url = 'plugins&operation=config&do='.$pluginid.'&identifier=prasopkan_chat&pmod=admincp_mobile';

if(submitcheck('mobilesubmit')) {
    $config = array(
        'smart_scroll' => intval($_GET['smart_scroll']),
        'fade_idle' => intval($_GET['fade_idle']),
        'draggable' => intval($_GET['draggable'])
    );
    C::t('common_setting')->update('prasopkan_chat_mobile', $config);
    updatecache('setting');
    cpmsg('อัปเดตการตั้งค่า Mobile UX เรียบร้อยแล้ว', 'action='.$plugin_url, 'succeed');
}

$config = $_G['setting']['prasopkan_chat_mobile'];
if(is_string($config)) { $config = unserialize($config); }

// ค่าเริ่มต้นหากยังไม่ได้ตั้งค่า
if(empty($config)) {
    $config = array('smart_scroll' => 1, 'fade_idle' => 1, 'draggable' => 1);
}

showformheader($plugin_url);
showtableheader('📱 การตั้งค่าปุ่มแชทบนมือถือ (Mobile UX)');

showsetting('1. ซ่อนปุ่มอัตโนมัติเวลาเลื่อนจอ (Smart Scroll)', 'smart_scroll', $config['smart_scroll'], 'radio', '', 0, 'เมื่อผู้ใช้เลื่อนจอลง ปุ่มจะซ่อนตัว และจะโผล่มาเมื่อเลื่อนขึ้น (เพิ่มพื้นที่อ่านกระทู้)');
showsetting('2. ปุ่มโปร่งแสงเมื่อไม่ได้ใช้งาน (Fade on Idle)', 'fade_idle', $config['fade_idle'], 'radio', '', 0, 'หากไม่มีการแตะปุ่มเกิน 3 วินาที ปุ่มจะโปร่งแสงลดความทึบลงเพื่อไม่ให้บังเนื้อหา');
showsetting('3. ปุ่มลากย้ายตำแหน่งได้ (Draggable)', 'draggable', $config['draggable'], 'radio', '', 0, 'ผู้ใช้สามารถแตะค้างที่ปุ่มแชทแล้วลากย้ายไปแปะไว้ตรงไหนของจอก็ได้');

showsubmit('mobilesubmit', 'บันทึกการตั้งค่า (Save Settings)');
showtablefooter();
showformfooter();
?>