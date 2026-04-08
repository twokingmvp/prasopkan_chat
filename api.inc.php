<?php
if(!defined('IN_DISCUZ')) {
    exit('Access Denied');
}

global $_G;
$action = isset($_GET['action']) ? $_GET['action'] : '';
$room_id = isset($_GET['room_id']) ? intval($_GET['room_id']) : 1; 

// --- 1. รับข้อความและบันทึก ---
if($action == 'send') {
    $message = dhtmlspecialchars(trim($_GET['message']));
    
    if(empty($message)) {
        echo json_encode(array('status' => 'error', 'msg' => 'กรุณาพิมพ์ข้อความ'));
        exit;
    }
    if(!$_G['uid']) {
        echo json_encode(array('status' => 'error', 'msg' => 'กรุณาล็อกอินก่อนส่งข้อความ'));
        exit;
    }

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
// --- 2. ดึงข้อความและเช็คสถานะแอดมิน ---
elseif($action == 'get') {
    $messages = array();
    $query = DB::query("SELECT * FROM ".DB::table('prasopkan_chat_messages')." WHERE room_id='$room_id' ORDER BY dateline DESC LIMIT 50");
    
    while($row = DB::fetch($query)) {
        $row['time'] = dgmdate($row['dateline'], 'H:i');
        $messages[] = $row;
    }
    $messages = array_reverse($messages);
    
    // เช็คว่าคนที่กำลังเปิดแชทเป็นแอดมินหรือผู้ดูแลหรือไม่ (adminid > 0)
    $is_admin = ($_G['uid'] && $_G['adminid'] > 0) ? true : false;
    
    echo json_encode(array('status' => 'success', 'data' => $messages, 'is_admin' => $is_admin));
    exit;
}
// --- 3. ฟังก์ชันลบข้อความ (เฉพาะแอดมิน) ---
elseif($action == 'delete') {
    if(!$_G['uid'] || $_G['adminid'] <= 0) {
        echo json_encode(array('status' => 'error', 'msg' => 'ไม่มีสิทธิ์ในการลบข้อความ'));
        exit;
    }
    
    $msg_id = isset($_GET['msg_id']) ? intval($_GET['msg_id']) : 0;
    if($msg_id) {
        DB::delete('prasopkan_chat_messages', "msg_id='$msg_id'");
    }
    
    echo json_encode(array('status' => 'success'));
    exit;
}
?>