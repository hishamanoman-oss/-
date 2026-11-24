<?php
/**
 * اختبار التحقق الآلي من تباين الوضع الفاتح وتجاوب الجوال
 * Verification Test for Light Mode Contrast and Mobile Form Fixes
 */

$cssTheme = file_get_contents(__DIR__ . '/../Css/theme.css');
$cssResponsive = file_get_contents(__DIR__ . '/../Css/responsive.css');

$tests = [];

// 1. فحص زر التحديث في الوضع الفاتح
$tests['Light Mode .btn-refresh styling'] = (
    strpos($cssTheme, 'html[data-theme="light"] .btn-refresh') !== false &&
    strpos($cssTheme, 'color: #0481ff !important;') !== false &&
    strpos($cssTheme, 'background: #ffffff !important;') !== false
);

// 2. فحص أزرار الإجراءات الفاتورة وتفقد المخزن
$tests['Light Mode .btn-sm-action styling'] = (
    strpos($cssTheme, 'html[data-theme="light"] .btn-sm-action') !== false &&
    strpos($cssTheme, 'background: #e0f2fe !important;') !== false &&
    strpos($cssTheme, 'color: #0369a1 !important;') !== false
);

// 3. فحص رموز الـ SKU والأكواد والأسعار والكميات
$tests['Light Mode Table Code, Price, Count'] = (
    strpos($cssTheme, 'html[data-theme="light"] td code') !== false &&
    strpos($cssTheme, 'html[data-theme="light"] .price') !== false &&
    strpos($cssTheme, 'html[data-theme="light"] .count') !== false
);

// 4. فحص أيقونات شارات الحالة
$tests['Light Mode Badge Child Icons Color Inherit'] = (
    strpos($cssTheme, 'html[data-theme="light"] [class*="badge"] i') !== false &&
    strpos($cssTheme, 'color: inherit !important;') !== false
);

// 5. فحص بنود الطلبات في orders
$tests['Light Mode Orders Items Card'] = (
    strpos($cssTheme, 'html[data-theme="light"] .items-card') !== false &&
    strpos($cssTheme, 'background: #f8fafc !important;') !== false
);

// 6. فحص بطاقات المفضلة والـ VIP
$tests['Light Mode Favorite Cards & VIP Badge'] = (
    strpos($cssTheme, 'html[data-theme="light"] .favorites-container .card') !== false &&
    strpos($cssTheme, 'html[data-theme="light"] .badge-vip') !== false &&
    strpos($cssTheme, 'color: #92400e !important;') !== false
);

// 7. فحص إزالة color: #cbd5e1 من تسميات النماذج في responsive.css
$tests['Responsive Labels Free of Hardcoded #cbd5e1'] = (
    !preg_match('/\.body\s+form#form\s+label[^{]*\{[^}]*color:\s*#cbd5e1/s', $cssResponsive)
);

// 8. فحص إزالة display: flex !important من button#cancel_btn في responsive.css
$tests['Responsive Cancel Button Free of display: flex !important'] = (
    !preg_match('/button#cancel_btn[^{]*\{[^}]*display:\s*flex\s*!important/s', $cssResponsive)
);

// 9. فحص شبكة .order-header-fields في responsive.css
$tests['Responsive Order Header Fields Grid 1fr'] = (
    strpos($cssResponsive, '.order-header-fields') !== false &&
    strpos($cssResponsive, 'grid-template-columns: 1fr !important;') !== false
);

// 10. فحص ترقية الإصدار إلى v=14 في جميع الصفحات الـ 9
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

$allPagesV14 = true;
foreach ($pages as $p) {
    $content = file_get_contents(__DIR__ . '/../' . $p);
    if (strpos($content, 'responsive.css?v=14') === false) {
        $allPagesV14 = false;
        break;
    }
}
$tests['All 9 Pages have ?v=14 cache-busting'] = $allPagesV14;

// طباعة النتائج
echo "=== نتائج فحص تباين الوضع الفاتح وتجاوب الجوال ===\n\n";
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
    echo "\n🎉 جميع فحوصات التباين والوضع الفاتح وتجاوب الجوال اجتازت الاختبار بنسبة 100%!\n";
    exit(0);
} else {
    echo "\n⚠️ يوجد اختبارات لم تنجح، يرجى المراجعة.\n";
    exit(1);
}
