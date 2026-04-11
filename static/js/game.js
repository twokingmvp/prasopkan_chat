(function($) {
    $(document).ready(function() {
        var gameApiUrl = 'plugin.php?id=prasopkan_chat:api_game';

        // สร้าง Modal กระดานผู้นำ (ตัดสีส้มออก ให้ใช้หน้าตาเดียวกับหน้าต่างร้านค้า)
        var lbModal = $(`
            <div id="pk-chat-lb-modal" style="display: none; position: absolute; top: 50px; left: 10px; right: 10px; bottom: 60px; background: var(--pk-bg); z-index: 100; border-radius: 10px; box-shadow: 0 4px 15px rgba(0,0,0,0.2); display: flex; flex-direction: column; overflow: hidden; border: 1px solid var(--pk-border);">
                <div class="pk-shop-header">
                    <h3>กระดานผู้นำนักแชท 🏆</h3>
                </div>
                <div id="pk-lb-content" style="flex: 1; overflow-y: auto; padding: 10px;">กำลังโหลด...</div>
                <div id="pk-lb-mystat" style="padding: 10px; background: var(--pk-input-bg); border-top: 1px solid var(--pk-border); text-align: center; font-weight: bold;"></div>
                <div style="text-align:center; margin-top:10px; margin-bottom:10px;"><button id="pk-lb-close" class="pk-btn-close">ปิดหน้าต่าง</button></div>
            </div>
        `);
        $('#pk-chat-box').append(lbModal);

        // เชื่อมระบบปุ่มเข้ากับ ID ในหน้า HTML 
        $('#pk-chat-lb-btn').click(function(e) {
            e.stopPropagation();
            $('#pk-chat-emoji-picker, #pk-chat-rp-modal, #pk-chat-settings-menu, #pk-chat-shop-modal').hide();
            lbModal.fadeToggle(150);
            
            $.ajax({
                url: gameApiUrl + '&action=leaderboard', dataType: 'json',
                success: function(res) {
                    if(res.status === 'success') {
                        var html = '<table style="width:100%; border-collapse: collapse; text-align: left;">';
                        html += '<tr style="border-bottom: 2px solid var(--pk-border);"><th style="padding:5px;">อันดับ</th><th>ชื่อ</th><th>เลเวล</th><th>EXP</th></tr>';
                        res.data.forEach(function(u) {
                            var medal = u.rank === 1 ? '🥇' : (u.rank === 2 ? '🥈' : (u.rank === 3 ? '🥉' : u.rank));
                            html += '<tr style="border-bottom: 1px solid var(--pk-border);"><td style="padding:5px;">'+medal+'</td><td><b>'+u.username+'</b></td><td><span style="color:#ff3b30; font-weight:bold;">Lv.'+u.level+'</span></td><td>'+u.exp+'</td></tr>';
                        });
                        html += '</table>';
                        $('#pk-lb-content').html(html);
                        $('#pk-lb-mystat').html('สถานะของคุณ: เลเวล ' + res.my_stat.level + ' (' + res.my_stat.exp + ' EXP)');
                    }
                }
            });
        });
        
        $('#pk-lb-close').click(function() { lbModal.fadeOut(150); });

        // ระบบแอบเพิ่ม EXP หลังจากที่ผู้ใช้กดส่งข้อความสำเร็จ! 
        $(document).ajaxSuccess(function(event, xhr, settings) {
            if (settings.url.indexOf('action=send') !== -1 && xhr.responseJSON && xhr.responseJSON.status === 'success') {
                var currentRoomId = $('.pk-room-tab.active').length > 0 ? $('.pk-room-tab.active').data('room') : 1;
                $.ajax({ url: gameApiUrl + '&action=add_exp&room_id=' + currentRoomId, dataType: 'json', global: false });
            }
        });

        // ดักจับคำสั่งมินิเกม (/roll)
        function checkGameCommand() {
            var input = $('#pk-chat-input');
            var msg = input.val().trim();
            if(msg === '/roll') {
                var currentRoomId = $('.pk-room-tab.active').length > 0 ? $('.pk-room-tab.active').data('room') : 1;
                input.val(''); // เคลียร์ข้อความทิ้ง
                $.ajax({
                    url: gameApiUrl + '&action=roll&room_id=' + currentRoomId, dataType: 'json',
                    success: function(res) {
                        if(res.status === 'error') {
                            var toast = $('#pk-chat-toast');
                            if(toast.length) { toast.removeClass().addClass('toast-error').html('❌ ' + res.msg).show(); setTimeout(function(){ toast.fadeOut(300); }, 3000); }
                        }
                    }
                });
            }
        }
        
        $('#pk-chat-send').on('mousedown', checkGameCommand);
        $('#pk-chat-input').on('keydown', function(e) { if(e.which == 13) checkGameCommand(); });

    });
})(jQuery);