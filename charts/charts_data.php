<?php
// إخراج بيانات تجريبية (Test Data) كما طلب المستخدم
header('Content-Type: application/json');

echo json_encode([
    "newClients" => [12, 19, 15, 25], // بيانات الأربعة أسابيع الأخيرة
    "topProducts" => [45, 32, 60, 28], // كميات مبيعات المنتجات
    "productLabels" => ["لابتوب", "ماوس", "لوحة مفاتيح", "شاشة"],
    "clientSegments" => [55, 30, 15], // توزيع العملاء (دائم، جديد، VIP)
    "monthlyRevenue" => [100, 150, 120, 180, 210, 250] // إيرادات الـ 6 شهور الأولى
]);
?>
