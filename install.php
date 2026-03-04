<?php
// Archivo de instalación del sistema
if (file_exists('config/database.php')) {
    die('El sistema ya está instalado. Elimine este archivo (install.php) por seguridad.');
}

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $host = $_POST['host'];
    $port = $_POST['port'];
    $dbname = $_POST['dbname'];
    $user = $_POST['user'];
    $password = $_POST['password'];
    
    // Probar conexión
    try {
        $conn = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
        $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        
        // Crear archivo de configuración
        $config_content = '<?php
$host = "' . $host . '";
$port = "' . $port . '";
$dbname = "' . $dbname . '";
$user = "' . $user . '";
$password = "' . $password . '";

try {
    $conn = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
    $conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch(PDOException $e) {
    die("Error de conexión: " . $e->getMessage());
}
session_start();
?>';
        
        // Crear directorio config si no existe
        if (!is_dir('config')) {
            mkdir('config', 0755);
        }
        
        file_put_contents('config/database.php', $config_content);
        
        // Ejecutar script SQL
        $sql = file_get_contents('database_schema.sql');
        $conn->exec($sql);
        
        $success = "Instalación completada exitosamente. Elimine este archivo (install.php) por seguridad.";
    } catch (PDOException $e) {
        $error = "Error de conexión: " . $e->getMessage();
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <title>Instalación - Punto de Venta</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
    <style>
        body { padding: 20px; }
        .container { max-width: 600px; }
    </style>
</head>
<body>
    <div class="container">
        <h4 class="center-align">Instalación del Sistema Punto de Venta</h4>
        
        <?php if(isset($success)): ?>
            <div class="card green lighten-4">
                <div class="card-content green-text">
                    <?php echo $success; ?>
                    <p><a href="login.php" class="btn green">Ir al Login</a></p>
                </div>
            </div>
        <?php else: ?>
            <div class="card">
                <div class="card-content">
                    <span class="card-title">Configuración de Base de Datos</span>
                    
                    <?php if(isset($error)): ?>
                        <div class="red-text"><?php echo $error; ?></div>
                    <?php endif; ?>
                    
                    <form method="POST">
                        <div class="input-field">
                            <input id="host" type="text" name="host" value="localhost" required>
                            <label for="host">Servidor PostgreSQL</label>
                        </div>
                        
                        <div class="input-field">
                            <input id="port" type="text" name="port" value="5432" required>
                            <label for="port">Puerto</label>
                        </div>
                        
                        <div class="input-field">
                            <input id="dbname" type="text" name="dbname" value="punto_de_venta" required>
                            <label for="dbname">Nombre de la Base de Datos</label>
                        </div>
                        
                        <div class="input-field">
                            <input id="user" type="text" name="user" value="postgres" required>
                            <label for="user">Usuario</label>
                        </div>
                        
                        <div class="input-field">
                            <input id="password" type="password" name="password">
                            <label for="password">Contraseña</label>
                        </div>
                        
                        <div class="center-align">
                            <button class="btn-large waves-effect waves-light blue" type="submit">
                                Instalar Sistema
                            </button>
                        </div>
                    </form>
                    
                    <p class="grey-text">
                        <strong>Nota:</strong> Asegúrese de que PostgreSQL esté instalado y funcionando.
                        El script creará la base de datos y las tablas necesarias.
                    </p>
                </div>
            </div>
        <?php endif; ?>
    </div>
</body>
</html>