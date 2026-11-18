document.addEventListener("DOMContentLoaded", function () {
    console.log("Charts initialization started...");

    // اختبار بسيط للكشف عن المشاكل
    const testCanvas = document.querySelector('canvas');
    if (testCanvas) {
        const testCtx = testCanvas.getContext('2d');
        testCtx.fillStyle = 'rgba(255, 255, 255, 0.1)';
        testCtx.fillRect(0, 0, 50, 50); // رسم مربع صغير جداً للتأكد من وصول الكود
    }

    // --- إعدادات عامة للرسوم البيانية (للوضع الداكن) ---
    if (typeof Chart !== 'undefined') {
        Chart.defaults.color = '#ccc';
        Chart.defaults.borderColor = 'rgba(255, 255, 255, 0.1)';
        Chart.defaults.font.family = "'Cairo', sans-serif";
    } else {
        console.error("Chart.js is not loaded!");
        return;
    }

    fetch("charts_data.php")
        .then(response => {
            if (!response.ok) throw new Error("Network response was not ok");
            return response.json();
        })
        .then(data => {
            console.log("Data received:", data);

            // --- 1. العملاء الجدد ---
            try {
                const canvas1 = document.getElementById('newClientsChart');
                if (canvas1) {
                    const ctx = canvas1.getContext('2d');
                    let gradient = ctx.createLinearGradient(0, 0, 0, 400);
                    gradient.addColorStop(0, 'rgba(4, 129, 255, 0.6)');
                    gradient.addColorStop(1, 'rgba(4, 129, 255, 0.05)');

                    new Chart(ctx, {
                        type: 'line',
                        data: {
                            labels: ['الأسبوع 1', 'الأسبوع 2', 'الأسبوع 3', 'الأسبوع 4'],
                            datasets: [{
                                label: 'عملاء جدد',
                                data: data.newClients,
                                borderColor: '#0481ff',
                                borderWidth: 3,
                                backgroundColor: gradient,
                                fill: true,
                                tension: 0.4,
                                pointBackgroundColor: '#fff',
                                pointBorderColor: '#0481ff',
                                pointRadius: 4
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            plugins: { legend: { labels: { font: { size: 14 } } } },
                            scales: {
                                y: { grid: { color: 'rgba(255,255,255,0.05)' } },
                                x: { grid: { display: false } }
                            }
                        }
                    });
                }
            } catch (e) { console.error("Error creating newClientsChart:", e); }

            // --- 2. المنتجات الأكثر مبيعاً ---
            try {
                const canvas2 = document.getElementById('topProductsChart');
                if (canvas2) {
                    new Chart(canvas2.getContext('2d'), {
                        type: 'bar',
                        data: {
                            labels: data.productLabels,
                            datasets: [{
                                label: 'الكمية المباعة',
                                data: data.topProducts,
                                backgroundColor: [
                                    'rgba(255, 99, 132, 0.8)', 'rgba(54, 162, 235, 0.8)',
                                    'rgba(255, 206, 86, 0.8)', 'rgba(75, 192, 192, 0.8)'
                                ],
                                borderColor: [
                                    'rgba(255, 99, 132, 1)', 'rgba(54, 162, 235, 1)',
                                    'rgba(255, 206, 86, 1)', 'rgba(75, 192, 192, 1)'
                                ],
                                borderWidth: 1,
                                borderRadius: 5
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            indexAxis: 'y',
                            scales: {
                                x: { grid: { color: 'rgba(255,255,255,0.05)' } },
                                y: { grid: { display: false } }
                            }
                        }
                    });
                }
            } catch (e) { console.error("Error creating topProductsChart:", e); }

            // --- 3. تصنيف العملاء ---
            try {
                const canvas3 = document.getElementById('clientSegmentsChart');
                if (canvas3) {
                    new Chart(canvas3.getContext('2d'), {
                        type: 'doughnut',
                        data: {
                            labels: ['عملاء دائمون', 'عملاء جدد', 'عملاء VIP'],
                            datasets: [{
                                data: data.clientSegments,
                                backgroundColor: ['#4BC0C0', '#FFCD56', '#FF6384'],
                                borderColor: '#1e1e1e',
                                borderWidth: 2,
                                hoverOffset: 10
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            cutout: '60%',
                            plugins: { legend: { position: 'bottom' } }
                        }
                    });
                }
            } catch (e) { console.error("Error creating clientSegmentsChart:", e); }

            // --- 4. الإيرادات الشهرية ---
            try {
                const canvas4 = document.getElementById('monthlyRevenueChart');
                if (canvas4) {
                    const ctx4 = canvas4.getContext('2d');
                    let gradient4 = ctx4.createLinearGradient(0, 0, 0, 400);
                    gradient4.addColorStop(0, '#8e44ad');
                    gradient4.addColorStop(1, '#3498db');

                    new Chart(ctx4, {
                        type: 'bar',
                        data: {
                            labels: ['يناير', 'فبراير', 'مارس', 'أبريل', 'مايو', 'يونيو'],
                            datasets: [{
                                label: 'الإيرادات',
                                data: data.monthlyRevenue,
                                backgroundColor: gradient4,
                                borderRadius: 8,
                                barPercentage: 0.6
                            }]
                        },
                        options: {
                            responsive: true,
                            maintainAspectRatio: false,
                            scales: {
                                y: { grid: { color: 'rgba(255,255,255,0.05)' } },
                                x: { grid: { display: false } }
                            }
                        }
                    });
                }
            } catch (e) { console.error("Error creating monthlyRevenueChart:", e); }

        })
        .catch(error => {
            console.error("Error fetching chart data:", error);
        });
});
