<?php
require './source/class/class_core.php';
$discuz = C::app();
$discuz->init();

$table = DB::table('prasopkan_chat_messages');
$check_col = DB::query("SHOW COLUMNS FROM `$table` LIKE 'room_id'");

if(DB::num_rows($check_col) == 0) {
    DB::query("ALTER TABLE `$table` ADD `room_id` int(10) NOT NULL DEFAULT '1'");
    echo "<h2 style='color:green;'>อัปเดตฐานข้อมูลสำเร็จ! เพิ่มระบบห้องแชท (room_id) เรียบร้อยแล้ว 🎉</h2>";
} else {
    echo "<h2 style='color:blue;'>ระบบฐานข้อมูลรองรับการแยกห้องแชทอยู่แล้วครับ</h2>";
}
echo "<br><a href='index.php'>คลิกที่นี่เพื่อกลับไปหน้าแรก</a>";
?>