(function($) {
    $(document).ready(function() {
        var chatHead = $('#pk-chat-head');
        if(chatHead.length === 0) return;

        // ดักจับเฉพาะโหมดมือถือ (ความกว้างจอน้อยกว่า 768px)
        var isMobile = function() { return $(window).width() <= 768; };

        // --- 1. Smart Scroll (ซ่อนเมื่อเลื่อนลง) ---
        if (pkMobileSettings.smart_scroll) {
            var lastScrollTop = 0;
            $(window).scroll(function() {
                if (!isMobile()) { chatHead.css('transform', ''); return; }
                var st = $(this).scrollTop();
                if (st > lastScrollTop && st > 50) {
                    // เลื่อนลง -> มุดหนีลงไป 150px
                    chatHead.css('transform', 'translateY(150px)');
                } else {
                    // เลื่อนขึ้น -> เด้งกลับมาที่เดิม
                    chatHead.css('transform', 'translateY(0)');
                }
                lastScrollTop = st;
            });
        }

        // --- 2. Fade on Idle (โปร่งแสงเมื่อไม่ได้ใช้) ---
        if (pkMobileSettings.fade_idle) {
            var idleTimer;
            function resetIdle() {
                if (!isMobile()) { chatHead.css('opacity', '1'); return; }
                chatHead.css('opacity', '1');
                clearTimeout(idleTimer);
                idleTimer = setTimeout(function() {
                    // ถ้ากล่องแชทปิดอยู่ ให้ปุ่มโปร่งแสงเหลือ 40%
                    if ($('#pk-chat-box').is(':hidden')) {
                        chatHead.css('opacity', '0.4');
                    }
                }, 3000);
            }
            $(window).on('scroll touchstart click', resetIdle);
            resetIdle();
        }

        // --- 3. Draggable (ลากย้ายตำแหน่งได้) ---
        if (pkMobileSettings.draggable) {
            var headEl = chatHead[0];
            var isDragging = false;
            var touchOffsetX, touchOffsetY;

            headEl.addEventListener("touchstart", function(e) {
                if (!isMobile()) return;
                var rect = headEl.getBoundingClientRect();
                touchOffsetX = e.touches[0].clientX - rect.left;
                touchOffsetY = e.touches[0].clientY - rect.top;
                isDragging = false;
            }, {passive: true});

            headEl.addEventListener("touchmove", function(e) {
                if (!isMobile()) return;
                isDragging = true;
                e.preventDefault(); // ล็อกหน้าจอไม่ให้เลื่อนตามตอนลากปุ่ม

                var newX = e.touches[0].clientX - touchOffsetX;
                var newY = e.touches[0].clientY - touchOffsetY;

                // เปลี่ยนระบบตำแหน่งให้ใช้ top/left เพื่อให้ลากได้อิสระ
                chatHead.css({
                    'transition': 'none',
                    'bottom': 'auto',
                    'right': 'auto',
                    'left': newX + 'px',
                    'top': newY + 'px',
                    'margin': '0'
                });
            }, {passive: false});

            headEl.addEventListener("touchend", function(e) {
                if (!isDragging) return;
                
                // แปะป้ายบอกว่า "นี่คือการลากนะ ห้ามเปิดแชท"
                chatHead.attr('data-dragged', 'true');
                setTimeout(() => chatHead.removeAttr('data-dragged'), 200);

                // ระบบดีดชิดขอบจออัตโนมัติ (Snap to Edge)
                var screenW = $(window).width();
                var rect = headEl.getBoundingClientRect();
                var snapX = (rect.left + rect.width/2 > screenW/2) ? screenW - rect.width - 20 : 20;

                chatHead.css({
                    'transition': 'left 0.3s ease, top 0.3s ease',
                    'left': snapX + 'px'
                });
            }, false);

            // ดักจับการ Click ของ chat.js (ใช้ Capture Phase เพื่อหยุด Event ก่อนใคร)
            headEl.addEventListener("click", function(e) {
                if (headEl.getAttribute('data-dragged')) {
                    e.stopPropagation();
                    e.preventDefault();
                }
            }, true);
        }

        // เพิ่ม CSS ควบคุมการเคลื่อนไหว (Animation)
        $('head').append(`
            <style>
                #pk-chat-head {
                    transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275), opacity 0.3s ease;
                }
            </style>
        `);
    });
})(jQuery);