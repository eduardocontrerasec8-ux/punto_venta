<?php
require_once 'config/database.php';

if ($_SERVER['REQUEST_METHOD'] == 'POST') {
    $username = $_POST['username'];
    $password = $_POST['password'];
    
    $stmt = $conn->prepare("SELECT * FROM usuario WHERE username = :username");
    $stmt->bindParam(':username', $username);
    $stmt->execute();
    $user = $stmt->fetch(PDO::FETCH_ASSOC);
    
    if ($user && $password === $user['password']) {
        $_SESSION['user_id'] = $user['id'];
        $_SESSION['nombre'] = $user['nombre'];
        $_SESSION['puesto'] = $user['puesto'];
        header('Location: dashboard.php');
        exit();
    } else {
        $error = "Usuario o contraseña incorrectos";
    }
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Punto de Venta</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <style>
        body {
            display: flex;
            min-height: 100vh;
            flex-direction: column;
        }
        main {
            flex: 1 0 auto;
        }
        .login-container {
            margin-top: 100px;
        }
    </style>
</head>
<body>
    <main>
        <div class="container">
            <div class="row">
                <div class="col s12 m6 offset-m3">
                    <div class="card login-container">
                        <div class="card-content">
                            <span class="card-title center-align">PUNTO DE VENTA</span>
                            <?php if(isset($error)): ?>
                                <div class="red-text center-align"><?php echo $error; ?></div>
                            <?php endif; ?>
                            <form method="POST" action="">
                                <div class="input-field">
                                    <i class="material-icons prefix">person</i>
                                    <input id="username" type="text" name="username" required>
                                    <label for="username">Usuario</label>
                                </div>
                                <div class="input-field">
                                    <i class="material-icons prefix">lock</i>
                                    <input id="password" type="password" name="password" required>
                                    <label for="password">Contraseña</label>
                                </div>
                                <div class="center-align">
                                    <button class="btn waves-effect waves-light" type="submit">
                                        Iniciar Sesión
                                        <i class="material-icons right">send</i>
                                    </button>
                                </div>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </main>
    <script src="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/js/materialize.min.js"></script>
</body>
</html>