<?php
include("../db_connect.php");

if($_SERVER['REQUEST_METHOD'] === 'POST'){
    $id    = intval($_POST['id']);
    $name  = $_POST['name'];
    $phone = $_POST['phone'];
    $email = $_POST['email'];

    $sql = "UPDATE clients SET name='$name', phone='$phone', email='$email' WHERE id=$id";

    if(mysqli_query($conn, $sql)){
        echo "success";
    } else {
        echo "error: " . mysqli_error($conn);
    }
}
?>
