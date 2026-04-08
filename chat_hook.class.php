<?php
if(!defined('IN_DISCUZ')) {
    exit('Access Denied');
}

class plugin_prasopkan_chat {
    public function global_footer() {
        global $_G;
        
        // บังคับโหลดแคชการตั้งค่า
        loadcache('plugin');
        $plugin_config = $_G['cache']['plugin']['prasopkan_chat'];
        
        $enable_rooms = $plugin_config['enable_rooms'];
        $chat_forums = $plugin_config['chat_forums'];
        
        $rooms_html = '';
        
        // จัดการสร้างแท็บห้องแชท
        if($enable_rooms) {
            if(!is_array($chat_forums)) {
                $chat_forums = @unserialize($chat_forums);
            }
            
            if(!empty($chat_forums) && is_array($chat_forums)) {
                $fids = implode(',', array_map('intval', $chat_forums));
                
                if($fids) {
                    $query = DB::query("SELECT fid, name FROM ".DB::table('forum_forum')." WHERE fid IN ($fids) ORDER BY displayorder");
                    $rooms = array();
                    while($row = DB::fetch($query)) {
                        $rooms[] = $row;
                    }

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

        ob_start();
        include template('prasopkan_chat:chat_ui');
        $html = ob_get_contents();
        ob_end_clean();
        
        return $html;
    }
}
?>