<?php
$host = "localhost";
$user = "root";
$pass = "";
$db   = "myproject_db";

$conn = mysqli_connect($host, $user, $pass, $db);

if(!$conn){
    die("فشل الاتصال بقاعدة البيانات: " . mysqli_connect_error());
}
?>
