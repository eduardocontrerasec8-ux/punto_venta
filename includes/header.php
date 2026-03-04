<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema Punto de Venta</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <!-- Navbar -->
    <nav class="blue darken-3">
        <div class="nav-wrapper">
            <div class="container">
                <a href="dashboard.php" class="brand-logo">Punto de Venta</a>
                <a href="#" data-target="mobile-demo" class="sidenav-trigger"><i class="material-icons">menu</i></a>
                <ul class="right hide-on-med-and-down">
                    <li><a href="dashboard.php"><i class="material-icons left">dashboard</i>Dashboard</a></li>
                    <li><a href="ventas.php"><i class="material-icons left">shopping_cart</i>Ventas</a></li>
                    <li><a href="productos.php"><i class="material-icons left">inventory</i>Productos</a></li>
                    <li><a href="proveedores.php"><i class="material-icons left">local_shipping</i>Proveedores</a></li>
                    <li><a href="corte_caja.php"><i class="material-icons left">attach_money</i>Corte</a></li>
                    <li><a href="usuarios.php"><i class="material-icons left">people</i>Usuarios</a></li>
                    <li><a href="logout.php"><i class="material-icons left">exit_to_app</i>Cerrar Sesión</a></li>
                </ul>
            </div>
        </div>
    </nav>

    <!-- Mobile Menu -->
    <ul class="sidenav" id="mobile-demo">
        <li><a href="dashboard.php"><i class="material-icons left">dashboard</i>Dashboard</a></li>
        <li><a href="ventas.php"><i class="material-icons left">shopping_cart</i>Ventas</a></li>
        <li><a href="productos.php"><i class="material-icons left">inventory</i>Productos</a></li>
        <li><a href="proveedores.php"><i class="material-icons left">local_shipping</i>Proveedores</a></li>
        <li><a href="corte_caja.php"><i class="material-icons left">attach_money</i>Corte</a></li>
        <li><a href="usuarios.php"><i class="material-icons left">people</i>Usuarios</a></li>
        <li><a href="logout.php"><i class="material-icons left">exit_to_app</i>Cerrar Sesión</a></li>
    </ul>
    
    <main>