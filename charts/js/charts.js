document.addEventListener("DOMContentLoaded", function () {
    if (typeof Chart === 'undefined') {
        console.error("مكتبة Chart.js غير محملة!");
        return;
    }

    // إعدادات عامة للرسوم البيانية (المظهر الداكن والخط العربي)
    Chart.defaults.color = '#ccc';
    Chart.defaults.borderColor = 'rgba(255, 255, 255, 0.08)';
    Chart.defaults.font.family = "'Cairo', sans-serif";

    fetch("charts_data.php")
        .then(response => {
            if (!response.ok) throw new Error("تعذر جلب بيانات الرسوم البيانية");
            return response.json();
        })
        .then(data => {
            if (!data || !data.success) return;

            // ==========================================
            // 1. مسار نمو المبيعات اليومية (Line Chart)
            // ==========================================
            const salesContainer = document.getElementById('salesTrendContainer');
            const salesCanvas = document.getElementById('salesTrendChart');
            if (data.sales_trend && data.sales_trend.length > 0 && salesCanvas) {
                const ctx = salesCanvas.getContext('2d');
                const gradient = ctx.createLinearGradient(0, 0, 0, 320);
                gradient.addColorStop(0, 'rgba(0, 198, 255, 0.45)');
                gradient.addColorStop(1, 'rgba(0, 198, 255, 0.02)');

                const labels = data.sales_trend.map(row => row.order_date);
                const salesValues = data.sales_trend.map(row => parseFloat(row.total_sales));

                new Chart(ctx, {
                    type: 'line',
                    data: {
                        labels: labels,
                        datasets: [{
                            label: 'إجمالي المبيعات (ر.س)',
                            data: salesValues,
                            borderColor: '#00c6ff',
                            borderWidth: 3,
                            backgroundColor: gradient,
                            fill: true,
                            tension: 0.35,
                            pointBackgroundColor: '#ffffff',
                            pointBorderColor: '#00c6ff',
                            pointBorderWidth: 2,
                            pointRadius: 5,
                            pointHoverRadius: 7
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                labels: { font: { size: 14 } }
                            },
                            tooltip: {
                                rtl: true,
                                callbacks: {
                                    label: function (context) {
                                        return ' المبيعات: ' + context.parsed.y.toLocaleString() + ' ر.س';
                                    }
                                }
                            }
                        },
                        scales: {
                            y: {
                                grid: { color: 'rgba(255, 255, 255, 0.06)' },
                                ticks: {
                                    callback: function (val) {
                                        return val.toLocaleString();
                                    }
                                }
                            },
                            x: {
                                grid: { display: false }
                            }
                        }
                    }
                });
            } else if (salesContainer) {
                salesContainer.innerHTML = '<div class="empty-chart-notice"><i class="fas fa-chart-line"></i> لا توجد بيانات مبيعات كافية لعرض مسار المبيعات حتى الآن</div>';
            }

            // ==========================================
            // 2. أعلى المنتجات مبيعاً (Bar Chart)
            // ==========================================
            const topProductsContainer = document.getElementById('topProductsContainer');
            const topProductsCanvas = document.getElementById('topProductsChart');
            if (data.top_products && data.top_products.length > 0 && topProductsCanvas) {
                const ctx2 = topProductsCanvas.getContext('2d');
                const labels2 = data.top_products.map(p => p.name);
                const quantities = data.top_products.map(p => parseInt(p.total_qty, 10));

                const barColors = [
                    'rgba(255, 215, 0, 0.85)',
                    'rgba(4, 129, 255, 0.85)',
                    'rgba(0, 255, 136, 0.85)',
                    'rgba(255, 170, 0, 0.85)',
                    'rgba(155, 81, 224, 0.85)'
                ];
                const borderColors = [
                    '#ffd700',
                    '#0481ff',
                    '#00ff88',
                    '#ffaa00',
                    '#9b51e0'
                ];

                new Chart(ctx2, {
                    type: 'bar',
                    data: {
                        labels: labels2,
                        datasets: [{
                            label: 'الكمية المباعة (قطعة)',
                            data: quantities,
                            backgroundColor: barColors.slice(0, labels2.length),
                            borderColor: borderColors.slice(0, labels2.length),
                            borderWidth: 1.5,
                            borderRadius: 6
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        indexAxis: 'y',
                        plugins: {
                            legend: { display: false },
                            tooltip: { rtl: true }
                        },
                        scales: {
                            x: {
                                grid: { color: 'rgba(255, 255, 255, 0.06)' },
                                ticks: { precision: 0 }
                            },
                            y: {
                                grid: { display: false }
                            }
                        }
                    }
                });
            } else if (topProductsContainer) {
                topProductsContainer.innerHTML = '<div class="empty-chart-notice"><i class="fas fa-box-open"></i> لا توجد مبيعات منتجات مسجلة حتى الآن</div>';
            }

            // ==========================================
            // 3. توزيع المخزون حسب الأقسام (Doughnut Chart)
            // ==========================================
            const stockContainer = document.getElementById('stockByCategoryContainer');
            const stockCanvas = document.getElementById('stockByCategoryChart');
            if (data.stock_by_category && data.stock_by_category.length > 0 && stockCanvas) {
                const ctx3 = stockCanvas.getContext('2d');
                const catLabels = data.stock_by_category.map(c => c.category_name);
                const stockValues = data.stock_by_category.map(c => parseInt(c.total_stock, 10));

                const doughnutColors = [
                    '#0481ff',
                    '#00c6ff',
                    '#00ff88',
                    '#ffd700',
                    '#ff7675',
                    '#9b51e0',
                    '#fdcb6e',
                    '#e17055'
                ];

                new Chart(ctx3, {
                    type: 'doughnut',
                    data: {
                        labels: catLabels,
                        datasets: [{
                            data: stockValues,
                            backgroundColor: doughnutColors.slice(0, catLabels.length),
                            borderColor: '#112233',
                            borderWidth: 2
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'bottom',
                                labels: { font: { size: 12 }, padding: 15 }
                            },
                            tooltip: {
                                rtl: true,
                                callbacks: {
                                    label: function (context) {
                                        const label = context.label || '';
                                        const value = context.parsed || 0;
                                        return ' ' + label + ': ' + value.toLocaleString() + ' قطعة في المخزن';
                                    }
                                }
                            }
                        },
                        cutout: '65%'
                    }
                });
            } else if (stockContainer) {
                stockContainer.innerHTML = '<div class="empty-chart-notice"><i class="fas fa-layer-group"></i> لا توجد أصناف مسجلة في المخزن حتى الآن</div>';
            }
        })
        .catch(err => {
            console.error("خطأ أثناء معالجة الرسوم البيانية:", err);
        });
});
