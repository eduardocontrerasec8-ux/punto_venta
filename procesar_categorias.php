<?php
require_once 'config/database.php';

session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

$mensaje = '';
$tipo_mensaje = '';

// Agregar categoría
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['agregar_categoria'])) {
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']) ?: null;
    
    try {
        // Verificar si la categoría ya existe
        $stmt_check = $conn->prepare("SELECT COUNT(*) as total FROM categoria WHERE LOWER(nombre) = LOWER(:nombre)");
        $stmt_check->execute([':nombre' => $nombre]);
        $resultado = $stmt_check->fetch(PDO::FETCH_ASSOC);
        
        if ($resultado['total'] > 0) {
            $mensaje = 'Error: La categoría "' . htmlspecialchars($nombre) . '" ya existe';
            $tipo_mensaje = 'error';
        } else {
            $stmt = $conn->prepare("INSERT INTO categoria (nombre, descripcion) VALUES (:nombre, :descripcion)");
            $stmt->execute([
                ':nombre' => $nombre,
                ':descripcion' => $descripcion
            ]);
            
            $mensaje = 'Categoría agregada correctamente';
            $tipo_mensaje = 'success';
        }
    } catch (PDOException $e) {
        $mensaje = 'Error al agregar categoría: ' . $e->getMessage();
        $tipo_mensaje = 'error';
    }
}

// Editar categoría
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['editar_categoria'])) {
    $id = $_POST['id'];
    $nombre = trim($_POST['nombre']);
    $descripcion = trim($_POST['descripcion']) ?: null;
    
    try {
        // Verificar si la categoría ya existe (excluyendo la actual)
        $stmt_check = $conn->prepare("SELECT COUNT(*) as total FROM categoria 
                                     WHERE LOWER(nombre) = LOWER(:nombre) AND id != :id");
        $stmt_check->execute([
            ':nombre' => $nombre,
            ':id' => $id
        ]);
        $resultado = $stmt_check->fetch(PDO::FETCH_ASSOC);
        
        if ($resultado['total'] > 0) {
            $mensaje = 'Error: La categoría "' . htmlspecialchars($nombre) . '" ya existe';
            $tipo_mensaje = 'error';
        } else {
            $stmt = $conn->prepare("UPDATE categoria SET nombre = :nombre, descripcion = :descripcion 
                                   WHERE id = :id");
            $stmt->execute([
                ':id' => $id,
                ':nombre' => $nombre,
                ':descripcion' => $descripcion
            ]);
            
            $mensaje = 'Categoría actualizada correctamente';
            $tipo_mensaje = 'success';
        }
    } catch (PDOException $e) {
        $mensaje = 'Error al actualizar categoría: ' . $e->getMessage();
        $tipo_mensaje = 'error';
    }
}

// Eliminar categoría
if (isset($_GET['eliminar'])) {
    $id = $_GET['eliminar'];
    
    try {
        // Primero, establecer productos con esta categoría a NULL
        $stmt_update = $conn->prepare("UPDATE producto SET categoria_id = NULL WHERE categoria_id = :id");
        $stmt_update->execute([':id' => $id]);
        
        // Luego eliminar la categoría
        $stmt_delete = $conn->prepare("DELETE FROM categoria WHERE id = :id");
        $stmt_delete->execute([':id' => $id]);
        
        $mensaje = 'Categoría eliminada correctamente. Los productos ahora están sin categoría.';
        $tipo_mensaje = 'success';
    } catch (PDOException $e) {
        $mensaje = 'Error al eliminar categoría: ' . $e->getMessage();
        $tipo_mensaje = 'error';
    }
}

// Redirigir de vuelta a productos.php con el mensaje
header('Location: productos.php?categoria_mensaje=' . urlencode($mensaje) . '&categoria_tipo=' . $tipo_mensaje . '#categorias');
exit();