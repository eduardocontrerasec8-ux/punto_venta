<?php
require_once 'config/database.php';
require_once 'includes/header.php';

// Verificar permisos
if ($_SESSION['puesto'] != 'Administrador') {
    echo "<script>alert('No tienes permisos para gestionar proveedores'); window.location.href='dashboard.php';</script>";
    exit();
}

// Agregar proveedor
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['agregar_proveedor'])) {
    $nombre = $_POST['nombre'];
    $telefono = $_POST['telefono'];
    $correo = $_POST['correo'];
    $direccion = $_POST['direccion'];
    $condiciones = $_POST['condiciones'];
    
    $stmt = $conn->prepare("INSERT INTO proveedor (nombre, telefono, correo, direccion, condiciones) 
                            VALUES (:nombre, :telefono, :correo, :direccion, :condiciones)");
    $stmt->execute([
        ':nombre' => $nombre,
        ':telefono' => $telefono,
        ':correo' => $correo,
        ':direccion' => $direccion,
        ':condiciones' => $condiciones
    ]);
    
    $mensaje = "Proveedor agregado exitosamente";
}

// Editar proveedor
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['editar_proveedor'])) {
    $id = $_POST['id'];
    $nombre = $_POST['nombre'];
    $telefono = $_POST['telefono'];
    $correo = $_POST['correo'];
    $direccion = $_POST['direccion'];
    $condiciones = $_POST['condiciones'];
    
    $stmt = $conn->prepare("UPDATE proveedor SET nombre = :nombre, telefono = :telefono, 
                           correo = :correo, direccion = :direccion, condiciones = :condiciones 
                           WHERE id = :id");
    $stmt->execute([
        ':id' => $id,
        ':nombre' => $nombre,
        ':telefono' => $telefono,
        ':correo' => $correo,
        ':direccion' => $direccion,
        ':condiciones' => $condiciones
    ]);
    
    $mensaje = "Proveedor actualizado exitosamente";
}

// Eliminar proveedor
if (isset($_GET['eliminar'])) {
    $id = $_GET['eliminar'];
    
    // Verificar si tiene productos asociados
    $stmt = $conn->prepare("SELECT COUNT(*) FROM producto WHERE proveedor_id = :id");
    $stmt->execute([':id' => $id]);
    $count = $stmt->fetchColumn();
    
    if ($count > 0) {
        $error = "No se puede eliminar el proveedor porque tiene productos asociados. Primero elimine o reasigne los productos.";
    } else {
        $stmt = $conn->prepare("DELETE FROM proveedor WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $mensaje = "Proveedor eliminado exitosamente";
    }
}

// Obtener proveedores
$proveedores = $conn->query("SELECT * FROM proveedor ORDER BY nombre")->fetchAll();

// Obtener un proveedor para editar
$proveedor_editar = null;
if (isset($_GET['editar'])) {
    $stmt = $conn->prepare("SELECT * FROM proveedor WHERE id = :id");
    $stmt->execute([':id' => $_GET['editar']]);
    $proveedor_editar = $stmt->fetch();
}
?>
<div class="container">
    <h4>Gestión de Proveedores</h4>
    
    <?php if(isset($mensaje)): ?>
        <div class="card green lighten-4">
            <div class="card-content green-text">
                <i class="material-icons left">check_circle</i>
                <?php echo $mensaje; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if(isset($error)): ?>
        <div class="card red lighten-4">
            <div class="card-content red-text">
                <i class="material-icons left">error</i>
                <?php echo $error; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Formulario -->
        <div class="col s12 m6">
            <div class="card">
                <div class="card-content">
                    <span class="card-title"><?php echo $proveedor_editar ? 'Editar' : 'Agregar'; ?> Proveedor</span>
                    
                    <form method="POST">
                        <?php if($proveedor_editar): ?>
                            <input type="hidden" name="id" value="<?php echo $proveedor_editar['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="input-field">
                            <input id="nombre" type="text" name="nombre" 
                                   value="<?php echo $proveedor_editar['nombre'] ?? ''; ?>" required>
                            <label for="nombre">Nombre del Proveedor</label>
                        </div>
                        
                        <div class="row">
                            <div class="input-field col s6">
                                <input id="telefono" type="tel" name="telefono" 
                                       value="<?php echo $proveedor_editar['telefono'] ?? ''; ?>">
                                <label for="telefono">Teléfono</label>
                            </div>
                            <div class="input-field col s6">
                                <input id="correo" type="email" name="correo" 
                                       value="<?php echo $proveedor_editar['correo'] ?? ''; ?>">
                                <label for="correo">Correo Electrónico</label>
                            </div>
                        </div>
                        
                        <div class="input-field">
                            <textarea id="direccion" name="direccion" class="materialize-textarea"><?php echo $proveedor_editar['direccion'] ?? ''; ?></textarea>
                            <label for="direccion">Dirección</label>
                        </div>
                        
                        <div class="input-field">
                            <textarea id="condiciones" name="condiciones" class="materialize-textarea"><?php echo $proveedor_editar['condiciones'] ?? ''; ?></textarea>
                            <label for="condiciones">Condiciones Comerciales</label>
                        </div>
                        
                        <div class="center-align">
                            <?php if($proveedor_editar): ?>
                                <button class="btn waves-effect waves-light orange" type="submit" name="editar_proveedor">
                                    <i class="material-icons left">edit</i>
                                    Actualizar
                                </button>
                                <a href="proveedores.php" class="btn grey waves-effect">
                                    <i class="material-icons left">cancel</i>
                                    Cancelar
                                </a>
                            <?php else: ?>
                                <button class="btn waves-effect waves-light blue" type="submit" name="agregar_proveedor">
                                    <i class="material-icons left">add</i>
                                    Agregar Proveedor
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Lista de proveedores -->
        <div class="col s12 m6">
            <div class="card">
                <div class="card-content">
                    <span class="card-title">Lista de Proveedores</span>
                    
                    <table class="striped">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Teléfono</th>
                                <th>Productos</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($proveedores as $proveedor): ?>
                                <tr>
                                    <td><?php echo htmlspecialchars($proveedor['nombre']); ?></td>
                                    <td><?php echo $proveedor['telefono']; ?></td>
                                    <td>
                                        <?php 
                                        $stmt = $conn->prepare("SELECT COUNT(*) FROM producto WHERE proveedor_id = :id");
                                        $stmt->execute([':id' => $proveedor['id']]);
                                        echo $stmt->fetchColumn();
                                        ?>
                                    </td>
                                    <td>
                                        <a href="proveedores.php?editar=<?php echo $proveedor['id']; ?>" 
                                           class="btn btn-small orange">
                                            <i class="material-icons">edit</i>
                                        </a>
                                        <a href="proveedores.php?eliminar=<?php echo $proveedor['id']; ?>" 
                                           class="btn btn-small red"
                                           onclick="return confirm('¿Eliminar este proveedor?')">
                                            <i class="material-icons">delete</i>
                                        </a>
                                        <a href="proveedor_detalle.php?id=<?php echo $proveedor['id']; ?>" 
                                           class="btn btn-small blue">
                                            <i class="material-icons">visibility</i>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>