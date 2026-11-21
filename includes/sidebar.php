<?php
/**
 * المكون المشترك للقائمة الجانبية
 * Shared Sidebar Navigation Component
 *
 * المتغيرات المطلوبة (مع قيم افتراضية):
 * @var string $activePage 'dashboard' | 'clients' | 'products' | 'charts' | 'posts' | 'favorite' | 'settings'
 * @var string $basePath   مسار الجذر النسبي: '' لملفات الجذر أو '../' للمجلدات الفرعية
 */

if (!function_exists('current_user')) {
    require_once __DIR__ . '/auth.php';
}

$currentUser = current_user() ?? ['full_name' => 'زائر', 'email' => '', 'role' => 'staff'];
$activePage  = $activePage ?? '';
$basePath    = $basePath ?? '';

$roleBadge = ($currentUser['role'] ?? '') === 'admin' ? 'مدير عام' : 'موظف';
?>
<div class="menu">
    <ul>
        <li class="profile">
            <div class="img-box">
                <img src="<?php echo e($basePath . 'work.jpg'); ?>" alt="profile">
            </div>
            <div>
                <h2><?php echo e($currentUser['full_name'] ?: $currentUser['email']); ?></h2>
                <span class="user-role-badge" style="font-size: 11px; color: #00c6ff; display: block; font-weight: bold;">
                    <?php echo $roleBadge; ?>
                </span>
            </div>
        </li>

        <li class="<?php echo $activePage === 'dashboard' ? 'active' : ''; ?>">
            <a href="<?php echo e($basePath . 'index.php'); ?>" class="<?php echo $activePage === 'dashboard' ? 'active' : ''; ?>">
                <i class="fas fa-home"></i>
                <p>لوحة التحكم</p>
            </a>
        </li>

        <li class="<?php echo $activePage === 'clients' ? 'active' : ''; ?>">
            <a href="<?php echo e($basePath . 'clients/clients.php'); ?>" class="<?php echo $activePage === 'clients' ? 'active' : ''; ?>">
                <i class="fas fa-user-group"></i>
                <p>العملاء</p>
            </a>
        </li>

        <li class="<?php echo $activePage === 'products' ? 'active' : ''; ?>">
            <a href="<?php echo e($basePath . 'Products/products.php'); ?>" class="<?php echo $activePage === 'products' ? 'active' : ''; ?>">
                <i class="fas fa-table"></i>
                <p>المنتجات</p>
            </a>
        </li>

        <li class="<?php echo $activePage === 'charts' ? 'active' : ''; ?>">
            <a href="<?php echo e($basePath . 'charts/charts.php'); ?>" class="<?php echo $activePage === 'charts' ? 'active' : ''; ?>">
                <i class="fas fa-chart-pie"></i>
                <p>الإحصائيات</p>
            </a>
        </li>

        <li class="<?php echo $activePage === 'posts' ? 'active' : ''; ?>">
            <a href="<?php echo e($basePath . 'posts/posts.php'); ?>" class="<?php echo $activePage === 'posts' ? 'active' : ''; ?>">
                <i class="fas fa-pen"></i>
                <p>المنشورات</p>
            </a>
        </li>

        <li class="<?php echo $activePage === 'favorite' ? 'active' : ''; ?>">
            <a href="<?php echo e($basePath . 'favorite/favorite.php'); ?>" class="<?php echo $activePage === 'favorite' ? 'active' : ''; ?>">
                <i class="fas fa-star"></i>
                <p>المفضلة</p>
            </a>
        </li>

        <?php if (has_role('admin')): ?>
        <li class="<?php echo $activePage === 'settings' ? 'active' : ''; ?>">
            <a href="<?php echo e($basePath . 'settings/settings.php'); ?>" class="<?php echo $activePage === 'settings' ? 'active' : ''; ?>">
                <i class="fas fa-cog"></i>
                <p>الإعدادات</p>
            </a>
        </li>
        <?php endif; ?>

        <li class="log-out">
            <a href="<?php echo e($basePath . 'logout.php'); ?>" id="log-out">
                <i class="fas fa-sign-out-alt"></i>
                <p>تسجيل الخروج</p>
            </a>
        </li>
    </ul>
</div>
