<?php
$host = "localhost";
$port = "5432";
$dbname = "punto_de_venta";
$user = "postgres";
$password = "24agosto";

try {
    $conn = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
session_start();
?>