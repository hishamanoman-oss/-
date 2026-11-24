<?php
/**
 * اختبار التحقق الآلي من الإصلاحات الستة لتباين الوضع الفاتح وتوحيد الجداول
 * Verification Test for All 6 Fixes (Light Mode & Table Unification)
 */

$cssTheme      = file_get_contents(__DIR__ . '/../Css/theme.css');
$cssUi         = file_get_contents(__DIR__ . '/../Css/ui.css');
$cssSidebar    = file_get_contents(__DIR__ . '/../Css/sidebar.css');
$cssPosts      = file_get_contents(__DIR__ . '/../posts/css/posts.css');
$cssFav        = file_get_contents(__DIR__ . '/../favorite/css/favorite.css');
$favPhp        = file_get_contents(__DIR__ . '/../favorite/favorite.php');
$ordersPhp     = file_get_contents(__DIR__ . '/../orders/orders.php');
$indexPhp      = file_get_contents(__DIR__ . '/../index.php');

$tests = [];

// [Fix 1]: عنصر القائمة الجانبية النشط عند التحويم في الوضع الفاتح
$tests['[Fix 1] Active sidebar link retains white text & icons on hover'] = (
    strpos($cssTheme, 'html[data-theme="light"] .menu ul li a.active:hover') !== false &&
    strpos($cssTheme, 'html[data-theme="light"] .menu ul li a.active:hover i') !== false &&
    strpos($cssTheme, 'color: #ffffff !important;') !== false
);

// [Fix 2]: إزالة شريط التمرير الأسود من posts وتنسيقه في sidebar و theme
$tests['[Fix 2] posts.css is free of global scrollbars'] = (
    strpos($cssPosts, '::-webkit-scrollbar-track') === false
);
$tests['[Fix 2] sidebar.css styles .menu::-webkit-scrollbar with transparent track'] = (
    strpos($cssSidebar, '.menu::-webkit-scrollbar') !== false &&
    strpos($cssSidebar, 'background: transparent;') !== false
);
$tests['[Fix 2] theme.css styles light mode scrollbar'] = (
    strpos($cssTheme, 'html[data-theme="light"] ::-webkit-scrollbar-track') !== false &&
    strpos($cssTheme, 'html[data-theme="light"] ::-webkit-scrollbar-thumb') !== false
);

// [Fix 3]: التنبيهات المنبثقة .custom-toast في الوضع الفاتح
$tests['[Fix 3] Toast notifications styled with high contrast in theme.css'] = (
    strpos($cssTheme, 'html[data-theme="light"] .custom-toast.toast-success') !== false &&
    strpos($cssTheme, 'html[data-theme="light"] .custom-toast.toast-error') !== false &&
    strpos($cssTheme, 'html[data-theme="light"] .custom-toast.toast-info') !== false &&
    strpos($cssTheme, ':not(.custom-toast)') !== false
);

// [Fix 4]: زر التحديث الحي بدون مربعات داخلية وبدون تكرار
$tests['[Fix 4] Refresh button background/border is clean on button only'] = (
    strpos($cssTheme, 'html[data-theme="light"] .btn-refresh span,') !== false &&
    strpos($cssTheme, 'border: none !important;') !== false &&
    strpos($cssTheme, 'background: transparent !important;') !== false
);

// [Fix 5]: الأنماط الدلالية لصفحة المفضلة
$tests['[Fix 5] favorite.php uses semantic CSS classes'] = (
    strpos($favPhp, 'class="fav-link"') !== false &&
    strpos($favPhp, 'class="fav-empty"') !== false &&
    strpos($favPhp, 'class="fav-meta"') !== false &&
    strpos($favPhp, 'class="fav-amount"') !== false &&
    strpos($favPhp, 'class="fav-submeta"') !== false &&
    strpos($favPhp, 'class="fav-stock-low"') !== false &&
    strpos($favPhp, 'class="fav-stock-ok"') !== false
);
$tests['[Fix 5] favorite/css/favorite.css defines semantic classes for Dark Mode'] = (
    strpos($cssFav, '.fav-link') !== false &&
    strpos($cssFav, '.fav-amount') !== false &&
    strpos($cssFav, '.fav-stock-low') !== false
);
$tests['[Fix 5] theme.css styles favorite semantic classes for Light Mode'] = (
    strpos($cssTheme, 'html[data-theme="light"] .fav-amount') !== false &&
    strpos($cssTheme, 'html[data-theme="light"] .fav-stock-low') !== false &&
    strpos($cssTheme, 'html[data-theme="light"] .fav-link') !== false &&
    strpos($cssTheme, 'html[data-theme="light"] .fav-empty') !== false
);

// [Fix 6]: توحيد الجداول وأزرار الإجراءات وشارات VIP
$tests['[Fix 6] ui.css has unified table headers and 34px action buttons'] = (
    strpos($cssUi, '.table-responsive thead th') !== false &&
    strpos($cssUi, 'linear-gradient(180deg, #1e293b, #0f172a)') !== false &&
    strpos($cssUi, 'height: 34px !important;') !== false
);
$tests['[Fix 6] orders.php and index.php use unified <span class="badge-vip">'] = (
    strpos($ordersPhp, '<span class="badge-vip"><i class="fas fa-crown"></i> VIP</span>') !== false &&
    strpos($indexPhp, '<span class="badge-vip"><i class="fas fa-crown"></i> VIP</span>') !== false
);

// ترقية الإصدار إلى v=15
$pages = [
    'index.php',
    'clients/clients.php',
    'Products/products.php',
    'orders/orders.php',
    'charts/charts.php',
    'posts/posts.php',
    'favorite/favorite.php',
    'settings/settings.php',
    'Login.php'
];

$allPagesV15 = true;
foreach ($pages as $p) {
    $content = file_get_contents(__DIR__ . '/../' . $p);
    if (strpos($content, 'responsive.css?v=15') === false) {
        $allPagesV15 = false;
        break;
    }
}
$tests['All 9 Pages have ?v=15 cache-busting'] = $allPagesV15;

// طباعة التقرير
echo "=== نتائج التحقق الآلي من الإصلاحات الستة وتوحيد الجداول ===\n\n";
$allPassed = true;
foreach ($tests as $title => $passed) {
    if ($passed) {
        echo "✔ [PASS] $title\n";
    } else {
        echo "✖ [FAIL] $title\n";
        $allPassed = false;
    }
}

if ($allPassed) {
    echo "\n🎉 جميع الفحوصات الـ 6 اجتازت الاختبار بنجاح تام 100%!\n";
    exit(0);
} else {
    echo "\n⚠️ يوجد فشل في بعض الاختبارات.\n";
    exit(1);
}
