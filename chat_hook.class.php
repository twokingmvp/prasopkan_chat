<?php
if(!defined('IN_DISCUZ')) {
    exit('Access Denied');
}

class plugin_prasopkan_chat {
    public function global_footer() {
        global $_G;
        
        // บังคับโหลดแคชการตั้งค่าปลั๊กอินให้ชัวร์ 100%
        loadcache('plugin');
        $plugin_config = $_G['cache']['plugin']['prasopkan_chat'];
        
        $enable_rooms = $plugin_config['enable_rooms'];
        $chat_forums = $plugin_config['chat_forums'];
        
        $rooms_html = '';
        
        // เช็คว่าเปิดใช้งานและมีการเลือกบอร์ดไว้หรือไม่
        if($enable_rooms) {
            // แปลงค่า forums ที่ได้จากระบบให้เป็น Array
            if(!is_array($chat_forums)) {
                $chat_forums = @unserialize($chat_forums);
            }
            
            if(!empty($chat_forums) && is_array($chat_forums)) {
                $fids = implode(',', array_map('intval', $chat_forums));
                
                if($fids) {
                    // ไปค้นหาชื่อบอร์ดจากฐานข้อมูล
                    $query = DB::query("SELECT fid, name FROM ".DB::table('forum_forum')." WHERE fid IN ($fids) ORDER BY displayorder");
                    $rooms = array();
                    while($row = DB::fetch($query)) {
                        $rooms[] = $row;
                    }

                    // สร้าง HTML สำหรับแท็บห้องแชท
                    if(!empty($rooms)) {
                        $rooms_html .= '<div id="pk-chat-rooms">';
                        $first = true;
                        foreach($rooms as $r) {
                            $active = $first ? ' active' : '';
                            $rooms_html .= '<div class="pk-room-tab'.$active.'" data-room="'.$r['fid'].'">'.strip_tags($r['name']).'</div>';
                            $first = false;
                        }
                        $rooms_html .= '</div>';
                    }
                }
            }
        }

        // ตัวแปรเช็คการล็อกอิน ส่งไปให้ HTML ตรวจสอบ
        $is_logged_in = ($_G['uid'] > 0) ? true : false;

        ob_start();
        include template('prasopkan_chat:chat_ui');
        $html = ob_get_contents();
        ob_end_clean();
        
        return $html;
    }
}
?>