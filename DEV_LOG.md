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

### 7. تحسين معالجة الأخطاء في تدفق تسجيل الدخول (AJAX Login Error Handling Fix):
- **التاريخ**: 2026-11-20
- **واجهة العميل (js/login.js)**:
  - قراءة وعرض نص الخطأ الفعلي القادم من السيرفر (`jqXHR.responseText`) بدلاً من الرسالة العامة الثابتة.
  - معالجة خطأ 403 (انتهاء الجلسة أو تعارض توكن CSRF) وتنبيه المستخدم لتحديث الصفحة والمحاولة مجدداً.
  - حصر رسالة "تعذر الاتصال بالسيرفر" فقط عند انعدام الاستجابة تماماً (`status === 0`).
- **معالجة السيرفر (login_process.php)**:
  - تحويل التقاط الأخطاء من `catch (Exception $e)` إلى `catch (Throwable $e)` لالتقاط وتسجيل أي خطأ فادح بأمان عبر `error_log` وتجنب ظهور صفحات بيضاء.
  - إضافة فحص `!headers_sent()` قبل تجديد الجلسة.
- **كوكيز الجلسة المحلية (includes/security.php)**:
  - التأكد من ضبط `secure => false` تلقائياً على خوادم HTTP المحلية (`localhost`)، وتفعيل `secure => true` فقط عند العمل عبر HTTPS.
- **التحقق الآلي**:
  - إنشاء وتشغيل `tests/test_login_flow.php` وتأكيد نجاح الدخول ببيانات `admin@store.com` / `Admin@123` بنسبة 100%، والتأكد من إرجاع رسائل أخطاء كلمة المرور ورمز CSRF بدقة.

### 8. حل مشكلة أيقونات Font Awesome والقائمة الجانبية ومنع الكاش (Font Awesome & Layout Fix):
- **التاريخ**: 2026-11-20
- **إصلاح حجب أيقونات Font Awesome**:
  - إزالة `font-family: 'Cairo', sans-serif !important;` من المحدد الشامل `*` عبر كافة ملفات CSS في المشروع (`Css/work3.css`, `Css/login.css`, `clients/css/clients.css`, `Products/css/products.css`, `charts/css/charts.css`, `posts/css/posts.css`, `favorite/css/favorite.css`, `settings/css/settings.css`).
  - تطبيق خط 'Cairo' حصراً على عناصر النصوص (`body, button, input, select, textarea, p, h1, h2, h3, h4, h5, h6, a, span, label, table, td, th`).
  - حماية وتثبيت خطوط Font Awesome الصريحة (`Font Awesome 6 Free`, `Font Awesome 6 Brands`) مع إعطاء الأولوية القصوى لأوزانها.
  - إضافة دعم مباشر ومتبادل لفئات تسجيل الخروج `.fa-sign-out:before, .fa-sign-out-alt:before { content: "\f2f5"; }` لضمان ظهور أيقونة تسجيل الخروج في كل الحالات.
- **إصلاح طي القائمة الجانبية (Sidebar Collapse Behavior)**:
  - ضبط `.menu ul li a` و `.menu ul li a p` بقواعد `white-space: nowrap; overflow: hidden;` لمنع تسرب النصوص أو التفافها الرأسي فوق عمود الأيقونات أثناء تصغير القائمة إلى `50px`.
  - تثبيت أبعاد الأيقونات بـ `min-width: 25px; text-align: center; flex-shrink: 0;` لتبقى متوسطة وثابتة بدقة.
- **تصحيح وسوم HTML والأخطاء الإملائية**:
  - تصحيح الخطأ الإملائي لفئة الإدخال `<div class="inpit1">` إلى `<div class="input1">` في `clients/clients.php`.
  - التحقق من سلامة وإغلاق كافة وسوم `<li class="log-out">` واستخدام `<i class="fas fa-sign-out-alt"></i>` عبر كافة الصفحات الـ 8.
  - ضبط محاذاة النص في أزرار النماذج عمودياً وأفقياً عبر `display: inline-flex; align-items: center; justify-content: center; height: 42px;`.
- **منع تخزين المتصفحات القديم للملفات (Cache-Busting)**:
  - إضافة الوسيط `?v=2` لكافة روابط ملفات CSS في رؤوس الصفحات الـ 8 لضمان تحميل التحديثات المرئية فوراً لدى المستخدم دون الحاجة لمسح الذاكرة المؤقتة يدوياً.
- **التحقق الآلي**:
  - اجتياز فحص `php -l` بنسبة 100% لكافة ملفات PHP الـ 25 في المشروع دون أي أخطاء.
  - نجاح كافة سيناريوهات اختبارات المصادقة وقاعدة البيانات والجلسات.

### 9. توحيد وضبط القائمة الجانبية وحل مشكلة الانضغاط والاقتطاع (Rock-Solid Sidebar & Content Flex):
- **التاريخ**: 2026-11-20
- **حل مشكلة انضغاط واقتطاع القائمة الجانبية في صفحة العملاء وكافة الصفحات**:
  - تعديل عرض القائمة الجانبية المطوية `.menu` إلى عرض ثابت ومريح `width: 80px; min-width: 80px; flex-shrink: 0; box-sizing: border-box; padding: 10px;`.
  - توسيع القائمة بسلاسة عند التحويم `.menu:hover` إلى `width: 260px; min-width: 260px;` عبر انتقال حركي ناعم `transition: width 0.3s ease;`.
  - معالجة الحاوية الرئيسية للمحتوى `.content` بـ `flex: 1; min-width: 0; box-sizing: border-box; margin: 10px;` لمنع الجداول العريضة أو النماذج المرنة من ضغط القائمة الجانبية أو تقليص مساحتها نهائياً.
  - ضبط الشعار/الصورة الشخصية `.img-box` بأبعاد `50px × 50px` متمركزة مع `margin: 0 auto;` وإخفاء بيانات المستخدم عند الطي وإظهارها بنعومة عند التحويم عبر `opacity: 0` إلى `opacity: 1`.
  - ضبط عناصر الروابط `.menu ul li a` بـ `display: flex; align-items: center; gap: 20px; white-space: nowrap;` مع تثبيت الأيقونات بـ `min-width: 30px; text-align: center; flex-shrink: 0;` وتلاشي نصوص `<p>` بالكامل عند الطي (`opacity: 0;`) وظهورها عند التحويم (`opacity: 1;`).
  - تثبيت ومحاذاة زر تسجيل الخروج الأحمر `.log-out` أسفل القائمة دون أي انزياح أو اقتطاع.
  - تطبيق هذا التنسيق الموحد والصارم على جميع ملفات التنسيق السبعة: (`Css/work3.css`, `clients/css/clients.css`, `Products/css/products.css`, `charts/css/charts.css`, `posts/css/posts.css`, `favorite/css/favorite.css`, `settings/css/settings.css`).
- **تحديث وسيط منع التخزين المؤقت (Cache-Busting)**:
  - رفع إصدار الملفات النمطية إلى `?v=3` في كافة صفحات النظام الـ 8 لضمان تحميل التعديلات المظهرية فوراً في متصفح المستخدم.
- **التحقق والمزامنة**:
  - نجاح فحص `php -l` بنسبة 100% لجميع ملفات PHP.
  - مزامنة كافة التعديلات ورفعها إلى مستودع GitHub.

### 10. تنفيذ المرحلة الثانية: المكون المشترك للقائمة الجانبية وترحيل وحدتي العملاء والمنتجات إلى PDO ونظام الصلاحيات (Step 2 Implementation):
- **التاريخ**: 2026-11-21
- **المكون المشترك للقائمة الجانبية (includes/sidebar.php)**:
  - إنشاء `includes/sidebar.php` واستبدال الأكواد المكررة للقائمة الجانبية عبر كافة صفحات النظام الـ 7 (`index.php`, `clients/clients.php`, `Products/products.php`, `charts/charts.php`, `posts/posts.php`, `favorite/favorite.php`, `settings/settings.php`).
  - دعم تمييز العنصر النشط آلياً (`class="active"`) بناءً على المتغير `$activePage`.
  - عرض اسم المستخدم المسجل وشارة الدور (`مدير عام` للمشرف، و`موظف` لموظف المبيعات).
  - حصر ظهور عنصر "الإعدادات" في القائمة على المشرف `admin` فقط وإخفاؤه تماماً عن حساب الموظف `staff`.
  - حماية صفحة الإعدادات `settings/settings.php` بـ `require_role(['admin'])` لمنع الوصول المباشر من الموظفين عبر الرابط وإرجاع خطأ 403.
  - إضافة نمط التمييز البصري للعنصر النشط `.menu ul li.active a` في كافة ملفات التنسيق.
- **تحديث وترحيل وحدة العملاء بالكامل (clients/) إلى PDO + CSRF + RBAC**:
  - الترحيل التام لملفات `clients.php`, `clients_process.php`, `update_client.php`, `delete_client.php` إلى استعلامات **PDO** المجهزة والتخلص نهائياً من `mysqli` و `db_connect.php`.
  - فرض التحقق من توكن الحماية `verify_csrf_token()` وطريقة الطلب `POST` على كافة المسارات المعدلة للبيانات.
  - دعم الحقول الحقيقية للعميل في النموذج والجدول: `name`, `phone`, `email`, `address`, و `is_vip`.
  - عرض شارة ذهبية أنيقة `<span class="badge-vip">` لعملاء VIP.
  - تقييد صلاحية الحذف: حصر زر الحذف في الواجهة على المشرف فقط `has_role('admin')`، وحظر مسار الحذف `delete_client.php` على الموظفين مع إرجاع 403، وفحص منع حذف العميل في حال وجود فواتير مبيعات مرتبطة به.
  - حذف ملف `clients/js/jquery-3.7.1.js` المكرر والاعتماد على الملف الرئيسي الموحد في `js/`.
  - تحديث `clients/js/clients.js` لإرسال توكن CSRF ومعالجة الإضافة والتعديل والحذف برسائل تنبيه عربية دقيقة.
- **تحديث وترحيل وحدة المنتجات بالكامل (Products/) إلى PDO + CSRF + RBAC**:
  - الترحيل التام لملفات `products.php`, `products_process.php`, `update_product.php`, `delete_product.php` إلى **PDO Prepared Statements** وإلغاء الاعتماد على `db_connect.php`.
  - فرض التحقق من توكن CSRF وطريقة الطلب `POST` ومنع الحذف عبر روابط GET.
  - دعم حقول المتجر الحقيقية: `name`, `sku` (فريد), `category`, `price`, `count`, `min_stock_alert`.
  - التحقق البرمجي من عدم تكرار رمز SKU وإرجاع رسالة خطأ عربية واضحة في حال وجود تكرار.
  - عرض شارة تحذيرية بصرية ملفتة "مخزون منخفض" `<span class="badge-low-stock">` عندما تكون الكمية أقل من أو تساوي حد التنبيه.
  - تقييد صلاحية الحذف: حصر زر الحذف على المشرف فقط، ومنع حذف المنتج إذا كان مرتبطاً ببنود طلبات سابقة مع إرجاع 409.
  - حذف ملف `Products/js/jquery-3.7.1.js` المكرر وتحديث `Products/js/products.js` للتعامل مع الحقول والـ CSRF.
- **منع التخزين المؤقت (Cache-Busting)**:
  - رفع وسيط التخزين إلى `?v=4` عبر كافة الصفحات ورؤوس ملفات CSS والجافا سكريبت.
- **التحقق الآلي الصارم (Mandatory Verification Gate)**:
  - اجتياز فحص `php -l` بنسبة 100% لكافة ملفات PHP الـ 26 بالمشروع.
  - إنشاء وتشغيل سكربت الفحص الآلي `tests/verify_step2.php` والتأكد من اجتياز كافة اختبارات القائمة الجانبية، وحجب الإعدادات، وصلاحيات الحذف، والحقول الإضافية، وحماية CSRF بنسبة 100%.
  - التأكد من عدم وجود أي استدعاء لـ `db_connect.php` داخل مجلدي `clients/` و `Products/`.
