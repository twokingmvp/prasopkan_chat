<?php
if(!defined('IN_DISCUZ')) { exit('Access Denied'); }

loadcache('plugin');
$plugin_config = $_G['cache']['plugin']['prasopkan_chat'];

$action = isset($_GET['action']) ? $_GET['action'] : '';
$room_id = isset($_GET['room_id']) ? intval($_GET['room_id']) : 1;
$uid = $_G['uid'];
$username = $_G['username'];
$shop_credit_id = isset($plugin_config['shop_credit_id']) ? intval($plugin_config['shop_credit_id']) : 2;
$rp_credit_id = isset($plugin_config['rp_credit_id']) ? intval($plugin_config['rp_credit_id']) : 2;
$credit_name = $_G['setting']['extcredits'][$shop_credit_id]['title'];

if($action == 'shop_info') {
    if(!$uid) { echo json_encode(array('status' => 'error', 'msg' => 'Please login')); exit; }
    $items = DB::fetch_all("SELECT * FROM ".DB::table('prasopkan_chat_shop')." ORDER BY item_type, display_order ASC");
    $shop_data = array('name_style' => array(), 'bubble_skin' => array(), 'gacha' => array());
    foreach($items as $item) { 
        $shop_data[$item['item_type']][$item['item_key']] = array('name' => $item['item_name'], 'icon' => $item['icon'], 'css' => $item['css_code'], 'price' => $item['price'], 'rarity' => $item['rarity']); 
    }
    $inv = DB::fetch_all("SELECT item_key FROM ".DB::table('prasopkan_chat_inventory')." WHERE uid='$uid'");
    $owned = array(); foreach($inv as $v) { $owned[] = $v['item_key']; }
    $eq = DB::fetch_first("SELECT name_style, bubble_skin FROM ".DB::table('prasopkan_chat_equipped')." WHERE uid='$uid'");
    if(!$eq) { $eq = array('name_style' => '', 'bubble_skin' => ''); }
    $credit = getuserprofile('extcredits'.$shop_credit_id);
    echo json_encode(array('status' => 'success', 'items' => $shop_data, 'inventory' => $owned, 'equipment' => $eq, 'credit' => $credit, 'credit_name' => $credit_name));
    exit;
}

if($action == 'shop_buy') {
    if(!$uid) { echo json_encode(array('status' => 'error', 'msg' => 'Please login')); exit; }
    $key = daddslashes($_GET['item_key']); $type = daddslashes($_GET['item_type']);
    $item = DB::fetch_first("SELECT * FROM ".DB::table('prasopkan_chat_shop')." WHERE item_key='$key' AND item_type='$type'");
    if(!$item) { echo json_encode(array('status' => 'error', 'msg' => 'ไม่พบสินค้านี้')); exit; }
    $credit = getuserprofile('extcredits'.$shop_credit_id);
    if($credit < $item['price']) { echo json_encode(array('status' => 'error', 'msg' => 'เครดิตของคุณไม่เพียงพอ')); exit; }

    if($type == 'gacha') {
        updatemembercount($uid, array('extcredits'.$shop_credit_id => -$item['price']), true, 'CHG', 1);
        $pool = DB::fetch_all("SELECT * FROM ".DB::table('prasopkan_chat_shop')." WHERE item_type != 'gacha'");
        $rand = mt_rand(1, 100); $won_item = null;
        $weighted_pool = array();
        foreach($pool as $p) {
            $chance = 50; if($p['rarity'] == 'rare') $chance = 15; elseif($p['rarity'] == 'epic') $chance = 5; elseif($p['rarity'] == 'legendary') $chance = 1;
            for($i=0; $i<$chance; $i++) { $weighted_pool[] = $p; }
        }
        $won_item = $weighted_pool[array_rand($weighted_pool)];
        $check = DB::result_first("SELECT COUNT(*) FROM ".DB::table('prasopkan_chat_inventory')." WHERE uid='$uid' AND item_key='{$won_item['item_key']}'");
        if(!$check) { DB::insert('prasopkan_chat_inventory', array('uid' => $uid, 'item_key' => $won_item['item_key'])); }
        echo json_encode(array('status' => 'success', 'is_gacha' => true, 'won_name' => $won_item['item_name'], 'won_icon' => $won_item['icon']));
    } else {
        $check = DB::result_first("SELECT COUNT(*) FROM ".DB::table('prasopkan_chat_inventory')." WHERE uid='$uid' AND item_key='$key'");
        if($check) { echo json_encode(array('status' => 'error', 'msg' => 'คุณมีสินค้านี้อยู่แล้ว')); exit; }
        updatemembercount($uid, array('extcredits'.$shop_credit_id => -$item['price']), true, 'CHB', 1);
        DB::insert('prasopkan_chat_inventory', array('uid' => $uid, 'item_key' => $key));
        echo json_encode(array('status' => 'success', 'is_gacha' => false));
    }
    exit;
}

if($action == 'shop_equip') {
    if(!$uid) { echo json_encode(array('status' => 'error', 'msg' => 'Please login')); exit; }
    $key = daddslashes($_GET['item_key']); $type = daddslashes($_GET['item_type']);
    if($key !== '') {
        $check = DB::result_first("SELECT COUNT(*) FROM ".DB::table('prasopkan_chat_inventory')." WHERE uid='$uid' AND item_key='$key'");
        if(!$check) { echo json_encode(array('status' => 'error', 'msg' => 'คุณยังไม่มีสินค้านี้')); exit; }
    }
    $eq = DB::fetch_first("SELECT * FROM ".DB::table('prasopkan_chat_equipped')." WHERE uid='$uid'");
    $data = array($type => $key);
    if($eq) { DB::update('prasopkan_chat_equipped', $data, "uid='$uid'"); } else { $data['uid'] = $uid; DB::insert('prasopkan_chat_equipped', $data); }
    echo json_encode(array('status' => 'success'));
    exit;
}

if($action == 'send') {
    if(!$uid) { echo json_encode(array('status' => 'error', 'msg' => 'Please login')); exit; }
    $message = daddslashes(trim($_GET['message']));
    if(empty($message)) { echo json_encode(array('status' => 'error', 'msg' => 'Empty message')); exit; }
    
    // 🧹 ระบบแอบเคลียร์ประวัติแชทอัตโนมัติ (ลบข้อความที่เก่ากว่า 7 วัน หรือ 604800 วินาที)
    $seven_days_ago = $_G['timestamp'] - 604800;
    DB::query("DELETE FROM ".DB::table('prasopkan_chat_messages')." WHERE dateline < $seven_days_ago");

    $eq = DB::fetch_first("SELECT e.name_style, e.bubble_skin, s1.css_code as n_css, s2.css_code as b_css FROM ".DB::table('prasopkan_chat_equipped')." e 
        LEFT JOIN ".DB::table('prasopkan_chat_shop')." s1 ON e.name_style = s1.item_key 
        LEFT JOIN ".DB::table('prasopkan_chat_shop')." s2 ON e.bubble_skin = s2.item_key 
        WHERE e.uid='$uid'");
    
    $name_css = $eq && $eq['n_css'] ? daddslashes($eq['n_css']) : '';
    $bubble_css = $eq && $eq['b_css'] ? daddslashes($eq['b_css']) : '';
    
    DB::insert('prasopkan_chat_messages', array(
        'uid' => $uid, 'username' => $username, 'message' => $message,
        'dateline' => $_G['timestamp'], 'ip' => $_G['clientip'], 'room_id' => $room_id,
        'name_css' => $name_css, 'bubble_css' => $bubble_css
    ));
    echo json_encode(array('status' => 'success')); exit;
}

if($action == 'get') {
    $limit = isset($plugin_config['message_limit']) ? intval($plugin_config['message_limit']) : 50;
    $query = DB::query("SELECT m.*, g.color, g.grouptitle FROM ".DB::table('prasopkan_chat_messages')." m LEFT JOIN ".DB::table('common_member')." u ON m.uid=u.uid LEFT JOIN ".DB::table('common_usergroup')." g ON u.groupid=g.groupid WHERE m.room_id='$room_id' ORDER BY m.dateline DESC LIMIT $limit");
    $messages = array();
    while($row = DB::fetch($query)) {
        $row['message'] = dhtmlspecialchars($row['message']);
        $row['message'] = preg_replace('/\[img\](.*?)\[\/img\]/is', '<img src="$1" class="pk-chat-img" onclick="window.open(this.src)">', $row['message']);
        $row['message'] = preg_replace('/\[url=(.*?)\](.*?)\[\/url\]/is', '<a href="$1" target="_blank" class="pk-text-link">$2</a>', $row['message']);
        $row['message'] = preg_replace('/@(\w+)/', '<span class="pk-mention-badge">@$1</span>', $row['message']);
        
        if($plugin_config['enable_link_preview']) {
            $row['message'] = preg_replace_callback('/(https?:\/\/[^\s<]+)/i', function($matches) {
                $url = $matches[1];
                if(preg_match('/(jpg|jpeg|png|gif)$/i', $url)) return $url;
                $host = parse_url($url, PHP_URL_HOST);
                return '<a href="'.$url.'" target="_blank" style="text-decoration:none;"><div class="pk-link-preview"><div class="pk-lp-fallback">🔗</div><div class="pk-lp-content"><div class="pk-lp-title">คลิกเพื่อดูลิงก์</div><div class="pk-lp-meta">'.$host.'</div></div></div></a>';
            }, $row['message']);
        }
        
        $row['time'] = dgmdate($row['dateline'], 'H:i');
        $row['badge_icon'] = '';
        if(strpos($row['grouptitle'], 'Admin') !== false) { $row['badge_icon'] = '👑'; }
        
        $reacts = DB::fetch_all("SELECT reaction, uid FROM ".DB::table('prasopkan_chat_reactions')." WHERE msg_id='{$row['msg_id']}'");
        $row['reactions'] = array();
        foreach($reacts as $r) {
            $emoji = $r['reaction'];
            if(!isset($row['reactions'][$emoji])) $row['reactions'][$emoji] = array('count' => 0, 'me' => false);
            $row['reactions'][$emoji]['count']++;
            if($r['uid'] == $uid) $row['reactions'][$emoji]['me'] = true;
        }

        $messages[] = $row;
    }
    $messages = array_reverse($messages);
    
    // 💡 ระบบบอทสุ่มกระทู้เก่ามาแนะนำ
    $enable_random_bot = isset($plugin_config['enable_random_bot']) ? $plugin_config['enable_random_bot'] : 1;
    if($enable_random_bot) {
        loadcache('prasopkan_chat_random_bot_time');
        $last_random_time = intval($_G['cache']['prasopkan_chat_random_bot_time']);
        $random_interval = 3600; // 3600 วินาที = 1 ชั่วโมง
        if($_G['timestamp'] - $last_random_time > $random_interval) {
            savecache('prasopkan_chat_random_bot_time', $_G['timestamp']);
            $random_thread = DB::fetch_first("SELECT tid, subject, author FROM ".DB::table('forum_thread')." WHERE displayorder >= 0 ORDER BY RAND() LIMIT 1");
            if($random_thread) {
                $bot_msg = "💡 แวะมาป้ายยากระทู้น่าสนใจ: [url=forum.php?mod=viewthread&tid=".$random_thread['tid']."]".$random_thread['subject']."[/url] (โดยคุณ ".$random_thread['author'].")";
                $chat_forums = @unserialize($plugin_config['chat_forums']); 
                if(!is_array($chat_forums)) $chat_forums = array(1);
                foreach($chat_forums as $r_id) { 
                    DB::insert('prasopkan_chat_messages', array('uid' => 0, 'username' => '🤖 System Bot', 'message' => $bot_msg, 'dateline' => $_G['timestamp'], 'ip' => '127.0.0.1', 'room_id' => intval($r_id))); 
                }
            }
        }
    }

    $is_admin = ($_G['adminid'] == 1) ? true : false;
    echo json_encode(array('status' => 'success', 'data' => $messages, 'current_uid' => $uid, 'is_admin' => $is_admin, 'enable_mention' => $plugin_config['enable_mention'], 'enable_reaction' => $plugin_config['enable_reaction']));
    exit;
}

if($action == 'delete') {
    if(!$_G['uid']) { echo json_encode(array('status' => 'error', 'msg' => 'Please login')); exit; }
    $msg_id = intval($_GET['msg_id']);
    $msg = DB::fetch_first("SELECT * FROM ".DB::table('prasopkan_chat_messages')." WHERE msg_id='$msg_id'");
    if(!$msg) { echo json_encode(array('status' => 'error', 'msg' => 'ไม่พบข้อความ')); exit; }
    if($_G['adminid'] != 1 && $_G['uid'] != 1) { 
        echo json_encode(array('status' => 'error', 'msg' => 'ไม่มีสิทธิ์ลบข้อความนี้')); exit; 
    }
    DB::delete('prasopkan_chat_messages', "msg_id='$msg_id'");
    DB::delete('prasopkan_chat_reactions', "msg_id='$msg_id'");
    echo json_encode(array('status' => 'success')); exit;
}
?>