<?php
require_once __DIR__ . '/../includes/security.php';
require_once __DIR__ . '/../includes/auth.php';
require_auth();
$currentUser = current_user();
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="../fonts/cairo.css?v=2">
    <link rel="stylesheet" href="../fontawesome/css/all.min.css?v=2">
    <link rel="stylesheet" href="css/clients.css?v=2">
    <title>العملاء</title>
</head>
<body>
     <div class="menu">
        <ul>
            <li class="profile">
                <div class="img-box">
                    <img src="../work.jpg" alt="profile">
                </div>
                <div>
                    <h2><?php echo e($currentUser['full_name'] ?: $currentUser['email']); ?></h2>
                    <span style="font-size: 11px; color: #00c6ff; display: block;"><?php echo $currentUser['role'] === 'admin' ? 'مدير النظام' : 'موظف مبيعات'; ?></span>
                </div>
            </li>
            <li><a href="../index.php"><i class="fas fa-home"></i><p>لوحة التحكم</p></a></li>
            <li><a href="clients.php"><i class="fas fa-user-group"></i><p>العملاء</p></a></li>
            <li><a href="../Products/products.php"><i class="fas fa-table"></i><p>المنتجات</p></a></li>
            <li><a href="../charts/charts.php"><i class="fas fa-chart-pie"></i><p>الإحصائيات</p></a></li>
            <li><a href="../posts/posts.php"><i class="fas fa-pen"></i><p>المنشورات</p></a></li>
            <li><a href="../favorite/favorite.php"><i class="fas fa-star"></i><p>المفضلة</p></a></li>
            <li><a href="../settings/settings.php"><i class="fas fa-cog"></i><p>الإعدادات</p></a></li>
            <li class="log-out"><a href="../logout.php"><i class="fas fa-sign-out-alt"></i><p>تسجيل الخروج</p></a></li>
        </ul>
    </div>

    <div class="content">
        <div class="title-info">
            <p>العملاء</p>
            <i class="fas fa-user-group"></i>
        </div>
        <div class="body">
           <form id="form" method="post">
            <div class="input1">
                <label for="name">الاسم</label><br>
                <input type="text" id="name" name="name" placeholder="أدخل اسم العميل" required>
            </div>
            <div class="input2">
                <label for="phone">الهاتف</label><br>
                <input type="text" id="phone" name="phone" placeholder="أدخل رقم الهاتف" required>
            </div><br>
            <div class="input3">
                <label for="email">البريد الإلكتروني</label><br>
                <input type="email" id="email" name="email" placeholder="أدخل البريد الإلكتروني">   
            </div><br>
            <div>
                <button type="submit">إضافة عميل</button>
            </div>
           </form>
        </div>
        <table id="table">
            <thead>
                <tr>
                    <th>الاسم</th>
                    <th>الهاتف</th>
                    <th>البريد الإلكتروني</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>
            <tbody>
                <?php
                include("../db_connect.php");
                $sql = "SELECT * FROM clients ORDER BY id DESC";
                $result = mysqli_query($conn, $sql);

                if(mysqli_num_rows($result) > 0){
                    while($row = mysqli_fetch_assoc($result)){
                        echo "<tr>
                                <td>{$row['name']}</td>
                                <td>{$row['phone']}</td>
                                <td>{$row['email']}</td>
                                <td class='actions'>
                                    <button class='edit' data-id='{$row['id']}'>تعديل</button>
                                    <button class='delete' data-id='{$row['id']}'>حذف</button>
                                </td>
                              </tr>";
                    }
                } else {
                    echo "<tr><td colspan='4'>لا يوجد عملاء بعد</td></tr>";
                }
                ?>
            </tbody>
        </table>
    </div>

    <script src="js/clients.js"></script>
</body>
</html>
