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

        // แทรก CSS สำหรับ Animation ความลื่นไหลตอนปุ่มวิ่งดูดขอบจอ
        if ($('#pk-mobile-ux-style').length === 0) {
            $('head').append(`
                <style id="pk-mobile-ux-style">
                    #pk-chat-head {
                        transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275), opacity 0.3s ease, left 0.4s cubic-bezier(0.25, 0.8, 0.25, 1), top 0.4s ease;
                        will-change: transform, left, top, opacity;
                    }
                    #pk-chat-head.pk-dragging {
                        transition: none !important; /* ปิด Effect ชั่วคราวตอนเอานิ้วลาก */
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
                
                // ถ้าเอานิ้วลากปุ่มอยู่ ห้ามซ่อนเด็ดขาด
                if (chatHead.hasClass('pk-dragging')) return;

                if (st > lastScrollTop && st > 100) {
                    // เลื่อนลง -> มุดหนีลงไปซ่อน
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
                    // ถ้าแชทปิดอยู่ ให้ปุ่มโปร่งแสงเหลือ 40%
                    if ($('#pk-chat-box').is(':hidden')) {
                        chatHead.css('opacity', '0.4');
                    }
                }, 3000);
            }
            $(window).on('scroll touchstart click', resetIdle);
            resetIdle();
        }

        // --- 3. Draggable (ลากย้ายตำแหน่งสไตล์ Facebook Messenger) ---
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
                
                // ปิด Tooltip เวลาแตะลาก เพื่อไม่ให้มันเกะกะจอ
                $('#pk-chat-tooltip').fadeOut(150);
            }, {passive: true});

            headEl.addEventListener("touchmove", function(e) {
                if (!isMobile() || e.touches.length > 1) return;
                isDragging = true;
                e.preventDefault(); // ล็อกหน้าจอไม่ให้เลื่อนตามตอนลากปุ่ม

                chatHead.addClass('pk-dragging'); 

                var screenW = window.innerWidth;
                var screenH = window.innerHeight;
                var headW = chatHead.outerWidth();
                var headH = chatHead.outerHeight();

                var newX = e.touches[0].clientX - touchOffsetX;
                var newY = e.touches[0].clientY - touchOffsetY;

                // 🛡️ บังคับไม่ให้ลากทะลุขอบจอ (Boundaries limits)
                newX = Math.max(0, Math.min(newX, screenW - headW));
                newY = Math.max(0, Math.min(newY, screenH - headH));

                // เปลี่ยนตำแหน่งให้ตามนิ้วอย่างอิสระ
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
                chatHead.removeClass('pk-dragging'); // เปิด Animation กลับมาให้ปุ่มวิ่งเข้าขอบจอ

                // แปะป้ายป้องกันการเปิดแชทลั่นตอนปล่อยนิ้ว
                chatHead.attr('data-dragged', 'true');
                setTimeout(function() { chatHead.removeAttr('data-dragged'); }, 200);

                var screenW = window.innerWidth;
                var screenH = window.innerHeight;
                var rect = headEl.getBoundingClientRect();
                
                // 🎯 คำนวณจุดกึ่งกลางของปุ่ม (ถ้าเอนไปซ้ายดูดซ้าย ถ้าเอนไปขวาดูดขวา)
                var centerX = rect.left + (rect.width / 2);
                var snapX = (centerX > screenW / 2) ? screenW - rect.width - 15 : 15;
                
                // ป้องกันไม่ให้ปุ่มไปค้างอยู่สูงเกินไป (ทับ Header เว็บ) หรือต่ำเกินไป (ทะลุจอ)
                var snapY = Math.max(15, Math.min(rect.top, screenH - rect.height - 80));

                chatHead.css({
                    'left': snapX + 'px',
                    'top': snapY + 'px'
                });
                
                if (parseInt(pkMobileSettings.fade_idle) === 1) resetIdle();
            }, {passive: true});

            // ดักจับ Click ไม่ให้แชทเปิดเองตอนที่เพิ่งลากเสร็จ
            headEl.addEventListener("click", function(e) {
                if (headEl.getAttribute('data-dragged')) {
                    e.stopPropagation();
                    e.preventDefault();
                }
            }, true);
        }

    });
})(jQuery);