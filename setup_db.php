<?php
/**
 * إعداد وتحديث قاعدة البيانات وغرس البيانات الأولية
 * Relational Database Setup & Seeder (Step 1)
 */

require_once __DIR__ . '/config/config.php';

// التحقق من صلاحية التشغيل: يُسمح فقط في بيئة التطوير المحلية أو عبر موجه الأوامر (CLI)
if (php_sapi_name() !== 'cli' && APP_ENV !== 'local') {
    http_response_code(403);
    die('غير مسموح بتشغيل هذا الملف إلا في البيئة المحلية (APP_ENV=local) أو عبر الطرفية.');
}

try {
    // 1. الاتصال المبدئي بخادم MySQL لإنشاء قاعدة البيانات إن لم تكن موجودة
    $pdoInit = new PDO(
        sprintf('mysql:host=%s;port=%s', DB_HOST, DB_PORT),
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]
    );

    $pdoInit->exec(sprintf(
        'CREATE DATABASE IF NOT EXISTS `%s` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci',
        DB_NAME
    ));
    echo "✔ تم التحقق من وجود قاعدة البيانات: " . DB_NAME . PHP_EOL;

    // 2. الاتصال المباشر بقاعدة البيانات المحددة
    $pdo = new PDO(
        sprintf('mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4', DB_HOST, DB_PORT, DB_NAME),
        DB_USER,
        DB_PASS,
        [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
            PDO::ATTR_EMULATE_PREPARES => false,
        ]
    );

    // التحقق مما إذا كانت الجداول القديمة بدون البنية الجديدة وتحديثها
    $checkOldUsers = $pdo->query("SHOW TABLES LIKE 'users'")->fetch();
    if ($checkOldUsers) {
        $hasPasswordHash = $pdo->query("SHOW COLUMNS FROM `users` LIKE 'password_hash'")->fetch();
        if (!$hasPasswordHash) {
            // ترقية الجدول القديم أو إعادة بنائه نظراً لتغير نوع الحقل ونظام التشفير
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
            $pdo->exec("DROP TABLE IF EXISTS `order_items`, `orders`, `posts`, `products`, `clients`, `settings`, `users`");
            $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
            echo "✔ تمت ترقية البنية التحتية للجداول القديمة إلى النموذج العلائقي الجديد." . PHP_EOL;
        }
    }

    // 3. قراءة وتنفيذ مخطط قاعدة البيانات
    $schemaFile = __DIR__ . '/database/schema.sql';
    if (!file_exists($schemaFile)) {
        throw new RuntimeException("ملف المخطط database/schema.sql غير موجود!");
    }

    $sql = file_get_contents($schemaFile);
    $pdo->exec($sql);
    echo "✔ تم إنشاء وتحديث الجداول العلائقية السبعة بنجاح." . PHP_EOL;

    // 4. غرس بيانات المستخدمين (Users Seeder)
    $stmtUserCheck = $pdo->prepare("SELECT COUNT(*) FROM `users` WHERE `email` = :email");
    
    $usersToSeed = [
        [
            'full_name' => 'مدير النظام',
            'email' => 'admin@store.com',
            'password' => 'Admin@123',
            'role' => 'admin',
        ],
        [
            'full_name' => 'موظف المبيعات',
            'email' => 'staff@store.com',
            'password' => 'Staff@123',
            'role' => 'staff',
        ],
        [
            'full_name' => 'هشام نعمان',
            'email' => 'hisham@gmail.com',
            'password' => '123456',
            'role' => 'admin',
        ]
    ];

    $stmtInsertUser = $pdo->prepare("
        INSERT INTO `users` (`full_name`, `email`, `password_hash`, `role`, `is_active`)
        VALUES (:full_name, :email, :password_hash, :role, 1)
    ");

    foreach ($usersToSeed as $u) {
        $stmtUserCheck->execute(['email' => $u['email']]);
        if ($stmtUserCheck->fetchColumn() == 0) {
            $stmtInsertUser->execute([
                'full_name' => $u['full_name'],
                'email' => $u['email'],
                'password_hash' => password_hash($u['password'], PASSWORD_DEFAULT),
                'role' => $u['role'],
            ]);
            echo "  + تم إنشاء حساب: {$u['email']} ({$u['role']})" . PHP_EOL;
        }
    }

    // جلب معرفات المستخدمين لربطها بالبيانات الأخرى
    $adminId = (int)$pdo->query("SELECT `id` FROM `users` WHERE `role` = 'admin' LIMIT 1")->fetchColumn();
    $staffId = (int)$pdo->query("SELECT `id` FROM `users` WHERE `role` = 'staff' LIMIT 1")->fetchColumn() ?: $adminId;

    // 5. غرس بيانات العملاء (Clients Seeder)
    $clientsCount = (int)$pdo->query("SELECT COUNT(*) FROM `clients`")->fetchColumn();
    if ($clientsCount === 0) {
        $clients = [
            ['name' => 'مؤسسة النخبة التجارية', 'phone' => '0501112233', 'email' => 'info@elite.com', 'address' => 'الرياض - حي العليا', 'is_vip' => 1, 'notes' => 'عميل استراتيجي معتمد'],
            ['name' => 'شركة الأفق للتجارة', 'phone' => '0552223344', 'email' => 'contact@horizon.com', 'address' => 'جدة - طريق الملك', 'is_vip' => 1, 'notes' => 'حساب شركات دفع آجل'],
            ['name' => 'أحمد علي المحمدي', 'phone' => '0543334455', 'email' => 'ahmed.m@example.com', 'address' => 'الدمام - حي الشاطئ', 'is_vip' => 0, 'notes' => 'عميل دائم'],
            ['name' => 'سارة خالد العتيبي', 'phone' => '0564445566', 'email' => 'sara.k@example.com', 'address' => 'مكة المكرمة - العوالي', 'is_vip' => 0, 'notes' => ''],
            ['name' => 'عبد الله عمر الشمري', 'phone' => '0595556677', 'email' => 'abdullah.sh@example.com', 'address' => 'المدينة المنورة - سلطانة', 'is_vip' => 0, 'notes' => 'توصيل سريع'],
        ];

        $stmtClient = $pdo->prepare("
            INSERT INTO `clients` (`name`, `phone`, `email`, `address`, `is_vip`, `notes`)
            VALUES (:name, :phone, :email, :address, :is_vip, :notes)
        ");
        foreach ($clients as $c) {
            $stmtClient->execute($c);
        }
        echo "✔ تم غرس 5 عملاء واقعيين بنجاح." . PHP_EOL;
    }

    // 6. غرس بيانات المنتجات (Products Seeder)
    $productsCount = (int)$pdo->query("SELECT COUNT(*) FROM `products`")->fetchColumn();
    if ($productsCount === 0) {
        $products = [
            ['name' => 'لابتوب ماك بوك برو M3', 'sku' => 'PROD-MBP-M3', 'category' => 'حواسيب محموله', 'price' => 9500.00, 'count' => 8, 'min_stock_alert' => 3],
            ['name' => 'لابتوب ديل XPS 15', 'sku' => 'PROD-DELL-XPS', 'category' => 'حواسيب محموله', 'price' => 2800.00, 'count' => 12, 'min_stock_alert' => 5],
            ['name' => 'شاشة سامسونج منحنية 27 بوصة', 'sku' => 'PROD-SAM-27', 'category' => 'شاشات عرض', 'price' => 1200.00, 'count' => 15, 'min_stock_alert' => 4],
            ['name' => 'سماعات سوني لاسلكية WH-1000XM5', 'sku' => 'PROD-SNY-XM5', 'category' => 'صوتيات', 'price' => 350.00, 'count' => 25, 'min_stock_alert' => 5],
            ['name' => 'لوحة مفاتيح ميكانيكية لاسلكية', 'sku' => 'PROD-KB-MECH', 'category' => 'ملحقات حواسيب', 'price' => 180.00, 'count' => 40, 'min_stock_alert' => 10],
            ['name' => 'ماوس مريح للأعمال إرجونوميك', 'sku' => 'PROD-MS-ERG', 'category' => 'ملحقات حواسيب', 'price' => 90.00, 'count' => 50, 'min_stock_alert' => 10],
        ];

        $stmtProd = $pdo->prepare("
            INSERT INTO `products` (`name`, `sku`, `category`, `price`, `count`, `min_stock_alert`, `status`)
            VALUES (:name, :sku, :category, :price, :count, :min_stock_alert, 'active')
        ");
        foreach ($products as $p) {
            $stmtProd->execute($p);
        }
        echo "✔ تم غرس 6 منتجات متوافقة مع المخزون والأسعار." . PHP_EOL;
    }

    // 7. غرس الطلبات وتفاصيلها (Orders & Order Items Seeder)
    $ordersCount = (int)$pdo->query("SELECT COUNT(*) FROM `orders`")->fetchColumn();
    if ($ordersCount === 0) {
        $clientIds = $pdo->query("SELECT `id` FROM `clients` ORDER BY `id` ASC LIMIT 5")->fetchAll(PDO::FETCH_COLUMN);
        $prodRows = $pdo->query("SELECT `id`, `price` FROM `products` ORDER BY `id` ASC")->fetchAll();

        if (count($clientIds) >= 4 && count($prodRows) >= 4) {
            $ordersData = [
                [
                    'number' => 'ORD-2026-001',
                    'client_id' => $clientIds[0],
                    'user_id' => $adminId,
                    'status' => 'completed',
                    'items' => [
                        ['product_id' => $prodRows[0]['id'], 'qty' => 1, 'price' => $prodRows[0]['price']], // 9500
                        ['product_id' => $prodRows[4]['id'], 'qty' => 2, 'price' => $prodRows[4]['price']], // 360
                    ]
                ],
                [
                    'number' => 'ORD-2026-002',
                    'client_id' => $clientIds[1],
                    'user_id' => $staffId,
                    'status' => 'completed',
                    'items' => [
                        ['product_id' => $prodRows[1]['id'], 'qty' => 2, 'price' => $prodRows[1]['price']], // 5600
                        ['product_id' => $prodRows[3]['id'], 'qty' => 3, 'price' => $prodRows[3]['price']], // 1050
                    ]
                ],
                [
                    'number' => 'ORD-2026-003',
                    'client_id' => $clientIds[2],
                    'user_id' => $staffId,
                    'status' => 'pending',
                    'items' => [
                        ['product_id' => $prodRows[2]['id'], 'qty' => 1, 'price' => $prodRows[2]['price']], // 1200
                        ['product_id' => $prodRows[5]['id'], 'qty' => 2, 'price' => $prodRows[5]['price']], // 180
                    ]
                ],
                [
                    'number' => 'ORD-2026-004',
                    'client_id' => $clientIds[3],
                    'user_id' => $adminId,
                    'status' => 'completed',
                    'items' => [
                        ['product_id' => $prodRows[3]['id'], 'qty' => 1, 'price' => $prodRows[3]['price']], // 350
                        ['product_id' => $prodRows[5]['id'], 'qty' => 1, 'price' => $prodRows[5]['price']], // 90
                    ]
                ]
            ];

            $stmtOrder = $pdo->prepare("
                INSERT INTO `orders` (`order_number`, `client_id`, `user_id`, `total_amount`, `status`)
                VALUES (:number, :client_id, :user_id, :total, :status)
            ");
            $stmtItem = $pdo->prepare("
                INSERT INTO `order_items` (`order_id`, `product_id`, `quantity`, `unit_price`, `subtotal`)
                VALUES (:order_id, :product_id, :quantity, :unit_price, :subtotal)
            ");

            foreach ($ordersData as $o) {
                $total = 0.0;
                foreach ($o['items'] as $item) {
                    $total += ((float)$item['price'] * (int)$item['qty']);
                }

                $stmtOrder->execute([
                    'number' => $o['number'],
                    'client_id' => $o['client_id'],
                    'user_id' => $o['user_id'],
                    'total' => $total,
                    'status' => $o['status']
                ]);
                $orderId = (int)$pdo->lastInsertId();

                foreach ($o['items'] as $item) {
                    $subtotal = (float)$item['price'] * (int)$item['qty'];
                    $stmtItem->execute([
                        'order_id' => $orderId,
                        'product_id' => $item['product_id'],
                        'quantity' => $item['qty'],
                        'unit_price' => $item['price'],
                        'subtotal' => $subtotal
                    ]);
                }
            }
            echo "✔ تم غرس 4 طلبات مبيعات مع بنودها وحساب الإجماليات بدقة." . PHP_EOL;
        }
    }

    // 8. غرس المنشورات والإعلانات (Posts Seeder)
    $postsCount = (int)$pdo->query("SELECT COUNT(*) FROM `posts`")->fetchColumn();
    if ($postsCount === 0) {
        $posts = [
            [
                'user_id' => $adminId,
                'title' => 'تحديث المخزون والواردات الجديدة',
                'content' => 'تم استلام وتحديث شحنة جديدة من ملحقات الحواسيب وشاشات العرض المنحنية. تم فحص الكميات وتحديث رصيد المخزن وجاهزة للمبيعات.',
                'status' => 'published'
            ],
            [
                'user_id' => $staffId,
                'title' => 'تقرير أداء المبيعات الأسبوعي',
                'content' => 'حقق قسم المبيعات نمواً بنسبة 18% مقارنة بالأسبوع الماضي، مدفوعاً بطلب متزايد على أجهزة اللابتوب الاحترافية وإكسسوارات العمل المكتبي.',
                'status' => 'published'
            ],
            [
                'user_id' => $adminId,
                'title' => 'عروض ترويجية حصرية لعملاء VIP',
                'content' => 'إطلاق حملة الخصومات الخاصة بالشركاء الاستراتيجيين وعملاء VIP على حزم الأجهزة والملحقات. يرجى التنسيق مع فريق المبيعات.',
                'status' => 'published'
            ],
        ];

        $stmtPost = $pdo->prepare("
            INSERT INTO `posts` (`user_id`, `title`, `content`, `status`)
            VALUES (:user_id, :title, :content, :status)
        ");
        foreach ($posts as $p) {
            $stmtPost->execute($p);
        }
        echo "✔ تم غرس المنشورات والإعلانات الواقعية." . PHP_EOL;
    }

    // 9. غرس إعدادات النظام الافتراضية (Settings Seeder)
    $settingsCount = (int)$pdo->query("SELECT COUNT(*) FROM `settings`")->fetchColumn();
    if ($settingsCount === 0) {
        $settings = [
            ['setting_key' => 'store_name', 'setting_value' => 'متجر المبيعات الذكي'],
            ['setting_key' => 'currency', 'setting_value' => 'ر.س'],
            ['setting_key' => 'theme', 'setting_value' => 'dark'],
            ['setting_key' => 'records_per_page', 'setting_value' => '10'],
            ['setting_key' => 'tax_percentage', 'setting_value' => '15'],
        ];

        $stmtSetting = $pdo->prepare("
            INSERT INTO `settings` (`setting_key`, `setting_value`)
            VALUES (:setting_key, :setting_value)
        ");
        foreach ($settings as $s) {
            $stmtSetting->execute($s);
        }
        echo "✔ تم غرس إعدادات المتجر الافتراضية بنجاح." . PHP_EOL;
    }

    echo PHP_EOL . "🎉 اكتمل تجهيز قاعدة البيانات وغرس البيانات الأولية بنجاح تام." . PHP_EOL;

} catch (Exception $e) {
    echo "❌ خطأ أثناء إعداد قاعدة البيانات: " . $e->getMessage() . PHP_EOL;
    exit(1);
}
