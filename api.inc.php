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
// --- 2. ดึงข้อความและแปลงรูปภาพ ---
elseif($action == 'get') {
    $messages = array();
    $query = DB::query("SELECT * FROM ".DB::table('prasopkan_chat_messages')." WHERE room_id='$room_id' ORDER BY dateline DESC LIMIT 50");
    
    while($row = DB::fetch($query)) {
        $row['time'] = dgmdate($row['dateline'], 'H:i');
        
        // แปลง BBCode รูปภาพ [img]url[/img] ให้แสดงเป็นรูปภาพ HTML
        $row['message'] = preg_replace('/\[img\](.*?)\[\/img\]/i', '<br><img src="$1" style="max-width:100%; border-radius:8px; margin-top:5px; border:1px solid #ddd;" />', $row['message']);
        
        $messages[] = $row;
    }
    $messages = array_reverse($messages);
    $is_admin = ($_G['uid'] && $_G['adminid'] > 0) ? true : false;
    
    echo json_encode(array('status' => 'success', 'data' => $messages, 'is_admin' => $is_admin));
    exit;
}
// --- 3. ฟังก์ชันลบข้อความ ---
elseif($action == 'delete') {
    if(!$_G['uid'] || $_G['adminid'] <= 0) {
        echo json_encode(array('status' => 'error', 'msg' => 'ไม่มีสิทธิ์ในการลบข้อความ'));
        exit;
    }
    $msg_id = isset($_GET['msg_id']) ? intval($_GET['msg_id']) : 0;
    if($msg_id) DB::delete('prasopkan_chat_messages', "msg_id='$msg_id'");
    echo json_encode(array('status' => 'success'));
    exit;
}
// --- 4. ฟังก์ชันอัปโหลดรูปภาพ 📸 ---
elseif($action == 'upload') {
    if(!$_G['uid']) { echo json_encode(array('status'=>'error','msg'=>'กรุณาล็อกอิน')); exit; }

    // สร้างโฟลเดอร์ uploads อัตโนมัติถ้ายังไม่มี
    $upload_dir = DISCUZ_ROOT . './source/plugin/prasopkan_chat/uploads/';
    if(!is_dir($upload_dir)) {
        @mkdir($upload_dir, 0777, true);
    }

    $file = $_FILES['chat_image'];
    if(!$file || $file['error'] != 0) {
        echo json_encode(array('status'=>'error','msg'=>'อัปโหลดล้มเหลว')); exit;
    }

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    $allowed = array('jpg', 'jpeg', 'png', 'gif', 'webp');
    if(!in_array($ext, $allowed)) {
        echo json_encode(array('status'=>'error','msg'=>'รองรับเฉพาะรูปภาพ jpg, png, gif, webp')); exit;
    }

    // ตั้งชื่อไฟล์ใหม่ไม่ให้ซ้ำ
    $filename = 'img_' . $_G['uid'] . '_' . time() . '_' . rand(1000,9999) . '.' . $ext;
    $dest = $upload_dir . $filename;

    if(move_uploaded_file($file['tmp_name'], $dest)) {
        $img_url = 'source/plugin/prasopkan_chat/uploads/' . $filename;
        echo json_encode(array('status'=>'success', 'url'=>$img_url));
    } else {
        echo json_encode(array('status'=>'error','msg'=>'เซิร์ฟเวอร์ไม่สามารถบันทึกไฟล์ได้'));
    }
    exit;
}
?>