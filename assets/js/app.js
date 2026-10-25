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
                        renderSlots(data.slots);
                    }
                })
                .catch(err => console.error('Lỗi tải ca:', err));
        });
    }

    function renderSlots(slots) {
        if (!slotContainer) return;
        slotContainer.innerHTML = '';

        if (!slots || slots.length === 0) {
            slotContainer.innerHTML = '<p class="text-xs text-gray-500 text-center py-3 col-span-2">Hôm nay bếp chưa mở ca, xin bạn quay lại sau nhé.</p>';
            return;
        }

        slots.forEach((slot, idx) => {
            const isFull = slot.is_full;
            const label = document.createElement('label');
            label.className = `zen-option ${isFull ? 'is-disabled' : ''} ${idx === 0 && !isFull ? 'is-checked' : ''}`;

            label.innerHTML = `
                <input type="radio" name="slot_id" value="${slot.id}" ${idx === 0 && !isFull ? 'checked' : ''} ${isFull ? 'disabled' : ''}>
                <p style="font-weight: 600; font-size: 0.85rem;">${slot.slot_time}</p>
                <p style="font-size: 0.7rem; opacity: 0.8; margin-top: 2px;">
                    ${isFull ? 'Đã hết suất' : `Còn ${slot.remaining_capacity} chỗ`}
                </p>
            `;

            slotContainer.appendChild(label);
        });

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
            } else {
                // Chưa đăng nhập Zalo
                if (zaloLoginSection) zaloLoginSection.classList.remove('hidden');
                if (zaloProfileSection) {
                    zaloProfileSection.classList.add('hidden');
                    zaloProfileSection.style.display = 'none';
                }
                if (custZaloId) custZaloId.value = '';
                if (custAvatarUrl) custAvatarUrl.value = '';
            }
        }

        if (zaloLogoutBtn) {
            zaloLogoutBtn.addEventListener('click', function () {
                localStorage.removeItem('public_customer_info');
                checkZaloSession();
            });
        }

        checkZaloSession();

        // 7. Kiểm tra validation trước khi gửi form
        orderForm.addEventListener('submit', function (e) {
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

