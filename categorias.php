<?php
require_once 'config/database.php';
require_once 'includes/header.php';

// Agregar categoría
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['agregar_categoria'])) {
    $nombre = $_POST['nombre'];
    $descripcion = $_POST['descripcion'];
    
    $stmt = $conn->prepare("INSERT INTO categoria (nombre, descripcion) VALUES (:nombre, :descripcion)");
    $stmt->execute([':nombre' => $nombre, ':descripcion' => $descripcion]);
    
    header('Location: productos.php');
    exit();
}

// Eliminar categoría
if (isset($_GET['eliminar'])) {
    $id = $_GET['eliminar'];
    
    // Verificar si hay productos en esta categoría
    $stmt = $conn->prepare("SELECT COUNT(*) as total FROM producto WHERE categoria_id = :id");
    $stmt->execute([':id' => $id]);
    $resultado = $stmt->fetch();
    
    if ($resultado['total'] == 0) {
        $stmt = $conn->prepare("DELETE FROM categoria WHERE id = :id");
        $stmt->execute([':id' => $id]);
    }
    
    header('Location: productos.php');
    exit();
}

// Obtener categorías
$categorias = $conn->query("SELECT * FROM categoria ORDER BY nombre")->fetchAll();
?>
<!-- El contenido de categorías se maneja desde productos.php -->
<?php require_once 'includes/footer.php'; ?>