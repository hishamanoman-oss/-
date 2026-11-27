import Chart from 'chart.js/auto';

const chartRegistry = new WeakMap();

function getThemePalette() {
    const isDark = document.documentElement.classList.contains('dark');
    const isRtl = document.documentElement.dir === 'rtl';
    const reducedMotion = Boolean(
        window.matchMedia && window.matchMedia('(prefers-reduced-motion: reduce)').matches
    );

    return {
        isDark,
        isRtl,
        reducedMotion,
        textColor: isDark ? '#cbd5e1' : '#334155',
        mutedColor: isDark ? '#94a3b8' : '#64748b',
        gridColor: isDark ? 'rgba(148, 163, 184, 0.14)' : 'rgba(15, 23, 42, 0.08)',
        surfaceBg: isDark ? '#0f172a' : '#ffffff',
    };
}

function renderSingleCanvas(canvas) {
    const raw = canvas.getAttribute('data-chart-config');
    if (!raw) return;

    if (canvas.getAttribute('data-rendered-spec') === raw && chartRegistry.has(canvas)) {
        return;
    }

    let spec;
    try {
        spec = JSON.parse(raw);
    } catch {
        return;
    }

    const prev = chartRegistry.get(canvas);
    if (prev) {
        prev.destroy();
        chartRegistry.delete(canvas);
    }

    const theme = getThemePalette();
    const chartType = spec.type || 'line';
    const isDoughnut = chartType === 'doughnut' || chartType === 'pie';

    const datasets = (spec.datasets || []).map((ds, idx) => {
        const defaultColors = ['#0284c7', '#10b981', '#f59e0b', '#e11d48', '#6366f1'];
        const baseColor = ds.color || defaultColors[idx % defaultColors.length];

        if (isDoughnut) {
            return {
                label: ds.label || '',
                data: ds.data || [],
                backgroundColor: ds.colors || defaultColors,
                borderColor: theme.surfaceBg,
                borderWidth: 2,
            };
        }

        if (chartType === 'bar') {
            return {
                label: ds.label || '',
                data: ds.data || [],
                backgroundColor: ds.backgroundColor || baseColor,
                borderRadius: 6,
                maxBarThickness: 36,
            };
        }

        return {
            label: ds.label || '',
            data: ds.data || [],
            borderColor: baseColor,
            backgroundColor: ds.fillColor || 'rgba(2, 132, 199, 0.14)',
            borderWidth: 2,
            borderDash: ds.dashed ? [5, 5] : [],
            tension: 0.28,
            fill: Boolean(ds.fill),
            pointRadius: 2.5,
            pointHoverRadius: 4,
        };
    });

    const config = {
        type: chartType,
        data: {
            labels: spec.labels || [],
            datasets,
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            animation: theme.reducedMotion ? false : { duration: 180 },
            plugins: {
                legend: {
                    rtl: theme.isRtl,
                    textDirection: theme.isRtl ? 'rtl' : 'ltr',
                    labels: {
                        color: theme.textColor,
                        boxWidth: 12,
                        font: { size: 11, family: 'IBM Plex Sans Arabic, Cairo, sans-serif' },
                    },
                },
                tooltip: {
                    rtl: theme.isRtl,
                    textDirection: theme.isRtl ? 'rtl' : 'ltr',
                },
            },
            scales: isDoughnut
                ? {}
                : {
                      x: {
                          ticks: { color: theme.mutedColor, font: { size: 10 }, maxRotation: 0, autoSkip: true },
                          grid: { display: false },
                      },
                      y: {
                          position: theme.isRtl ? 'right' : 'left',
                          beginAtZero: true,
                          ticks: { color: theme.mutedColor, font: { size: 10 } },
                          grid: { color: theme.gridColor },
                      },
                  },
        },
    };

    const instance = new Chart(canvas, config);
    chartRegistry.set(canvas, instance);
    canvas.setAttribute('data-rendered-spec', raw);
}

export function mountAllRbdCharts() {
    document.querySelectorAll('canvas[data-rbd-chart]').forEach((canvas) => {
        if (canvas.isConnected) {
            renderSingleCanvas(canvas);
        }
    });
}

let scheduledFrame = null;
function scheduleChartMount() {
    if (scheduledFrame !== null) return;
    scheduledFrame = window.requestAnimationFrame(() => {
        scheduledFrame = null;
        mountAllRbdCharts();
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', mountAllRbdCharts);
} else {
    mountAllRbdCharts();
}

document.addEventListener('livewire:navigated', scheduleChartMount);

document.addEventListener('livewire:initialized', () => {
    if (window.Livewire && typeof window.Livewire.hook === 'function') {
        window.Livewire.hook('morph.updated', () => {
            scheduleChartMount();
        });
    }
});

const observer = new MutationObserver(() => {
    scheduleChartMount();
});

if (document.body) {
    observer.observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['data-chart-config', 'class', 'dir'] });
} else {
    document.addEventListener('DOMContentLoaded', () => {
        observer.observe(document.body, { childList: true, subtree: true, attributes: true, attributeFilter: ['data-chart-config', 'class', 'dir'] });
    });
}
