<?php
if(!defined('IN_DISCUZ') || !defined('IN_ADMINCP')) {
    exit('Access Denied');
}

// ตรวจสอบว่าเปิดแท็บไหนอยู่ (เพิ่มแท็บ inventory)
$view = isset($_GET['view']) && in_array($_GET['view'], array('name_style', 'badge', 'bubble_skin', 'gacha', 'inventory')) ? trim($_GET['view']) : 'name_style';
$plugin_url = 'plugins&operation=config&do='.$pluginid.'&identifier=prasopkan_chat&pmod=admincp_shop';

if(submitcheck('shop_submit')) {
    if($view == 'inventory') {
        // --- 1. ระบบยึดไอเทมคืน (ลบออกจากกระเป๋า) ---
        if(!empty($_GET['delete_inv']) && is_array($_GET['delete_inv'])) {
            foreach($_GET['delete_inv'] as $uid_item => $val) {
                // แยก uid และ item_key ออกจากกัน
                list($inv_uid, $inv_key) = explode('|', $uid_item);
                $inv_uid = intval($inv_uid);
                $inv_key = trim($inv_key);
                
                // ลบออกจากกระเป๋า
                DB::delete('prasopkan_chat_inventory', "uid='$inv_uid' AND item_key='$inv_key'");
                
                // ถ้าเขากำลังใส่อยู่ ให้ถอดออกด้วย
                DB::query("UPDATE ".DB::table('prasopkan_chat_equipment')." SET name_style='' WHERE uid='$inv_uid' AND name_style='$inv_key'");
                DB::query("UPDATE ".DB::table('prasopkan_chat_equipment')." SET badge='' WHERE uid='$inv_uid' AND badge='$inv_key'");
                DB::query("UPDATE ".DB::table('prasopkan_chat_equipment')." SET bubble_skin='' WHERE uid='$inv_uid' AND bubble_skin='$inv_key'");
            }
        }

        // --- 2. ระบบเสกไอเทมให้สมาชิก (แจกฟรี) ---
        if(!empty($_GET['give_username']) && !empty($_GET['give_item_key'])) {
            $username = trim($_GET['give_username']);
            $item_key = trim($_GET['give_item_key']);
            
            // หา UID จากชื่อ Username
            $target_user = DB::fetch_first("SELECT uid FROM ".DB::table('common_member')." WHERE username='$username'");
            if($target_user) {
                $target_uid = $target_user['uid'];
                // เช็คว่ามีของชิ้นนี้อยู่แล้วหรือยัง
                $exists = DB::result_first("SELECT COUNT(*) FROM ".DB::table('prasopkan_chat_inventory')." WHERE uid='$target_uid' AND item_key='$item_key'");
                if(!$exists) {
                    DB::insert('prasopkan_chat_inventory', array('uid' => $target_uid, 'item_key' => $item_key, 'dateline' => $_G['timestamp']));
                }
            }
        }
        cpmsg('อัปเดตข้อมูลกระเป๋าสมาชิกเรียบร้อยแล้ว', 'action='.$plugin_url.'&view='.$view, 'succeed');
    } 
    else {
        // --- ระบบบันทึก/แก้ไขร้านค้าปกติ ---
        if(is_array($_GET['name'])) {
            foreach($_GET['name'] as $id => $val) {
                $id = intval($id);
                if($_GET['delete'][$id]) {
                    DB::delete('prasopkan_chat_shop_items', "item_id='$id'");
                } else {
                    DB::update('prasopkan_chat_shop_items', array(
                        'name' => trim($_GET['name'][$id]),
                        'price' => intval($_GET['price'][$id]),
                        'css' => trim($_GET['css'][$id]),
                        'icon' => trim($_GET['icon'][$id]),
                        'displayorder' => intval($_GET['displayorder'][$id]),
                    ), "item_id='$id'");
                }
            }
        }
        if(!empty($_GET['new_name']) && !empty($_GET['new_item_key'])) {
            DB::insert('prasopkan_chat_shop_items', array(
                'item_key' => trim($_GET['new_item_key']),
                'name' => trim($_GET['new_name']),
                'price' => intval($_GET['new_price']),
                'item_type' => $view, 
                'css' => trim($_GET['new_css']),
                'icon' => trim($_GET['new_icon']),
                'displayorder' => intval($_GET['new_displayorder']),
            ));
        }
        cpmsg('อัปเดตข้อมูลร้านค้าเรียบร้อยแล้ว', 'action='.$plugin_url.'&view='.$view, 'succeed');
    }
}

// 🗂️ สร้างเมนู 5 แท็บ (เพิ่มกระเป๋าสมาชิก)
showsubmenu('จัดการร้านค้าแชท (Chat Shop)', array(
    array('🎨 สีชื่อ (Name Style)', $plugin_url.'&view=name_style', $view == 'name_style'),
    array('📛 ป้ายฉายา (Badge)', $plugin_url.'&view=badge', $view == 'badge'),
    array('💬 กรอบคำพูด (Bubble)', $plugin_url.'&view=bubble_skin', $view == 'bubble_skin'),
    array('🎁 สุ่มกาชา (Gacha)', $plugin_url.'&view=gacha', $view == 'gacha'),
    array('🎒 กระเป๋าสมาชิก (Inventory)', $plugin_url.'&view=inventory', $view == 'inventory')
));

showformheader($plugin_url.'&view='.$view);

// ==========================================
// ส่วนของการแสดงผล แท็บกระเป๋าสมาชิก
// ==========================================
if($view == 'inventory') {
    showtableheader('รายการสินค้าที่สมาชิกครอบครอง 🎒 (แสดง 100 รายการล่าสุด)');
    showsubtitle(array('ลบ (ยึดคืน)', 'ชื่อผู้ใช้ (Username)', 'ไอเทมที่ครอบครอง', 'ประเภท', 'วันที่ได้รับ'));

    // ดึงข้อมูลกระเป๋า เชื่อมกับตารางสมาชิก และ ตารางสินค้า
    $query = DB::query("SELECT i.*, m.username, s.name as item_name, s.item_type 
                        FROM ".DB::table('prasopkan_chat_inventory')." i 
                        LEFT JOIN ".DB::table('common_member')." m ON i.uid = m.uid 
                        LEFT JOIN ".DB::table('prasopkan_chat_shop_items')." s ON i.item_key = s.item_key 
                        ORDER BY i.dateline DESC LIMIT 100");
    
    while($row = DB::fetch($query)) {
        $date = dgmdate($row['dateline'], 'd/m/Y H:i');
        // สร้าง key พิเศษเพื่อส่งไปลบ (uid|item_key)
        $val_key = $row['uid'].'|'.$row['item_key'];
        $item_display = $row['item_name'] ? "{$row['item_name']} ({$row['item_key']})" : "<i>ไม่ทราบชื่อ ({$row['item_key']})</i>";
        
        showtablerow('', array('class="td25"', '', '', '', ''), array(
            "<input type=\"checkbox\" class=\"checkbox\" name=\"delete_inv[{$val_key}]\" value=\"1\">",
            "<a href=\"home.php?mod=space&uid={$row['uid']}\" target=\"_blank\"><b>{$row['username']}</b></a>",
            $item_display,
            $row['item_type'],
            $date
        ));
    }

    // ดึงรายการสินค้าทั้งหมดมาทำ Dropdown เพื่อให้แอดมินเลือกแจก
    $item_options = '';
    $q_items = DB::query("SELECT item_key, name, item_type FROM ".DB::table('prasopkan_chat_shop_items')." WHERE item_type != 'gacha' ORDER BY item_type, displayorder");
    while($it = DB::fetch($q_items)) {
        $item_options .= "<option value=\"{$it['item_key']}\">[{$it['item_type']}] {$it['name']}</option>";
    }

    showtablerow('', array('class="td25"', '', '', '', ''), array(
        "<b style='color:green;'>เสกไอเทมแจก:</b>",
        "<input type=\"text\" class=\"txt\" name=\"give_username\" value=\"\" placeholder=\"พิมพ์ Username ที่ต้องการแจก\">",
        "<select name=\"give_item_key\"><option value=\"\">-- เลือกไอเทมที่จะแจกฟรี --</option>{$item_options}</select>",
        "<span style='color:#888;'>&lt;- มอบไอเทมให้ผู้ใช้นี้ทันทีโดยไม่หักเครดิต</span>",
        ""
    ));

    showsubmit('shop_submit', 'บันทึกข้อมูล / แจกไอเทม');
} 
// ==========================================
// ส่วนของการแสดงผล แท็บร้านค้าปกติ (1-4)
// ==========================================
else {
    if($view == 'name_style') {
        showtableheader('รายการสินค้า: 🎨 สีชื่อตกแต่ง');
        showsubtitle(array('ลบ', 'ลำดับ', 'รหัสอ้างอิง', 'ชื่อสินค้า', 'ราคา', 'โค้ดตกแต่ง CSS'));
    } elseif($view == 'bubble_skin') {
        showtableheader('รายการสินค้า: 💬 กรอบคำพูด (Bubble Skin)');
        showsubtitle(array('ลบ', 'ลำดับ', 'รหัสอ้างอิง', 'ชื่อกรอบคำพูด', 'ราคา', 'โค้ดตกแต่ง CSS พื้นหลังกล่อง'));
    } elseif($view == 'gacha') {
        showtableheader('รายการสินค้า: 🎁 กล่องสุ่มกาชาปอง');
        showsubtitle(array('ลบ', 'ลำดับ', 'รหัสอ้างอิง', 'ชื่อกล่องสุ่ม', 'ราคา', 'เป้าหมายการสุ่ม (all, name_style, badge, bubble_skin) และ Icon'));
    } else {
        showtableheader('รายการสินค้า: 📛 ป้ายฉายาหน้าชื่อ');
        showsubtitle(array('ลบ', 'ลำดับ', 'รหัสอ้างอิง', 'ชื่อป้ายฉายา', 'ราคา', 'Icon (อีโมจิ / รูปภาพ)'));
    }

    $query = DB::query("SELECT * FROM ".DB::table('prasopkan_chat_shop_items')." WHERE item_type='$view' ORDER BY displayorder ASC");
    while($row = DB::fetch($query)) {
        if($view == 'name_style' || $view == 'bubble_skin') {
            $input_extra = "<textarea name=\"css[{$row['item_id']}]\" style=\"width:250px; height:40px;\" placeholder=\"ใส่โค้ด CSS\">{$row['css']}</textarea><input type=\"hidden\" name=\"icon[{$row['item_id']}]\" value=\"{$row['icon']}\">";
        } elseif($view == 'gacha') {
            $input_extra = "<select name=\"css[{$row['item_id']}]\"><option value=\"all\" ".($row['css']=='all'?'selected':'').">สุ่มทุกอย่าง (all)</option><option value=\"name_style\" ".($row['css']=='name_style'?'selected':'').">สุ่มเฉพาะสีชื่อ</option><option value=\"badge\" ".($row['css']=='badge'?'selected':'').">สุ่มเฉพาะป้าย</option><option value=\"bubble_skin\" ".($row['css']=='bubble_skin'?'selected':'').">สุ่มเฉพาะกรอบ</option></select> <input type=\"text\" class=\"txt\" size=\"5\" name=\"icon[{$row['item_id']}]\" value=\"{$row['icon']}\" placeholder=\"Icon\">";
        } else {
            $input_extra = "<input type=\"text\" class=\"txt\" size=\"10\" name=\"icon[{$row['item_id']}]\" value=\"{$row['icon']}\" placeholder=\"ใส่อีโมจิ\"><input type=\"hidden\" name=\"css[{$row['item_id']}]\" value=\"{$row['css']}\">";
        }

        showtablerow('', array('class="td25"', 'class="td25"'), array(
            "<input type=\"checkbox\" class=\"checkbox\" name=\"delete[{$row['item_id']}]\" value=\"1\">",
            "<input type=\"text\" class=\"txt\" size=\"3\" name=\"displayorder[{$row['item_id']}]\" value=\"{$row['displayorder']}\">",
            "<b>{$row['item_key']}</b>", 
            "<input type=\"text\" class=\"txt\" size=\"15\" name=\"name[{$row['item_id']}]\" value=\"{$row['name']}\">",
            "<input type=\"text\" class=\"txt\" size=\"5\" name=\"price[{$row['item_id']}]\" value=\"{$row['price']}\">",
            $input_extra
        ));
    }

    if($view == 'name_style' || $view == 'bubble_skin') {
        $new_input_extra = "<textarea name=\"new_css\" style=\"width:250px; height:40px;\" placeholder=\"ใส่โค้ด CSS\"></textarea><input type=\"hidden\" name=\"new_icon\" value=\"\">";
    } elseif($view == 'gacha') {
        $new_input_extra = "<select name=\"new_css\"><option value=\"all\">สุ่มทุกอย่าง (all)</option><option value=\"name_style\">สุ่มเฉพาะสีชื่อ</option><option value=\"badge\">สุ่มเฉพาะป้าย</option><option value=\"bubble_skin\">สุ่มเฉพาะกรอบ</option></select> <input type=\"text\" class=\"txt\" size=\"5\" name=\"new_icon\" value=\"🎁\" placeholder=\"Icon\">";
    } else {
        $new_input_extra = "<input type=\"text\" class=\"txt\" size=\"10\" name=\"new_icon\" value=\"\" placeholder=\"ใส่อีโมจิ\"><input type=\"hidden\" name=\"new_css\" value=\"\">";
    }

    showtablerow('', array('class="td25"', 'class="td25"'), array(
        "<b style='color:green;'>เพิ่มใหม่:</b>",
        "<input type=\"text\" class=\"txt\" size=\"3\" name=\"new_displayorder\" value=\"0\">",
        "<input type=\"text\" class=\"txt\" size=\"10\" name=\"new_item_key\" value=\"\" placeholder=\"ใส่รหัส\">",
        "<input type=\"text\" class=\"txt\" size=\"15\" name=\"new_name\" value=\"\" placeholder=\"ชื่อสินค้า\">",
        "<input type=\"text\" class=\"txt\" size=\"5\" name=\"new_price\" value=\"500\">",
        $new_input_extra
    ));

    showsubmit('shop_submit', 'บันทึกข้อมูล / เพิ่มสินค้า');
}

showtablefooter();
showformfooter();
?>