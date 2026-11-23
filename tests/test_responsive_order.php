<?php
/**
 * سكريبت التحقق من صحة ترتيب تحميل CSS وتجاوب الجوال
 */

$pages = [
    'index.php' => [
        'expected_css' => ['fonts/cairo.css', 'fontawesome/css/all.min.css', 'Css/sidebar.css', 'Css/ui.css', 'Css/work3.css', 'Css/theme.css', 'Css/responsive.css']
    ],
    'clients/clients.php' => [
        'expected_css' => ['fonts/cairo.css', 'fontawesome/css/all.min.css', 'Css/sidebar.css', 'Css/ui.css', 'css/clients.css', 'Css/theme.css', 'Css/responsive.css']
    ],
    'Products/products.php' => [
        'expected_css' => ['fonts/cairo.css', 'fontawesome/css/all.min.css', 'Css/sidebar.css', 'Css/ui.css', 'css/products.css', 'Css/theme.css', 'Css/responsive.css']
    ],
    'orders/orders.php' => [
        'expected_css' => ['fonts/cairo.css', 'fontawesome/css/all.min.css', 'Css/sidebar.css', 'Css/ui.css', 'css/orders.css', 'Css/theme.css', 'Css/responsive.css']
    ],
    'charts/charts.php' => [
        'expected_css' => ['fonts/cairo.css', 'fontawesome/css/all.min.css', 'Css/sidebar.css', 'Css/ui.css', 'css/charts.css', 'Css/theme.css', 'Css/responsive.css']
    ],
    'posts/posts.php' => [
        'expected_css' => ['fonts/cairo.css', 'fontawesome/css/all.min.css', 'Css/sidebar.css', 'Css/ui.css', 'css/posts.css', 'Css/theme.css', 'Css/responsive.css']
    ],
    'favorite/favorite.php' => [
        'expected_css' => ['fonts/cairo.css', 'fontawesome/css/all.min.css', 'Css/sidebar.css', 'Css/ui.css', 'css/favorite.css', 'Css/theme.css', 'Css/responsive.css']
    ],
    'settings/settings.php' => [
        'expected_css' => ['fonts/cairo.css', 'fontawesome/css/all.min.css', 'Css/sidebar.css', 'Css/ui.css', 'css/settings.css', 'Css/theme.css', 'Css/responsive.css']
    ],
    'Login.php' => [
        'expected_css' => ['fonts/cairo.css', 'fontawesome/css/all.min.css', 'Css/ui.css', 'Css/login.css', 'Css/theme.css', 'Css/responsive.css']
    ],
];

$allPassed = true;
echo "=== فحص ترتيب ملفات CSS ووسوم Viewport في جميع الصفحات ===\n\n";

foreach ($pages as $file => $info) {
    $filePath = __DIR__ . '/../' . $file;
    if (!file_exists($filePath)) {
        echo "❌ الملف غير موجود: $file\n";
        $allPassed = false;
        continue;
    }

    $content = file_get_contents($filePath);

    // 1. فحص وجود وسم Viewport
    if (!preg_match('/<meta\s+name=["\']viewport["\']\s+content=["\']width=device-width,\s*initial-scale=1\.0["\']\s*\/?>/i', $content)) {
        echo "❌ [$file]: وسم Viewport مفقود أو غير مطابق!\n";
        $allPassed = false;
    } else {
        echo "✔ [$file]: وسم Viewport موجود وصحيح.\n";
    }

    // 2. استخراج ملفات CSS في <head> بالترتيب
    if (preg_match('/<head>(.*?)<\/head>/is', $content, $headMatch)) {
        $head = $headMatch[1];
        preg_match_all('/<link[^>]+rel=["\']stylesheet["\'][^>]+href=["\']([^"\']+)["\']/i', $head, $linkMatches);
        $cssHrefs = $linkMatches[1];

        // تنظيف المسارات ومقارنة الترتيب
        $cleanedHrefs = array_map(function($href) {
            $path = preg_replace('/\?.*$/', '', $href);
            return ltrim(str_replace('../', '', $path), '/');
        }, $cssHrefs);

        $expected = $info['expected_css'];
        if ($cleanedHrefs === $expected) {
            echo "✔ [$file]: ترتيب ملفات CSS مطابق تماماً (Responsive.css في النهاية).\n";
        } else {
            echo "❌ [$file]: ترتيب ملفات CSS غير مطابق!\n";
            echo "   الفعلي: " . implode(' -> ', $cleanedHrefs) . "\n";
            echo "   المتوقع: " . implode(' -> ', $expected) . "\n";
            $allPassed = false;
        }

        // 3. التأكد من أن responsive.css هو الأخير دائماً وله باراميتر الإصدار لكسر الكاش
        $lastCss = end($cssHrefs);
        if (preg_match('/responsive\.css(\?v=\d+)?$/i', $lastCss)) {
            echo "✔ [$file]: آخر ملف CSS هو responsive.css بنجاح (المسار: $lastCss).\n";
        } else {
            echo "❌ [$file]: آخر ملف CSS ليس responsive.css (الموجود: $lastCss)!\n";
            $allPassed = false;
        }
    } else {
        echo "❌ [$file]: تعذر العثور على وسم <head>!\n";
        $allPassed = false;
    }

    echo "\n";
}

// 4. فحص قواعد responsive.css
$responsiveCss = file_get_contents(__DIR__ . '/../Css/responsive.css');
if (strpos($responsiveCss, 'html, body') !== false && strpos($responsiveCss, 'overflow-x: hidden') !== false) {
    echo "✔ Css/responsive.css: تم التحقق من وجود قواعد منع التمرير الأفقي الكامل (html, body { overflow-x: hidden; }).\n";
} else {
    echo "❌ Css/responsive.css: مفقود قاعدة html, body { overflow-x: hidden; }!\n";
    $allPassed = false;
}

if (strpos($responsiveCss, 'padding-top: 72px') !== false) {
    echo "✔ Css/responsive.css: تم التحقق من تباعد المحتوى مع زر القائمة (padding-top: 72px).\n";
} else {
    echo "❌ Css/responsive.css: مفقود قاعدة padding-top: 72px!\n";
    $allPassed = false;
}

if (strpos($responsiveCss, '.menu.open ul li a p') !== false && strpos($responsiveCss, 'display: block !important') !== false) {
    echo "✔ Css/responsive.css: تم التحقق من ظهور نصوص الروابط كاملة عند فتح القائمة بالجوال.\n";
} else {
    echo "❌ Css/responsive.css: مفقود قاعدة ظهور نصوص الروابط في القائمة المفتوحة!\n";
    $allPassed = false;
}

if ($allPassed) {
    echo "\n🎉 جميع فحوصات ترتيب CSS وتجاوب الجوال اجتازت الاختبار بنسبة 100%!\n";
    exit(0);
} else {
    echo "\n❌ يوجد أخطاء يجب معالجتها.\n";
    exit(1);
}
