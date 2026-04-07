<?php
if(!defined('IN_DISCUZ')) {
    exit('Access Denied');
}

global $_G;
$action = isset($_GET['action']) ? $_GET['action'] : '';

// รับค่า room_id ถ้าไม่ได้เลือกให้ค่าเริ่มต้นคือห้อง 1
$room_id = isset($_GET['room_id']) ? intval($_GET['room_id']) : 1; 

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
        'room_id' => $room_id // บันทึกไอดีห้องแชท
    );
    DB::insert('prasopkan_chat_messages', $data);

    echo json_encode(array('status' => 'success'));
    exit;
} 
elseif($action == 'get') {
    $messages = array();
    // ดึงข้อมูลโดยฟิลเตอร์เฉพาะข้อความของห้องที่ผู้ใช้กำลังเลือกอยู่
    $query = DB::query("SELECT * FROM ".DB::table('prasopkan_chat_messages')." WHERE room_id='$room_id' ORDER BY dateline DESC LIMIT 50");
    
    while($row = DB::fetch($query)) {
        $row['time'] = dgmdate($row['dateline'], 'H:i');
        $messages[] = $row;
    }
    $messages = array_reverse($messages);
    
    echo json_encode(array('status' => 'success', 'data' => $messages));
    exit;
}
?>