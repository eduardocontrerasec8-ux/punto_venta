<?php
require_once 'config/database.php';
require_once 'includes/header.php';

// Verificar permisos (solo administrador)
if ($_SESSION['puesto'] != 'Administrador') {
    echo "<script>alert('No tienes permisos para gestionar usuarios'); window.location.href='dashboard.php';</script>";
    exit();
}

// Agregar usuario
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['agregar_usuario'])) {
    $nombre = $_POST['nombre'];
    $puesto = $_POST['puesto'];
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    // Verificar si el username ya existe
    $stmt = $conn->prepare("SELECT COUNT(*) FROM usuario WHERE username = :username");
    $stmt->execute([':username' => $username]);
    if ($stmt->fetchColumn() > 0) {
        $error = "El nombre de usuario ya está registrado";
    } else {
        $stmt = $conn->prepare("INSERT INTO usuario (nombre, puesto, username, password) 
                               VALUES (:nombre, :puesto, :username, :password)");
        $stmt->execute([
            ':nombre' => $nombre,
            ':puesto' => $puesto,
            ':username' => $username,
            ':password' => $password
        ]);
        
        $mensaje = "Usuario agregado exitosamente";
    }
}

// Editar usuario
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['editar_usuario'])) {
    $id = $_POST['id'];
    $nombre = $_POST['nombre'];
    $puesto = $_POST['puesto'];
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    // Construir consulta dinámica
    if (!empty($password)) {
        $sql = "UPDATE usuario SET nombre = :nombre, puesto = :puesto, 
                username = :username, password = :password WHERE id = :id";
        $params = [
            ':id' => $id,
            ':nombre' => $nombre,
            ':puesto' => $puesto,
            ':username' => $username,
            ':password' => $password
        ];
    } else {
        $sql = "UPDATE usuario SET nombre = :nombre, puesto = :puesto, 
                username = :username WHERE id = :id";
        $params = [
            ':id' => $id,
            ':nombre' => $nombre,
            ':puesto' => $puesto,
            ':username' => $username
        ];
    }
    
    $stmt = $conn->prepare($sql);
    $stmt->execute($params);
    
    $mensaje = "Usuario actualizado exitosamente";
}

// Eliminar usuario
if (isset($_GET['eliminar'])) {
    $id = $_GET['eliminar'];
    
    // No permitir eliminar al propio usuario
    if ($id == $_SESSION['user_id']) {
        $error = "No puedes eliminar tu propio usuario";
    } else {
        $stmt = $conn->prepare("DELETE FROM usuario WHERE id = :id");
        $stmt->execute([':id' => $id]);
        $mensaje = "Usuario eliminado exitosamente";
    }
}

// Obtener usuarios
$usuarios = $conn->query("SELECT * FROM usuario ORDER BY puesto, nombre")->fetchAll();

// Obtener un usuario para editar
$usuario_editar = null;
if (isset($_GET['editar'])) {
    $stmt = $conn->prepare("SELECT * FROM usuario WHERE id = :id");
    $stmt->execute([':id' => $_GET['editar']]);
    $usuario_editar = $stmt->fetch();
}
?>
<div class="container">
    <h4>Gestión de Usuarios</h4>
    
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
        <div class="col s12 m5">
            <div class="card">
                <div class="card-content">
                    <span class="card-title"><?php echo $usuario_editar ? 'Editar' : 'Nuevo'; ?> Usuario</span>
                    
                    <form method="POST">
                        <?php if($usuario_editar): ?>
                            <input type="hidden" name="id" value="<?php echo $usuario_editar['id']; ?>">
                        <?php endif; ?>
                        
                        <div class="input-field">
                            <input id="nombre" type="text" name="nombre" 
                                   value="<?php echo $usuario_editar['nombre'] ?? ''; ?>" required>
                            <label for="nombre">Nombre Completo</label>
                        </div>
                        
                        <div class="input-field">
                            <select name="puesto" required>
                                <option value="" disabled <?php echo !isset($usuario_editar) ? 'selected' : ''; ?>>Seleccionar puesto</option>
                                <option value="Administrador" <?php echo ($usuario_editar['puesto'] ?? '') == 'Administrador' ? 'selected' : ''; ?>>Administrador</option>
                                <option value="Operador" <?php echo ($usuario_editar['puesto'] ?? '') == 'Operador' ? 'selected' : ''; ?>>Operador</option>
                                <option value="Solo lectura" <?php echo ($usuario_editar['puesto'] ?? '') == 'Solo lectura' ? 'selected' : ''; ?>>Solo lectura</option>
                            </select>
                            <label>Puesto</label>
                        </div>
                        
                        <div class="input-field">
                            <input id="username" type="text" name="username" 
                                   value="<?php echo $usuario_editar['username'] ?? ''; ?>" required>
                            <label for="username">Nombre de Usuario</label>
                        </div>
                        
                        <div class="input-field">
                            <input id="password" type="password" name="password" 
                                   <?php echo !isset($usuario_editar) ? 'required' : 'placeholder="Dejar vacío para no cambiar"'; ?>>
                            <label for="password"><?php echo $usuario_editar ? 'Nueva Contraseña' : 'Contraseña'; ?></label>
                        </div>
                        
                        <div class="center-align">
                            <?php if($usuario_editar): ?>
                                <button class="btn waves-effect waves-light orange" type="submit" name="editar_usuario">
                                    <i class="material-icons left">save</i>
                                    Actualizar
                                </button>
                                <a href="usuarios.php" class="btn grey waves-effect">
                                    <i class="material-icons left">cancel</i>
                                    Cancelar
                                </a>
                            <?php else: ?>
                                <button class="btn waves-effect waves-light blue" type="submit" name="agregar_usuario">
                                    <i class="material-icons left">person_add</i>
                                    Agregar Usuario
                                </button>
                            <?php endif; ?>
                        </div>
                    </form>
                </div>
            </div>
            
            <!-- Estadísticas -->
            <div class="card">
                <div class="card-content">
                    <span class="card-title">Estadísticas</span>
                    <p>Total de Usuarios: <strong><?php echo count($usuarios); ?></strong></p>
                    <p>Administradores: 
                        <strong><?php echo count(array_filter($usuarios, function($u) { return $u['puesto'] == 'Administrador'; })); ?></strong>
                    </p>
                    <p>Operadores: 
                        <strong><?php echo count(array_filter($usuarios, function($u) { return $u['puesto'] == 'Operador'; })); ?></strong>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Lista de usuarios -->
        <div class="col s12 m7">
            <div class="card">
                <div class="card-content">
                    <span class="card-title">Usuarios Registrados</span>
                    
                    <table class="striped responsive-table">
                        <thead>
                            <tr>
                                <th>Nombre</th>
                                <th>Puesto</th>
                                <th>Usuario</th>
                                <th>Registro</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($usuarios as $usuario): ?>
                                <tr class="<?php echo $usuario['id'] == $_SESSION['user_id'] ? 'blue lighten-5' : ''; ?>">
                                    <td>
                                        <?php echo htmlspecialchars($usuario['nombre']); ?>
                                        <?php if($usuario['id'] == $_SESSION['user_id']): ?>
                                            <span class="badge blue white-text">Tú</span>
                                        <?php endif; ?>
                                    </td>
                                    <td>
                                        <?php 
                                        $color = 'grey';
                                        if ($usuario['puesto'] == 'Administrador') $color = 'red';
                                        if ($usuario['puesto'] == 'Operador') $color = 'green';
                                        ?>
                                        <span class="badge <?php echo $color; ?> white-text"><?php echo $usuario['puesto']; ?></span>
                                    </td>
                                    <td><?php echo $usuario['username']; ?></td>
                                    <td><?php echo date('d/m/Y', strtotime($usuario['created_at'] ?? 'now')); ?></td>
                                    <td>
                                        <a href="usuarios.php?editar=<?php echo $usuario['id']; ?>" 
                                           class="btn btn-small orange">
                                            <i class="material-icons">edit</i>
                                        </a>
                                        <?php if($usuario['id'] != $_SESSION['user_id']): ?>
                                            <a href="usuarios.php?eliminar=<?php echo $usuario['id']; ?>" 
                                               class="btn btn-small red"
                                               onclick="return confirm('¿Eliminar este usuario?')">
                                                <i class="material-icons">delete</i>
                                            </a>
                                        <?php endif; ?>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
            
            <!-- Permisos por puesto -->
            <div class="card">
                <div class="card-content">
                    <span class="card-title">Niveles de Acceso</span>
                    
                    <ul class="collection">
                        <li class="collection-item">
                            <strong>Administrador:</strong> Acceso completo a todas las funciones del sistema
                        </li>
                        <li class="collection-item">
                            <strong>Operador:</strong> Puede realizar ventas, ver productos y proveedores
                        </li>
                        <li class="collection-item">
                            <strong>Solo lectura:</strong> Solo puede ver información, no puede realizar modificaciones
                        </li>
                    </ul>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    M.FormSelect.init(document.querySelectorAll('select'));
});
</script>

<?php require_once 'includes/footer.php'; ?>