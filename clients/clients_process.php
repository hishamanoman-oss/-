<?php
include("../db_connect.php");

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $name  = $_POST['name'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];

    $sql = "INSERT INTO clients (name, phone, email) VALUES ('$name', '$phone', '$email')";
    if(mysqli_query($conn, $sql)){
        echo "success";
    } else {
        echo "error: " . mysqli_error($conn);
    }
}
?>
