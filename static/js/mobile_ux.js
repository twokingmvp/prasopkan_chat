(function($) {
    $(document).ready(function() {
        var chatHead = $('#pk-chat-head');
        if(chatHead.length === 0) return;

        // 🛠️ ตรวจสอบว่าเป็นมือถือ/แท็บเล็ตจริงๆ (รองรับจอ Touch Screen)
        var isMobile = function() { 
            return window.innerWidth <= 900 || ('ontouchstart' in window) || navigator.maxTouchPoints > 0; 
        };

        // ป้องกัน Error หากดึงการตั้งค่าไม่สำเร็จ
        if(typeof pkMobileSettings === 'undefined') {
            window.pkMobileSettings = { smart_scroll: 1, fade_idle: 1, draggable: 1 };
        }

        // แทรก CSS สำหรับ Animation ความลื่นไหล
        if ($('#pk-mobile-ux-style').length === 0) {
            $('head').append(`
                <style id="pk-mobile-ux-style">
                    #pk-chat-head {
                        transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275), opacity 0.3s ease, left 0.3s ease, top 0.3s ease;
                        will-change: transform, left, top, opacity;
                    }
                    #pk-chat-head.pk-dragging {
                        transition: none !important; /* ปิด Effect ตอนลากนิ้ว */
                        opacity: 1 !important;
                    }
                </style>
            `);
        }

        // --- 1. Smart Scroll (ซ่อนเมื่อเลื่อนลง) ---
        if (parseInt(pkMobileSettings.smart_scroll) === 1) {
            var lastScrollTop = 0;
            $(window).on('scroll', function() {
                if (!isMobile()) { chatHead.css('transform', ''); return; }
                
                var st = $(this).scrollTop();
                // ถ้าเอานิ้วลากปุ่มอยู่ ห้ามซ่อน
                if (chatHead.hasClass('pk-dragging')) return;

                if (st > lastScrollTop && st > 100) {
                    // เลื่อนลง -> มุดหนี (ผลักลงไปซ่อนด้านล่าง)
                    chatHead.css('transform', 'translateY(150px) scale(0.8)');
                } else {
                    // เลื่อนขึ้น -> เด้งกลับมา
                    chatHead.css('transform', 'translateY(0) scale(1)');
                }
                lastScrollTop = st;
            });
        }

        // --- 2. Fade on Idle (โปร่งแสงเมื่อไม่ได้ใช้) ---
        if (parseInt(pkMobileSettings.fade_idle) === 1) {
            var idleTimer;
            function resetIdle() {
                if (!isMobile() || chatHead.hasClass('pk-dragging')) { 
                    chatHead.css('opacity', '1'); 
                    return; 
                }
                chatHead.css('opacity', '1');
                clearTimeout(idleTimer);
                idleTimer = setTimeout(function() {
                    // ถ้าแชทปิดอยู่ ให้มันโปร่งแสง
                    if ($('#pk-chat-box').is(':hidden')) {
                        chatHead.css('opacity', '0.4');
                    }
                }, 3000); // โปร่งแสงใน 3 วินาที
            }
            $(window).on('scroll touchstart click', resetIdle);
            resetIdle();
        }

        // --- 3. Draggable (ลากย้ายตำแหน่งได้) ---
        if (parseInt(pkMobileSettings.draggable) === 1) {
            var headEl = chatHead[0];
            var isDragging = false;
            var touchOffsetX, touchOffsetY;

            headEl.addEventListener("touchstart", function(e) {
                if (!isMobile() || e.touches.length > 1) return;
                
                var rect = headEl.getBoundingClientRect();
                touchOffsetX = e.touches[0].clientX - rect.left;
                touchOffsetY = e.touches[0].clientY - rect.top;
                isDragging = false;
            }, {passive: true});

            headEl.addEventListener("touchmove", function(e) {
                if (!isMobile() || e.touches.length > 1) return;
                isDragging = true;
                e.preventDefault(); // ล็อกหน้าจอไม่ให้เลื่อนตาม

                chatHead.addClass('pk-dragging'); // ปิด Transition ชั่วคราว

                var newX = e.touches[0].clientX - touchOffsetX;
                var newY = e.touches[0].clientY - touchOffsetY;

                chatHead.css({
                    'bottom': 'auto',
                    'right': 'auto',
                    'left': newX + 'px',
                    'top': newY + 'px',
                    'margin': '0'
                });
            }, {passive: false});

            headEl.addEventListener("touchend", function(e) {
                if (!isDragging) return;
                isDragging = false;
                chatHead.removeClass('pk-dragging'); // เปิด Transition กลับมา

                // แปะป้ายป้องกันการคลิกเปิดแชทลั่นตอนปล่อยนิ้ว
                chatHead.attr('data-dragged', 'true');
                setTimeout(function() { chatHead.removeAttr('data-dragged'); }, 200);

                // ดีดปุ่มเข้าขอบจอซ้าย-ขวาอัตโนมัติ (Snap)
                var screenW = $(window).width();
                var rect = headEl.getBoundingClientRect();
                var snapX = (rect.left + rect.width / 2 > screenW / 2) ? screenW - rect.width - 15 : 15;

                chatHead.css('left', snapX + 'px');
                
                if (parseInt(pkMobileSettings.fade_idle) === 1) resetIdle();
            }, {passive: true});

            // ดักจับ Click ไม่ให้แชทเด้งตอนลากเสร็จ
            headEl.addEventListener("click", function(e) {
                if (headEl.getAttribute('data-dragged')) {
                    e.stopPropagation();
                    e.preventDefault();
                }
            }, true);
        }

    });
})(jQuery);