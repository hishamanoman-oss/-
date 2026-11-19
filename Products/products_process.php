<?php
require_once __DIR__ . '/../includes/auth.php';
require_auth(true);
include("../db_connect.php");

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $name  = $_POST['name'];
    $price = $_POST['price'];
    $count = $_POST['count'];

    $sql = "INSERT INTO products (name, price, count) VALUES ('$name', '$price', '$count')";
    if(mysqli_query($conn, $sql)){
        echo "success";
    } else {
        echo "error: " . mysqli_error($conn);
    }
}
?>
