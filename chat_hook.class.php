<?php
if(!defined('IN_DISCUZ')) {
    exit('Access Denied');
}

class plugin_prasopkan_chat {
    public function global_footer() {
        global $_G;
        
        // โหลดการตั้งค่าปลั๊กอิน
        $plugin_config = $_G['cache']['plugin']['prasopkan_chat'];
        $enable_rooms = $plugin_config['enable_rooms'];
        
        // จัดการดึงข้อมูลบอร์ดที่เลือกไว้
        $chat_forums = $plugin_config['chat_forums'];
        if(!is_array($chat_forums)) {
            $chat_forums = @unserialize($chat_forums);
            if(!$chat_forums) $chat_forums = array();
        }

        $rooms_html = '';
        if($enable_rooms && !empty($chat_forums)) {
            $fids = implode(',', array_map('intval', $chat_forums));
            if(!empty($fids)) {
                // ไปค้นหาชื่อบอร์ดจากระบบ Discuz
                $query = DB::query("SELECT fid, name FROM ".DB::table('forum_forum')." WHERE fid IN ($fids) AND status=1 ORDER BY displayorder");
                $rooms = array();
                while($row = DB::fetch($query)) {
                    $rooms[] = $row;
                }

                // สร้างแท็บ HTML จาก PHP เลยเพื่อลดข้อผิดพลาด
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

        // เช็คการล็อกอินให้เด็ดขาดจากฝั่ง PHP
        $is_logged_in = ($_G['uid'] > 0) ? true : false;

        ob_start();
        include template('prasopkan_chat:chat_ui');
        $html = ob_get_contents();
        ob_end_clean();
        
        return $html;
    }
}
?>