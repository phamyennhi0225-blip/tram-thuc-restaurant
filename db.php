<?php
$host = "localhost";
$username = "root"; 
$password = "";     
$dbname = "TramThucRestaurant"; 

try {
    $conn = new PDO("mysql:host=$host;dbname=$dbname;charset=utf8mb4", $username, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Lỗi kết nối cơ sở dữ liệu: " . $e->getMessage());
}
?>
