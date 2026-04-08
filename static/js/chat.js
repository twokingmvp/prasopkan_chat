(function($) {
    $(document).ready(function() {
        var chatBox = $('#pk-chat-messages');
        var chatBody = $('#pk-chat-body');
        var chatHead = $('#pk-chat-head');
        var chatBoxContainer = $('#pk-chat-box');
        var isFetching = false;
        var apiUrl = 'plugin.php?id=prasopkan_chat:api';
        var currentRoomId = 0; 
        
        if ($('.pk-room-tab.active').length > 0) currentRoomId = $('.pk-room-tab.active').data('room');

        // --- ระบบเปิด-ปิดแบบ Floating Head ---
        function toggleChat() {
            if(chatBoxContainer.is(':hidden')) {
                chatHead.hide();
                chatBoxContainer.fadeIn(200);
                localStorage.setItem('prasopkan_chat_state', 'open');
                fetchMessages(true);
                setTimeout(function(){ $('#pk-chat-input').focus(); }, 200);
            } else {
                chatBoxContainer.fadeOut(200, function() { chatHead.fadeIn(200); });
                localStorage.setItem('prasopkan_chat_state', 'closed');
            }
        }
        chatHead.on('click', toggleChat);
        $('#pk-chat-close-btn').on('click', toggleChat);
        if(localStorage.getItem('prasopkan_chat_state') === 'closed') { chatBoxContainer.hide(); chatHead.show(); } 
        else { chatBoxContainer.show(); chatHead.hide(); }

        // --- ส่งสถานะกำลังพิมพ์ ✍️ ---
        var typingTimer;
        $('#pk-chat-input').on('input', function() {
            clearTimeout(typingTimer);
            typingTimer = setTimeout(function() { $.ajax({url: apiUrl + '&action=typing&room_id=' + currentRoomId}); }, 1000);
        });

        // --- ระบบสลับแท็บ ---
        $('.pk-room-tab').on('click', function() {
            $('.pk-room-tab').removeClass('active'); $(this).addClass('active'); currentRoomId = $(this).data('room'); 
            chatBox.html('<div style="text-align:center; padding:15px; color:var(--pk-text-muted);">กำลังโหลด...</div>'); fetchMessages(true); 
        });

        // --- ระบบดึงข้อมูล (แย่งฝั่งซ้าย-ขวา) ---
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
                        var isReactionEnabled = (res.enable_reaction == 1);

                        // แสดงคนกำลังพิมพ์
                        if(res.typing_users && res.typing_users.length > 0) {
                            $('#pk-typing-names').text(res.typing_users.join(', '));
                            $('#pk-chat-typing-indicator').show();
                        } else {
                            $('#pk-chat-typing-indicator').hide();
                        }

                        if(res.data.length === 0) {
                            html = '<div style="text-align:center; padding:15px; color:var(--pk-text-muted);">ยังไม่มีคนคุย มาเริ่มคุยกันเลย!</div>';
                        } else {
                            $.each(res.data, function(index, item) {
                                // เช็คว่าเป็นข้อความของเราไหม
                                var isMine = (item.uid == res.current_uid);
                                var rowClass = isMine ? 'pk-msg-mine' : 'pk-msg-other';
                                var nameColor = isMine ? 'var(--pk-text-muted)' : (item.color ? item.color : 'var(--pk-text)');
                                
                                var delBtn = isAdmin ? '<span class="pk-msg-action-btn pk-msg-delete" data-id="'+item.msg_id+'" title="ลบ">🗑️</span>' : '';
                                var replyBtn = isMentionEnabled && !isMine ? '<span class="pk-msg-action-btn pk-msg-reply" data-user="'+item.username+'" title="ตอบกลับ">↩️</span>' : '';
                                var reactBtn = isReactionEnabled && !isMine ? '<span class="pk-msg-action-btn pk-msg-add-react" data-id="'+item.msg_id+'" data-emoji="👍">👍</span><span class="pk-msg-action-btn pk-msg-add-react" data-id="'+item.msg_id+'" data-emoji="❤️">❤️</span><span class="pk-msg-action-btn pk-msg-add-react" data-id="'+item.msg_id+'" data-emoji="😂">😂</span>' : '';
                                
                                var pills = '';
                                if(item.reactions) {
                                    $.each(item.reactions, function(emoji, data) {
                                        var activeClass = data.me ? ' active' : '';
                                        pills += '<span class="pk-react-pill'+activeClass+'" data-id="'+item.msg_id+'" data-emoji="'+emoji+'">'+emoji+' '+data.count+'</span>';
                                    });
                                }
                                var reactionsHtml = pills !== '' ? '<div class="pk-reactions-display">' + pills + '</div>' : '';
                                var headerInfo = isMine ? '<span class="pk-msg-time">'+item.time+'</span>' : '<b style="color:'+nameColor+';">'+item.username+'</b> <span class="pk-msg-time">'+item.time+'</span>';

                                // สร้างโครงสร้างกล่องคำพูด (LINE Style)
                                html += '<div class="pk-msg-row ' + rowClass + '">';
                                html += '<div class="pk-msg-info">' + headerInfo + '</div>';
                                html += '<div class="pk-bubble-wrapper">';
                                if(isMine) {
                                    html += '<div class="pk-msg-actions-inline">' + delBtn + '</div>';
                                    html += '<div class="pk-bubble">' + item.message + '</div>';
                                } else {
                                    html += '<div class="pk-bubble">' + item.message + '</div>';
                                    html += '<div class="pk-msg-actions-inline">' + replyBtn + reactBtn + delBtn + '</div>';
                                }
                                html += '</div>'; // end wrapper
                                html += reactionsHtml;
                                html += '</div>'; // end row
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
        fetchMessages(true); setInterval(function() { fetchMessages(false); }, 3000);

        // --- ระบบอั่งเปา & ปุ่มต่างๆ (ปล่อยไว้เหมือนเดิม แค่รวมโค้ด) ---
        var rpModal = $('#pk-chat-rp-modal');
        $('#pk-chat-rp-btn').on('click', function(e) { e.stopPropagation(); $('#pk-chat-emoji-picker').hide(); rpModal.fadeToggle(150); });
        $('#pk-rp-cancel').on('click', function() { rpModal.fadeOut(150); });
        $('#pk-rp-submit').on('click', function() {
            var amount = $('#pk-rp-amount').val(); var count = $('#pk-rp-count').val();
            if(!amount || !count) return;
            $(this).prop('disabled', true).text('กำลังส่ง...');
            $.ajax({ url: apiUrl + '&action=send_redpacket&room_id=' + currentRoomId, type: 'GET', data: { amount: amount, count: count }, dataType: 'json', success: function(res) { $('#pk-rp-submit').prop('disabled', false).text('โยนซอง!'); if(res.status === 'success') { rpModal.fadeOut(150); $('#pk-rp-amount').val(''); $('#pk-rp-count').val(''); fetchMessages(true); } else { alert(res.msg); } } });
        });
        $(document).on('click', '.pk-redpacket-box', function() { $.ajax({ url: apiUrl + '&action=claim_redpacket&env_id=' + $(this).data('envid'), type: 'GET', dataType: 'json', success: function(res) { if(res.status === 'success') { alert('🎉 ยินดีด้วย! คุณได้รับอั่งเปาจำนวน ' + res.amount + ' เครดิต'); } else { alert(res.msg); } } }); });

        var emojis = ['😀','😂','🤣','😊','😍','🥰','😘','😜','😎','🥺','😭','😡','👍','👎','👏','🙏','❤️','🔥','✨','🎉','🌟','🐱','🐶','💡','📌'];
        var emojiPicker = $('#pk-chat-emoji-picker'); var emojiHtml = '';
        $.each(emojis, function(i, emoji) { emojiHtml += '<span class="pk-emoji-item">' + emoji + '</span>'; }); emojiPicker.html(emojiHtml);
        $('#pk-chat-emoji-btn').on('click', function(e) { e.stopPropagation(); rpModal.hide(); emojiPicker.fadeToggle(150); });
        $(document).on('click', '.pk-emoji-item', function() { var input = $('#pk-chat-input'); input.val(input.val() + $(this).text()).focus(); });
        $(document).on('click', function(e) { if (!$(e.target).closest('#pk-chat-emoji-picker, #pk-chat-emoji-btn, #pk-chat-rp-modal, #pk-chat-rp-btn').length) { emojiPicker.fadeOut(150); rpModal.fadeOut(150); } });

        $('#pk-chat-img-btn').on('click', function() { $('#pk-chat-file-upload').click(); });
        $('#pk-chat-file-upload').on('change', function() {
            var file = this.files[0]; if(!file) return; var formData = new FormData(); formData.append('chat_image', file);
            var inputField = $('#pk-chat-input'); inputField.val('กำลังอัปโหลด...').prop('disabled', true);
            $.ajax({ url: apiUrl + '&action=upload', type: 'POST', data: formData, processData: false, contentType: false, dataType: 'json', success: function(res) { inputField.val('').prop('disabled', false).focus(); if(res.status === 'success') { $.ajax({ url: apiUrl + '&action=send&room_id=' + currentRoomId, type: 'GET', data: { message: '[img]' + res.url + '[/img]' }, dataType: 'json', success: function(s) { if(s.status === 'success') fetchMessages(true); } }); } else { alert(res.msg); } $('#pk-chat-file-upload').val(''); } });
        });

        $(document).on('click', '.pk-msg-reply', function() { var user = $(this).data('user'); var input = $('#pk-chat-input'); var currentVal = input.val(); if(currentVal.indexOf('@' + user) === -1) input.val('@' + user + ' ' + currentVal).focus(); else input.focus(); });
        $(document).on('click', '.pk-msg-add-react, .pk-react-pill', function() { $.ajax({ url: apiUrl + '&action=react&msg_id=' + $(this).data('id') + '&reaction=' + encodeURIComponent($(this).data('emoji')), type: 'GET', dataType: 'json', success: function(res) { if(res.status === 'success') fetchMessages(false); } }); });
        $(document).on('click', '.pk-msg-delete', function() { if(confirm('ต้องการลบข้อความนี้ใช่หรือไม่?')) { $.ajax({ url: apiUrl + '&action=delete&msg_id=' + $(this).data('id'), type: 'GET', dataType: 'json', success: function(res) { if(res.status === 'success') fetchMessages(false); else alert(res.msg); } }); } });

        function sendMessage(e) {
            if(e) e.preventDefault(); var inputField = $('#pk-chat-input'); if(inputField.length === 0) return; 
            var msg = inputField.val();
            if(msg.trim() !== '') {
                inputField.prop('disabled', true); emojiPicker.fadeOut(150); 
                $.ajax({ url: apiUrl + '&action=send&room_id=' + currentRoomId, type: 'GET', data: { message: msg }, dataType: 'json', success: function(res) { inputField.prop('disabled', false).val('').focus(); if(res.status === 'success') fetchMessages(true); else alert(res.msg); }, error: function() { inputField.prop('disabled', false).focus(); alert('เกิดข้อผิดพลาดในการส่งข้อความ'); } });
            }
        }
        $('#pk-chat-send').off('click').on('click', sendMessage);
        $('#pk-chat-input').off('keypress').on('keypress', function(e) { if(e.which == 13) { sendMessage(e); } });
    });
})(jQuery);