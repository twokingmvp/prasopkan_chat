<?php
if(!defined('IN_DISCUZ')) {
    exit('Access Denied');
}

global $_G;
$action = isset($_GET['action']) ? $_GET['action'] : '';
$room_id = isset($_GET['room_id']) ? intval($_GET['room_id']) : 1; 

if($action == 'send') {
    $message = dhtmlspecialchars(trim($_GET['message']));
    if(empty($message)) { echo json_encode(array('status' => 'error', 'msg' => 'กรุณาพิมพ์ข้อความ')); exit; }
    if(!$_G['uid']) { echo json_encode(array('status' => 'error', 'msg' => 'กรุณาล็อกอินก่อนส่งข้อความ')); exit; }
    $message = preg_replace('/(?<!\])(https?:\/\/[^\s<]+)/i', '[url=$1]$1[/url]', $message);
    $data = array('uid' => $_G['uid'], 'username' => $_G['username'], 'message' => $message, 'dateline' => $_G['timestamp'], 'ip' => $_G['clientip'], 'room_id' => $room_id);
    DB::insert('prasopkan_chat_messages', $data);
    echo json_encode(array('status' => 'success')); exit;
} 
// --- ระบบ "กำลังพิมพ์..." ---
elseif($action == 'typing') {
    if($_G['uid']) {
        DB::query("REPLACE INTO ".DB::table('prasopkan_chat_typing')." (room_id, uid, username, dateline) VALUES ('$room_id', '{$_G['uid']}', '{$_G['username']}', '{$_G['timestamp']}')");
    }
    echo json_encode(array('status'=>'success')); exit;
}
elseif($action == 'get') {
    loadcache('plugin'); loadcache('usergroups');
    $plugin_config = $_G['cache']['plugin']['prasopkan_chat'];
    $enable_color = $plugin_config['enable_color'];
    $enable_mention = $plugin_config['enable_mention'];
    $enable_bot = $plugin_config['enable_bot'];
    $enable_linkpreview = $plugin_config['enable_linkpreview']; 
    $enable_reaction = $plugin_config['enable_reaction']; 
    $current_uid = $_G['uid']; // ส่ง UID ปัจจุบันกลับไปให้ JS จัดหน้าจอฝั่งขวา

    if($enable_bot) {
        loadcache('prasopkan_chat_last_tid'); $last_tid = intval($_G['cache']['prasopkan_chat_last_tid']);
        $latest_thread = DB::fetch_first("SELECT tid, subject, author FROM ".DB::table('forum_thread')." WHERE displayorder >= 0 ORDER BY tid DESC LIMIT 1");
        if($latest_thread && $latest_thread['tid'] > $last_tid) {
            savecache('prasopkan_chat_last_tid', $latest_thread['tid']);
            if($last_tid > 0) {
                $bot_msg = "📢 มีกระทู้ใหม่มาสดๆ ร้อนๆ: [url=forum.php?mod=viewthread&tid=".$latest_thread['tid']."]".$latest_thread['subject']."[/url]";
                $chat_forums = $plugin_config['chat_forums'];
                if(!is_array($chat_forums)) $chat_forums = @unserialize($chat_forums);
                if(empty($chat_forums)) $chat_forums = array(1);
                foreach($chat_forums as $r_id) {
                    DB::insert('prasopkan_chat_messages', array('uid' => 0, 'username' => '🤖 System Bot', 'message' => $bot_msg, 'dateline' => $_G['timestamp'], 'ip' => '127.0.0.1', 'room_id' => intval($r_id)));
                }
            }
        }
    }

    // ล้างและดึงข้อมูลคนกำลังพิมพ์
    DB::query("DELETE FROM ".DB::table('prasopkan_chat_typing')." WHERE dateline < ".($_G['timestamp'] - 6));
    $typing_users = array();
    $q_type = DB::query("SELECT username FROM ".DB::table('prasopkan_chat_typing')." WHERE room_id='$room_id' AND uid != '{$_G['uid']}'");
    while($t = DB::fetch($q_type)) { $typing_users[] = $t['username']; }

    $messages = array(); $msg_ids = array();
    $query = DB::query("SELECT c.*, m.groupid FROM ".DB::table('prasopkan_chat_messages')." c LEFT JOIN ".DB::table('common_member')." m ON c.uid = m.uid WHERE c.room_id='$room_id' ORDER BY c.dateline DESC LIMIT 50");
    
    while($row = DB::fetch($query)) {
        $row['time'] = dgmdate($row['dateline'], 'H:i');
        $row['message'] = preg_replace('/\[img\](.*?)\[\/img\]/i', '<img src="$1" class="pk-chat-img" />', $row['message']);
        
        if($enable_linkpreview) {
            $row['message'] = preg_replace_callback('/\[url=(.*?(?:tid=|t-|thread-)(\d+)[^\]]*?)\](.*?)\[\/url\]/i', function($matches) {
                global $_G; $url = $matches[1]; $tid = intval($matches[2]); $text = $matches[3];
                $thread = DB::fetch_first("SELECT subject, author, views, replies FROM ".DB::table('forum_thread')." WHERE tid='$tid'");
                if($thread) {
                    $img_html = '<div class="pk-lp-fallback">💬</div>';
                    $threadimage = DB::fetch_first("SELECT attachment FROM ".DB::table('forum_threadimage')." WHERE tid='$tid'");
                    if($threadimage && !empty($threadimage['attachment'])) {
                        $attachurl = !empty($_G['setting']['attachurl']) ? $_G['setting']['attachurl'] : 'data/attachment/';
                        $img_url = preg_match('/^http/i', $threadimage['attachment']) ? $threadimage['attachment'] : $attachurl.'forum/'.$threadimage['attachment'];
                        $img_html = '<img src="'.$img_url.'" class="pk-lp-img" onerror="this.style.display=\'none\'">';
                    }
                    return '<div class="pk-link-preview">'.$img_html.'<div class="pk-lp-content"><a href="'.$url.'" target="_blank" class="pk-lp-title" title="'.strip_tags($thread['subject']).'">📄 '.strip_tags($thread['subject']).'</a><div class="pk-lp-meta">✍️ '.$thread['author'].' &nbsp;|&nbsp; 👁️ '.$thread['views'].' &nbsp;|&nbsp; 💬 '.$thread['replies'].'</div></div></div>';
                }
                return '<a href="'.$url.'" target="_blank" class="pk-text-link">'.$text.'</a>';
            }, $row['message']);
            $row['message'] = preg_replace('/\[url=(.*?)\](.*?)\[\/url\]/i', '<a href="$1" target="_blank" class="pk-text-link">$2</a>', $row['message']);
        } else {
            $row['message'] = preg_replace('/\[url=(.*?)\](.*?)\[\/url\]/i', '<a href="$1" target="_blank" class="pk-text-link">$2</a>', $row['message']);
        }

        if($enable_mention) $row['message'] = preg_replace('/@([^\s]+)/u', '<strong class="pk-mention-badge">@$1</strong>', $row['message']);
        $row['message'] = preg_replace('/\[redpacket\](\d+)\[\/redpacket\]/i', '<div class="pk-redpacket-box" data-envid="$1"><div class="pk-rp-icon">🧧</div><div class="pk-rp-text"><b>อั่งเปาเครดิต</b><br><span>คลิกลุ้นรับเครดิต!</span></div></div>', $row['message']);

        $row['color'] = ''; // ปล่อยให้ CSS จัดการถ้าไม่ได้กำหนด
        if($row['uid'] == 0) $row['color'] = '#ff6600';
        elseif($enable_color && !empty($row['groupid'])) {
            $group_color = $_G['cache']['usergroups'][$row['groupid']]['color'];
            if(!empty($group_color)) $row['color'] = $group_color;
        }
        $messages[] = $row; $msg_ids[] = $row['msg_id'];
    }
    
    $reactions_map = array();
    if($enable_reaction && !empty($msg_ids)) {
        $ids_str = implode(',', $msg_ids);
        $req_query = DB::query("SELECT msg_id, reaction, uid FROM ".DB::table('prasopkan_chat_reactions')." WHERE msg_id IN ($ids_str)");
        while($r = DB::fetch($req_query)) {
            $m_id = $r['msg_id']; $rx = $r['reaction'];
            if(!isset($reactions_map[$m_id])) $reactions_map[$m_id] = array();
            if(!isset($reactions_map[$m_id][$rx])) $reactions_map[$m_id][$rx] = array('count'=>0, 'me'=>false);
            $reactions_map[$m_id][$rx]['count']++;
            if($r['uid'] == $_G['uid']) $reactions_map[$m_id][$rx]['me'] = true; 
        }
    }
    foreach($messages as &$m) { $m['reactions'] = isset($reactions_map[$m['msg_id']]) ? $reactions_map[$m['msg_id']] : null; }
    $messages = array_reverse($messages);
    $is_admin = ($_G['uid'] && $_G['adminid'] > 0) ? true : false;
    
    echo json_encode(array('status' => 'success', 'data' => $messages, 'is_admin' => $is_admin, 'enable_mention' => $enable_mention, 'enable_reaction' => $enable_reaction, 'current_uid' => $current_uid, 'typing_users' => $typing_users));
    exit;
}
elseif($action == 'react') { /* (โค้ดรีแอคชั่นเดิม ปล่อยไว้เลยครับ) */
    if(!$_G['uid']) { echo json_encode(array('status'=>'error', 'msg'=>'กรุณาล็อกอิน')); exit; }
    $msg_id = intval($_GET['msg_id']); $reaction = dhtmlspecialchars(trim($_GET['reaction']));
    if(!$msg_id || !$reaction) { echo json_encode(array('status'=>'error')); exit; }
    $exists = DB::fetch_first("SELECT id FROM ".DB::table('prasopkan_chat_reactions')." WHERE msg_id='$msg_id' AND uid='{$_G['uid']}' AND reaction='$reaction'");
    if($exists) DB::delete('prasopkan_chat_reactions', "id='{$exists['id']}'");
    else DB::insert('prasopkan_chat_reactions', array('msg_id'=>$msg_id, 'uid'=>$_G['uid'], 'reaction'=>$reaction));
    echo json_encode(array('status'=>'success')); exit;
}
elseif($action == 'delete') {
    if(!$_G['uid'] || $_G['adminid'] <= 0) { echo json_encode(array('status' => 'error', 'msg' => 'ไม่มีสิทธิ์ในการลบข้อความ')); exit; }
    $msg_id = isset($_GET['msg_id']) ? intval($_GET['msg_id']) : 0;
    if($msg_id) DB::delete('prasopkan_chat_messages', "msg_id='$msg_id'");
    echo json_encode(array('status' => 'success')); exit;
}
elseif($action == 'upload') {
    if(!$_G['uid']) { echo json_encode(array('status'=>'error','msg'=>'กรุณาล็อกอิน')); exit; }
    $upload_dir = DISCUZ_ROOT . './source/plugin/prasopkan_chat/uploads/';
    if(!is_dir($upload_dir)) { @mkdir($upload_dir, 0777, true); }
    $file = $_FILES['chat_image'];
    if(!$file || $file['error'] != 0) { echo json_encode(array('status'=>'error','msg'=>'อัปโหลดล้มเหลว')); exit; }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if(!in_array($ext, array('jpg', 'jpeg', 'png', 'gif', 'webp'))) { echo json_encode(array('status'=>'error','msg'=>'รองรับรูปภาพเท่านั้น')); exit; }
    $filename = 'img_'.$_G['uid'].'_'.time().'_'.rand(1000,9999).'.'.$ext;
    if(move_uploaded_file($file['tmp_name'], $upload_dir.$filename)) { echo json_encode(array('status'=>'success', 'url'=>'source/plugin/prasopkan_chat/uploads/'.$filename)); } else { echo json_encode(array('status'=>'error','msg'=>'บันทึกไฟล์ไม่ได้')); } exit;
}
elseif($action == 'send_redpacket') {
    loadcache('plugin'); $config = $_G['cache']['plugin']['prasopkan_chat'];
    if(!$config['enable_redpacket']) { echo json_encode(['status'=>'error', 'msg'=>'ระบบแจกอั่งเปาปิดใช้งานอยู่']); exit; }
    if(!$_G['uid']) { echo json_encode(['status'=>'error', 'msg'=>'กรุณาล็อกอิน']); exit; }
    $amount = intval($_GET['amount']); $count = intval($_GET['count']); $credit_id = intval($config['redpacket_credit_id']);
    if($amount < intval($config['redpacket_min']) || $amount > intval($config['redpacket_max'])) { echo json_encode(['status'=>'error', 'msg'=>"ตั้งจำนวนเงินได้ระหว่าง {$config['redpacket_min']} - {$config['redpacket_max']}"]); exit; }
    if($count < 1 || $count > $amount) { echo json_encode(['status'=>'error', 'msg'=>"จำนวนซองไม่ถูกต้อง"]); exit; }
    $user_credit = DB::result_first("SELECT extcredits".$credit_id." FROM ".DB::table('common_member_count')." WHERE uid='{$_G['uid']}'");
    if($user_credit < $amount) { echo json_encode(['status'=>'error', 'msg'=>'เครดิตของคุณไม่เพียงพอ!']); exit; }
    updatemembercount($_G['uid'], array('extcredits'.$credit_id => -$amount));
    $env_id = DB::insert('prasopkan_chat_envelopes', array('uid' => $_G['uid'], 'total_amount' => $amount, 'total_count' => $count, 'remain_amount' => $amount, 'remain_count' => $count, 'dateline' => $_G['timestamp']), true);
    $msg_id = DB::insert('prasopkan_chat_messages', array('uid' => $_G['uid'], 'username' => $_G['username'], 'message' => '[redpacket]'.$env_id.'[/redpacket]', 'dateline' => $_G['timestamp'], 'ip' => $_G['clientip'], 'room_id' => $room_id), true);
    DB::update('prasopkan_chat_envelopes', array('msg_id' => $msg_id), "id='$env_id'");
    echo json_encode(['status' => 'success']); exit;
}
elseif($action == 'claim_redpacket') {
    if(!$_G['uid']) { echo json_encode(['status'=>'error', 'msg'=>'กรุณาล็อกอิน']); exit; }
    $env_id = intval($_GET['env_id']); $env = DB::fetch_first("SELECT * FROM ".DB::table('prasopkan_chat_envelopes')." WHERE id='$env_id'");
    if(!$env) { echo json_encode(['status'=>'error', 'msg'=>'ไม่พบซองอั่งเปานี้']); exit; }
    if($env['remain_count'] <= 0) { echo json_encode(['status'=>'error', 'msg'=>'อั่งเปาถูกรับไปหมดแล้ว 😭']); exit; }
    if(DB::fetch_first("SELECT log_id FROM ".DB::table('prasopkan_chat_envlogs')." WHERE env_id='$env_id' AND uid='{$_G['uid']}'")) { echo json_encode(['status'=>'error', 'msg'=>'คุณรับอั่งเปาซองนี้ไปแล้วจ้า!']); exit; }
    if($env['remain_count'] == 1) { $get_amount = $env['remain_amount']; } else {
        $max_get = floor($env['remain_amount'] / $env['remain_count'] * 2); $get_amount = rand(1, $max_get);
        if($env['remain_amount'] - $get_amount < $env['remain_count'] - 1) $get_amount = $env['remain_amount'] - ($env['remain_count'] - 1);
    }
    DB::query("UPDATE ".DB::table('prasopkan_chat_envelopes')." SET remain_amount=remain_amount-$get_amount, remain_count=remain_count-1 WHERE id='$env_id' AND remain_count>0");
    if(DB::affected_rows() > 0) {
        DB::insert('prasopkan_chat_envlogs', array('env_id'=>$env_id, 'uid'=>$_G['uid'], 'username'=>$_G['username'], 'amount'=>$get_amount, 'dateline'=>$_G['timestamp']));
        loadcache('plugin'); $credit_id = intval($_G['cache']['plugin']['prasopkan_chat']['redpacket_credit_id']);
        updatemembercount($_G['uid'], array('extcredits'.$credit_id => $get_amount));
        echo json_encode(['status'=>'success', 'amount'=>$get_amount]);
    } else { echo json_encode(['status'=>'error', 'msg'=>'ซองอั่งเปาหมดพอดี!']); } exit;
}
?>