(function($) {
    $(document).ready(function() {
        var chatHead = $('#pk-chat-head');
        if(chatHead.length === 0) return;

        var isMobile = function() { 
            return window.innerWidth <= 900 || ('ontouchstart' in window) || navigator.maxTouchPoints > 0; 
        };

        if(typeof pkMobileSettings === 'undefined') {
            window.pkMobileSettings = { smart_scroll: 1, fade_idle: 1, draggable: 1 };
        }

        if ($('#pk-mobile-ux-style').length === 0) {
            $('head').append(`
                <style id="pk-mobile-ux-style">
                    #pk-chat-head {
                        transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275), opacity 0.3s ease, left 0.4s ease-out, top 0.4s ease-out;
                    }
                    #pk-chat-head.pk-dragging {
                        transition: none !important; /* ปิด Effect ชั่วคราวตอนเอานิ้วลาก */
                        opacity: 1 !important;
                        transform: scale(1.05) !important;
                    }
                </style>
            `);
        }

        // --- 1. Smart Scroll ---
        if (pkMobileSettings.smart_scroll === 1) {
            var lastScrollTop = 0;
            $(window).on('scroll', function() {
                if (!isMobile() || chatHead.hasClass('pk-dragging')) return;
                var st = $(this).scrollTop();
                if (st > lastScrollTop && st > 100) { chatHead.css('transform', 'translateY(150px)'); } 
                else { chatHead.css('transform', 'translateY(0)'); }
                lastScrollTop = st;
            });
        }

        // --- 2. Fade on Idle ---
        if (pkMobileSettings.fade_idle === 1) {
            var idleTimer;
            function resetIdle() {
                if (!isMobile() || chatHead.hasClass('pk-dragging')) { chatHead.css('opacity', '1'); return; }
                chatHead.css('opacity', '1');
                clearTimeout(idleTimer);
                idleTimer = setTimeout(function() {
                    if ($('#pk-chat-box').is(':hidden')) chatHead.css('opacity', '0.4');
                }, 3000);
            }
            $(window).on('scroll touchstart click', resetIdle);
            resetIdle();
        }

        // --- 3. Draggable (ลากอิสระ + ดูดขอบ Messenger Style) ---
        if (pkMobileSettings.draggable === 1) {
            var headEl = chatHead[0];
            var isDragging = false;
            var touchOffsetX, touchOffsetY;

            headEl.addEventListener("touchstart", function(e) {
                if (!isMobile() || e.touches.length > 1) return;
                var rect = headEl.getBoundingClientRect();
                
                // 🛠️ ปลดล็อกพันธนาการ CSS! บังคับตัด Right/Bottom ทิ้งแบบถาวรในจังหวะที่แตะครั้งแรก
                if (!chatHead.data('detached')) {
                    chatHead.css({
                        'left': rect.left + 'px',
                        'top': rect.top + 'px',
                        'bottom': 'auto',
                        'right': 'auto',
                        'margin': '0'
                    });
                    chatHead.data('detached', true);
                }
                
                // คำนวณกรอบใหม่หลังจากตัดสปริงแล้ว
                rect = headEl.getBoundingClientRect();
                touchOffsetX = e.touches[0].clientX - rect.left;
                touchOffsetY = e.touches[0].clientY - rect.top;
                isDragging = false;
                $('#pk-chat-tooltip').fadeOut(150);
            }, {passive: true});

            headEl.addEventListener("touchmove", function(e) {
                if (!isMobile() || e.touches.length > 1) return;
                isDragging = true;
                e.preventDefault();
                chatHead.addClass('pk-dragging'); 

                var newX = e.touches[0].clientX - touchOffsetX;
                var newY = e.touches[0].clientY - touchOffsetY;

                // ตีเส้นขอบหน้าจอ ห้ามลากปุ่มหลุดจอหายไป
                var maxW = window.innerWidth - headEl.offsetWidth;
                var maxH = window.innerHeight - headEl.offsetHeight;

                newX = Math.max(0, Math.min(newX, maxW));
                newY = Math.max(0, Math.min(newY, maxH));

                chatHead.css({ 'left': newX + 'px', 'top': newY + 'px' });
            }, {passive: false});

            headEl.addEventListener("touchend", function(e) {
                if (!isDragging) return;
                isDragging = false;
                chatHead.removeClass('pk-dragging'); 
                chatHead.attr('data-dragged', 'true');
                setTimeout(() => chatHead.removeAttr('data-dragged'), 200);

                var rect = headEl.getBoundingClientRect();
                var centerX = rect.left + (rect.width / 2);
                var screenW = window.innerWidth;
                
                // 🎯 ระบบแม่เหล็ก: ถ้าปล่อยมือฝั่งขวาดูดขวา ปล่อยซ้ายดูดซ้าย
                var snapX = (centerX > screenW / 2) ? screenW - rect.width - 20 : 20;
                
                chatHead.css('left', snapX + 'px');
                if (pkMobileSettings.fade_idle === 1) resetIdle();
            }, {passive: true});

            headEl.addEventListener("click", function(e) {
                if (headEl.getAttribute('data-dragged')) {
                    e.stopPropagation(); e.preventDefault();
                }
            }, true);
        }
    });
})(jQuery);