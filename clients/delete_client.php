<?php
require_once __DIR__ . '/../includes/auth.php';
require_auth(true);
include("../db_connect.php");

if(isset($_POST['id'])){
    $id = intval($_POST['id']);
    $sql = "DELETE FROM clients WHERE id = $id";

    if(mysqli_query($conn, $sql)){
        echo "success";
    } else {
        echo "error: " . mysqli_error($conn);
    }
}
?>
