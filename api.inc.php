<?php
if(!defined('IN_DISCUZ')) { exit('Access Denied'); }

global $_G;
$action = isset($_GET['action']) ? $_GET['action'] : '';
$room_id = isset($_GET['room_id']) ? intval($_GET['room_id']) : 1; 

$shop_items = array('name_style' => array(), 'badge' => array(), 'bubble_skin' => array(), 'gacha' => array());
$q_shop = DB::query("SELECT * FROM ".DB::table('prasopkan_chat_shop_items')." ORDER BY displayorder ASC");
while($s = DB::fetch($q_shop)) {
    $shop_items[$s['item_type']][$s['item_key']] = array('name' => $s['name'], 'price' => $s['price'], 'css' => $s['css'], 'icon' => $s['icon']);
}

if($action == 'send') {
    $message = dhtmlspecialchars(trim($_GET['message']));
    if(empty($message)) { echo json_encode(array('status' => 'error', 'msg' => 'กรุณาพิมพ์ข้อความ')); exit; }
    if(!$_G['uid']) { echo json_encode(array('status' => 'error', 'msg' => 'กรุณาล็อกอินก่อนส่งข้อความ')); exit; }

    // 🔥 คำสั่งลับสำหรับแอดมิน (Live Test)
    if($message === '!testbot' && $_G['adminid'] == 1) {
        $ai_config = $_G['setting']['prasopkan_chat_aibots'];
        if(is_string($ai_config)) { $ai_config = @unserialize($ai_config); }
        
        $key = isset($ai_config['gemini_api_key']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $ai_config['gemini_api_key']) : '';
        
        if(empty($key)) {
            DB::insert('prasopkan_chat_messages', array('uid' => 0, 'username' => '🛠️ System', 'message' => 'ไม่พบ API Key กรุณาตั้งค่าหลังบ้านก่อน', 'dateline' => $_G['timestamp'], 'ip' => '127.0.0.1', 'room_id' => $room_id));
            echo json_encode(array('status' => 'success')); exit;
        }

        $masked = substr($key, 0, 5) . '***' . substr($key, -4);
        // 🚀 อัปเดตชื่อ Model เป็น gemini-1.5-flash-latest
        $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=' . $key;
        $post_data = array("contents" => array(array("parts" => array(array("text" => "ตอบกลับมาสั้นๆ ว่า '✅ ระบบ AI เชื่อมต่อสำเร็จแล้ว!'")))));
        
        $ch = curl_init($api_url);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Referer: ' . $_G['siteurl'] ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $response = curl_exec($ch);
        $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $err = curl_error($ch);
        curl_close($ch);

        if($response === false && stripos($err, 'SSL') !== false) {
            $ctx = stream_context_create(array(
                'http' => array('method' => 'POST', 'header' => "Content-Type: application/json\r\nReferer: " . $_G['siteurl'] . "\r\n", 'content' => json_encode($post_data), 'timeout' => 15, 'ignore_errors' => true),
                'ssl' => array('verify_peer' => false, 'verify_peer_name' => false)
            ));
            $response = @file_get_contents($api_url, false, $ctx);
            if($response !== false) {
                $err = ''; $httpcode = 200;
                if(isset($http_response_header)) {
                    foreach($http_response_header as $h) { if(preg_match('/^HTTP\/\d+\.\d+\s+(\d+)/', $h, $m)) { $httpcode = intval($m[1]); break; } }
                }
            }
        }

        if($response === false) {
            $msg = "cURL Error: " . $err;
        } elseif($httpcode != 200) {
            $err_json = json_decode($response, true);
            $reason = isset($err_json['error']['message']) ? $err_json['error']['message'] : $response;
            $msg = "[เชื่อมต่อล้มเหลว รหัส $httpcode] Google ปฏิเสธคีย์ ($masked): " . $reason;
        } else {
            $res_json = json_decode($response, true);
            $ans = isset($res_json['candidates'][0]['content']['parts'][0]['text']) ? trim($res_json['candidates'][0]['content']['parts'][0]['text']) : 'ไม่สามารถอ่านข้อความได้';
            $msg = "เชื่อมต่อสำเร็จ! Key [$masked] \nAI ตอบว่า: " . $ans;
            savecache('prasopkan_chat_ai_last_talk', 0);
        }

        DB::insert('prasopkan_chat_messages', array('uid' => 0, 'username' => '🛠️ API Test', 'message' => $msg, 'dateline' => $_G['timestamp'], 'ip' => '127.0.0.1', 'room_id' => $room_id));
        echo json_encode(array('status' => 'success')); exit;
    }

    $message = preg_replace('/(?<!\])(https?:\/\/[^\s<]+)/i', '[url=$1]$1[/url]', $message);

    loadcache('plugin');
    $plugin_config = $_G['cache']['plugin']['prasopkan_chat'];
    $auto_cleanup_days = isset($plugin_config['auto_cleanup_days']) ? intval($plugin_config['auto_cleanup_days']) : 7;
    if($auto_cleanup_days > 0) {
        $cleanup_time = $_G['timestamp'] - ($auto_cleanup_days * 86400); 
        DB::query("DELETE FROM ".DB::table('prasopkan_chat_messages')." WHERE dateline < $cleanup_time");
    }

    DB::insert('prasopkan_chat_messages', array('uid' => $_G['uid'], 'username' => $_G['username'], 'message' => $message, 'dateline' => $_G['timestamp'], 'ip' => $_G['clientip'], 'room_id' => $room_id));
    echo json_encode(array('status' => 'success')); exit;
} 
elseif($action == 'typing') {
    if($_G['uid']) { DB::query("REPLACE INTO ".DB::table('prasopkan_chat_typing')." (room_id, uid, username, dateline) VALUES ('$room_id', '{$_G['uid']}', '{$_G['username']}', '{$_G['timestamp']}')"); }
    echo json_encode(array('status'=>'success')); exit;
}
elseif($action == 'get') {
    loadcache('plugin'); loadcache('usergroups'); $plugin_config = $_G['cache']['plugin']['prasopkan_chat'];
    $enable_color = $plugin_config['enable_color']; $enable_mention = $plugin_config['enable_mention'];
    $enable_bot = $plugin_config['enable_bot']; $enable_linkpreview = $plugin_config['enable_linkpreview']; 
    $enable_reaction = $plugin_config['enable_reaction']; $current_uid = $_G['uid']; 

    if($enable_bot) {
        loadcache('prasopkan_chat_last_tid'); $last_tid = intval($_G['cache']['prasopkan_chat_last_tid']);
        $latest_thread = DB::fetch_first("SELECT tid, subject, author FROM ".DB::table('forum_thread')." WHERE displayorder >= 0 ORDER BY tid DESC LIMIT 1");
        if($latest_thread && $latest_thread['tid'] > $last_tid) {
            savecache('prasopkan_chat_last_tid', $latest_thread['tid']);
            if($last_tid > 0) {
                $bot_msg = "📢 มีกระทู้ใหม่มาสดๆ ร้อนๆ: [url=forum.php?mod=viewthread&tid=".$latest_thread['tid']."]".$latest_thread['subject']."[/url]";
                $chat_forums = @unserialize($plugin_config['chat_forums']); if(!is_array($chat_forums)) $chat_forums = array(1);
                foreach($chat_forums as $r_id) { DB::insert('prasopkan_chat_messages', array('uid' => 0, 'username' => '🤖 System Bot', 'message' => $bot_msg, 'dateline' => $_G['timestamp'], 'ip' => '127.0.0.1', 'room_id' => intval($r_id))); }
            }
        }
    }

    $enable_random_bot = isset($plugin_config['enable_random_bot']) ? intval($plugin_config['enable_random_bot']) : 0;
    if($enable_random_bot) {
        loadcache('prasopkan_chat_random_bot_time');
        $last_random_time = intval($_G['cache']['prasopkan_chat_random_bot_time']);
        $interval_minutes = isset($plugin_config['bot_interval']) ? intval($plugin_config['bot_interval']) : 60;
        $random_interval = $interval_minutes * 60;

        if($_G['timestamp'] - $last_random_time > $random_interval) {
            savecache('prasopkan_chat_random_bot_time', $_G['timestamp']);
            $min_replies = isset($plugin_config['bot_min_replies']) ? intval($plugin_config['bot_min_replies']) : 5;

            $random_thread = DB::fetch_first("SELECT tid, subject, author, views, replies FROM ".DB::table('forum_thread')."
                WHERE displayorder = 0 AND closed = 0 AND replies >= $min_replies
                ORDER BY RAND() LIMIT 1");

            if($random_thread) {
                $bot_msg = "💡 แวะมาป้ายยากระทู้เด็ด: [url=forum.php?mod=viewthread&tid=".$random_thread['tid']."]".$random_thread['subject']."[/url] (เข้าชม ".$random_thread['views']." ครั้ง, ตอบกลับ ".$random_thread['replies']." ครั้ง)";
                $chat_forums = @unserialize($plugin_config['chat_forums']);
                if(!is_array($chat_forums)) $chat_forums = array(1);

                foreach($chat_forums as $r_id) {
                    DB::insert('prasopkan_chat_messages', array('uid' => 0, 'username' => '🤖 System Bot', 'message' => $bot_msg, 'dateline' => $_G['timestamp'], 'ip' => '127.0.0.1', 'room_id' => intval($r_id)));
                }
            }
        }
    }

    // ==========================================
    // 🧠 3. ระบบ AI Seed Bots (Gemini API) 
    // ==========================================
    $ai_config = $_G['setting']['prasopkan_chat_aibots'];
    if(is_string($ai_config)) { $ai_config = @unserialize($ai_config); }

    $enable_ai_bots = isset($ai_config['enable_ai_bots']) ? intval($ai_config['enable_ai_bots']) : 0;
    $gemini_api_key = isset($ai_config['gemini_api_key']) ? preg_replace('/[^a-zA-Z0-9_-]/', '', $ai_config['gemini_api_key']) : '';

    if($enable_ai_bots && !empty($gemini_api_key)) {
        
        $today_date = date('Y-m-d', $_G['timestamp']);
        loadcache('prasopkan_chat_ai_usage_'.$today_date);
        $daily_usage = intval($_G['cache']['prasopkan_chat_ai_usage_'.$today_date]);
        $daily_limit = isset($ai_config['ai_daily_limit']) ? intval($ai_config['ai_daily_limit']) : 50;

        if($daily_usage < $daily_limit) {
            
            loadcache('prasopkan_chat_ai_last_talk');
            $last_talk_time = intval($_G['cache']['prasopkan_chat_ai_last_talk']);
            $ai_interval_minutes = isset($ai_config['ai_chat_interval']) ? intval($ai_config['ai_chat_interval']) : 20;
            
            $raw_allowed_forums = isset($ai_config['ai_allowed_forums']) && is_array($ai_config['ai_allowed_forums']) ? $ai_config['ai_allowed_forums'] : array(1);
            $ai_allowed_forums = array_map('intval', $raw_allowed_forums);
            $current_room_id = intval($room_id);

            $is_time_to_talk = ($last_talk_time == 0) || ($_G['timestamp'] - $last_talk_time > ($ai_interval_minutes * 60));

            if($is_time_to_talk && in_array($current_room_id, $ai_allowed_forums)) {
                
                savecache('prasopkan_chat_ai_last_talk', $_G['timestamp']);
                savecache('prasopkan_chat_ai_usage_'.$today_date, $daily_usage + 1);

                $ai_bot_list_raw = explode("\n", str_replace("\r", "", $ai_config['ai_bot_list']));
                $bots = array();
                foreach($ai_bot_list_raw as $b) {
                    $parts = explode("|", $b);
                    if(count($parts) >= 3) { $bots[] = array('name' => trim($parts[1]) . ' ' . trim($parts[0]), 'persona' => trim($parts[2])); }
                }

                if(!empty($bots)) {
                    $selected_bot = $bots[array_rand($bots)];
                    
                    $topics_raw = explode("\n", str_replace("\r", "", $ai_config['ai_chat_topic']));
                    $valid_topics = array();
                    foreach($topics_raw as $t) { if(trim($t) !== '') $valid_topics[] = trim($t); }
                    $selected_topic = !empty($valid_topics) ? $valid_topics[array_rand($valid_topics)] : 'เรื่องทั่วไป';
                    
                    $context = "ประวัติการสนทนาล่าสุดในห้องแชท:\n";
                    $q_history = DB::query("SELECT username, message FROM ".DB::table('prasopkan_chat_messages')." WHERE room_id='$current_room_id' AND uid != 0 ORDER BY dateline DESC LIMIT 5");
                    $chat_history = array();
                    while($h = DB::fetch($q_history)) { $chat_history[] = $h['username'].": ".$h['message']; }
                    if(!empty($chat_history)) { $context .= implode("\n", array_reverse($chat_history)); } 
                    else { $context .= "(ยังไม่มีใครคุยกันเลย คุณเป็นคนแรก)"; }

                    $system_prompt = "คุณคือผู้ใช้งานเว็บบอร์ดชาวไทย ชื่อของคุณคือ '{$selected_bot['name']}' บุคลิกของคุณคือ: {$selected_bot['persona']}\n\n";
                    $system_prompt .= "หัวข้อหลักที่เว็บนี้ชอบคุยกันคือ: {$selected_topic}\n";
                    $system_prompt .= "กฎข้อบังคับ (สำคัญมาก):\n";
                    $system_prompt .= "1. ห้ามบอกเด็ดขาดว่าคุณเป็น AI หรือ Assistant คุณคือคนจริงๆ ที่กำลังพิมพ์แชท\n";
                    $system_prompt .= "2. พิมพ์ตอบสั้นๆ สไตล์การพิมพ์แชท (ความยาว 1-3 ประโยค) ไม่ต้องเขียนเรียงความ\n";
                    $system_prompt .= "3. ใช้คำศัพท์วัยรุ่น หรือคำสร้อยแบบธรรมชาติ ตามบุคลิก\n";
                    $system_prompt .= "4. พิมพ์เฉพาะข้อความที่คุณจะตอบ ห้ามพิมพ์ชื่อตัวเองนำหน้า\n";
                    $system_prompt .= "5. อ่านประวัติแชทด้านล่าง แล้วพิจารณาว่าจะ 'ตอบกลับเพื่อน' หรือ 'ชวนคุยเรื่องใหม่' ให้เข้ากับหัวข้อหลักที่กำหนดไว้ให้เนียนที่สุด\n\n";
                    $system_prompt .= $context;

                    // 🚀 อัปเดตชื่อ Model เป็น gemini-1.5-flash-latest สำหรับบอทหลัก
                    $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash-latest:generateContent?key=' . $gemini_api_key;
                    
                    $post_data = array(
                        "contents" => array(
                            array("parts" => array( array("text" => $system_prompt) ))
                        ),
                        "generationConfig" => array(
                            "temperature" => 0.8,
                            "maxOutputTokens" => 150
                        )
                    );

                    $ch = curl_init($api_url);
                    curl_setopt($ch, CURLOPT_POST, 1);
                    curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
                    curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json', 'Referer: ' . $_G['siteurl']));
                    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, 0); 
                    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 0);
                    $response = curl_exec($ch);
                    $httpcode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                    $curlerror = curl_error($ch);
                    curl_close($ch);

                    if($response === false && stripos($curlerror, 'SSL') !== false) {
                        $ctx = stream_context_create(array(
                            'http' => array('method' => 'POST', 'header' => "Content-Type: application/json\r\nReferer: " . $_G['siteurl'] . "\r\n", 'content' => json_encode($post_data), 'timeout' => 15, 'ignore_errors' => true),
                            'ssl' => array('verify_peer' => false, 'verify_peer_name' => false)
                        ));
                        $response = @file_get_contents($api_url, false, $ctx);
                        if($response !== false) { 
                            $curlerror = ''; $httpcode = 200;
                            if(isset($http_response_header)) { foreach($http_response_header as $h) { if(preg_match('/^HTTP\/\d+\.\d+\s+(\d+)/', $h, $m)) { $httpcode = intval($m[1]); break; } } }
                        }
                    }

                    if($response === false) {
                        DB::insert('prasopkan_chat_messages', array('uid' => 0, 'username' => '🛠️ Debug Bot', 'message' => "[ระบบขัดข้อง] ยิง API ไม่ผ่าน (cURL Error): " . $curlerror, 'dateline' => $_G['timestamp'], 'ip' => '127.0.0.1', 'room_id' => $current_room_id));
                    } elseif ($httpcode != 200) {
                        $err_data = json_decode($response, true);
                        $err_msg = isset($err_data['error']['message']) ? $err_data['error']['message'] : "รหัส ($httpcode)";
                        DB::insert('prasopkan_chat_messages', array('uid' => 0, 'username' => '🛠️ Debug Bot', 'message' => "[API Error] Google ปฏิเสธ: " . $err_msg, 'dateline' => $_G['timestamp'], 'ip' => '127.0.0.1', 'room_id' => $current_room_id));
                    } else {
                        $res_json = json_decode($response, true);
                        if(isset($res_json['candidates'][0]['content']['parts'][0]['text'])) {
                            $ai_reply = trim($res_json['candidates'][0]['content']['parts'][0]['text']);
                            $ai_reply = preg_replace('/^'.$selected_bot['name'].'\s*:\s*/i', '', $ai_reply);
                            $ai_reply = preg_replace('/^\[\s*'.$selected_bot['name'].'\s*\]\s*/i', '', $ai_reply);

                            if(!empty($ai_reply)) {
                                DB::insert('prasopkan_chat_messages', array(
                                    'uid' => 0, 
                                    'username' => $selected_bot['name'], 
                                    'message' => $ai_reply, 
                                    'dateline' => $_G['timestamp'], 
                                    'ip' => '127.0.0.1', 
                                    'room_id' => $current_room_id
                                ));
                            }
                        }
                    }
                }
            }
        }
    }
    // ==========================================

    DB::query("DELETE FROM ".DB::table('prasopkan_chat_typing')." WHERE dateline < ".($_G['timestamp'] - 6));
    $typing_users = array(); $q_type = DB::query("SELECT username FROM ".DB::table('prasopkan_chat_typing')." WHERE room_id='$room_id' AND uid != '{$_G['uid']}'");
    while($t = DB::fetch($q_type)) { $typing_users[] = $t['username']; }

    $messages = array(); $msg_ids = array();
    $query = DB::query("SELECT c.*, m.groupid, e.name_style, e.badge, e.bubble_skin FROM ".DB::table('prasopkan_chat_messages')." c LEFT JOIN ".DB::table('common_member')." m ON c.uid = m.uid LEFT JOIN ".DB::table('prasopkan_chat_equipment')." e ON c.uid = e.uid WHERE c.room_id='$room_id' ORDER BY c.dateline DESC LIMIT 50");
    
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
        } else { $row['message'] = preg_replace('/\[url=(.*?)\](.*?)\[\/url\]/i', '<a href="$1" target="_blank" class="pk-text-link">$2</a>', $row['message']); }

        if($enable_mention) $row['message'] = preg_replace('/@([^\s]+)/u', '<strong class="pk-mention-badge">@$1</strong>', $row['message']);
        $row['message'] = preg_replace('/\[redpacket\](\d+)\[\/redpacket\]/i', '<div class="pk-redpacket-box" data-envid="$1"><div class="pk-rp-icon">🧧</div><div class="pk-rp-text"><b>อั่งเปาเครดิต</b><br><span>คลิกลุ้นรับเครดิต!</span></div></div>', $row['message']);

        $row['name_css'] = ''; $row['badge_icon'] = ''; $row['bubble_css'] = '';
        if(!empty($row['name_style']) && isset($shop_items['name_style'][$row['name_style']])) { $row['name_css'] = $shop_items['name_style'][$row['name_style']]['css']; } 
        elseif($row['uid'] == 0) { $row['name_css'] = 'color:#ff6600;'; } 
        elseif($enable_color && !empty($row['groupid'])) { $group_color = $_G['cache']['usergroups'][$row['groupid']]['color']; if(!empty($group_color)) $row['name_css'] = 'color:'.$group_color.';'; }

        if(!empty($row['badge']) && isset($shop_items['badge'][$row['badge']])) { $row['badge_icon'] = $shop_items['badge'][$row['badge']]['icon']; }
        if(!empty($row['bubble_skin']) && isset($shop_items['bubble_skin'][$row['bubble_skin']])) { $row['bubble_css'] = $shop_items['bubble_skin'][$row['bubble_skin']]['css']; }

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
    $messages = array_reverse($messages); $is_admin = ($_G['uid'] && $_G['adminid'] > 0) ? true : false;
    
    echo json_encode(array('status' => 'success', 'data' => $messages, 'is_admin' => $is_admin, 'enable_mention' => $enable_mention, 'enable_reaction' => $enable_reaction, 'current_uid' => $current_uid, 'typing_users' => $typing_users)); exit;
}
elseif($action == 'shop_info') {
    if(!$_G['uid']) exit;
    loadcache('plugin'); $config = $_G['cache']['plugin']['prasopkan_chat'];
    
    $credit_id = intval($config['shop_credit_id'] ? $config['shop_credit_id'] : 2);
    $user_credit = DB::result_first("SELECT extcredits".$credit_id." FROM ".DB::table('common_member_count')." WHERE uid='{$_G['uid']}'");
    
    $inventory = array(); $q_inv = DB::query("SELECT item_key FROM ".DB::table('prasopkan_chat_inventory')." WHERE uid='{$_G['uid']}'");
    while($inv = DB::fetch($q_inv)) { $inventory[] = $inv['item_key']; }
    
    $equipment = DB::fetch_first("SELECT name_style, badge, bubble_skin FROM ".DB::table('prasopkan_chat_equipment')." WHERE uid='{$_G['uid']}'");
    if(!$equipment) $equipment = array('name_style'=>'', 'badge'=>'', 'bubble_skin'=>'');

    echo json_encode(array('status'=>'success', 'items'=>$shop_items, 'inventory'=>$inventory, 'equipment'=>$equipment, 'credit'=>intval($user_credit), 'credit_name'=>$_G['setting']['extcredits'][$credit_id]['title'])); exit;
}
elseif($action == 'shop_buy') {
    if(!$_G['uid']) { echo json_encode(['status'=>'error', 'msg'=>'กรุณาล็อกอิน']); exit; }
    $item_key = trim($_GET['item_key']); $item_type = trim($_GET['item_type']); 
    if(!isset($shop_items[$item_type][$item_key])) { echo json_encode(['status'=>'error', 'msg'=>'ไม่พบสินค้านี้']); exit; }

    loadcache('plugin'); $config = $_G['cache']['plugin']['prasopkan_chat'];
    
    $credit_id = intval($config['shop_credit_id'] ? $config['shop_credit_id'] : 2);
    $price = intval($shop_items[$item_type][$item_key]['price']);
    $user_credit = DB::result_first("SELECT extcredits".$credit_id." FROM ".DB::table('common_member_count')." WHERE uid='{$_G['uid']}'");
    
    $owned = array();
    $q_inv = DB::query("SELECT item_key FROM ".DB::table('prasopkan_chat_inventory')." WHERE uid='{$_G['uid']}'");
    while($inv = DB::fetch($q_inv)) { $owned[] = $inv['item_key']; }

    if($item_type == 'gacha') {
        $target_type = $shop_items['gacha'][$item_key]['css']; 
        $pool = array();
        
        if($target_type == 'all') {
            $pool = array_merge(array_keys($shop_items['name_style']), array_keys($shop_items['badge']), array_keys($shop_items['bubble_skin']));
        } elseif(isset($shop_items[$target_type])) {
            $pool = array_keys($shop_items[$target_type]);
        }

        $available_pool = array_diff($pool, $owned);
        if(empty($available_pool)) { echo json_encode(['status'=>'error', 'msg'=>'คุณมีไอเทมในกล่องนี้ครบหมดแล้ว! ไม่ต้องสุ่มแล้วจ้า 🎉']); exit; }

        if($user_credit < $price) { echo json_encode(['status'=>'error', 'msg'=>'เครดิตไม่พอสุ่มกาชาจ้า!']); exit; }

        $won_key = $available_pool[array_rand($available_pool)];
        $won_name = '';
        foreach(array('name_style', 'badge', 'bubble_skin') as $t) {
            if(isset($shop_items[$t][$won_key])) { $won_name = $shop_items[$t][$won_key]['name']; break; }
        }

        updatemembercount($_G['uid'], array('extcredits'.$credit_id => -$price));
        DB::insert('prasopkan_chat_inventory', array('uid'=>$_G['uid'], 'item_key'=>$won_key, 'dateline'=>$_G['timestamp']));
        
        echo json_encode(['status'=>'success', 'is_gacha'=>true, 'won_name'=>$won_name]); exit;
    } 
    else {
        if(in_array($item_key, $owned)) { echo json_encode(['status'=>'error', 'msg'=>'คุณมีไอเทมชิ้นนี้อยู่แล้ว!']); exit; }
        if($user_credit < $price) { echo json_encode(['status'=>'error', 'msg'=>'เครดิตไม่พอจ้า!']); exit; }

        updatemembercount($_G['uid'], array('extcredits'.$credit_id => -$price));
        DB::insert('prasopkan_chat_inventory', array('uid'=>$_G['uid'], 'item_key'=>$item_key, 'dateline'=>$_G['timestamp']));
        echo json_encode(['status'=>'success']); exit;
    }
}
elseif($action == 'shop_equip') {
    if(!$_G['uid']) { echo json_encode(['status'=>'error', 'msg'=>'กรุณาล็อกอิน']); exit; }
    $item_key = trim($_GET['item_key']); $item_type = trim($_GET['item_type']);
    if($item_key !== '') {
        $check_inv = DB::fetch_first("SELECT item_key FROM ".DB::table('prasopkan_chat_inventory')." WHERE uid='{$_G['uid']}' AND item_key='$item_key'");
        if(!$check_inv) { echo json_encode(['status'=>'error', 'msg'=>'คุณยังไม่มีไอเทมชิ้นนี้']); exit; }
    }
    $eq = DB::fetch_first("SELECT uid FROM ".DB::table('prasopkan_chat_equipment')." WHERE uid='{$_G['uid']}'");
    if(!$eq) DB::insert('prasopkan_chat_equipment', array('uid'=>$_G['uid']));
    if(in_array($item_type, array('name_style', 'badge', 'bubble_skin'))) { DB::update('prasopkan_chat_equipment', array($item_type => $item_key), "uid='{$_G['uid']}'"); }
    echo json_encode(['status'=>'success']); exit;
}
elseif($action == 'react') { 
    if(!$_G['uid']) { echo json_encode(array('status'=>'error', 'msg'=>'กรุณาล็อกอิน')); exit; }
    $msg_id = intval($_GET['msg_id']); $reaction = dhtmlspecialchars(trim($_GET['reaction']));
    if(!$msg_id || !$reaction) { echo json_encode(array('status'=>'error')); exit; }
    $exists = DB::fetch_first("SELECT id FROM ".DB::table('prasopkan_chat_reactions')." WHERE msg_id='$msg_id' AND uid='{$_G['uid']}' AND reaction='$reaction'");
    if($exists) DB::delete('prasopkan_chat_reactions', "id='{$exists['id']}'"); else DB::insert('prasopkan_chat_reactions', array('msg_id'=>$msg_id, 'uid'=>$_G['uid'], 'reaction'=>$reaction));
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
    $amount = intval($_GET['amount']); $count = intval($_GET['count']); $credit_id = intval($config['redpacket_credit_id'] ? $config['redpacket_credit_id'] : 2);
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
        loadcache('plugin'); $config = $_G['cache']['plugin']['prasopkan_chat'];
        $credit_id = intval($config['redpacket_credit_id'] ? $config['redpacket_credit_id'] : 2);
        updatemembercount($_G['uid'], array('extcredits'.$credit_id => $get_amount));
        echo json_encode(['status'=>'success', 'amount'=>$get_amount]);
    } else { echo json_encode(['status'=>'error', 'msg'=>'ซองอั่งเปาหมดพอดี!']); } exit;
}
?>