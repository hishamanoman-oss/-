# سجل التطوير والتوثيق المرجعي (DEV_LOG.md)

## تاريخ التحديث: 2026-11-18

### 1. ملخص المشروع:
- **اسم المشروع**: myproject (لوحة تحكم إدارية وموقع متكامل).
- **التقنيات المستخدمة**: PHP 8.2, MySQL, JavaScript (jQuery), HTML5, CSS3, XAMPP.

### 2. الإجراءات المنجزة:
1. **فحص بنية المشروع والتحقق البرمجي:**
   - فحص جميع ملفات PHP والتأكد من سلامتها التركيبية بنسبة 100% عبر مترجم PHP (`php -l`).
   - فحص مسارات الواجهة ولوحة التحكم (`index.php`)، وصفحات تسجيل الدخول (`Login.php`, `login_process.php`).
   - فحص وتأكيد عمل الأقسام الفرعية:
     - قسم العملاء (`clients`).
     - قسم المنتجات (`Products`).
     - قسم الرسوم البيانية والإحصائيات (`charts`).
     - قسم المنشورات (`posts`).
     - قسم المفضلة وعملاء VIP (`favorite`).
     - قسم الإعدادات (`settings`).

2. **تجهيز وإصلاح قاعدة البيانات:**
   - تحديث ملف `setup_db.php` لبناء قاعدة البيانات `myproject_db` والجداول الثلاثة الأساسية (`users`, `clients`, `products`).
   - توفير بيانات تجريبية افتراضية للمستخدم (`hisham@gmail.com`) والعملاء والمنتجات.

3. **تثبيت وتفعيل أداة Git:**
   - تثبيت حزمة Git المحمولة الرسمية وربطها بمتغيرات النظام `PATH` بنجاح.
   - تهيئة مستودع Git المحلي للمشروع.

4. **تطبيق الدستور البرمجي الصارم:**
   - حفظ وتثبيت "دستور الهندسة البرمجية الصارم" في ملفات `GEMINI.md` المحلية والعامة.

### 3. حل مشكلة تسجيل الدخول:
- **السبب الأساسي**: قاعدة البيانات `myproject_db` والجداول لم تكن مهيأة داخل MySQL عند التشغيل الأولي، بالإضافة إلى الاعتماد على رابط خارجي لمكتبة jQuery.
- **الإجراءات**:
  - تشغيل `setup_db.php` وإنشاء قاعدة البيانات والجداول والمستخدم بنجاح.
  - تحويل استدعاء مكتبة jQuery في `Login.php` إلى المسار المحلي (`js/jquery-3.7.1.js`).
  - تحسين `login_process.php` ليدعم كلاً من طلبات AJAX والتحويل المباشر في المتصفح.

### 4. التعريب الكامل وتحويل الاتجاه إلى RTL (Localization & RTL Layout):
- **تاريخ الإنجاز**: 2026-11-19
- **التحويل الاتجاهي (RTL)**:
  - تحويل جميع صفحات المشروع إلى `<html lang="ar" dir="rtl">`.
  - عكس الخصائص الاتجاهية في ملفات الـ CSS (`direction: rtl`, `text-align: right`, `margin-right/left`).
- **الطباعة والخطوط (Typography)**:
  - استيراد وتطبيق خط **Cairo** المعتمد من Google Fonts على جميع العناصر.
- **الأمان البرمجي (Backend Safety)**:
  - الحفاظ بنسبة 100% على كافة أسماء المتغيرات، الجلسات، المعرفات، وحقول النماذج واستعلامات قواعد البيانات بدون أي تعديل.
- **الصفحات المعربة**:
  - لوحة التحكم (`index.php`, `Css/work3.css`).
  - تسجيل الدخول (`Login.php`, `Css/login.css`).
  - العملاء (`clients/clients.php`, `clients/css/clients.css`, `clients/js/clients.js`).
  - المنتجات (`Products/products.php`, `Products/css/products.css`, `Products/js/products.js`).
  - الإحصائيات (`charts/charts.php`, `charts/css/charts.css`, `charts/js/charts.js`, `charts/charts_data.php`).
  - المنشورات (`posts/posts.php`, `posts/css/posts.css`).
  - المفضلة وعملاء VIP (`favorite/favorite.php`, `favorite/css/favorite.css`).
  - الإعدادات (`settings/settings.php`, `settings/css/settings.css`).

### 5. جعل المشروع يعمل دون إنترنت (100% Offline-Ready Assets):
- **الخطوط**:
  - تحميل ملف خط `Cairo.ttf` وحفظه في المجلد `fonts/`.
  - إنشاء ملف `fonts/cairo.css` مع قاعدة `@font-face` المحلية.
- **الأيقونات**:
  - تحميل حزمة Font Awesome الكاملة وحفظها في `fontawesome/css/all.min.css` وملفات خطوط الويب `fontawesome/webfonts/`.
- **المكتبات**:
  - تحميل وتوفير مكتبة `js/chart.min.js` محلياً بدلاً من روابط CDN.
  - تأكيد ارتباط كافة الصفحات بالأصول المحلية لضمان تشغيل الموقع بكفاءة تامة وبدون أي اتصال بالإنترنت.

### 6. تنفيذ المرحلة الأولى: البنية التحتية، المخطط العلائقي، والمصادقة متعددة الأدوار (Step 1 Foundation):
- **التاريخ**: 2026-11-20
- **إعدادات البيئة (Environment & Config)**:
  - إنشاء ملفي `.env` و `.env.example` لضبط متغيرات السيرفر وقاعدة البيانات.
  - إنشاء `.gitignore` لمنع تتبع الملفات الحساسة وسجلات السيرفر.
  - إنشاء `config/config.php` لقراءة ملفات البيئة مع توفير قيم احتياطية آمنة.
  - إنشاء `config/database.php` لإدارة اتصالات **PDO** بترميز `utf8mb4`، تفعيل الاستعلامات المجهزة الحقيقية، وإخفاء أخطاء قواعد البيانات عن المستخدم مع تسجيلها داخلياً عبر `error_log`.
  - تحديث المحول القديم `db_connect.php` ليعتمد على `config/config.php` بدون كسر الصفحات القديمة.
- **المخطط العلائقي وغرس البيانات (Relational Schema & Seeder)**:
  - إنشاء `database/schema.sql` بالجداول السبعة المتكاملة والمفاتيح الأجنبية:
    1. `users` (id, full_name, email UNIQUE, password_hash, role ENUM('admin','staff'), is_active, timestamps).
    2. `clients` (id, name, phone, email, address, is_vip, notes, timestamps).
    3. `products` (id, name, sku UNIQUE, category, price, count, min_stock_alert, status, timestamps).
    4. `orders` (id, order_number UNIQUE, client_id FK, user_id FK, total_amount, status, timestamps).
    5. `order_items` (id, order_id FK, product_id FK, quantity, unit_price, subtotal).
    6. `posts` (id, user_id FK, title, content, status, timestamps).
    7. `settings` (id, setting_key UNIQUE, setting_value, updated_at).
  - إعادة بناء `setup_db.php` ليعمل بنظام Idempotent آمن ومحصور في البيئة المحلية (APP_ENV=local أو CLI).
  - غرس بيانات عربية واقعية لـ 3 مستخدمين مشفرين (Admin و Staff)، و 5 عملاء (بينهم VIP)، و 6 منتجات، و 4 طلبات مبيعات مع بنودها، و 3 منشورات، وإعدادات المتجر.
- **طبقة الأمان والمصادقة (Security & Multi-Role Auth)**:
  - إنشاء `includes/security.php`: جلسات محمية (`httponly`, `samesite=Lax`, `use_strict_mode`), دالة `e()` لفلترة XSS، وحماية متكاملة ضد CSRF (`csrf_token`, `csrf_field`, `verify_csrf_token`).
  - إنشاء `includes/auth.php`: دوال `current_user()`, `is_logged_in()`, `has_role()`, `require_auth()`, و `require_role()`.
  - إعادة بناء مسار تسجيل الدخول في `Login.php`, `login_process.php`, `js/login.js`, و `logout.php` مع تجديد معرف الجلسة بعد الدخول والتشفير بـ `password_hash` و `password_verify`.
  - إغلاق وتأمين كافة نقاط النهاية المفتوحة (Endpoints) السبعة لمنع أي وصول غير مصرح.
- **التحقق الآلي**:
  - نجاح فحص `php -l` بنسبة 100% لكافة ملفات المشروع (24 ملفاً).
  - نجاح ملف الفحص الآلي `tests/verify_step1.php` بنسبة 100%.
