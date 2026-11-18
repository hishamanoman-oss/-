<?php
session_start();

$host = "localhost";
$user = "root";
$pass = "";
$db   = "myproject_db";

$conn = mysqli_connect($host, $user, $pass, $db);

if(!$conn){
    echo "فشل الاتصال بقاعدة البيانات";
    exit();
}

$email    = $_POST['email'];
$password = $_POST['password'];

$sql = "SELECT * FROM users WHERE email='$email' AND password='$password'";
$result = mysqli_query($conn, $sql);

if(mysqli_num_rows($result) > 0){
    $_SESSION['email'] = $email;
    // بدل التحويل بالـ header، نرجع نص بسيط
    echo "success";
} else {
    echo "البريد أو كلمة المرور غير صحيحة";
}

mysqli_close($conn);
?>
