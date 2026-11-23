/**
 * إدارة واجهة المنتجات والمخزون (Products Management)
 */
document.addEventListener('DOMContentLoaded', function () {
    const form = document.getElementById('form');
    const table = document.getElementById('table');
    const tableBody = table ? table.getElementsByTagName('tbody')[0] : null;
    const submitBtn = document.getElementById('submit_btn');
    const cancelBtn = document.getElementById('cancel_btn');
    const productIdInput = document.getElementById('product_id');
    const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') 
        || document.getElementById('csrf_token')?.value || '';

    // دالة لإعادة تعيين النموذج للوضع الافتراضي
    function resetForm() {
        form.reset();
        productIdInput.value = '';
        submitBtn.innerText = 'إضافة منتج';
        submitBtn.style.background = '';
        if (cancelBtn) cancelBtn.style.display = 'none';
        document.getElementById('min_stock_alert').value = '5';
    }

    if (cancelBtn) {
        cancelBtn.addEventListener('click', resetForm);
    }

    // معالجة إرسال النموذج (إضافة أو تعديل)
    if (form) {
        form.addEventListener('submit', function (e) {
            e.preventDefault();

            const isEdit = !!productIdInput.value;
            const targetUrl = isEdit ? 'update_product.php' : 'products_process.php';

            const formData = new FormData(form);
            if (!formData.get('csrf_token')) {
                formData.append('csrf_token', csrfToken);
            }

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
                        window.showToast(data.message || (isEdit ? 'تم تحديث بيانات المنتج بنجاح.' : 'تمت إضافة المنتج بنجاح.'), 'success');
                    }
                    resetForm();
                    setTimeout(function () {
                        location.reload();
                    }, 600);
                } else {
                    if (typeof window.showToast === 'function') {
                        window.showToast(data.error || 'تعذر إتمام العملية. يرجى مراجعة البيانات المدخلة.', 'error');
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
                const sku = editBtn.getAttribute('data-sku') || '';
                const category = editBtn.getAttribute('data-category') || '';
                const price = editBtn.getAttribute('data-price') || '0';
                const count = editBtn.getAttribute('data-count') || '0';
                const minStock = editBtn.getAttribute('data-min-stock') || '5';

                productIdInput.value = id;
                document.getElementById('name').value = name;
                document.getElementById('sku').value = sku;
                document.getElementById('category').value = category;
                document.getElementById('price').value = price;
                document.getElementById('count').value = count;
                document.getElementById('min_stock_alert').value = minStock;

                submitBtn.innerText = 'تحديث بيانات المنتج';
                submitBtn.style.background = 'linear-gradient(135deg, #ff9800, #f57c00)';
                if (cancelBtn) cancelBtn.style.display = 'inline-block';

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

                    fetch('delete_product.php', {
                        method: 'POST',
                        body: formData
                    })
                    .then(res => res.json().catch(() => ({ success: false, error: 'حدث خطأ في قراءة استجابة الخادم.' })))
                    .then(data => {
                        deleteBtn.disabled = false;
                        if (data.success) {
                            const row = deleteBtn.closest('tr');
                            if (row) row.remove();
                            if (productIdInput.value === id) resetForm();
                            if (typeof window.showToast === 'function') {
                                window.showToast(data.message || 'تم حذف المنتج بنجاح.', 'success');
                            }
                        } else {
                            if (typeof window.showToast === 'function') {
                                window.showToast(data.error || 'فشل حذف المنتج.', 'error');
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
                        title: 'تأكيد حذف المنتج',
                        message: 'هل أنت متأكد من رغبتك في حذف هذا المنتج من المخزون؟',
                        confirmText: 'نعم، حذف المنتج',
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
