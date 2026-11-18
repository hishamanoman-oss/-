<?php
session_start();
if(!isset($_SESSION['email'])){
    header("Location: ../Login.php");
    exit();
}
?>
<!DOCTYPE html>
<html lang="ar" dir="rtl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Cairo:wght@400;600;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/products.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <title>المنتجات</title>
</head>
<body>
    <div class="menu">
        <ul>
            <li class="profile">
                <div class="img-box">
                    <img src="../work.jpg" alt="profile">
                </div>
                <h2><?php echo $_SESSION['email']; ?></h2>
            </li>
            <li><a href="../index.php"><i class="fas fa-home"></i><p>لوحة التحكم</p></a></li>
            <li><a href="../clients/clients.php"><i class="fas fa-user-group"></i><p>العملاء</p></a></li>
            <li><a href="products.php"><i class="fas fa-table"></i><p>المنتجات</p></a></li>
            <li><a href="../charts/charts.php"><i class="fas fa-chart-pie"></i><p>الإحصائيات</p></a></li>
            <li><a href="../posts/posts.php"><i class="fas fa-pen"></i><p>المنشورات</p></a></li>
            <li><a href="../favorite/favorite.php"><i class="fas fa-star"></i><p>المفضلة</p></a></li>
            <li><a href="../settings/settings.php"><i class="fas fa-cog"></i><p>الإعدادات</p></a></li>
            <li class="log-out"><a href="../logout.php"><i class="fas fa-sign-out"></i><p>تسجيل الخروج</p></a></li>
        </ul>
    </div>
    <div class="content">
        <div class="title-info">
            <p>المنتجات</p>
            <i class="fas fa-table"></i>
        </div>
        <div class="body">
            <form id="form" method="post" action="products_process.php">
                <div>
                   <label for="name">اسم المنتج</label><br>
                   <input type="text" id="name" name="name" placeholder="أدخل اسم المنتج" required> 
                </div><br>
                <div>
                   <label for="price">السعر</label><br>
                   <input type="text" id="price" name="price" placeholder="أدخل السعر" required>
                </div><br>
                <div>
                   <label for="count">الكمية</label><br>
                   <input type="text" id="count" name="count" placeholder="أدخل الكمية" required>
                </div><br>
                <div>
                   <button type="submit">إضافة منتج</button> 
                </div>   
            </form>
        </div>
        <table id="table">
            <thead>
                <tr>
                    <th>المنتج</th>
                    <th>السعر</th>
                    <th>الكمية</th>
                    <th>الإجراءات</th>
                </tr>
            </thead>
            <tbody>
<?php
include("../db_connect.php");
$sql = "SELECT * FROM products ORDER BY id DESC";
$result = mysqli_query($conn, $sql);

if(mysqli_num_rows($result) > 0){
    while($row = mysqli_fetch_assoc($result)){
        echo "<tr>
                <td>{$row['name']}</td>
                <td>{$row['price']}</td>
                <td>{$row['count']}</td>
                <td class='actions'>
                    <button class='edit' data-id='{$row['id']}'>تعديل</button>
                    <button class='delete' data-id='{$row['id']}'>حذف</button>
                </td>
              </tr>";
    }
} else {
    echo "<tr><td colspan='4'>لا يوجد منتجات بعد</td></tr>";
}
?>
            </tbody>
        </table>
    </div>
    <script src="js/products.js"></script>
</body>
</html>
