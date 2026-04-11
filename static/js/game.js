(function($) {
    $(document).ready(function() {
        var gameApiUrl = 'plugin.php?id=prasopkan_chat:api_game';

        // 🛠️ 1. เอา CSS ที่บังคับสี (!important) ออกไป เพื่อไม่ให้กวนสีปุ่มอื่นๆ
        // และดึงปุ่มถ้วยรางวัลยัดเข้าไปใหม่แบบคลีนๆ
        if ($('#pk-chat-lb-btn').length === 0) {
            var lbBtnHtml = `
            <span id="pk-chat-lb-btn" class="pk-header-icon" title="กระดานผู้นำนักแชท" style="cursor:pointer; margin-right: 5px;">
                <svg viewBox="0 0 24 24" width="18" height="18" fill="currentColor"><path d="M19 5h-2V3H7v2H5c-1.1 0-2 .9-2 2v1c0 2.55 1.92 4.63 4.39 4.94A5.01 5.01 0 0 0 11 15.9V19H7v2h10v-2h-4v-3.1a5.01 5.01 0 0 0 3.61-2.96C19.08 12.63 21 10.55 21 8V7c0-1.1-.9-2-2-2zM7 10.82C5.84 10.4 5 9.3 5 8V7h2v3.82zM19 8c0 1.3-.84 2.4-2 2.82V7h2v1z"></path></svg>
            </span>`;
            $('.pk-header-actions').prepend(lbBtnHtml);
        }

        // 🛠️ แก้ไข: ล้าง Margin ของ <h3> ทิ้ง และบังคับให้อยู่กึ่งกลางพอดีเป๊ะ
        if ($('#pk-chat-lb-modal').length === 0) {
            var lbModalHtml = `
            <div id="pk-chat-lb-modal" style="display: none;">
                <div class="pk-shop-header" style="align-items: center; justify-content: center; padding: 12px;">
                    <h3 style="margin: 0; line-height: 1;">กระดานผู้นำนักแชท 🏆</h3>
                </div>
                <div id="pk-lb-content" class="pk-lb-container">กำลังโหลด...</div>
                <div id="pk-lb-mystat" class="pk-lb-my-stat"></div>
                <div class="pk-lb-footer"><button id="pk-lb-close" class="pk-btn-close">ปิดหน้าต่าง</button></div>
            </div>`;
            $('#pk-chat-box').append(lbModalHtml);
        }

        var lbModal = $('#pk-chat-lb-modal');
        var lbBtn = $('#pk-chat-lb-btn');

        // 🛠️ 3. กดปุ่มเพื่อเปิดกระดานผู้นำและดึงข้อมูลจาก API
        lbBtn.off('click').on('click', function(e) {
            e.stopPropagation();
            $('#pk-chat-emoji-picker, #pk-chat-rp-modal, #pk-chat-settings-menu, #pk-chat-shop-modal').hide();
            lbModal.fadeToggle(150);
            
            $.ajax({
                url: gameApiUrl + '&action=leaderboard', dataType: 'json',
                success: function(res) {
                    if(res.status === 'success') {
                        var html = '';
                        
                        // วาดการ์ดรายชื่อ (คล้ายๆ ร้านค้า)
                        res.data.forEach(function(u) {
                            var rankClass = u.rank <= 3 ? 'rank-' + u.rank : 'rank-other';
                            var medal = u.rank === 1 ? '🥇' : (u.rank === 2 ? '🥈' : (u.rank === 3 ? '🥉' : '#' + u.rank));
                            
                            html += `
                                <div class="pk-lb-item">
                                    <div class="pk-lb-rank ${rankClass}">${medal}</div>
                                    <div class="pk-lb-details">
                                        <div class="pk-lb-name">${u.username}</div>
                                        <div class="pk-lb-exp">✨ ${u.exp} EXP</div>
                                    </div>
                                    <div class="pk-lb-level-badge">Lv. ${u.level}</div>
                                </div>
                            `;
                        });
                        $('#pk-lb-content').html(html);
                        
                        // แสดงสถานะตัวเอง
                        var myStatHtml = `
                            <div><b>ข้อมูลของคุณ</b></div>
                            <div>
                                <span style="margin-right: 10px; color: var(--pk-text-muted);">✨ ${res.my_stat.exp} EXP</span>
                                <span class="pk-lb-level-badge">Lv. ${res.my_stat.level}</span>
                            </div>
                        `;
                        $('#pk-lb-mystat').html(myStatHtml);
                    }
                }
            });
        });
        
        $('#pk-lb-close').off('click').on('click', function() { lbModal.fadeOut(150); });

        // 🛠️ 4. ระบบเพิ่ม EXP เมื่อส่งข้อความ
        $(document).ajaxSuccess(function(event, xhr, settings) {
            if (settings.url.indexOf('action=send') !== -1 && xhr.responseJSON && xhr.responseJSON.status === 'success') {
                var currentRoomId = $('.pk-room-tab.active').length > 0 ? $('.pk-room-tab.active').data('room') : 1;
                $.ajax({ url: gameApiUrl + '&action=add_exp&room_id=' + currentRoomId, dataType: 'json', global: false });
            }
        });

        // 🛠️ 5. ระบบมินิเกม (/roll)
        function checkGameCommand() {
            var input = $('#pk-chat-input');
            var msg = input.val().trim();
            if(msg === '/roll') {
                var currentRoomId = $('.pk-room-tab.active').length > 0 ? $('.pk-room-tab.active').data('room') : 1;
                input.val(''); 
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