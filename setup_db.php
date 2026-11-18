<?php
$servername = "localhost";
$username = "root";
$password = "";

// Create connection
$conn = new mysqli($servername, $username, $password);

// Check connection
if ($conn->connect_error) {
  die("Connection failed: " . $conn->connect_error);
}

// Create database
$sql = "CREATE DATABASE IF NOT EXISTS myproject_db";
if ($conn->query($sql) === TRUE) {
  echo "Database created successfully\n";
} else {
  echo "Error creating database: " . $conn->error . "\n";
}

$conn->select_db("myproject_db");

// Create table users
$sql = "CREATE TABLE IF NOT EXISTS users (
id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
email VARCHAR(50) NOT NULL,
password VARCHAR(50) NOT NULL
)";
if ($conn->query($sql) === TRUE) {
  echo "Table users created successfully\n";
} else {
  echo "Error creating table users: " . $conn->error . "\n";
}

// Create table clients
$sql = "CREATE TABLE IF NOT EXISTS clients (
id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
name VARCHAR(100) NOT NULL,
phone VARCHAR(50) NOT NULL,
email VARCHAR(100) NOT NULL
)";
if ($conn->query($sql) === TRUE) {
  echo "Table clients created successfully\n";
} else {
  echo "Error creating table clients: " . $conn->error . "\n";
}

// Create table products
$sql = "CREATE TABLE IF NOT EXISTS products (
id INT(6) UNSIGNED AUTO_INCREMENT PRIMARY KEY,
name VARCHAR(100) NOT NULL,
price DECIMAL(10,2) NOT NULL,
count INT(11) NOT NULL
)";
if ($conn->query($sql) === TRUE) {
  echo "Table products created successfully\n";
} else {
  echo "Error creating table products: " . $conn->error . "\n";
}

// Insert test user if not exists
$email = "hisham@gmail.com"; 
$pass = "123456"; 

$check = "SELECT * FROM users WHERE email='$email'";
$result = $conn->query($check);

if ($result->num_rows == 0) {
    $sql = "INSERT INTO users (email, password) VALUES ('$email', '$pass')";
    if ($conn->query($sql) === TRUE) {
        echo "New record created successfully. Email: $email, Password: $pass\n";
    } else {
        echo "Error: " . $sql . "\n" . $conn->error;
    }
} else {
    echo "User already exists.\n";
}

// Insert sample client if empty
$checkClients = "SELECT * FROM clients";
$resClients = $conn->query($checkClients);
if ($resClients && $resClients->num_rows == 0) {
    $conn->query("INSERT INTO clients (name, phone, email) VALUES ('أحمد علي', '0551234567', 'ahmed@example.com')");
    echo "Sample client created.\n";
}

// Insert sample product if empty
$checkProducts = "SELECT * FROM products";
$resProducts = $conn->query($checkProducts);
if ($resProducts && $resProducts->num_rows == 0) {
    $conn->query("INSERT INTO products (name, price, count) VALUES ('لابتوب ديل', 3500.00, 10)");
    echo "Sample product created.\n";
}

$conn->close();
?>
