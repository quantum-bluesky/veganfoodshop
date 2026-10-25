/**
 * Bếp Chay An Yên - Client Interaction Logic
 * Lightweight, Vanilla JS, Mobile-First
 */

document.addEventListener('DOMContentLoaded', function () {
    const orderForm = document.getElementById('orderForm');
    const custPhone = document.getElementById('custPhone');
    const custName = document.getElementById('custName');
    const trustedBadge = document.getElementById('trustedBadge');
    const payLaterWrapper = document.getElementById('payLaterWrapper');
    const payLaterRadio = document.getElementById('payLaterRadio');
    const vietqrRadio = document.getElementById('vietqrRadio');
    const submitBtn = document.getElementById('submitBtn');
    const btnTotalText = document.getElementById('btnTotalText');
    const dateSelector = document.getElementById('dateSelector');
    const slotContainer = document.getElementById('slotContainer');

    // 1. Cập nhật giao diện radio option khi click
    function setupRadioGroup(groupName) {
        const radios = document.querySelectorAll(`input[name="${groupName}"]`);
        radios.forEach(radio => {
            radio.addEventListener('change', function () {
                radios.forEach(r => {
                    const label = r.closest('.zen-option');
                    if (label) {
                        label.classList.toggle('is-checked', r.checked);
                    }
                });
            });
            // Khởi tạo trạng thái ban đầu
            if (radio.checked) {
                const label = radio.closest('.zen-option');
                if (label) label.classList.add('is-checked');
            }
        });
    }

    setupRadioGroup('order_type');
    setupRadioGroup('slot_id');
    setupRadioGroup('payment_method');

    // Lọc món theo hình thức nhận (Tại chỗ vs Mang về) (Issue #17)
    function filterMenuByOrderType(orderType) {
        const rows = document.querySelectorAll('.menu-item-row');
        rows.forEach(row => {
            const scope = row.dataset.scope || 'both';
            if (scope === 'both') {
                row.style.display = 'flex';
            } else if (orderType === 'dine_in' && scope === 'dine_in_only') {
                row.style.display = 'flex';
            } else if (orderType === 'takeaway' && scope === 'takeaway_only') {
                row.style.display = 'flex';
            } else {
                row.style.display = 'none';
                const input = row.querySelector('.qty-input');
                if (input && parseInt(input.value || '0', 10) > 0) {
                    input.value = '0';
                }
            }
        });
        calculateTotal();
    }

    const orderTypeRadios = document.querySelectorAll('input[name="order_type"]');
    orderTypeRadios.forEach(radio => {
        radio.addEventListener('change', function () {
            if (this.checked) {
                filterMenuByOrderType(this.value);
            }
        });
    });

    const initialOrderType = document.querySelector('input[name="order_type"]:checked')?.value || 'dine_in';
    filterMenuByOrderType(initialOrderType);

    // 2. Tính toán tổng tiền theo thời gian thực
    function calculateTotal() {
        let total = 0;
        let totalItems = 0;
        const itemRows = document.querySelectorAll('.menu-item-row');

        itemRows.forEach(row => {
            const price = parseInt(row.dataset.price || '0', 10);
            const qtyInput = row.querySelector('.qty-input');
            const qty = parseInt(qtyInput.value || '0', 10);

            if (qty > 0) {
                total += price * qty;
                totalItems += qty;
            }
        });

        // Format tiền VND
        const formattedTotal = new Intl.NumberFormat('vi-VN').format(total) + 'đ';
        if (btnTotalText) {
            btnTotalText.textContent = total > 0 ? ` (${formattedTotal})` : '';
        }

        return { total, totalItems };
    }

    // 3. Tăng giảm số lượng món
    document.querySelectorAll('.qty-control').forEach(control => {
        const minusBtn = control.querySelector('.qty-minus');
        const plusBtn = control.querySelector('.qty-plus');
        const qtyInput = control.querySelector('.qty-input');

        minusBtn.addEventListener('click', (e) => {
            e.preventDefault();
            let val = parseInt(qtyInput.value || '0', 10);
            if (val > 0) {
                qtyInput.value = val - 1;
                calculateTotal();
            }
        });

        plusBtn.addEventListener('click', (e) => {
            e.preventDefault();
            let val = parseInt(qtyInput.value || '0', 10);
            if (val < 10) {
                qtyInput.value = val + 1;
                calculateTotal();
            }
        });
    });

    // 4. Kiểm tra SĐT khách quen khi blur
    if (custPhone) {
        custPhone.addEventListener('blur', function () {
            const rawPhone = this.value.trim();
            const cleanPhone = rawPhone.replace(/[^0-9]/g, '');

            if (cleanPhone.length >= 9) {
                fetch(`api.php?action=check_phone&phone=${encodeURIComponent(cleanPhone)}`)
                    .then(res => res.json())
                    .then(data => {
                        if (data && data.success && data.is_trusted === 1) {
                            // Hiện badge khách quen và mở tùy chọn trả sau
                            if (trustedBadge) {
                                trustedBadge.classList.remove('hidden');
                                trustedBadge.style.display = 'block';
                            }
                            if (payLaterWrapper) {
                                payLaterWrapper.classList.remove('hidden');
                                payLaterWrapper.style.display = 'flex';
                            }
                            if (payLaterRadio) {
                                payLaterRadio.checked = true;
                                payLaterRadio.dispatchEvent(new Event('change'));
                            }
                            // Tự điền tên nếu người dùng chưa nhập
                            if (data.name && custName && custName.value.trim() === '') {
                                custName.value = data.name;
                            }
                        } else {
                            // Khách mới
                            if (trustedBadge) {
                                trustedBadge.classList.add('hidden');
                                trustedBadge.style.display = 'none';
                            }
                            if (payLaterWrapper) {
                                payLaterWrapper.classList.add('hidden');
                                payLaterWrapper.style.display = 'none';
                            }
                            if (vietqrRadio) {
                                vietqrRadio.checked = true;
                                vietqrRadio.dispatchEvent(new Event('change'));
                            }
                        }
                    })
                    .catch(err => {
                        console.error('Lỗi kiểm tra khách quen:', err);
                    });
            }
        });
    }

    // 5. Thay đổi ngày mở ca (nếu có selector ngày)
    if (dateSelector && slotContainer) {
        dateSelector.addEventListener('change', function () {
            const selectedDate = this.value;
            fetch(`api.php?action=get_slots&date=${encodeURIComponent(selectedDate)}`)
                .then(res => res.json())
                .then(data => {
                    if (data && data.success) {
                        renderSlots(data.slots, Boolean(data.is_closed), data.closed_reason || '');
                    }
                })
                .catch(err => console.error('Lỗi tải ca:', err));
        });
    }

    function renderSlots(slots, isClosed = false, closedReason = '') {
        if (!slotContainer) return;
        slotContainer.innerHTML = '';

        const urgentZaloNote = document.getElementById('urgentZaloNote');
        let hasPastCutoff = false;
        let firstChecked = false;

        if (isClosed) {
            slotContainer.innerHTML = `
                <div style="text-align: center; padding: 16px 12px; background: #FAF7F2; border-radius: 12px; grid-column: span 2;">
                    <p style="font-size: 0.85rem; font-weight: 600; color: #8C4A26;">🍃 Bếp tạm nghỉ vào ngày này</p>
                    <p style="font-size: 0.75rem; color: #5C4033; margin-top: 4px; line-height: 1.4;">
                        ${closedReason || 'Bếp xin phép tạm nghỉ đi hái trà & chuẩn bị nguyên liệu mới.'}
                    </p>
                </div>
            `;
            if (urgentZaloNote) urgentZaloNote.style.display = 'none';
            return;
        }

        if (!slots || slots.length === 0) {
            slotContainer.innerHTML = '<p class="text-xs text-gray-500 text-center py-3 col-span-2">Hôm nay bếp chưa mở ca, xin bạn quay lại sau nhé.</p>';
            if (urgentZaloNote) urgentZaloNote.style.display = 'none';
            return;
        }

        slots.forEach((slot) => {
            const isFull = Boolean(slot.is_full);
            const isPastSlot = Boolean(slot.is_past_slot);
            const isPastCutoff = Boolean(slot.is_past_cutoff);
            const isUnavailable = isFull || isPastCutoff;

            if (isPastCutoff) {
                hasPastCutoff = true;
            }

            let statusText = `Còn ${slot.remaining_capacity} chỗ`;
            if (isPastSlot) {
                statusText = 'Đã qua giờ';
            } else if (isPastCutoff) {
                statusText = `Quá giờ đặt (trước ${slot.cutoff_hours || 3}h)`;
            } else if (isFull) {
                statusText = 'Đã hết suất';
            }

            const shouldCheck = !firstChecked && !isUnavailable;
            if (shouldCheck) {
                firstChecked = true;
            }

            const label = document.createElement('label');
            label.className = `zen-option ${isUnavailable ? 'is-disabled' : ''} ${shouldCheck ? 'is-checked' : ''}`;

            label.innerHTML = `
                <input type="radio" name="slot_id" value="${slot.id}" ${shouldCheck ? 'checked' : ''} ${isUnavailable ? 'disabled' : ''}>
                <p style="font-weight: 600; font-size: 0.85rem;">${slot.slot_time}</p>
                <p style="font-size: 0.7rem; opacity: 0.8; margin-top: 2px;">
                    ${statusText}
                </p>
            `;

            slotContainer.appendChild(label);
        });

        if (urgentZaloNote) {
            urgentZaloNote.style.display = hasPastCutoff ? 'block' : 'none';
        }

        setupRadioGroup('slot_id');
    }

    // 6. Kiểm tra validation trước khi gửi form
    if (orderForm) {
        // 6. Xử lý thông tin đăng nhập Zalo (Option tự động điền)
        const zaloLoginSection = document.getElementById('zaloLoginSection');
        const zaloProfileSection = document.getElementById('zaloProfileSection');
        const zaloAvatarWrapper = document.getElementById('zaloAvatarWrapper');
        const zaloProfileName = document.getElementById('zaloProfileName');
        const zaloProfilePhone = document.getElementById('zaloProfilePhone');
        const zaloLogoutBtn = document.getElementById('zaloLogoutBtn');
        const custZaloId = document.getElementById('custZaloId');
        const custAvatarUrl = document.getElementById('custAvatarUrl');
        const upcomingBookingBanner = document.getElementById('upcomingBookingBanner');
        const upcomingBookingText = document.getElementById('upcomingBookingText');
        const btnViewUpcoming = document.getElementById('btnViewUpcoming');
        const btnOpenMyBookings = document.getElementById('btnOpenMyBookings');
        const myBookingsModal = document.getElementById('myBookingsModal');
        const btnCloseMyBookings = document.getElementById('btnCloseMyBookings');
        const myBookingsList = document.getElementById('myBookingsList');

        let myOrdersData = [];

        function renderMyBookings(orders) {
            if (!myBookingsList) return;
            if (!orders || orders.length === 0) {
                myBookingsList.innerHTML = `
                    <div style="text-align: center; padding: 30px 10px; color: var(--text-muted-warm); font-size: 0.85rem;">
                        <span style="font-size: 2rem; display: block; margin-bottom: 8px;">🍃</span>
                        Bạn chưa có đơn đặt món nào gần đây.<br>Hãy chọn một ca lành hôm nay để bếp phục vụ nhé!
                    </div>
                `;
                return;
            }

            myBookingsList.innerHTML = orders.map(order => {
                const isPaid = order.payment_status === 'paid';
                const isPayLater = order.payment_status === 'pay_later';
                const isCancelled = order.order_status === 'cancelled';
                
                let paymentBadge = '<span style="font-size: 0.7rem; background: #FEF3C7; color: #92400E; padding: 2px 6px; border-radius: 6px; font-weight: 600;">Chờ VietQR</span>';
                if (isPaid) {
                    paymentBadge = '<span style="font-size: 0.7rem; background: #D1FAE5; color: #065F46; padding: 2px 6px; border-radius: 6px; font-weight: 600;">✓ Đã thanh toán</span>';
                } else if (isPayLater) {
                    paymentBadge = '<span style="font-size: 0.7rem; background: #E0E7FF; color: #3730A3; padding: 2px 6px; border-radius: 6px; font-weight: 600;">Trả sau tại bếp</span>';
                }

                let statusBadge = '<span style="font-size: 0.7rem; background: #F3F4F6; color: #4B5563; padding: 2px 6px; border-radius: 6px;">Đã xác nhận</span>';
                if (order.order_status === 'completed') {
                    statusBadge = '<span style="font-size: 0.7rem; background: #E5E7EB; color: #374151; padding: 2px 6px; border-radius: 6px;">Hoàn tất</span>';
                } else if (isCancelled) {
                    statusBadge = '<span style="font-size: 0.7rem; background: #FEE2E2; color: #991B1B; padding: 2px 6px; border-radius: 6px;">Đã hủy</span>';
                }

                const itemsText = (order.items || []).map(it => `${it.item_name} x${it.quantity}`).join(', ');

                return `
                    <div style="background: #FFFFFF; border: 1px solid var(--brand-border-soft); border-radius: 14px; padding: 12px 14px; box-shadow: 0 2px 6px rgba(0,0,0,0.02);">
                        <div style="display: flex; justify-content: space-between; align-items: flex-start; margin-bottom: 6px;">
                            <div>
                                <strong style="font-size: 0.88rem; color: var(--brand-tea-dark);">${order.order_code}</strong>
                                <div style="font-size: 0.74rem; color: var(--brand-tea-green); font-weight: 600; margin-top: 2px;">
                                    ⏰ Ca ${order.slot_time} • ${order.slot_date}
                                </div>
                            </div>
                            <div style="display: flex; flex-direction: column; align-items: flex-end; gap: 4px;">
                                ${paymentBadge}
                                ${statusBadge}
                            </div>
                        </div>

                        <div style="font-size: 0.76rem; color: var(--text-muted-warm); margin-bottom: 6px; line-height: 1.4;">
                            ${order.order_type === 'dine_in' ? '🍵 Ghé ăn tại bếp' : '🥡 Ship mang về'}: ${itemsText}
                        </div>

                        <div style="display: flex; justify-content: space-between; align-items: center; border-top: 1px dashed var(--brand-border-soft); padding-top: 8px; margin-top: 6px;">
                            <span style="font-size: 0.85rem; font-weight: 700; color: var(--brand-tea-dark);">
                                ${new Intl.NumberFormat('vi-VN').format(order.total_amount)}đ
                            </span>
                            <div style="display: flex; gap: 6px;">
                                ${(!isPaid && !isPayLater && !isCancelled) ? `
                                    <a href="process_order.php?code=${encodeURIComponent(order.order_code)}" 
                                       style="font-size: 0.72rem; background: #0068FF; color: #FFFFFF; padding: 4px 8px; border-radius: 6px; text-decoration: none; font-weight: 600;">
                                        Mở VietQR ↗
                                    </a>
                                ` : ''}
                                <a href="process_order.php?code=${encodeURIComponent(order.order_code)}" 
                                   style="font-size: 0.72rem; background: #F3F4F6; color: #374151; padding: 4px 8px; border-radius: 6px; text-decoration: none;">
                                    Chi tiết
                                </a>
                            </div>
                        </div>
                    </div>
                `;
            }).join('');
        }

        function loadMyBookings(identifier) {
            if (!identifier) return;
            fetch(`api.php?action=get_my_orders&identifier=${encodeURIComponent(identifier)}`)
                .then(res => res.json())
                .then(data => {
                    if (data && data.success) {
                        myOrdersData = data.orders || [];
                        renderMyBookings(myOrdersData);

                        // Xử lý Thẻ Nhắc Nhở Lịch Hẹn Sắp Tới (Issue #14)
                        if (data.upcoming && upcomingBookingBanner && upcomingBookingText) {
                            const up = data.upcoming;
                            const typeStr = up.order_type === 'dine_in' ? 'tại chỗ' : 'ship mang về';
                            upcomingBookingText.innerHTML = `Bạn có lịch hẹn dùng bữa <strong>${typeStr}</strong> vào <strong>Ca ${up.slot_time} - ${up.slot_date}</strong> (Đơn <strong>#${up.order_code}</strong>). Bếp đang chuẩn bị chu đáo để đón bạn ghé nhé!`;
                            upcomingBookingBanner.classList.remove('hidden');
                            upcomingBookingBanner.style.display = 'block';

                            if (btnViewUpcoming) {
                                btnViewUpcoming.onclick = function() {
                                    if (myBookingsModal) {
                                        myBookingsModal.classList.remove('hidden');
                                        myBookingsModal.style.display = 'flex';
                                    }
                                };
                            }
                        } else if (upcomingBookingBanner) {
                            upcomingBookingBanner.classList.add('hidden');
                            upcomingBookingBanner.style.display = 'none';
                        }
                    }
                })
                .catch(err => {
                    console.error('Lỗi tải lịch đặt của tôi:', err);
                });
        }

        function checkZaloSession() {
            let savedZalo = null;
            try {
                const raw = localStorage.getItem('public_customer_info');
                if (raw) savedZalo = JSON.parse(raw);
            } catch (e) {
                savedZalo = null;
            }

            if (savedZalo && (savedZalo.zalo_id || savedZalo.name)) {
                // Hiển thị phần profile Zalo
                if (zaloLoginSection) zaloLoginSection.classList.add('hidden');
                if (zaloProfileSection) {
                    zaloProfileSection.classList.remove('hidden');
                    zaloProfileSection.style.display = 'flex';
                }

                if (zaloProfileName) zaloProfileName.textContent = savedZalo.name || 'Khách Zalo';
                if (zaloProfilePhone) zaloProfilePhone.textContent = savedZalo.phone ? `SĐT: ${savedZalo.phone}` : '';

                // Avatar
                if (zaloAvatarWrapper) {
                    if (savedZalo.avatar_url) {
                        zaloAvatarWrapper.innerHTML = `<img src="${savedZalo.avatar_url}" alt="Avatar" style="width: 100%; height: 100%; object-fit: cover;" onerror="this.parentElement.innerHTML='${(savedZalo.name || 'Z').charAt(0).toUpperCase()}';">`;
                    } else {
                        zaloAvatarWrapper.textContent = (savedZalo.name || 'Z').charAt(0).toUpperCase();
                    }
                }

                // Tự động điền Form nếu form đang trống hoặc có dữ liệu mới
                if (custName && savedZalo.name && !custName.value.trim()) {
                    custName.value = savedZalo.name;
                }
                if (custPhone && savedZalo.phone && !savedZalo.phone.startsWith('Zalo:') && !custPhone.value.trim()) {
                    custPhone.value = savedZalo.phone;
                    // Kích hoạt blur để kiểm tra trạng thái khách quen
                    custPhone.dispatchEvent(new Event('blur'));
                }

                if (custZaloId) custZaloId.value = savedZalo.zalo_id || '';
                if (custAvatarUrl) custAvatarUrl.value = savedZalo.avatar_url || '';

                // Tải lịch đặt cá nhân (Issue #14)
                loadMyBookings(savedZalo.zalo_id || savedZalo.phone);
            } else {
                // Chưa đăng nhập Zalo
                if (zaloLoginSection) zaloLoginSection.classList.remove('hidden');
                if (zaloProfileSection) {
                    zaloProfileSection.classList.add('hidden');
                    zaloProfileSection.style.display = 'none';
                }
                if (custZaloId) custZaloId.value = '';
                if (custAvatarUrl) custAvatarUrl.value = '';

                if (upcomingBookingBanner) {
                    upcomingBookingBanner.classList.add('hidden');
                    upcomingBookingBanner.style.display = 'none';
                }
            }
        }

        if (btnOpenMyBookings && myBookingsModal) {
            btnOpenMyBookings.addEventListener('click', function () {
                myBookingsModal.classList.remove('hidden');
                myBookingsModal.style.display = 'flex';
            });
        }

        if (btnCloseMyBookings && myBookingsModal) {
            btnCloseMyBookings.addEventListener('click', function () {
                myBookingsModal.classList.add('hidden');
                myBookingsModal.style.display = 'none';
            });
        }

        if (myBookingsModal) {
            myBookingsModal.addEventListener('click', function (e) {
                if (e.target === myBookingsModal) {
                    myBookingsModal.classList.add('hidden');
                    myBookingsModal.style.display = 'none';
                }
            });
        }

        if (zaloLogoutBtn) {
            zaloLogoutBtn.addEventListener('click', function () {
                localStorage.removeItem('public_customer_info');
                checkZaloSession();
            });
        }

        checkZaloSession();

        let isSubmitting = false;

        // Khôi phục trạng thái nút gửi nếu người dùng quay lại trang bằng nút Back của trình duyệt
        window.addEventListener('pageshow', function () {
            isSubmitting = false;
            if (submitBtn) {
                submitBtn.disabled = false;
                calculateTotal();
            }
        });

        // 7. Kiểm tra validation trước khi gửi form (Fix bug bắt buộc Zalo & chống submit 2 lần liên tiếp)
        orderForm.addEventListener('submit', function (e) {
            if (isSubmitting) {
                e.preventDefault();
                return false;
            }

            // Fix bug: Kiểm tra bắt buộc đăng nhập Zalo nếu hệ thống yêu cầu
            if (window.REQUIRE_ZALO_LOGIN && (!custZaloId || !custZaloId.value.trim())) {
                e.preventDefault();
                alert('Bạn thương ơi, bếp cần bạn bấm "Đăng nhập Zalo để tự động điền" phía trên để kết nối giữ chỗ trước khi gửi đơn nhé.');
                const zaloContainer = document.getElementById('zaloAuthContainer');
                if (zaloContainer) {
                    zaloContainer.scrollIntoView({ behavior: 'smooth', block: 'center' });
                    zaloContainer.style.transition = 'all 0.3s ease';
                    zaloContainer.style.boxShadow = '0 0 0 3px rgba(0, 104, 255, 0.4)';
                    setTimeout(() => zaloContainer.style.boxShadow = '', 2500);
                }
                return false;
            }

            const { totalItems } = calculateTotal();

            if (totalItems <= 0) {
                e.preventDefault();
                alert('Bạn thương ơi, xin chọn ít nhất 1 món ăn hoặc đồ uống nhé.');
                return false;
            }

            const selectedSlot = document.querySelector('input[name="slot_id"]:checked');
            if (!selectedSlot) {
                e.preventDefault();
                alert('Xin bạn chọn một khung giờ để bếp kịp chuẩn bị nước dùng nhé.');
                return false;
            }

            isSubmitting = true;

            // Hiệu ứng loading trên nút
            if (submitBtn) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = '<span>Đang gửi đến Bếp...</span>';
            }
        });
    }

    // Tính toán ban đầu
    calculateTotal();
});

