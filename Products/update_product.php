<?php
include("../db_connect.php");

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $id    = intval($_POST['id']);
    $name  = $_POST['name'];
    $price = $_POST['price'];
    $count = $_POST['count'];

    $sql = "UPDATE products SET name='$name', price='$price', count='$count' WHERE id=$id";

    if(mysqli_query($conn, $sql)){
        echo "success";
    } else {
        echo "error: " . mysqli_error($conn);
    }
}
?>
