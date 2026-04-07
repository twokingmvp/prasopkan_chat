(function($) {
    $(document).ready(function() {
        var chatBox = $('#pk-chat-messages');
        var chatBody = $('#pk-chat-body');
        var toggleBtn = $('#pk-chat-toggle-btn');
        var isFetching = false;
        var apiUrl = 'plugin.php?id=prasopkan_chat:api';
        var currentRoomId = 0; // ค่าเริ่มต้นถ้าไม่เปิดแยกห้อง
        if ($('.pk-room-tab.active').length > 0) {
            currentRoomId = $('.pk-room-tab.active').data('room');
        }

        // --- ระบบสลับแท็บห้องแชท ---
        $('.pk-room-tab').on('click', function() {
            $('.pk-room-tab').removeClass('active');
            $(this).addClass('active');
            currentRoomId = $(this).data('room'); // รับค่าไอดีห้องจาก HTML
            
            // เคลียร์ข้อความและแสดงสถานะกำลังโหลด
            chatBox.html('<div style="text-align:center; padding:15px; color:#999;">กำลังโหลดข้อความ...</div>');
            fetchMessages(true); // บังคับเลื่อนลงล่างสุดเมื่อสลับห้อง
        });

        // --- ระบบเปิด-ปิดกล่องแชท ---
        function toggleChat() {
            chatBody.slideToggle(200, function() {
                var isHidden = chatBody.is(':hidden');
                toggleBtn.text(isHidden ? '☐' : '_');
                localStorage.setItem('prasopkan_chat_state', isHidden ? 'closed' : 'open');
            });
        }

        $('#pk-chat-header').off('click').on('click', toggleChat);

        if(localStorage.getItem('prasopkan_chat_state') === 'closed') {
            chatBody.hide();
            toggleBtn.text('☐');
        }

        // --- ระบบดึงข้อมูล ---
        function fetchMessages(forceScroll = false) {
            if(isFetching) return;
            isFetching = true;
            $.ajax({
                url: apiUrl + '&action=get&room_id=' + currentRoomId, // ส่ง room_id ไปด้วย
                type: 'GET',
                dataType: 'json',
                cache: false,
                success: function(res) {
                    if(res.status === 'success') {
                        var html = '';
                        if(res.data.length === 0) {
                            html = '<div style="text-align:center; padding:15px; color:#999;">ยังไม่มีคนคุยในห้องนี้ มาเริ่มคุยกันเลย!</div>';
                        } else {
                            $.each(res.data, function(index, item) {
                                html += '<div class="pk-msg-item"><b>' + item.username + ':</b> ' + item.message + '<span class="pk-msg-time">[' + item.time + ']</span></div>';
                            });
                        }
                        
                        var isAtBottom = (chatBox[0].scrollHeight - chatBox.scrollTop() <= chatBox.outerHeight() + 15);
                        chatBox.html(html);
                        
                        if(isAtBottom || forceScroll) {
                            chatBox.scrollTop(chatBox[0].scrollHeight);
                        }
                    }
                    isFetching = false;
                },
                error: function() { isFetching = false; }
            });
        }

        fetchMessages(true);
        setInterval(function() { fetchMessages(false); }, 3000);

        // --- ระบบส่งข้อมูล ---
        function sendMessage(e) {
            if(e) e.preventDefault();
            var inputField = $('#pk-chat-input');
            if(inputField.length === 0) return; 

            var msg = inputField.val();
            if(msg.trim() !== '') {
                inputField.prop('disabled', true);
                $.ajax({
                    url: apiUrl + '&action=send&room_id=' + currentRoomId, // ส่ง room_id ไปด้วย
                    type: 'GET',
                    data: { message: msg },
                    dataType: 'json',
                    success: function(res) {
                        inputField.prop('disabled', false).val('').focus();
                        if(res.status === 'success') {
                            fetchMessages(true);
                        } else {
                            alert(res.msg);
                        }
                    },
                    error: function() {
                        inputField.prop('disabled', false).focus();
                        alert('เกิดข้อผิดพลาดในการส่งข้อความ');
                    }
                });
            }
        }

        $('#pk-chat-send').off('click').on('click', sendMessage);
        $('#pk-chat-input').off('keypress').on('keypress', function(e) {
            if(e.which == 13) { sendMessage(e); }
        });
    });
})(jQuery);