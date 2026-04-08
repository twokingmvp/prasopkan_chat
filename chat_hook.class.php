<?php
if(!defined('IN_DISCUZ')) {
    exit('Access Denied');
}

class plugin_prasopkan_chat {
    public function global_footer() {
        global $_G;
        
        loadcache('plugin');
        $plugin_config = $_G['cache']['plugin']['prasopkan_chat'];
        
        $enable_rooms = $plugin_config['enable_rooms'];
        $chat_forums = $plugin_config['chat_forums'];
        $enable_image = $plugin_config['enable_image'];
        $enable_redpacket = $plugin_config['enable_redpacket'];
        
        $admin_announcement = trim($plugin_config['admin_announcement']);
        // 💭 ดึงข้อความบอลลูน
        $welcome_tooltip = trim($plugin_config['welcome_tooltip']);
        
        $rooms_html = '';
        
        if($enable_rooms) {
            if(!is_array($chat_forums)) $chat_forums = @unserialize($chat_forums);
            if(!is_array($chat_forums)) $chat_forums = array();
            
            $fids = array_map('intval', $chat_forums);
            
            $rooms_html .= '<div id="pk-chat-rooms">';
            $rooms_html .= '<div class="pk-room-tab active" data-room="1">ทั่วไป</div>';
            
            if(!empty($fids)) {
                $fids_str = implode(',', $fids);
                $query = DB::query("SELECT fid, name FROM ".DB::table('forum_forum')." WHERE fid IN ($fids_str) ORDER BY displayorder");
                while($row = DB::fetch($query)) {
                    if($row['fid'] == 1) continue; 
                    $rooms_html .= '<div class="pk-room-tab" data-room="'.$row['fid'].'">'.strip_tags($row['name']).'</div>';
                }
            }
            $rooms_html .= '</div>';
        }

        $is_logged_in = ($_G['uid'] > 0) ? true : false;

        ob_start();
        include template('prasopkan_chat:chat_ui');
        $html = ob_get_contents();
        ob_end_clean();
        
        return $html;
    }
}
?>