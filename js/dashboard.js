/**
 * لوحة التحكم والمتابعة عن بُعد - Live Remote Monitoring
 */
document.addEventListener('DOMContentLoaded', function () {
    const refreshBtn = document.getElementById('refreshBtn');
    const refreshIcon = document.getElementById('refreshIcon');
    const lastUpdatedSpan = document.getElementById('lastUpdated');

    // دالة لتنظيف وتأمين النصوص قبل العرض لمنع ثغرات XSS
    function escapeHtml(str) {
        if (str === null || str === undefined) return '';
        return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#039;');
    }

    // دالة لتنسيق الأرقام العشرية
    function formatMoney(amount) {
        return parseFloat(amount || 0).toLocaleString('en-US', {
            minimumFractionDigits: 2,
            maximumFractionDigits: 2
        });
    }

    // جلب البيانات وتحديث الواجهة
    function fetchDashboardStats(isManual) {
        if (refreshIcon && isManual) {
            refreshIcon.classList.add('fa-spin');
        }

        fetch('api/dashboard_stats.php')
            .then(response => {
                if (response.status === 401) {
                    window.location.href = 'Login.php';
                    return null;
                }
                if (!response.ok) {
                    throw new Error('فشل جلب إحصائيات لوحة التحكم');
                }
                return response.json();
            })
            .then(data => {
                if (!data || !data.success) return;

                // تحديث وقت آخر مزامنة
                if (lastUpdatedSpan && data.last_updated) {
                    lastUpdatedSpan.textContent = data.last_updated;
                }

                // تحديث اسم المتجر والعملة
                const currency = data.currency || 'ر.س';
                const storeNameEl = document.getElementById('storeNameDisplay');
                if (storeNameEl && data.store_name) {
                    storeNameEl.textContent = data.store_name;
                }

                // 1. مبيعات اليوم
                const kpiTodaySales = document.getElementById('kpiTodaySales');
                if (kpiTodaySales) kpiTodaySales.textContent = formatMoney(data.today_sales_amount);

                const kpiTotalSales = document.getElementById('kpiTotalSales');
                if (kpiTotalSales) kpiTotalSales.textContent = formatMoney(data.total_sales_amount);

                // 2. طلبات اليوم
                const kpiTodayOrders = document.getElementById('kpiTodayOrders');
                if (kpiTodayOrders) kpiTodayOrders.textContent = data.today_orders_count;

                const kpiTotalOrders = document.getElementById('kpiTotalOrders');
                if (kpiTotalOrders) kpiTotalOrders.textContent = data.total_orders_count;

                // 3. العملاء
                const kpiTotalClients = document.getElementById('kpiTotalClients');
                if (kpiTotalClients) kpiTotalClients.textContent = data.total_clients_count;

                const kpiTodayClients = document.getElementById('kpiTodayClients');
                if (kpiTodayClients) kpiTodayClients.textContent = data.today_new_clients_count;

                // 4. المخزون الحرج
                const kpiLowStock = document.getElementById('kpiLowStock');
                if (kpiLowStock) {
                    kpiLowStock.textContent = data.low_stock_count;
                    if (data.low_stock_count > 0) {
                        kpiLowStock.className = 'text-warning';
                    } else {
                        kpiLowStock.className = 'text-success';
                    }
                }

                const kpiTotalProducts = document.getElementById('kpiTotalProducts');
                if (kpiTotalProducts) kpiTotalProducts.textContent = data.total_products_count;

                // 5. جدول آخر المبيعات
                const recentOrdersTbody = document.getElementById('recentOrdersBody');
                if (recentOrdersTbody) {
                    recentOrdersTbody.innerHTML = '';
                    if (data.recent_orders && data.recent_orders.length > 0) {
                        data.recent_orders.forEach(order => {
                            const tr = document.createElement('tr');

                            let statusBadgeClass = 'badge-completed';
                            let statusText = 'مكتمل';
                            if (order.status === 'pending') {
                                statusBadgeClass = 'badge-pending';
                                statusText = 'معلق';
                            } else if (order.status === 'cancelled') {
                                statusBadgeClass = 'badge-cancelled';
                                statusText = 'ملغي';
                            }

                            const vipBadge = (parseInt(order.is_vip, 10) === 1) 
                                ? ' <span class="badge-vip" style="background:#ffd700;color:#000;padding:1px 5px;border-radius:10px;font-size:10px;font-weight:bold;">VIP</span>' 
                                : '';

                            tr.innerHTML = `
                                <td><strong>${escapeHtml(order.order_number)}</strong></td>
                                <td>${escapeHtml(order.client_name)}${vipBadge}</td>
                                <td>${escapeHtml(order.user_name)}</td>
                                <td><strong>${formatMoney(order.total_amount)}</strong> <small>${escapeHtml(currency)}</small></td>
                                <td><span class="badge-status ${statusBadgeClass}">${statusText}</span></td>
                                <td><small>${escapeHtml(order.created_at)}</small></td>
                                <td>
                                    <a href="orders/invoice.php?id=${encodeURIComponent(order.id)}" class="btn-sm-action">
                                        <i class="fas fa-file-invoice"></i> الفاتورة
                                    </a>
                                </td>
                            `;
                            recentOrdersTbody.appendChild(tr);
                        });
                    } else {
                        recentOrdersTbody.innerHTML = '<tr><td colspan="7" style="text-align:center;color:#8899aa;padding:15px;">لا توجد طلبات مسجلة حتى الآن</td></tr>';
                    }
                }

                // 6. جدول تنبيهات المخزون المنخفض
                const lowStockTbody = document.getElementById('lowStockBody');
                if (lowStockTbody) {
                    lowStockTbody.innerHTML = '';
                    if (data.low_stock_products && data.low_stock_products.length > 0) {
                        data.low_stock_products.forEach(prod => {
                            const tr = document.createElement('tr');
                            tr.innerHTML = `
                                <td><strong>${escapeHtml(prod.name)}</strong></td>
                                <td><code>${escapeHtml(prod.sku)}</code></td>
                                <td>${escapeHtml(prod.category || 'عام')}</td>
                                <td><strong class="text-danger">${escapeHtml(prod.count)}</strong></td>
                                <td><span style="color:#aaa;">${escapeHtml(prod.min_stock_alert)}</span></td>
                                <td>
                                    <a href="Products/products.php" class="btn-sm-action">
                                        <i class="fas fa-boxes"></i> تفقد المخزن
                                    </a>
                                </td>
                            `;
                            lowStockTbody.appendChild(tr);
                        });
                    } else {
                        lowStockTbody.innerHTML = '<tr><td colspan="6" style="text-align:center;color:#00ff88;padding:15px;"><i class="fas fa-check-circle"></i> جميع المنتجات بمستويات مخزون آمنة</td></tr>';
                    }
                }
            })
            .catch(err => {
                console.error('Error fetching dashboard stats:', err);
            })
            .finally(() => {
                if (refreshIcon && isManual) {
                    setTimeout(() => {
                        refreshIcon.classList.remove('fa-spin');
                    }, 400);
                }
            });
    }

    // زر التحديث اليدوي
    if (refreshBtn) {
        refreshBtn.addEventListener('click', function () {
            fetchDashboardStats(true);
        });
    }

    // التحديث التلقائي الهادئ كل 60 ثانية
    setInterval(function () {
        fetchDashboardStats(false);
    }, 60000);
});
