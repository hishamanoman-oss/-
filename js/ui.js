/**
 * السكريبت الموحد لواجهات التنبيه والنوافذ التفاعلية وقائمة الجوال
 * Unified UI Script: Toasts, Confirm Modals & Mobile Sidebar Management
 */
(function () {
    'use strict';

    // 1. نظام التنبيهات المنبثقة (Toast Notifications)
    function ensureToastContainer() {
        let container = document.getElementById('toastContainer');
        if (!container) {
            container = document.createElement('div');
            container.id = 'toastContainer';
            container.setAttribute('aria-live', 'polite');
            document.body.appendChild(container);
        }
        return container;
    }

    window.showToast = function (message, type) {
        if (!message) return;
        type = type || 'success';
        if (type === 'danger') type = 'error';

        const container = ensureToastContainer();
        const toast = document.createElement('div');
        toast.className = 'custom-toast toast-' + type;

        let iconClass = 'fas fa-check-circle';
        if (type === 'error') iconClass = 'fas fa-exclamation-circle';
        if (type === 'info') iconClass = 'fas fa-info-circle';

        const content = document.createElement('div');
        content.className = 'custom-toast-content';

        const icon = document.createElement('i');
        icon.className = iconClass;

        const textSpan = document.createElement('span');
        textSpan.textContent = message;

        content.appendChild(icon);
        content.appendChild(textSpan);

        const closeBtn = document.createElement('button');
        closeBtn.type = 'button';
        closeBtn.className = 'custom-toast-close';
        closeBtn.innerHTML = '&times;';
        closeBtn.setAttribute('aria-label', 'إغلاق');

        toast.appendChild(content);
        toast.appendChild(closeBtn);
        container.appendChild(toast);

        // إظهار التنبيه بحركة سلسة
        requestAnimationFrame(function () {
            toast.classList.add('show');
        });

        let dismissTimer = null;

        function dismissToast() {
            if (dismissTimer) clearTimeout(dismissTimer);
            toast.classList.remove('show');
            toast.classList.add('hide');
            setTimeout(function () {
                if (toast.parentNode) {
                    toast.parentNode.removeChild(toast);
                }
            }, 350);
        }

        closeBtn.addEventListener('click', dismissToast);
        dismissTimer = setTimeout(dismissToast, 4000);
    };

    // 2. نظام نافذة التأكيد المخصصة (Confirm Modal)
    let modalBackdrop = null;
    let modalBox = null;
    let modalTitleEl = null;
    let modalBodyEl = null;
    let modalConfirmBtn = null;
    let modalCancelBtn = null;
    let currentConfirmCallback = null;

    function ensureConfirmModal() {
        modalBackdrop = document.getElementById('confirmModalBackdrop');
        if (!modalBackdrop) {
            modalBackdrop = document.createElement('div');
            modalBackdrop.id = 'confirmModalBackdrop';

            modalBox = document.createElement('div');
            modalBox.className = 'confirm-modal-box';

            modalTitleEl = document.createElement('h3');
            modalTitleEl.className = 'confirm-modal-title';
            modalTitleEl.innerHTML = '<i class="fas fa-exclamation-triangle"></i> <span>تأكيد الإجراء</span>';

            modalBodyEl = document.createElement('div');
            modalBodyEl.className = 'confirm-modal-body';

            const actionsDiv = document.createElement('div');
            actionsDiv.className = 'confirm-modal-actions';

            modalCancelBtn = document.createElement('button');
            modalCancelBtn.type = 'button';
            modalCancelBtn.className = 'confirm-btn-cancel';
            modalCancelBtn.textContent = 'إلغاء';

            modalConfirmBtn = document.createElement('button');
            modalConfirmBtn.type = 'button';
            modalConfirmBtn.className = 'confirm-btn-danger';
            modalConfirmBtn.textContent = 'نعم، تأكيد';

            actionsDiv.appendChild(modalCancelBtn);
            actionsDiv.appendChild(modalConfirmBtn);

            modalBox.appendChild(modalTitleEl);
            modalBox.appendChild(modalBodyEl);
            modalBox.appendChild(actionsDiv);
            modalBackdrop.appendChild(modalBox);
            document.body.appendChild(modalBackdrop);

            // مستمعي الأحداث للإغلاق والتأكيد
            modalCancelBtn.addEventListener('click', closeConfirmModal);
            modalBackdrop.addEventListener('click', function (e) {
                if (e.target === modalBackdrop) {
                    closeConfirmModal();
                }
            });

            modalConfirmBtn.addEventListener('click', function () {
                const callback = currentConfirmCallback;
                closeConfirmModal();
                if (typeof callback === 'function') {
                    callback();
                }
            });
        }
    }

    function closeConfirmModal() {
        if (modalBackdrop) {
            modalBackdrop.classList.remove('open');
        }
        currentConfirmCallback = null;
    }

    window.showConfirmModal = function (options) {
        options = options || {};
        ensureConfirmModal();

        const titleText = options.title || 'تأكيد الإجراء';
        const span = modalTitleEl.querySelector('span');
        if (span) span.textContent = titleText;

        modalBodyEl.textContent = options.message || 'هل أنت متأكد من رغبتك في متابعة هذا الإجراء؟';
        modalConfirmBtn.textContent = options.confirmText || 'نعم، تأكيد';
        modalCancelBtn.textContent = options.cancelText || 'إلغاء';

        currentConfirmCallback = options.onConfirm || null;
        modalBackdrop.classList.add('open');
    };

    // إغلاق النافذة أو القائمة الجانبية بالضغط على زر الهروب (Escape)
    document.addEventListener('keydown', function (e) {
        if (e.key === 'Escape') {
            if (modalBackdrop && modalBackdrop.classList.contains('open')) {
                closeConfirmModal();
            }
            closeMobileMenu();
        }
    });

    // 3. إدارة قائمة الجوال المنسدلة (Mobile Sidebar Drawer)
    function closeMobileMenu() {
        const menu = document.querySelector('.menu');
        const overlay = document.getElementById('sidebarOverlay');
        if (menu) menu.classList.remove('open');
        if (overlay) overlay.classList.remove('open');
    }

    function toggleMobileMenu() {
        const menu = document.querySelector('.menu');
        const overlay = document.getElementById('sidebarOverlay');
        if (menu) menu.classList.toggle('open');
        if (overlay) overlay.classList.toggle('open');
    }

    // 4. تهيئة الصفحة والتقاط النماذج والتنبيهات التلقائية
    document.addEventListener('DOMContentLoaded', function () {
        // فحص رسائل الفلاش في الجلسة الممررة عبر سمات البيانات في الوسم body أو عنصر flashMessagesData
        const flashHolder = document.getElementById('flashMessagesData');
        const flashSuccess = document.body.getAttribute('data-flash-success') 
            || flashHolder?.getAttribute('data-flash-success');
        const flashError = document.body.getAttribute('data-flash-error') 
            || flashHolder?.getAttribute('data-flash-error');
        const flashInfo = document.body.getAttribute('data-flash-info') 
            || flashHolder?.getAttribute('data-flash-info');

        if (flashSuccess) window.showToast(flashSuccess, 'success');
        if (flashError) window.showToast(flashError, 'error');
        if (flashInfo) window.showToast(flashInfo, 'info');

        // ربط أزرار قائمة الجوال
        const mobileMenuBtn = document.getElementById('mobileMenuBtn');
        const sidebarOverlay = document.getElementById('sidebarOverlay');

        if (mobileMenuBtn) {
            mobileMenuBtn.addEventListener('click', function (e) {
                e.stopPropagation();
                toggleMobileMenu();
            });
        }

        if (sidebarOverlay) {
            sidebarOverlay.addEventListener('click', function () {
                closeMobileMenu();
            });
        }

        // اعتراض النماذج التي تحتوي على data-confirm
        document.addEventListener('submit', function (e) {
            const form = e.target;
            const confirmMsg = form.getAttribute('data-confirm');
            if (confirmMsg && !form.dataset.confirmed) {
                e.preventDefault();
                window.showConfirmModal({
                    title: 'تأكيد الإجراء',
                    message: confirmMsg,
                    confirmText: 'نعم، تأكيد',
                    cancelText: 'إلغاء',
                    onConfirm: function () {
                        form.dataset.confirmed = 'true';
                        form.submit();
                    }
                });
            }
        });

        // اعتراض الروابط والأزرار التي تحتوي على data-confirm
        document.addEventListener('click', function (e) {
            const btn = e.target.closest('button[data-confirm], a[data-confirm]');
            if (!btn || btn.type === 'submit') return; // تُعالج نماذج Submit عبر الحدث السابق

            if (!btn.dataset.confirmed) {
                e.preventDefault();
                const confirmMsg = btn.getAttribute('data-confirm');
                window.showConfirmModal({
                    title: 'تأكيد الإجراء',
                    message: confirmMsg,
                    confirmText: 'نعم، تأكيد',
                    cancelText: 'إلغاء',
                    onConfirm: function () {
                        btn.dataset.confirmed = 'true';
                        if (btn.tagName === 'A' && btn.href) {
                            window.location.href = btn.href;
                        } else {
                            btn.click();
                        }
                    }
                });
            }
        });
    });
})();
