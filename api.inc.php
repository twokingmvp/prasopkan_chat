// ==========================================
// 🧠 ระบบ AI Seed Bots (Gemini API) - โหลดจากการตั้งค่าแท็บแยก
// ==========================================
$ai_config = $_G['setting']['prasopkan_chat_aibots'];
if(is_string($ai_config)) { $ai_config = @unserialize($ai_config); }

$enable_ai_bots = isset($ai_config['enable_ai_bots']) ? intval($ai_config['enable_ai_bots']) : 0;
$gemini_api_key = isset($ai_config['gemini_api_key']) ? trim($ai_config['gemini_api_key']) : '';

if($enable_ai_bots && !empty($gemini_api_key) && $action == 'get') {
    
    $today_date = date('Y-m-d', $_G['timestamp']);
    loadcache('prasopkan_chat_ai_usage_'.$today_date);
    $daily_usage = intval($_G['cache']['prasopkan_chat_ai_usage_'.$today_date]);
    $daily_limit = isset($ai_config['ai_daily_limit']) ? intval($ai_config['ai_daily_limit']) : 50;

    if($daily_usage < $daily_limit) {
        
        loadcache('prasopkan_chat_ai_last_talk');
        $last_talk_time = intval($_G['cache']['prasopkan_chat_ai_last_talk']);
        $ai_interval_minutes = isset($ai_config['ai_chat_interval']) ? intval($ai_config['ai_chat_interval']) : 20;
        
        // แก้ปัญหาประเภทตัวแปร: บังคับให้เป็นอาร์เรย์ของตัวเลข (Integer) ทั้งหมด
        $raw_allowed_forums = isset($ai_config['ai_allowed_forums']) && is_array($ai_config['ai_allowed_forums']) ? $ai_config['ai_allowed_forums'] : array(1);
        $ai_allowed_forums = array_map('intval', $raw_allowed_forums);
        $current_room_id = intval($room_id);

        if(($_G['timestamp'] - $last_talk_time > ($ai_interval_minutes * 60)) && in_array($current_room_id, $ai_allowed_forums)) {
            
            savecache('prasopkan_chat_ai_last_talk', $_G['timestamp']);
            savecache('prasopkan_chat_ai_usage_'.$today_date, $daily_usage + 1);

            $ai_bot_list_raw = explode("\n", str_replace("\r", "", $ai_config['ai_bot_list']));
            $bots = array();
            foreach($ai_bot_list_raw as $b) {
                $parts = explode("|", $b);
                if(count($parts) >= 3) { $bots[] = array('name' => trim($parts[0]), 'icon' => trim($parts[1]), 'persona' => trim($parts[2])); }
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

                // แก้ปัญหาโมเดล URL รุ่นเก่า เปลี่ยนเป็นรุ่น gemini-1.5-flash แทน
                $api_url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key=' . $gemini_api_key;
                
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
                curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
                curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/json'));
                curl_setopt($ch, CURLOPT_POST, true);
                curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($post_data));
                curl_setopt($ch, CURLOPT_TIMEOUT, 15);
                
                // 🔥 เพิ่มคำสั่งแก้ปัญหาโฮสติ้งที่บล็อกการเช็ค SSL (มักทำให้ API เงียบหายไปเลย)
                curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false); 
                curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);

                $response = curl_exec($ch);
                
                // ถ้ามี Error ระหว่างยิง cURL (เอาไว้เช็คได้ถ้ายังพังอยู่)
                // $err = curl_error($ch); 
                
                curl_close($ch);

                if($response) {
                    $res_json = json_decode($response, true);
                    if(isset($res_json['candidates'][0]['content']['parts'][0]['text'])) {
                        $ai_reply = trim($res_json['candidates'][0]['content']['parts'][0]['text']);
                        $ai_reply = preg_replace('/^'.$selected_bot['name'].'\s*:\s*/i', '', $ai_reply);
                        $ai_reply = preg_replace('/^\[\s*'.$selected_bot['name'].'\s*\]\s*/i', '', $ai_reply); // ดักกันเผื่อพิมพ์แบบ [ชื่อ]

                        if(!empty($ai_reply)) {
                            DB::insert('prasopkan_chat_messages', array(
                                'uid' => 0, 
                                'username' => $selected_bot['name'], 
                                'message' => $ai_reply, 
                                'dateline' => $_G['timestamp'], 
                                'ip' => '127.0.0.1', 
                                'room_id' => $current_room_id,
                                'badge_icon' => $selected_bot['icon']
                            ));
                        }
                    }
                }
            }
        }
    }
}