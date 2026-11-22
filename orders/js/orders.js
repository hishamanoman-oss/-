/**
 * نظام إدارة بنود الطلب اللحظي
 * Dynamic Multi-Product Order Line Items & Live Subtotals
 */
document.addEventListener('DOMContentLoaded', function () {
    const itemsContainer = document.getElementById('items_container');
    const btnAddItem = document.getElementById('btn_add_item');
    const grandTotalElement = document.getElementById('grand_total_amount');
    const totalItemsCountElement = document.getElementById('total_items_count');
    const orderForm = document.getElementById('order_form');

    // قراءة بيانات المنتجات المتوفرة بأمان من عنصر JSON المضمن في الصفحة
    let productsCatalog = [];
    const productsDataElem = document.getElementById('products_data');
    if (productsDataElem) {
        try {
            productsCatalog = JSON.parse(productsDataElem.textContent || '[]');
        } catch (e) {
            console.error('فشل قراءة بيانات المنتجات:', e);
        }
    }

    let rowIndex = 0;

    // دالة إنشاء خيارات المنتجات
    function buildProductOptions(selectedId = '') {
        let options = '<option value="">-- اختر المنتج من المخزن --</option>';
        productsCatalog.forEach(p => {
            const isSelected = String(p.id) === String(selectedId) ? 'selected' : '';
            const price = parseFloat(p.price).toFixed(2);
            options += `<option value="${p.id}" data-price="${p.price}" data-stock="${p.count}" ${isSelected}>
                ${escapeHtml(p.name)} [${escapeHtml(p.sku)}] - السعر: ${price} ر.س (المتوفر: ${p.count})
            </option>`;
        });
        return options;
    }

    // دالة هروب آمنة لـ HTML
    function escapeHtml(text) {
        const div = document.createElement('div');
        div.textContent = text || '';
        return div.innerHTML;
    }

    // دالة إضافة سطر منتج جديد
    function addOrderRow(selectedId = '', defaultQty = 1) {
        rowIndex++;
        const row = document.createElement('div');
        row.className = 'order-item-row';
        row.dataset.rowId = rowIndex;

        row.innerHTML = `
            <div class="col-product form-group">
                <label>المنتج <span style="color:#ff4d4f;">*</span></label>
                <select name="items[${rowIndex}][product_id]" class="product-select" required>
                    ${buildProductOptions(selectedId)}
                </select>
            </div>
            <div class="col-qty form-group">
                <label>الكمية <span style="color:#ff4d4f;">*</span></label>
                <input type="number" name="items[${rowIndex}][quantity]" class="quantity-input" min="1" value="${defaultQty}" required>
            </div>
            <div class="col-price form-group">
                <label>سعر الوحدة</label>
                <div class="price-display">0.00 ر.س</div>
            </div>
            <div class="col-subtotal form-group">
                <label>الإجمالي الفرعي</label>
                <div class="subtotal-display">0.00 ر.س</div>
            </div>
            <div class="col-action">
                <button type="button" class="btn-remove-row" title="حذف هذا البند">
                    <i class="fas fa-trash-alt"></i>
                </button>
            </div>
        `;

        itemsContainer.appendChild(row);
        updateRowCalculations(row);
        updateGrandTotal();
    }

    // دالة تحديث حسابات سطر واحد
    function updateRowCalculations(row) {
        const select = row.querySelector('.product-select');
        const qtyInput = row.querySelector('.quantity-input');
        const priceDisplay = row.querySelector('.price-display');
        const subtotalDisplay = row.querySelector('.subtotal-display');

        const selectedOption = select.options[select.selectedIndex];
        const price = selectedOption && selectedOption.dataset.price ? parseFloat(selectedOption.dataset.price) : 0;
        const stock = selectedOption && selectedOption.dataset.stock ? parseInt(selectedOption.dataset.stock, 10) : 0;

        let qty = parseInt(qtyInput.value, 10) || 0;
        if (qty < 1 && select.value !== '') {
            qty = 1;
            qtyInput.value = 1;
        }

        if (stock > 0) {
            qtyInput.max = stock;
            if (qty > stock) {
                qty = stock;
                qtyInput.value = stock;
                alert(`أقصى كمية متوفرة لهذا المنتج هي ${stock} قطعة.`);
            }
        }

        const subtotal = price * qty;

        priceDisplay.textContent = price > 0 ? `${price.toFixed(2)} ر.س` : '0.00 ر.س';
        subtotalDisplay.textContent = subtotal > 0 ? `${subtotal.toFixed(2)} ر.س` : '0.00 ر.س';
        row.dataset.subtotal = subtotal;
        row.dataset.qty = qty;
    }

    // دالة تحديث الإجمالي العام للطلب
    function updateGrandTotal() {
        let grandTotal = 0;
        let totalItems = 0;

        const rows = itemsContainer.querySelectorAll('.order-item-row');
        rows.forEach(row => {
            const subtotal = parseFloat(row.dataset.subtotal || 0);
            const qty = parseInt(row.dataset.qty || 0, 10);
            const select = row.querySelector('.product-select');

            if (select && select.value !== '') {
                grandTotal += subtotal;
                totalItems += qty;
            }
        });

        if (grandTotalElement) {
            grandTotalElement.textContent = `${grandTotal.toFixed(2)} ر.س`;
        }
        if (totalItemsCountElement) {
            totalItemsCountElement.textContent = totalItems;
        }
    }

    // مراقبة التغييرات داخل الحاوية (Event Delegation)
    if (itemsContainer) {
        itemsContainer.addEventListener('change', function (e) {
            const row = e.target.closest('.order-item-row');
            if (!row) return;

            if (e.target.classList.contains('product-select')) {
                const select = e.target;
                const opt = select.options[select.selectedIndex];
                const stock = opt && opt.dataset.stock ? parseInt(opt.dataset.stock, 10) : 0;
                const qtyInput = row.querySelector('.quantity-input');
                if (qtyInput) {
                    qtyInput.max = stock;
                    qtyInput.value = 1;
                }
                updateRowCalculations(row);
                updateGrandTotal();
            }

            if (e.target.classList.contains('quantity-input')) {
                updateRowCalculations(row);
                updateGrandTotal();
            }
        });

        itemsContainer.addEventListener('input', function (e) {
            if (e.target.classList.contains('quantity-input')) {
                const row = e.target.closest('.order-item-row');
                if (row) {
                    updateRowCalculations(row);
                    updateGrandTotal();
                }
            }
        });

        itemsContainer.addEventListener('click', function (e) {
            const removeBtn = e.target.closest('.btn-remove-row');
            if (removeBtn) {
                const rows = itemsContainer.querySelectorAll('.order-item-row');
                if (rows.length > 1) {
                    removeBtn.closest('.order-item-row').remove();
                } else {
                    // إذا كان السطر الوحيد المتبقي نقوم بتفريغه
                    const row = rows[0];
                    row.querySelector('.product-select').value = '';
                    row.querySelector('.quantity-input').value = 1;
                    updateRowCalculations(row);
                }
                updateGrandTotal();
            }
        });
    }

    // زر إضافة منتج آخر
    if (btnAddItem) {
        btnAddItem.addEventListener('click', function () {
            addOrderRow();
        });
    }

    // تهيئة أول سطر تلقائياً إذا كانت الحاوية فارغة
    if (itemsContainer && itemsContainer.children.length === 0) {
        addOrderRow();
    }

    // التحقق من النموذج قبل الإرسال
    if (orderForm) {
        orderForm.addEventListener('submit', function (e) {
            const rows = itemsContainer.querySelectorAll('.order-item-row');
            let hasValidItem = false;
            const productQuantities = {};

            for (const row of rows) {
                const select = row.querySelector('.product-select');
                const qtyInput = row.querySelector('.quantity-input');
                const productId = select ? select.value : '';
                const qty = qtyInput ? parseInt(qtyInput.value, 10) : 0;

                if (productId !== '') {
                    if (qty <= 0) {
                        e.preventDefault();
                        alert('يرجى تحديد كمية صحيحة (1 أو أكثر) لكل منتج محدد.');
                        return;
                    }
                    hasValidItem = true;
                    productQuantities[productId] = (productQuantities[productId] || 0) + qty;
                }
            }

            if (!hasValidItem) {
                e.preventDefault();
                alert('يرجى إضافة واختيار منتج واحد على الأقل في الطلب.');
                return;
            }

            // فحص إجمالي الكميات المجمعة لنفس المنتج مقابل المخزون
            for (const [pId, totalQty] of Object.entries(productQuantities)) {
                const pObj = productsCatalog.find(p => String(p.id) === String(pId));
                if (pObj && totalQty > pObj.count) {
                    e.preventDefault();
                    alert(`إجمالي الكمية المطلوبة للمنتج "${pObj.name}" (${totalQty}) يتجاوز المتوفر في المخزن (${pObj.count}).`);
                    return;
                }
            }
        });
    }

    // تأكيد إلغاء الطلب للمدير
    document.querySelectorAll('.form-cancel-order').forEach(form => {
        form.addEventListener('submit', function (e) {
            if (!confirm('هل أنت متأكد من إلغاء هذا الطلب؟ سيتم استرجاع كميات المنتجات المباعة إلى المخزون تلقائياً.')) {
                e.preventDefault();
            }
        });
    });
});
