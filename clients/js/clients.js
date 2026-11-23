/**
 * إدارة واجهة العملاء (Clients Management)
 */
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('form');
    const table = document.getElementById('table');
    const tableBody = table ? table.getElementsByTagName('tbody')[0] : null;
    const submitBtn = document.getElementById('submit_btn');
    const cancelBtn = document.getElementById('cancel_btn');
    const clientIdInput = document.getElementById('client_id');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') 
        || document.getElementById('csrf_token')?.value || '';

    // دالة لإعادة تعيين النموذج للوضع الافتراضي
    function resetForm() {
        form.reset();
        clientIdInput.value = '';
        submitBtn.innerText = 'إضافة عميل';
        submitBtn.style.background = '';
        if (cancelBtn) cancelBtn.style.display = 'none';
    }

    if (cancelBtn) {
        cancelBtn.addEventListener('click', resetForm);
    }

    // معالجة إرسال النموذج (إضافة أو تعديل)
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            const isEdit = !!clientIdInput.value;
            const targetUrl = isEdit ? 'update_client.php' : 'clients_process.php';

            const formData = new FormData(form);
            if (!formData.get('csrf_token')) {
                formData.append('csrf_token', csrfToken);
            }

            // إرسال الطلب عبر Fetch
            submitBtn.disabled = true;
            submitBtn.style.opacity = '0.7';

            fetch(targetUrl, {
                method: 'POST',
                body: formData
            })
            .then(res => res.json().catch(() => ({ success: false, error: 'حدث خطأ في قراءة استجابة الخادم.' })))
            .then(data => {
                submitBtn.disabled = false;
                submitBtn.style.opacity = '1';

                if (data.success) {
                    if (typeof window.showToast === 'function') {
                        window.showToast(data.message || (isEdit ? 'تم تحديث البيانات بنجاح.' : 'تمت الإضافة بنجاح.'), 'success');
                    }
                    resetForm();
                    setTimeout(function () {
                        location.reload();
                    }, 600);
                } else {
                    if (typeof window.showToast === 'function') {
                        window.showToast(data.error || 'تعذر إتمام العملية. يرجى المحاولة لاحقاً.', 'error');
                    }
                }
            })
            .catch(err => {
                submitBtn.disabled = false;
                submitBtn.style.opacity = '1';
                console.error('Network error:', err);
                if (typeof window.showToast === 'function') {
                    window.showToast('حدث خطأ في الاتصال بالسيرفر.', 'error');
                }
            });
        });
    }

    // معالجة أزرار التعديل والحذف داخل الجدول
    if (tableBody) {
        tableBody.addEventListener('click', function (e) {
            const editBtn = e.target.closest('.edit');
            const deleteBtn = e.target.closest('.delete');

            // 1. زر التعديل
            if (editBtn) {
                const id = editBtn.getAttribute('data-id');
                const name = editBtn.getAttribute('data-name') || '';
                const phone = editBtn.getAttribute('data-phone') || '';
                const email = editBtn.getAttribute('data-email') || '';
                const address = editBtn.getAttribute('data-address') || '';
                const isVip = editBtn.getAttribute('data-vip') === '1';

                clientIdInput.value = id;
                document.getElementById('name').value = name;
                document.getElementById('phone').value = phone;
                document.getElementById('email').value = email;
                document.getElementById('address').value = address;
                document.getElementById('is_vip').checked = isVip;

                submitBtn.innerText = 'تحديث بيانات العميل';
                submitBtn.style.background = 'linear-gradient(135deg, #ff9800, #f57c00)';
                if (cancelBtn) cancelBtn.style.display = 'inline-block';

                // التمرير السلس إلى النموذج
                form.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }

            // 2. زر الحذف (خاص بالمدير)
            if (deleteBtn) {
                const id = deleteBtn.getAttribute('data-id');
                if (!id) return;

                const performDelete = function () {
                    deleteBtn.disabled = true;

                    const formData = new FormData();
                    formData.append('id', id);
                    formData.append('csrf_token', csrfToken);

                    fetch('delete_client.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json().catch(() => ({ success: false, error: 'حدث خطأ في قراءة استجابة الخادم.' })))
                    .then(data => {
                        deleteBtn.disabled = false;
                        if (data.success) {
                            const row = deleteBtn.closest('tr');
                            if (row) row.remove();
                            if (clientIdInput.value === id) resetForm();
                            if (typeof window.showToast === 'function') {
                                window.showToast(data.message || 'تم حذف العميل بنجاح.', 'success');
                            }
                        } else {
                            if (typeof window.showToast === 'function') {
                                window.showToast(data.error || 'فشل حذف العميل.', 'error');
                            }
                        }
                    })
                    .catch(err => {
                        deleteBtn.disabled = false;
                        console.error('Delete error:', err);
                        if (typeof window.showToast === 'function') {
                            window.showToast('حدث خطأ في الاتصال أثناء محاولة الحذف.', 'error');
                        }
                    });
                };

                if (typeof window.showConfirmModal === 'function') {
                    window.showConfirmModal({
                        title: 'تأكيد حذف العميل',
                        message: 'هل أنت متأكد من رغبتك في حذف هذا العميل؟ لا يمكن التراجع عن هذا الإجراء.',
                        confirmText: 'نعم، حذف العميل',
                        cancelText: 'إلغاء',
                        onConfirm: performDelete
                    });
                } else {
                    performDelete();
                }
            }
        });
    }
});
