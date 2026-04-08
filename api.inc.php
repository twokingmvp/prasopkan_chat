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

    $data = array(
        'uid' => $_G['uid'],
        'username' => $_G['username'],
        'message' => $message,
        'dateline' => $_G['timestamp'],
        'ip' => $_G['clientip'],
        'room_id' => $room_id
    );
    DB::insert('prasopkan_chat_messages', $data);
    echo json_encode(array('status' => 'success'));
    exit;
} 
elseif($action == 'get') {
    loadcache('plugin');
    loadcache('usergroups');
    $plugin_config = $_G['cache']['plugin']['prasopkan_chat'];
    
    $enable_color = $plugin_config['enable_color'];
    $enable_mention = $plugin_config['enable_mention'];
    $enable_bot = $plugin_config['enable_bot'];

    if($enable_bot) {
        loadcache('prasopkan_chat_last_tid');
        $last_tid = intval($_G['cache']['prasopkan_chat_last_tid']);
        $latest_thread = DB::fetch_first("SELECT tid, subject, author FROM ".DB::table('forum_thread')." WHERE displayorder >= 0 ORDER BY tid DESC LIMIT 1");
        
        if($latest_thread && $latest_thread['tid'] > $last_tid) {
            savecache('prasopkan_chat_last_tid', $latest_thread['tid']);
            if($last_tid > 0) {
                $bot_msg = "📢 มีกระทู้ใหม่มาสดๆ ร้อนๆ: [url=forum.php?mod=viewthread&tid=".$latest_thread['tid']."]".$latest_thread['subject']."[/url] (โดย ".$latest_thread['author'].")";
                $chat_forums = $plugin_config['chat_forums'];
                if(!is_array($chat_forums)) $chat_forums = @unserialize($chat_forums);
                if(empty($chat_forums)) $chat_forums = array(1);
                
                foreach($chat_forums as $r_id) {
                    $bot_data = array('uid' => 0, 'username' => '🤖 System Bot', 'message' => $bot_msg, 'dateline' => $_G['timestamp'], 'ip' => '127.0.0.1', 'room_id' => intval($r_id));
                    DB::insert('prasopkan_chat_messages', $bot_data);
                }
            }
        }
    }

    $messages = array();
    $query = DB::query("SELECT c.*, m.groupid FROM ".DB::table('prasopkan_chat_messages')." c LEFT JOIN ".DB::table('common_member')." m ON c.uid = m.uid WHERE c.room_id='$room_id' ORDER BY c.dateline DESC LIMIT 50");
    
    while($row = DB::fetch($query)) {
        $row['time'] = dgmdate($row['dateline'], 'H:i');
        $row['message'] = preg_replace('/\[img\](.*?)\[\/img\]/i', '<br><img src="$1" style="max-width:100%; border-radius:8px; margin-top:5px; border:1px solid #ddd;" />', $row['message']);
        $row['message'] = preg_replace('/\[url=(.*?)\](.*?)\[\/url\]/i', '<a href="$1" target="_blank" style="color:#ff6600; text-decoration:underline; font-weight:bold;">$2</a>', $row['message']);
        if($enable_mention) {
            $row['message'] = preg_replace('/@([^\s]+)/u', '<strong style="color:#0073ff; background:#e6f2ff; padding:0 4px; border-radius:3px;">@$1</strong>', $row['message']);
        }
        
        // 🧧 แปลงโค้ด [redpacket] เป็นการ์ดอั่งเปา
        $row['message'] = preg_replace('/\[redpacket\](\d+)\[\/redpacket\]/i', '<div class="pk-redpacket-box" data-envid="$1"><div class="pk-rp-icon">🧧</div><div class="pk-rp-text"><b>อั่งเปาเครดิต</b><br><span style="font-size:11px; opacity:0.8;">คลิกเพื่อลุ้นรับเครดิต!</span></div></div>', $row['message']);

        $row['color'] = '#333333';
        if($row['uid'] == 0) {
            $row['color'] = '#ff6600';
        } elseif($enable_color && !empty($row['groupid'])) {
            $group_color = $_G['cache']['usergroups'][$row['groupid']]['color'];
            if(!empty($group_color)) $row['color'] = $group_color;
        }
        $messages[] = $row;
    }
    $messages = array_reverse($messages);
    $is_admin = ($_G['uid'] && $_G['adminid'] > 0) ? true : false;
    
    echo json_encode(array('status' => 'success', 'data' => $messages, 'is_admin' => $is_admin, 'enable_mention' => $enable_mention));
    exit;
}
elseif($action == 'delete') { /* ปล่อยไว้เหมือนเดิม (ประหยัดพื้นที่) */
    if(!$_G['uid'] || $_G['adminid'] <= 0) { echo json_encode(array('status' => 'error', 'msg' => 'ไม่มีสิทธิ์ในการลบข้อความ')); exit; }
    $msg_id = isset($_GET['msg_id']) ? intval($_GET['msg_id']) : 0;
    if($msg_id) DB::delete('prasopkan_chat_messages', "msg_id='$msg_id'");
    echo json_encode(array('status' => 'success')); exit;
}
elseif($action == 'upload') { /* ปล่อยไว้เหมือนเดิม (ประหยัดพื้นที่) */
    if(!$_G['uid']) { echo json_encode(array('status'=>'error','msg'=>'กรุณาล็อกอิน')); exit; }
    $upload_dir = DISCUZ_ROOT . './source/plugin/prasopkan_chat/uploads/';
    if(!is_dir($upload_dir)) { @mkdir($upload_dir, 0777, true); }
    $file = $_FILES['chat_image'];
    if(!$file || $file['error'] != 0) { echo json_encode(array('status'=>'error','msg'=>'อัปโหลดล้มเหลว')); exit; }
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = array('jpg', 'jpeg', 'png', 'gif', 'webp');
    if(!in_array($ext, $allowed)) { echo json_encode(array('status'=>'error','msg'=>'รองรับรูปภาพเท่านั้น')); exit; }
    $filename = 'img_'.$_G['uid'].'_'.time().'_'.rand(1000,9999).'.'.$ext;
    if(move_uploaded_file($file['tmp_name'], $upload_dir.$filename)) {
        echo json_encode(array('status'=>'success', 'url'=>'source/plugin/prasopkan_chat/uploads/'.$filename));
    } else { echo json_encode(array('status'=>'error','msg'=>'บันทึกไฟล์ไม่ได้')); } exit;
}
// --- 5. ฟังก์ชันสร้างอั่งเปา (Send Red Packet) 🧧 ---
elseif($action == 'send_redpacket') {
    loadcache('plugin');
    $config = $_G['cache']['plugin']['prasopkan_chat'];
    if(!$config['enable_redpacket']) { echo json_encode(['status'=>'error', 'msg'=>'ระบบแจกอั่งเปาปิดใช้งานอยู่']); exit; }
    if(!$_G['uid']) { echo json_encode(['status'=>'error', 'msg'=>'กรุณาล็อกอิน']); exit; }

    $amount = intval($_GET['amount']);
    $count = intval($_GET['count']);
    $credit_id = intval($config['redpacket_credit_id']);
    
    if($amount < intval($config['redpacket_min']) || $amount > intval($config['redpacket_max'])) { 
        echo json_encode(['status'=>'error', 'msg'=>"ตั้งจำนวนเงินได้ระหว่าง {$config['redpacket_min']} - {$config['redpacket_max']}"]); exit; 
    }
    if($count < 1 || $count > $amount) { echo json_encode(['status'=>'error', 'msg'=>"จำนวนซองไม่ถูกต้อง (ต้องไม่เกินจำนวนเครดิต)"]); exit; }

    // เช็คว่าเครดิตพอไหม
    $user_credit = DB::result_first("SELECT extcredits".$credit_id." FROM ".DB::table('common_member_count')." WHERE uid='{$_G['uid']}'");
    if($user_credit < $amount) { echo json_encode(['status'=>'error', 'msg'=>'เครดิตของคุณไม่เพียงพอ!']); exit; }

    // หักเงินคนแจก
    updatemembercount($_G['uid'], array('extcredits'.$credit_id => -$amount));

    // สร้างซองในฐานข้อมูล
    $env_id = DB::insert('prasopkan_chat_envelopes', array(
        'uid' => $_G['uid'], 'total_amount' => $amount, 'total_count' => $count,
        'remain_amount' => $amount, 'remain_count' => $count, 'dateline' => $_G['timestamp']
    ), true);

    // ส่งข้อความเข้าแชท
    $msg_id = DB::insert('prasopkan_chat_messages', array(
        'uid' => $_G['uid'], 'username' => $_G['username'], 'message' => '[redpacket]'.$env_id.'[/redpacket]',
        'dateline' => $_G['timestamp'], 'ip' => $_G['clientip'], 'room_id' => $room_id
    ), true);
    
    DB::update('prasopkan_chat_envelopes', array('msg_id' => $msg_id), "id='$env_id'");
    echo json_encode(['status' => 'success']); exit;
}
// --- 6. ฟังก์ชันรับอั่งเปา (Claim Red Packet) 🧧 ---
elseif($action == 'claim_redpacket') {
    if(!$_G['uid']) { echo json_encode(['status'=>'error', 'msg'=>'กรุณาล็อกอิน']); exit; }
    
    $env_id = intval($_GET['env_id']);
    $env = DB::fetch_first("SELECT * FROM ".DB::table('prasopkan_chat_envelopes')." WHERE id='$env_id'");
    if(!$env) { echo json_encode(['status'=>'error', 'msg'=>'ไม่พบซองอั่งเปานี้']); exit; }
    if($env['remain_count'] <= 0) { echo json_encode(['status'=>'error', 'msg'=>'เสียใจด้วย! อั่งเปาถูกรับไปหมดแล้ว 😭']); exit; }

    // เช็คว่าเคยกดรับไปหรือยัง
    $log = DB::fetch_first("SELECT log_id FROM ".DB::table('prasopkan_chat_envlogs')." WHERE env_id='$env_id' AND uid='{$_G['uid']}'");
    if($log) { echo json_encode(['status'=>'error', 'msg'=>'คุณรับอั่งเปาซองนี้ไปแล้วจ้า!']); exit; }

    // สุ่มจำนวนเงิน
    if($env['remain_count'] == 1) {
        $get_amount = $env['remain_amount'];
    } else {
        $max_get = floor($env['remain_amount'] / $env['remain_count'] * 2);
        $get_amount = rand(1, $max_get);
        if($env['remain_amount'] - $get_amount < $env['remain_count'] - 1) {
            $get_amount = $env['remain_amount'] - ($env['remain_count'] - 1);
        }
    }

    // อัปเดตซองและเพิ่มเงิน
    DB::query("UPDATE ".DB::table('prasopkan_chat_envelopes')." SET remain_amount=remain_amount-$get_amount, remain_count=remain_count-1 WHERE id='$env_id' AND remain_count>0");
    if(DB::affected_rows() > 0) {
        DB::insert('prasopkan_chat_envlogs', array('env_id'=>$env_id, 'uid'=>$_G['uid'], 'username'=>$_G['username'], 'amount'=>$get_amount, 'dateline'=>$_G['timestamp']));
        loadcache('plugin');
        $credit_id = intval($_G['cache']['plugin']['prasopkan_chat']['redpacket_credit_id']);
        updatemembercount($_G['uid'], array('extcredits'.$credit_id => $get_amount)); // เพิ่มเงินให้คนรับ
        echo json_encode(['status'=>'success', 'amount'=>$get_amount]);
    } else {
        echo json_encode(['status'=>'error', 'msg'=>'ซองอั่งเปาหมดพอดี!']);
    }
    exit;
}
?>