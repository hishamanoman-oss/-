<?php
session_start();

// مسح كل بيانات الجلسة
session_unset();

// إنهاء الجلسة بالكامل
session_destroy();

// إعادة التوجيه لصفحة تسجيل الدخول
header("Location: Login.php");
exit();
?>
