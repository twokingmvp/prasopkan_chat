<?php
if(!defined('IN_DISCUZ') || !defined('IN_ADMINCP')) {
    exit('Access Denied');
}

$act = isset($_GET['act']) ? $_GET['act'] : 'list';

if(submitcheck('shop_submit')) {
    // บันทึกการแก้ไข
    if(is_array($_GET['name'])) {
        foreach($_GET['name'] as $id => $val) {
            $id = intval($id);
            if($_GET['delete'][$id]) {
                DB::delete('prasopkan_chat_shop_items', "item_id='$id'");
            } else {
                DB::update('prasopkan_chat_shop_items', array(
                    'name' => trim($_GET['name'][$id]),
                    'price' => intval($_GET['price'][$id]),
                    'item_type' => trim($_GET['item_type'][$id]),
                    'css' => trim($_GET['css'][$id]),
                    'icon' => trim($_GET['icon'][$id]),
                    'displayorder' => intval($_GET['displayorder'][$id]),
                ), "item_id='$id'");
            }
        }
    }
    // เพิ่มสินค้าใหม่
    if(!empty($_GET['new_name']) && !empty($_GET['new_item_key'])) {
        DB::insert('prasopkan_chat_shop_items', array(
            'item_key' => trim($_GET['new_item_key']),
            'name' => trim($_GET['new_name']),
            'price' => intval($_GET['new_price']),
            'item_type' => trim($_GET['new_item_type']),
            'css' => trim($_GET['new_css']),
            'icon' => trim($_GET['new_icon']),
            'displayorder' => intval($_GET['new_displayorder']),
        ));
    }
    cpmsg('อัปเดตข้อมูลร้านค้าเรียบร้อยแล้ว', 'action=plugins&operation=config&do='.$pluginid.'&identifier=prasopkan_chat&pmod=admincp_shop', 'succeed');
}

showformheader('plugins&operation=config&do='.$pluginid.'&identifier=prasopkan_chat&pmod=admincp_shop');
showtableheader('การจัดการสินค้าในร้านค้าแชท (Chat Shop)');
showsubtitle(array('ลบ', 'ลำดับ', 'รหัสไอเทม (ห้ามซ้ำ)', 'ชื่อสินค้า', 'ประเภท', 'ราคา (เครดิต)', 'CSS (สำหรับสีชื่อ) / Icon (สำหรับป้าย)'));

$query = DB::query("SELECT * FROM ".DB::table('prasopkan_chat_shop_items')." ORDER BY displayorder ASC");
while($row = DB::fetch($query)) {
    $type_select = '<select name="item_type['.$row['item_id'].']"><option value="name_style" '.($row['item_type']=='name_style'?'selected':'').'>สีชื่อ (name_style)</option><option value="badge" '.($row['item_type']=='badge'?'selected':'').'>ป้ายฉายา (badge)</option></select>';
    showtablerow('', array('class="td25"', 'class="td25"'), array(
        "<input type=\"checkbox\" class=\"checkbox\" name=\"delete[{$row['item_id']}]\" value=\"1\">",
        "<input type=\"text\" class=\"txt\" size=\"3\" name=\"displayorder[{$row['item_id']}]\" value=\"{$row['displayorder']}\">",
        "{$row['item_key']}", // รหัสไม่ให้แก้ ป้องกันบัก
        "<input type=\"text\" class=\"txt\" size=\"15\" name=\"name[{$row['item_id']}]\" value=\"{$row['name']}\">",
        $type_select,
        "<input type=\"text\" class=\"txt\" size=\"5\" name=\"price[{$row['item_id']}]\" value=\"{$row['price']}\">",
        "<textarea name=\"css[{$row['item_id']}]\" style=\"width:150px; height:30px;\" placeholder=\"CSS\">{$row['css']}</textarea> <input type=\"text\" class=\"txt\" size=\"4\" name=\"icon[{$row['item_id']}]\" value=\"{$row['icon']}\" placeholder=\"Icon\">"
    ));
}

// แถวสำหรับเพิ่มใหม่
$new_type_select = '<select name="new_item_type"><option value="name_style">สีชื่อ (name_style)</option><option value="badge">ป้ายฉายา (badge)</option></select>';
showtablerow('', array('class="td25"', 'class="td25"'), array(
    "<b>เพิ่ม:</b>",
    "<input type=\"text\" class=\"txt\" size=\"3\" name=\"new_displayorder\" value=\"0\">",
    "<input type=\"text\" class=\"txt\" size=\"10\" name=\"new_item_key\" value=\"\" placeholder=\"ns_new\">",
    "<input type=\"text\" class=\"txt\" size=\"15\" name=\"new_name\" value=\"\" placeholder=\"ชื่อสินค้า\">",
    $new_type_select,
    "<input type=\"text\" class=\"txt\" size=\"5\" name=\"new_price\" value=\"500\">",
    "<textarea name=\"new_css\" style=\"width:150px; height:30px;\" placeholder=\"ใส่ CSS\"></textarea> <input type=\"text\" class=\"txt\" size=\"4\" name=\"new_icon\" value=\"\" placeholder=\"Emoji\">"
));

showsubmit('shop_submit', 'บันทึกข้อมูล / เพิ่มสินค้า');
showtablefooter();
showformfooter();
?>