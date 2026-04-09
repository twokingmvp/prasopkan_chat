(function($) {
    $(document).ready(function() {
        
        // --- 🍞 ระบบแจ้งเตือน (Toast Notification) สไตล์แอป ---
        if($('#pk-chat-toast').length === 0) { $('body').append('<div id="pk-chat-toast"></div>'); }
        function showToast(msg, type = 'info') {
            var toast = $('#pk-chat-toast');
            toast.removeClass('toast-error toast-success toast-info').addClass('toast-' + type);
            toast.html(msg).show();
            // ให้หายไปเองใน 3 วินาที
            setTimeout(function() { toast.fadeOut(300); }, 3000);
        }

        var chatBox = $('#pk-chat-messages'); var chatBody = $('#pk-chat-body'); var chatHead = $('#pk-chat-head'); var chatBoxContainer = $('#pk-chat-box');
        var isFetching = false; var apiUrl = 'plugin.php?id=prasopkan_chat:api'; var currentRoomId = 1; 
        if ($('.pk-room-tab.active').length > 0) currentRoomId = $('.pk-room-tab.active').data('room');

        var savedTheme = localStorage.getItem('prasopkan_chat_theme');
        if(savedTheme === 'dark') { $('#pk-chat-box, #pk-chat-head').addClass('pk-dark-mode'); $('#pk-dark-mode-toggle').prop('checked', true); }

        $('#pk-chat-settings-btn').on('click', function(e) { e.stopPropagation(); $('#pk-chat-emoji-picker, #pk-chat-rp-modal, #pk-chat-shop-modal').hide(); $('#pk-chat-settings-menu').fadeToggle(150); });
        $('#pk-dark-mode-toggle').on('change', function() {
            if($(this).is(':checked')) { $('#pk-chat-box, #pk-chat-head').addClass('pk-dark-mode'); localStorage.setItem('prasopkan_chat_theme', 'dark'); } 
            else { $('#pk-chat-box, #pk-chat-head').removeClass('pk-dark-mode'); localStorage.setItem('prasopkan_chat_theme', 'light'); }
        });

        if($('#pk-chat-action-overlay').length === 0) {
            $('#pk-chat-box').append(`
                <div id="pk-chat-action-overlay" style="display:none;">
                    <div id="pk-chat-action-menu">
                        <div class="pk-menu-react-bar">
                            <span class="pk-msg-menu-react" data-emoji="👍">👍</span>
                            <span class="pk-msg-menu-react" data-emoji="❤️">❤️</span>
                            <span class="pk-msg-menu-react" data-emoji="😂">😂</span>
                            <span class="pk-msg-menu-react" data-emoji="😮">😮</span>
                            <span class="pk-msg-menu-react" data-emoji="😢">😢</span>
                            <span class="pk-msg-menu-react" data-emoji="😡">😡</span>
                        </div>
                        <div class="pk-menu-actions">
                            <div class="pk-menu-btn" id="pk-menu-reply"><span class="icon">↩️</span> ตอบกลับ</div>
                            <div class="pk-menu-btn" id="pk-menu-delete" style="color: #ff3b30;"><span class="icon">🗑️</span> ลบข้อความ</div>
                        </div>
                    </div>
                </div>
            `);
        }

        var shopModal = $('#pk-chat-shop-modal'); var currentShopType = 'name_style'; var shopDataCache = null;
        function loadShop() { $.ajax({ url: apiUrl + '&action=shop_info', type: 'GET', dataType: 'json', success: function(res) { if(res.status === 'success') { shopDataCache = res; $('#pk-user-balance').text(res.credit + ' ' + res.credit_name); renderShopItems(currentShopType); } } }); }
        
function renderShopItems(type) {
            if(!shopDataCache) return; var html = ''; var items = shopDataCache.items[type]; var inv = shopDataCache.inventory; var eq = shopDataCache.equipment[type];
            $.each(items, function(key, val) {
                var isOwned = inv.indexOf(key) !== -1; var isEquipped = (eq === key);
                html += '<div class="pk-shop-item '+(type=='gacha'?'pk-gacha-item':'')+'">';
                if(type === 'gacha') { html += '<div class="pk-shop-item-preview" style="font-size:30px; animation: pk-bounce 1s infinite alternate;">'+val.icon+'</div>'; } 
                else if(type === 'name_style') { html += '<div class="pk-shop-item-preview" style="'+val.css+'">ชื่อคุณ</div>'; } 
                else if (type === 'bubble_skin') { html += '<div class="pk-shop-item-preview" style="padding:10px; background:var(--pk-bg); border:none;"><div class="pk-bubble" style="'+val.css+'; display:inline-block; font-size:12px;">สวัสดี!</div></div>'; } 
                else { html += '<div class="pk-shop-item-preview">'+val.icon+' ชื่อคุณ</div>'; }
                html += '<div class="pk-shop-item-name">'+val.name+'</div>';
                
                // 💎 แก้ไขข้อความปุ่มตรงนี้! (สุ่มกาชา)
                if(type === 'gacha') { 
                    html += '<button class="pk-btn-buy" style="background:#d9363e;" data-key="'+key+'" data-type="'+type+'" data-price="'+val.price+'">สุ่มลุ้นโชค! '+val.price+' '+shopDataCache.credit_name+'</button>'; 
                } 
                else {
                    if(isEquipped) { 
                        html += '<button class="pk-btn-equip" style="background: #ff3b30; color: #ffffff; border: 1px solid #d32f2f;" data-key="" data-type="'+type+'">ถอดออก</button>'; 
                    } 
                    else if(isOwned) { 
                        html += '<button class="pk-btn-equip" data-key="'+key+'" data-type="'+type+'">ใช้งาน</button>'; 
                    } 
                    else { 
                        // 💎 แก้ไขข้อความปุ่มตรงนี้! (ซื้อสินค้า)
                        html += '<button class="pk-btn-buy" data-key="'+key+'" data-type="'+type+'" data-price="'+val.price+'">ซื้อ '+val.price+' '+shopDataCache.credit_name+'</button>'; 
                    }
                }
                html += '</div>';
            });
            $('#pk-shop-content').html(html);
        }

        $('#pk-chat-shop-btn').on('click', function(e) { e.stopPropagation(); $('#pk-chat-emoji-picker, #pk-chat-rp-modal, #pk-chat-settings-menu').hide(); shopModal.fadeToggle(150); loadShop(); });
        $('#pk-shop-close').on('click', function() { shopModal.fadeOut(150); });
        $('.pk-shop-tab').on('click', function() { $('.pk-shop-tab').removeClass('active'); $(this).addClass('active'); currentShopType = $(this).data('type'); renderShopItems(currentShopType); });
        
        $(document).on('click', '.pk-btn-buy', function() {
            var btn = $(this); var key = btn.data('key'); var type = btn.data('type'); var price = btn.data('price');
            var confirmMsg = type === 'gacha' ? 'ต้องการสุ่มกล่องนี้ในราคา ' + price + ' ใช่หรือไม่?' : 'ต้องการซื้อสินค้านี้ในราคา ' + price + ' ใช่หรือไม่?';
            if(confirm(confirmMsg)) {
                btn.text('กำลังร่ายมนต์...').prop('disabled', true);
                $.ajax({
                    url: apiUrl + '&action=shop_buy&item_key='+key+'&item_type='+type, type: 'GET', dataType: 'json',
                    success: function(res) { 
                        if(res.status === 'success') { 
                            if(res.is_gacha) { showToast('🎉 ยินดีด้วย! ได้รับ ' + res.won_name, 'success'); } 
                            else { showToast('✨ ซื้อสินค้าสำเร็จ!', 'success'); } 
                            loadShop(); 
                        } else { showToast('❌ ' + res.msg, 'error'); loadShop(); } 
                    }
                });
            }
        });
        
        $(document).on('click', '.pk-btn-equip', function() { $.ajax({ url: apiUrl + '&action=shop_equip&item_key='+$(this).data('key')+'&item_type='+$(this).data('type'), type: 'GET', dataType: 'json', success: function(res) { if(res.status === 'success') { loadShop(); fetchMessages(false); } else { showToast('❌ ' + res.msg, 'error'); } } }); });

        var badge = $('#pk-chat-badge'); var tooltip = $('#pk-chat-tooltip');
        var chatState = localStorage.getItem('prasopkan_chat_state');
        if(chatState !== 'open') { badge.text(Math.floor(Math.random() * 3) + 1).show(); }
        if (tooltip.length > 0) { function showTooltip() { if (chatBoxContainer.is(':hidden')) { tooltip.addClass('pk-tooltip-show'); setTimeout(function() { tooltip.removeClass('pk-tooltip-show'); }, 5000); } } setTimeout(showTooltip, 3000); setInterval(showTooltip, 45000); }
        
        function toggleChat() {
            if(chatBoxContainer.is(':hidden')) { 
                chatHead.hide(); badge.hide(); tooltip.removeClass('pk-tooltip-show'); 
                chatBoxContainer.css('display', 'flex').hide().fadeIn(200);
                localStorage.setItem('prasopkan_chat_state', 'open'); fetchMessages(true); setTimeout(function(){ $('#pk-chat-input').focus(); }, 200); 
            } 
            else { 
                chatBoxContainer.fadeOut(200, function() { chatHead.fadeIn(200); }); 
                localStorage.setItem('prasopkan_chat_state', 'closed'); setTimeout(function() { badge.text(Math.floor(Math.random() * 2) + 1).fadeIn(); }, 15000); 
            }
        }
        chatHead.on('click', toggleChat); $('#pk-chat-close-btn').on('click', toggleChat);
        if(chatState === 'open') { chatBoxContainer.css('display', 'flex'); chatHead.hide(); } else { chatBoxContainer.hide(); chatHead.show(); }

// --- ⏱️ ระบบ Snooze Logic ---

// 1. ตรวจสอบสถานะ Snooze ทันทีที่โหลดหน้าเว็บ
var snoozeUntil = localStorage.getItem('pk_chat_snooze_until');
var now = Math.floor(Date.now() / 1000);

if (snoozeUntil) {
    if (snoozeUntil === 'permanent' || now < parseInt(snoozeUntil)) {
        $('#pk-chat-head, #pk-chat-box').hide(); // ซ่อนทิ้งทั้งหมด
    } else {
        localStorage.removeItem('pk_chat_snooze_until'); // หมดเวลาแล้ว ลบทิ้ง
    }
}

// 2. เมื่อกดปุ่ม 'X' บนหัวแชท
$('#pk-chat-close-btn').off('click').on('click', function(e) {
    e.stopPropagation();
    $('#pk-chat-snooze-modal').fadeIn(200);
});

// 3. จัดการการเลือกเวลา
$(document).on('click', '.pk-snooze-btn', function() {
    var time = $(this).data('time');
    var expiry = '';

    if (time === 'permanent') {
        expiry = 'permanent';
    } else {
        expiry = Math.floor(Date.now() / 1000) + parseInt(time);
    }

    localStorage.setItem('pk_chat_snooze_until', expiry);
    localStorage.setItem('prasopkan_chat_state', 'closed');
    
    // ปิดหน้าจอทั้งหมด
    $('#pk-chat-snooze-modal').fadeOut(150);
    $('#pk-chat-box').fadeOut(200, function() {
        $('#pk-chat-head').fadeOut(200); // หายวับไปทั้งปุ่มลอย
    });
    
    showToast('🤫 ซ่อนแชทเรียบร้อยแล้ว', 'success');
});

// 4. ปุ่มยกเลิก (ปิดเมนู Snooze แต่ไม่ซ่อนปุ่มแชท)
$('#pk-snooze-cancel').on('click', function() {
    $('#pk-chat-snooze-modal').fadeOut(150);
});

        var typingTimer; $('#pk-chat-input').on('input', function() { clearTimeout(typingTimer); typingTimer = setTimeout(function() { $.ajax({url: apiUrl + '&action=typing&room_id=' + currentRoomId}); }, 1000); });
        $('.pk-room-tab').on('click', function() { $('.pk-room-tab').removeClass('active'); $(this).addClass('active'); currentRoomId = $(this).data('room'); chatBox.html('<div style="text-align:center; padding:15px; color:var(--pk-text-muted);">กำลังโหลด...</div>'); fetchMessages(true); });

        function fetchMessages(forceScroll = false) {
            if(isFetching) return; isFetching = true;
            $.ajax({
                url: apiUrl + '&action=get&room_id=' + currentRoomId, type: 'GET', dataType: 'json', cache: false,
                success: function(res) {
                    if(res.status === 'success') {
                        window.pkIsAdmin = res.is_admin; window.pkCurrentUid = res.current_uid; window.pkEnableMention = res.enable_mention; window.pkEnableReaction = res.enable_reaction;
                        var html = '';
                        if(res.typing_users && res.typing_users.length > 0) { $('#pk-typing-names').text(res.typing_users.join(', ')); $('#pk-chat-typing-indicator').show(); } else { $('#pk-chat-typing-indicator').hide(); }
                        if(res.data.length === 0) { html = '<div style="text-align:center; padding:15px; color:var(--pk-text-muted);">ยังไม่มีคนคุย มาเริ่มคุยกันเลย!</div>'; } else {
                            $.each(res.data, function(index, item) {
                                var isMine = (item.uid == res.current_uid); var isBot = (item.uid == 0); 
                                var rowClass = isMine ? 'pk-msg-mine' : (isBot ? 'pk-msg-bot' : 'pk-msg-other');
                                var displayName = item.username;
                                if(item.name_css !== '') { displayName = '<span style="'+item.name_css+'">' + displayName + '</span>'; }
                                else if(!isMine) { displayName = '<b style="color:'+(item.color ? item.color : 'var(--pk-text)')+';">' + displayName + '</b>'; }
                                else { displayName = '<b style="color:var(--pk-text-muted);">' + displayName + '</b>'; }
                                if(item.badge_icon !== '') { displayName = '<span class="pk-chat-badge-icon">' + item.badge_icon + '</span> ' + displayName; }
                                var bubbleStyle = item.bubble_css !== '' ? ' style="'+item.bubble_css+'"' : '';
                                var pills = ''; if(item.reactions && !isBot) { $.each(item.reactions, function(emoji, data) { var activeClass = data.me ? ' active' : ''; pills += '<span class="pk-react-pill'+activeClass+'" data-id="'+item.msg_id+'" data-emoji="'+emoji+'">'+emoji+' '+data.count+'</span>'; }); }
                                var reactionsHtml = pills !== '' ? '<div class="pk-reactions-display">' + pills + '</div>' : '';
                                var headerInfo = isMine ? '<span class="pk-msg-time">'+item.time+'</span>' : displayName + ' <span class="pk-msg-time">'+item.time+'</span>';
                                var bubbleData = ' data-id="'+item.msg_id+'" data-user="'+item.username+'" data-bot="'+(isBot?'1':'0')+'"';
                                html += '<div class="pk-msg-row ' + rowClass + '">'; if(!isBot) { html += '<div class="pk-msg-info">' + headerInfo + '</div>'; }
                                html += '<div class="pk-bubble-wrapper"><div class="pk-bubble"' + bubbleStyle + bubbleData + '>' + item.message + '</div></div>'; 
                                if(!isBot) html += reactionsHtml; html += '</div>';
                            });
                        }
                        var isAtBottom = (chatBox[0].scrollHeight - chatBox.scrollTop() <= chatBox.outerHeight() + 15); chatBox.html(html); if(isAtBottom || forceScroll) chatBox.scrollTop(chatBox[0].scrollHeight);
                    }
                    isFetching = false;
                }, error: function() { isFetching = false; }
            });
        }
        fetchMessages(true); setInterval(function() { fetchMessages(false); }, 3000);

        var pressTimer; var isLongPress = false;
        $(document).on('contextmenu', '.pk-bubble', function(e) { e.preventDefault(); return false; }); 
        $(document).on('touchstart mousedown', '.pk-bubble', function(e) {
            var $bubble = $(this); if($bubble.data('bot') == '1') return; isLongPress = false;
            pressTimer = setTimeout(function() {
                isLongPress = true; if (navigator.vibrate) { navigator.vibrate(50); }
                var msgId = $bubble.data('id'); var user = $bubble.data('user');
                $('.pk-msg-menu-react').attr('data-id', msgId);
                $('#pk-menu-reply').attr('data-user', user).toggle(window.pkEnableMention == 1);
                $('.pk-menu-react-bar').toggle(window.pkEnableReaction == 1);
                if(window.pkIsAdmin || window.pkCurrentUid == 1) { $('#pk-menu-delete').attr('data-id', msgId).show(); } else { $('#pk-menu-delete').hide(); }
                $('#pk-chat-action-overlay').fadeIn(200);
            }, 500);
        });
        $(document).on('touchmove mousemove', '.pk-bubble', function() { clearTimeout(pressTimer); isLongPress = false; });
        $(document).on('touchend mouseup', '.pk-bubble', function(e) { clearTimeout(pressTimer); if(isLongPress) { e.preventDefault(); e.stopPropagation(); } });
        $('#pk-chat-box').on('click', '#pk-chat-action-overlay', function(e) { if(e.target.id === 'pk-chat-action-overlay') { $(this).fadeOut(150); } });

        $(document).on('click', '.pk-msg-menu-react', function() { var msgId = $(this).attr('data-id'); var emoji = $(this).attr('data-emoji'); $('#pk-chat-action-overlay').fadeOut(150); $.ajax({ url: apiUrl + '&action=react&msg_id=' + msgId + '&reaction=' + encodeURIComponent(emoji), type: 'GET', dataType: 'json', success: function(res) { if(res.status === 'success') fetchMessages(false); } }); });
        $(document).on('click', '#pk-menu-reply', function() { var user = $(this).attr('data-user'); $('#pk-chat-action-overlay').fadeOut(150); var input = $('#pk-chat-input'); var currentVal = input.val(); if(currentVal.indexOf('@' + user) === -1) input.val('@' + user + ' ' + currentVal).focus(); else input.focus(); });
        $(document).on('click', '#pk-menu-delete', function() { var msgId = $(this).attr('data-id'); $('#pk-chat-action-overlay').fadeOut(150); if(confirm('ต้องการลบข้อความนี้?')) { $.ajax({ url: apiUrl + '&action=delete&msg_id=' + msgId, type: 'GET', dataType: 'json', success: function(res) { if(res.status === 'success') fetchMessages(false); else showToast('❌ ' + res.msg, 'error'); } }); } });
        $(document).on('click', '.pk-react-pill', function() { $.ajax({ url: apiUrl + '&action=react&msg_id=' + $(this).data('id') + '&reaction=' + encodeURIComponent($(this).data('emoji')), type: 'GET', dataType: 'json', success: function(res) { if(res.status === 'success') fetchMessages(false); } }); });

        $(document).on('click', function(e) { if (!$(e.target).closest('#pk-chat-emoji-picker, #pk-chat-emoji-btn, #pk-chat-rp-modal, #pk-chat-rp-btn, #pk-chat-settings-btn, #pk-chat-settings-menu, #pk-chat-shop-btn, #pk-chat-shop-modal').length) { $('#pk-chat-emoji-picker, #pk-chat-rp-modal, #pk-chat-settings-menu, #pk-chat-shop-modal').fadeOut(150); } });
        
        var rpModal = $('#pk-chat-rp-modal'); 
        $('#pk-chat-rp-btn').on('click', function(e) { e.stopPropagation(); $('#pk-chat-emoji-picker, #pk-chat-settings-menu, #pk-chat-shop-modal').hide(); rpModal.fadeToggle(150); }); 
        $('#pk-rp-cancel').on('click', function() { rpModal.fadeOut(150); });
        
        $('#pk-rp-submit').on('click', function() { 
            var amount = $('#pk-rp-amount').val(); var count = $('#pk-rp-count').val(); if(!amount || !count) return; 
            $(this).prop('disabled', true).text('กำลังส่ง...'); 
            $.ajax({ 
                url: apiUrl + '&action=send_redpacket&room_id=' + currentRoomId, type: 'GET', data: { amount: amount, count: count }, dataType: 'json', 
                success: function(res) { 
                    $('#pk-rp-submit').prop('disabled', false).text('โยนซอง!'); 
                    if(res.status === 'success') { 
                        rpModal.fadeOut(150); $('#pk-rp-amount').val(''); $('#pk-rp-count').val(''); fetchMessages(true); 
                        showToast('🧧 สร้างอั่งเปาสำเร็จ!', 'success');
                    } else { showToast('❌ ' + res.msg, 'error'); } 
                } 
            }); 
        });
        
        $(document).on('click', '.pk-redpacket-box', function() { 
            $.ajax({ 
                url: apiUrl + '&action=claim_redpacket&env_id=' + $(this).data('envid'), type: 'GET', dataType: 'json', 
                success: function(res) { 
                    if(res.status === 'success') { showToast('🎉 ดีใจด้วย! คุณได้รับอั่งเปา ' + res.amount + ' เครดิต', 'success'); } 
                    else { showToast('😢 ' + res.msg, 'error'); } 
                } 
            }); 
        });

        var emojis = ['😀','😂','🤣','😊','😍','🥰','😘','😜','😎','🥺','😭','😡','👍','👎','👏','🙏','❤️','🔥','✨','🎉','🌟','🐱','🐶','💡','📌'];
        var emojiPicker = $('#pk-chat-emoji-picker'); var emojiHtml = ''; $.each(emojis, function(i, emoji) { emojiHtml += '<span class="pk-emoji-item">' + emoji + '</span>'; }); emojiPicker.html(emojiHtml);
        $('#pk-chat-emoji-btn').on('click', function(e) { e.stopPropagation(); rpModal.hide(); $('#pk-chat-settings-menu, #pk-chat-shop-modal').hide(); emojiPicker.fadeToggle(150); });
        $(document).on('click', '.pk-emoji-item', function() { var input = $('#pk-chat-input'); input.val(input.val() + $(this).text()).focus(); });
        $('#pk-chat-img-btn').on('click', function() { $('#pk-chat-file-upload').click(); });
        $('#pk-chat-file-upload').on('change', function() { var file = this.files[0]; if(!file) return; var formData = new FormData(); formData.append('chat_image', file); var inputField = $('#pk-chat-input'); inputField.val('กำลังอัปโหลด...').prop('disabled', true); $.ajax({ url: apiUrl + '&action=upload', type: 'POST', data: formData, processData: false, contentType: false, dataType: 'json', success: function(res) { inputField.val('').prop('disabled', false).focus(); if(res.status === 'success') { $.ajax({ url: apiUrl + '&action=send&room_id=' + currentRoomId, type: 'GET', data: { message: '[img]' + res.url + '[/img]' }, dataType: 'json', success: function(s) { if(s.status === 'success') fetchMessages(true); } }); } else { showToast('❌ ' + res.msg, 'error'); } $('#pk-chat-file-upload').val(''); } }); });

        function sendMessage(e) { if(e) e.preventDefault(); var inputField = $('#pk-chat-input'); if(inputField.length === 0) return; var msg = inputField.val(); if(msg.trim() !== '') { inputField.prop('disabled', true); emojiPicker.fadeOut(150); $.ajax({ url: apiUrl + '&action=send&room_id=' + currentRoomId, type: 'GET', data: { message: msg }, dataType: 'json', success: function(res) { inputField.prop('disabled', false).val('').focus(); if(res.status === 'success') fetchMessages(true); else showToast('❌ ' + res.msg, 'error'); }, error: function() { inputField.prop('disabled', false).focus(); showToast('❌ เกิดข้อผิดพลาดในการส่ง', 'error'); } }); } }
        $('#pk-chat-send').off('click').on('click', sendMessage); $('#pk-chat-input').off('keypress').on('keypress', function(e) { if(e.which == 13) { sendMessage(e); } });
    });
})(jQuery);