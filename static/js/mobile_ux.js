(function($) {
    $(document).ready(function() {
        var chatHead = $('#pk-chat-head');
        if(chatHead.length === 0) return;

        // 📱 ตรวจจับมือถือ
        var isMobile = function() { 
            return window.innerWidth <= 900 || ('ontouchstart' in window) || navigator.maxTouchPoints > 0; 
        };

        // 🎨 ฝัง CSS ย่อขนาดปุ่มให้มินิมอล (เฉพาะมือถือ) + Animation ซ่อนปุ่ม
        if ($('#pk-mobile-ux-style').length === 0) {
            $('head').append(`
                <style id="pk-mobile-ux-style">
                    @media (max-width: 900px) {
                        /* ย่อขนาดกล่องปุ่มแชทให้มินิมอล */
                        #pk-chat-head {
                            width: 45px !important;
                            height: 45px !important;
                            bottom: 20px !important;
                            right: 15px !important;
                            transition: transform 0.4s cubic-bezier(0.175, 0.885, 0.32, 1.275) !important;
                        }
                        /* ย่อขนาดไอคอนแชทด้านใน */
                        #pk-chat-head .pk-chat-head-icon svg {
                            width: 22px !important;
                            height: 22px !important;
                        }
                        /* ปรับขนาดตัวเลขแจ้งเตือนสีแดงให้เล็กลงตามสัดส่วน */
                        #pk-chat-badge {
                            width: 18px !important;
                            height: 18px !important;
                            font-size: 11px !important;
                            line-height: 18px !important;
                            top: -2px !important;
                            right: -2px !important;
                        }
                        /* ปรับขนาดบอลลูนข้อความทักทายให้พอดีกัน */
                        #pk-chat-tooltip {
                            right: 55px !important;
                            font-size: 13px !important;
                            padding: 6px 12px !important;
                        }
                    }
                </style>
            `);
        }

        // ==========================================
        // ระบบเดียวที่เหลืออยู่: Smart Scroll (ซ่อนปุ่มเวลาไถจอลง)
        // ==========================================
        var lastScrollTop = 0;
        $(window).on('scroll', function() {
            if (!isMobile()) {
                chatHead.css('transform', 'translateY(0)');
                return;
            }
            
            var st = $(this).scrollTop();
            
            // ถ้ากำลังเปิดหน้าต่างแชทคุยอยู่ ไม่ต้องซ่อนปุ่ม
            if ($('#pk-chat-box').is(':visible')) {
                return;
            }

            if (st > lastScrollTop && st > 100) { 
                // ไถจอลง -> มุดหนีลงไปซ่อนด้านล่าง 100px
                chatHead.css('transform', 'translateY(200px)'); 
            } else { 
                // ไถจอขึ้น -> เด้งกลับมาตำแหน่งเดิม
                chatHead.css('transform', 'translateY(0)'); 
            }
            lastScrollTop = st;
        });

    });
})(jQuery);