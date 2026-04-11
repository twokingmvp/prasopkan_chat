<?php
if(!defined('IN_DISCUZ')) { exit('Access Denied'); }

global $_G;
$action = isset($_GET['action']) ? $_GET['action'] : '';
$room_id = isset($_GET['room_id']) ? intval($_GET['room_id']) : 1;

// 🛠️ สร้างตารางเก็บเลเวลอัตโนมัติ (ถ้ายังไม่มี)
DB::query("CREATE TABLE IF NOT EXISTS ".DB::table('prasopkan_chat_levels')." (
    uid mediumint(8) unsigned NOT NULL,
    username varchar(255) NOT NULL,
    exp int(10) unsigned NOT NULL DEFAULT '0',
    level int(10) unsigned NOT NULL DEFAULT '1',
    last_update int(10) unsigned NOT NULL DEFAULT '0',
    PRIMARY KEY (uid)
) ENGINE=MyISAM DEFAULT CHARSET=utf8;");

if(!$_G['uid']) { echo json_encode(['status'=>'error', 'msg'=>'กรุณาล็อกอิน']); exit; }

// 🎮 1. ระบบเพิ่ม EXP เมื่อพิมพ์แชท
if($action == 'add_exp') {
    $user = DB::fetch_first("SELECT * FROM ".DB::table('prasopkan_chat_levels')." WHERE uid='{$_G['uid']}'");
    if(!$user) {
        DB::insert('prasopkan_chat_levels', ['uid'=>$_G['uid'], 'username'=>$_G['username'], 'exp'=>10, 'level'=>1, 'last_update'=>$_G['timestamp']]);
        echo json_encode(['status'=>'success']); exit;
    }
    
    // ป้องกันการสแปม (ต้องห่างกัน 15 วินาทีถึงจะได้ EXP)
    if($_G['timestamp'] - $user['last_update'] < 15) { echo json_encode(['status'=>'cooldown']); exit; }

    $gain = rand(5, 15); // สุ่มได้ EXP 5-15 ต่อข้อความ
    $new_exp = $user['exp'] + $gain;
    $next_level_exp = $user['level'] * 100; // ใช้ 100 EXP ในการขึ้นเลเวล 2, 200 EXP สำหรับเลเวล 3...
    $new_level = $user['level'];

    if($new_exp >= $next_level_exp) {
        $new_level++;
        // แจกรางวัล 5 เครดิต (คุณแก้เลข extcredits2 เป็นหน่วยเงินเว็บคุณได้)
        updatemembercount($_G['uid'], array('extcredits2' => 5));
        
        // บอทประกาศแสดงความยินดี
        $msg = "🎉 ขอแสดงความยินดี! คุณ [b]{$_G['username']}[/b] แชทเก่งจนเลเวลอัปเป็นระดับ [color=#ff3b30][b]Lv.{$new_level}[/b][/color] แล้ว! (ได้รับรางวัล 5 เครดิต)";
        DB::insert('prasopkan_chat_messages', array('uid' => 0, 'username' => '🏆 System', 'message' => $msg, 'dateline' => $_G['timestamp'], 'ip' => '127.0.0.1', 'room_id' => $room_id));
    }

    DB::update('prasopkan_chat_levels', ['exp'=>$new_exp, 'level'=>$new_level, 'last_update'=>$_G['timestamp']], "uid='{$_G['uid']}'");
    echo json_encode(['status'=>'success']); exit;
}
// 🏆 2. ระบบดึงข้อมูลกระดานผู้นำ
elseif($action == 'leaderboard') {
    $q = DB::query("SELECT * FROM ".DB::table('prasopkan_chat_levels')." ORDER BY level DESC, exp DESC LIMIT 10");
    $data = []; $rank = 1;
    while($r = DB::fetch($q)) { $r['rank'] = $rank++; $data[] = $r; }
    
    $my_stat = DB::fetch_first("SELECT * FROM ".DB::table('prasopkan_chat_levels')." WHERE uid='{$_G['uid']}'");
    if(!$my_stat) $my_stat = ['level'=>1, 'exp'=>0];
    
    echo json_encode(['status'=>'success', 'data'=>$data, 'my_stat'=>$my_stat]); exit;
}
// 🎲 3. มินิเกมทอยเต๋า (/roll)
elseif($action == 'roll') {
    loadcache('chat_roll_limit_'.$_G['uid']);
    if($_G['cache']['chat_roll_limit_'.$_G['uid']] > $_G['timestamp'] - 10) { echo json_encode(['status'=>'error', 'msg'=>'รอ 10 วินาทีก่อนทอยเต๋าใหม่']); exit; }
    savecache('chat_roll_limit_'.$_G['uid'], $_G['timestamp']);

    $num = rand(1, 100);
    $msg = "🎲 [b]{$_G['username']}[/b] ทอยลูกเต๋าได้แต้ม: [b][color=#ff9800]{$num}[/color][/b] (จาก 100)";
    DB::insert('prasopkan_chat_messages', array('uid' => 0, 'username' => '🎲 Mini-Game', 'message' => $msg, 'dateline' => $_G['timestamp'], 'ip' => '127.0.0.1', 'room_id' => $room_id));
    echo json_encode(['status'=>'success']); exit;
}
?>