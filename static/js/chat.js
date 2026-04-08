(function($) {
    $(document).ready(function() {
        var chatBox = $('#pk-chat-messages');
        var chatBody = $('#pk-chat-body');
        var toggleBtn = $('#pk-chat-toggle-btn');
        var isFetching = false;
        var apiUrl = 'plugin.php?id=prasopkan_chat:api';
        var currentRoomId = 0; 
        
        if ($('.pk-room-tab.active').length > 0) currentRoomId = $('.pk-room-tab.active').data('room');

        // --- ระบบอั่งเปา (Red Packet) 🧧 ---
        var rpModal = $('#pk-chat-rp-modal');
        $('#pk-chat-rp-btn').on('click', function(e) {
            e.stopPropagation();
            $('#pk-chat-emoji-picker').hide();
            rpModal.fadeToggle(150);
        });

        $('#pk-rp-cancel').on('click', function() { rpModal.fadeOut(150); });

        // เวลากดปุ่ม โยนซอง!
        $('#pk-rp-submit').on('click', function() {
            var amount = $('#pk-rp-amount').val();
            var count = $('#pk-rp-count').val();
            if(!amount || !count) { alert('กรุณากรอกข้อมูลให้ครบถ้วน'); return; }
            
            $(this).prop('disabled', true).text('กำลังส่ง...');
            $.ajax({
                url: apiUrl + '&action=send_redpacket&room_id=' + currentRoomId,
                type: 'GET',
                data: { amount: amount, count: count },
                dataType: 'json',
                success: function(res) {
                    $('#pk-rp-submit').prop('disabled', false).text('โยนซอง!');
                    if(res.status === 'success') {
                        rpModal.fadeOut(150);
                        $('#pk-rp-amount').val(''); $('#pk-rp-count').val('');
                        fetchMessages(true);
                    } else { alert(res.msg); }
                }
            });
        });

        // เวลากดปุ่ม แย่งซองอั่งเปาในแชท!
        $(document).on('click', '.pk-redpacket-box', function() {
            var envId = $(this).data('envid');
            var btn = $(this);
            $.ajax({
                url: apiUrl + '&action=claim_redpacket&env_id=' + envId,
                type: 'GET',
                dataType: 'json',
                success: function(res) {
                    if(res.status === 'success') {
                        alert('🎉 ยินดีด้วย! คุณได้รับอั่งเปาจำนวน ' + res.amount + ' เครดิต');
                    } else {
                        alert(res.msg); // แจ้งเตือนเช่น หมดแล้ว หรือเคยรับแล้ว
                    }
                }
            });
        });

        // --- ระบบอีโมจิ (Emoji) ---
        var emojis = ['😀','😂','🤣','😊','😍','🥰','😘','😜','😎','🥺','😭','😡','👍','👎','👏','🙏','❤️','🔥','✨','🎉','🌟','🐱','🐶','💡','📌'];
        var emojiPicker = $('#pk-chat-emoji-picker');
        var emojiHtml = '';
        $.each(emojis, function(i, emoji) { emojiHtml += '<span class="pk-emoji-item">' + emoji + '</span>'; });
        emojiPicker.html(emojiHtml);

        $('#pk-chat-emoji-btn').on('click', function(e) {
            e.stopPropagation(); 
            rpModal.hide();
            emojiPicker.fadeToggle(150);
        });

        $(document).on('click', '.pk-emoji-item', function() {
            var input = $('#pk-chat-input');
            input.val(input.val() + $(this).text()).focus();
        });

        $(document).on('click', function(e) {
            if (!$(e.target).closest('#pk-chat-emoji-picker, #pk-chat-emoji-btn, #pk-chat-rp-modal, #pk-chat-rp-btn').length) {
                emojiPicker.fadeOut(150);
                rpModal.fadeOut(150);
            }
        });

        // --- ระบบส่งรูปภาพ (Image Upload) ---
        $('#pk-chat-img-btn').on('click', function() { $('#pk-chat-file-upload').click(); });
        $('#pk-chat-file-upload').on('change', function() {
            var file = this.files[0];
            if(!file) return;
            var formData = new FormData(); formData.append('chat_image', file);
            var inputField = $('#pk-chat-input');
            inputField.val('กำลังอัปโหลดรูปภาพ...').prop('disabled', true);

            $.ajax({
                url: apiUrl + '&action=upload',
                type: 'POST', data: formData, processData: false, contentType: false, dataType: 'json',
                success: function(res) {
                    inputField.val('').prop('disabled', false).focus();
                    if(res.status === 'success') {
                        $.ajax({
                            url: apiUrl + '&action=send&room_id=' + currentRoomId,
                            type: 'GET', data: { message: '[img]' + res.url + '[/img]' }, dataType: 'json',
                            success: function(sendRes) { if(sendRes.status === 'success') fetchMessages(true); }
                        });
                    } else { alert(res.msg); }
                    $('#pk-chat-file-upload').val(''); 
                }
            });
        });

        // --- ระบบแท็กชื่อ (Mention) ---
        $(document).on('click', '.pk-msg-reply', function() {
            var user = $(this).data('user');
            var input = $('#pk-chat-input');
            var currentVal = input.val();
            if(currentVal.indexOf('@' + user) === -1) input.val('@' + user + ' ' + currentVal).focus();
            else input.focus();
        });

        // --- ระบบสลับแท็บห้องแชท ---
        $('.pk-room-tab').on('click', function() {
            $('.pk-room-tab').removeClass('active');
            $(this).addClass('active');
            currentRoomId = $(this).data('room'); 
            chatBox.html('<div style="text-align:center; padding:15px; color:#999;">กำลังโหลดข้อความ...</div>');
            fetchMessages(true); 
        });

        function toggleChat() {
            chatBody.slideToggle(200, function() {
                var isHidden = chatBody.is(':hidden');
                toggleBtn.text(isHidden ? '☐' : '_');
                localStorage.setItem('prasopkan_chat_state', isHidden ? 'closed' : 'open');
            });
        }
        $('#pk-chat-header').off('click').on('click', toggleChat);
        if(localStorage.getItem('prasopkan_chat_state') === 'closed') { chatBody.hide(); toggleBtn.text('☐'); }

        // --- ระบบดึงข้อมูล ---
        function fetchMessages(forceScroll = false) {
            if(isFetching) return;
            isFetching = true;
            $.ajax({
                url: apiUrl + '&action=get&room_id=' + currentRoomId,
                type: 'GET', dataType: 'json', cache: false,
                success: function(res) {
                    if(res.status === 'success') {
                        var html = '';
                        var isAdmin = res.is_admin; 
                        var isMentionEnabled = (res.enable_mention == 1);

                        if(res.data.length === 0) {
                            html = '<div style="text-align:center; padding:15px; color:#999;">ยังไม่มีคนคุยในห้องนี้ มาเริ่มคุยกันเลย!</div>';
                        } else {
                            $.each(res.data, function(index, item) {
                                var delBtn = isAdmin ? '<span class="pk-msg-delete" data-id="' + item.msg_id + '">[ลบ]</span>' : '';
                                var replyBtn = isMentionEnabled ? '<span class="pk-msg-reply" data-user="' + item.username + '">[ตอบ]</span>' : '';
                                html += '<div class="pk-msg-item"><b style="color:' + item.color + ';">' + item.username + ':</b> ' + item.message + '<span class="pk-msg-time">[' + item.time + ']</span>' + replyBtn + delBtn + '</div>';
                            });
                        }
                        
                        var isAtBottom = (chatBox[0].scrollHeight - chatBox.scrollTop() <= chatBox.outerHeight() + 15);
                        chatBox.html(html);
                        if(isAtBottom || forceScroll) chatBox.scrollTop(chatBox[0].scrollHeight);
                    }
                    isFetching = false;
                },
                error: function() { isFetching = false; }
            });
        }

        fetchMessages(true);
        setInterval(function() { fetchMessages(false); }, 3000);

        // --- ระบบคลิกลบข้อความ ---
        $(document).on('click', '.pk-msg-delete', function() {
            if(confirm('คุณต้องการลบข้อความนี้ใช่หรือไม่?')) {
                $.ajax({
                    url: apiUrl + '&action=delete&msg_id=' + $(this).data('id'),
                    type: 'GET', dataType: 'json',
                    success: function(res) { if(res.status === 'success') fetchMessages(false); else alert(res.msg); }
                });
            }
        });

        // --- ระบบส่งข้อมูลข้อความปกติ ---
        function sendMessage(e) {
            if(e) e.preventDefault();
            var inputField = $('#pk-chat-input');
            if(inputField.length === 0) return; 

            var msg = inputField.val();
            if(msg.trim() !== '') {
                inputField.prop('disabled', true);
                emojiPicker.fadeOut(150); 
                
                $.ajax({
                    url: apiUrl + '&action=send&room_id=' + currentRoomId,
                    type: 'GET', data: { message: msg }, dataType: 'json',
                    success: function(res) {
                        inputField.prop('disabled', false).val('').focus();
                        if(res.status === 'success') fetchMessages(true);
                        else alert(res.msg);
                    },
                    error: function() { inputField.prop('disabled', false).focus(); alert('เกิดข้อผิดพลาดในการส่งข้อความ'); }
                });
            }
        }
        $('#pk-chat-send').off('click').on('click', sendMessage);
        $('#pk-chat-input').off('keypress').on('keypress', function(e) { if(e.which == 13) { sendMessage(e); } });
    });
})(jQuery);