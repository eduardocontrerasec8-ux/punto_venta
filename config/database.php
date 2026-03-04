<?php
$host = "dpg-d6jrpravjfc73f2p1og-a.oregon-postgres.render.com";
$port = "5432";
$dbname = "puntoventa_u3ov";
$user = "adminpv";
$password = "gCyxuddd846AjN7zzLfamq7WCpQK4611";

try {
    $conn = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}

session_start();
