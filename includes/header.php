<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Sistema Punto de Venta</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/materialize/1.0.0/css/materialize.min.css">
    <link href="https://fonts.googleapis.com/icon?family=Material+Icons" rel="stylesheet">
    <link href="https://fonts.googleapis.com/css2?family=Nunito:wght@600;700;800;900&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="css/styles.css">
</head>
<body>
    <?php
    $sesion_activa = $conn->prepare("SELECT * FROM sesion_caja WHERE usuario_id = :user_id AND estatus = 'abierta'");
    $sesion_activa->execute([':user_id' => $_SESSION['user_id']]);
    $sesion_activa_result = $sesion_activa->fetch();
    $caja_abierta = (bool)$sesion_activa_result;

    // Detectar página activa
    $pagina_actual = basename($_SERVER['PHP_SELF']);

    $nav_items = [
        ['url' => 'dashboard.php',  'icon' => 'dashboard',      'label' => 'Dashboard'],
        ['url' => 'ventas.php',     'icon' => 'point_of_sale',   'label' => 'Ventas'],
        ['url' => 'productos.php',  'icon' => 'inventory_2',     'label' => 'Productos'],
        ['url' => 'proveedores.php','icon' => 'local_shipping',  'label' => 'Proveedores'],
        ['url' => 'corte_caja.php', 'icon' => 'receipt_long',    'label' => 'Corte'],
        ['url' => 'usuarios.php',   'icon' => 'manage_accounts', 'label' => 'Usuarios'],
    ];
    ?>

    <style>
        /* ── Variables ── */
        :root {
            --nav-h: 62px;
            --azul1: #0d47a1;
            --azul2: #1565c0;
            --azul3: #1976d2;
            --azul4: #42a5f5;
            --font:  'Nunito', sans-serif;
        }

        body {
            font-family: var(--font);
            background: #f0f4ff;
        }

        /* ── Navbar principal ── */
        nav.pos-nav {
            background: linear-gradient(135deg, var(--azul1) 0%, var(--azul2) 55%, var(--azul3) 100%);
            height: var(--nav-h);
            line-height: var(--nav-h);
            box-shadow: 0 4px 18px rgba(13, 71, 161, .45);
            position: sticky;
            top: 0;
            z-index: 1000;
        }

        nav.pos-nav .nav-wrapper {
            padding: 0 20px;
        }

        /* Logo / Brand */
        nav.pos-nav .brand-logo {
            font-family: var(--font);
            font-weight: 900;
            font-size: 1.15rem;
            letter-spacing: .03em;
            color: #fff;
            display: flex;
            align-items: center;
            gap: 10px;
            height: var(--nav-h);
            padding: 0;
        }

        nav.pos-nav .brand-logo .logo-icon {
            width: 36px;
            height: 36px;
            background: rgba(255, 255, 255, .2);
            border-radius: 10px;
            display: flex;
            align-items: center;
            justify-content: center;
            border: 1px solid rgba(255, 255, 255, .25);
            flex-shrink: 0;
        }

        nav.pos-nav .brand-logo .logo-icon i {
            font-size: 1.2rem;
            line-height: 1;
        }

        nav.pos-nav .brand-logo .logo-text span {
            display: block;
            font-size: .6rem;
            font-weight: 600;
            opacity: .65;
            letter-spacing: .08em;
            text-transform: uppercase;
            line-height: 1;
            margin-top: -2px;
        }

        /* Links de navegación */
        nav.pos-nav ul.right li a {
            font-family: var(--font);
            font-size: .78rem;
            font-weight: 700;
            color: rgba(255, 255, 255, .78);
            padding: 0 11px;
            height: var(--nav-h);
            line-height: var(--nav-h);
            display: flex;
            align-items: center;
            gap: 5px;
            border-bottom: 3px solid transparent;
            transition: all .18s;
            letter-spacing: .02em;
        }

        nav.pos-nav ul.right li a i {
            font-size: 1rem;
        }

        nav.pos-nav ul.right li a:hover {
            color: #fff;
            background: rgba(255, 255, 255, .1);
            border-bottom-color: rgba(255, 255, 255, .4);
        }

        nav.pos-nav ul.right li a.nav-active {
            color: #fff;
            background: rgba(255, 255, 255, .15);
            border-bottom-color: #fff;
        }

        /* Badge caja */
        nav.pos-nav .caja-badge {
            display: flex;
            align-items: center;
            gap: 7px;
            padding: 0 13px;
            height: var(--nav-h);
            font-size: .77rem;
            font-weight: 800;
            font-family: var(--font);
            cursor: pointer;
            border-bottom: 3px solid transparent;
            transition: all .18s;
            text-decoration: none;
        }

        nav.pos-nav .caja-badge .cb-inner {
            display: flex;
            align-items: center;
            gap: 6px;
            background: rgba(255, 255, 255, .15);
            border: 1px solid rgba(255, 255, 255, .25);
            border-radius: 20px;
            padding: 4px 12px 4px 8px;
            transition: .18s;
        }

        nav.pos-nav .caja-badge:hover .cb-inner {
            background: rgba(255, 255, 255, .28);
        }

        nav.pos-nav .caja-badge .cb-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        nav.pos-nav .caja-badge.abierta .cb-dot {
            background: #69f0ae;
            box-shadow: 0 0 6px rgba(105, 240, 174, .7);
            animation: pulse-dot 2s infinite;
        }

        nav.pos-nav .caja-badge.cerrada .cb-dot {
            background: #ef9a9a;
        }

        nav.pos-nav .caja-badge .cb-text {
            color: #fff;
            font-family: var(--font);
            letter-spacing: .03em;
        }

        @keyframes pulse-dot {
            0%, 100% { opacity: 1; transform: scale(1); }
            50%       { opacity: .5; transform: scale(.8); }
        }

        /* Botón cerrar sesión */
        nav.pos-nav .btn-logout {
            display: flex;
            align-items: center;
            gap: 5px;
            margin: 0 4px 0 6px;
            background: rgba(255, 255, 255, .12);
            border: 1.5px solid rgba(255, 255, 255, .25);
            border-radius: 8px;
            color: rgba(255, 255, 255, .85);
            font-family: var(--font);
            font-size: .77rem;
            font-weight: 700;
            padding: 6px 12px;
            cursor: pointer;
            text-decoration: none;
            transition: .16s;
            height: 34px;
            line-height: 1;
            align-self: center;
        }

        nav.pos-nav .btn-logout i { font-size: .9rem; }

        nav.pos-nav .btn-logout:hover {
            background: rgba(255, 255, 255, .25);
            color: #fff;
        }

        /* ── Sidenav móvil ── */
        .sidenav {
            background: linear-gradient(180deg, var(--azul1) 0%, var(--azul2) 100%);
        }

        .sidenav .sidenav-header {
            padding: 20px 18px 14px;
            border-bottom: 1px solid rgba(255, 255, 255, .15);
            margin-bottom: 6px;
        }

        .sidenav .sidenav-header .sh-brand {
            display: flex;
            align-items: center;
            gap: 10px;
        }

        .sidenav .sidenav-header .sh-brand .sh-icon {
            width: 38px; height: 38px;
            background: rgba(255, 255, 255, .2);
            border-radius: 10px;
            display: flex; align-items: center; justify-content: center;
        }

        .sidenav .sidenav-header .sh-brand .sh-icon i { color: #fff; font-size: 1.3rem; }

        .sidenav .sidenav-header .sh-brand .sh-text {
            color: #fff;
            font-family: var(--font);
            font-weight: 900;
            font-size: 1rem;
        }

        .sidenav .sidenav-header .sh-brand .sh-text span {
            display: block;
            font-size: .65rem;
            opacity: .6;
            font-weight: 600;
        }

        .sidenav li a {
            color: rgba(255, 255, 255, .8) !important;
            font-family: var(--font) !important;
            font-weight: 700 !important;
            font-size: .9rem !important;
            display: flex !important;
            align-items: center !important;
            gap: 10px !important;
            padding: 0 18px !important;
            border-left: 3px solid transparent;
            transition: all .15s !important;
        }

        .sidenav li a i {
            color: rgba(255, 255, 255, .6) !important;
            font-size: 1.1rem !important;
            margin: 0 !important;
        }

        .sidenav li a:hover {
            background: rgba(255, 255, 255, .12) !important;
            color: #fff !important;
            border-left-color: rgba(255, 255, 255, .5);
        }

        .sidenav li a.active-side {
            background: rgba(255, 255, 255, .18) !important;
            color: #fff !important;
            border-left-color: #fff;
        }

        .sidenav li a.active-side i { color: #fff !important; }

        .sidenav .caja-side {
            margin: 8px 14px;
            background: rgba(255, 255, 255, .12);
            border: 1px solid rgba(255, 255, 255, .2);
            border-radius: 10px;
            padding: 10px 14px !important;
            height: auto !important;
            line-height: 1.4 !important;
            flex-direction: column !important;
            align-items: flex-start !important;
            gap: 4px !important;
        }

        .sidenav .caja-side .cs-top {
            display: flex;
            align-items: center;
            gap: 6px;
            font-size: .85rem !important;
        }

        .sidenav .caja-side .cs-dot {
            width: 8px; height: 8px;
            border-radius: 50%;
            flex-shrink: 0;
        }

        .sidenav .caja-side.ab .cs-dot {
            background: #69f0ae;
            box-shadow: 0 0 6px rgba(105, 240, 174, .6);
            animation: pulse-dot 2s infinite;
        }

        .sidenav .caja-side.ce .cs-dot { background: #ef9a9a; }

        .sidenav .side-divider {
            border-top: 1px solid rgba(255,255,255,.12);
            margin: 8px 0;
        }

        .sidenav .btn-logout-side {
            margin: 6px 14px 16px;
            background: rgba(255, 255, 255, .1);
            border: 1.5px solid rgba(255, 255, 255, .2);
            border-radius: 9px;
            color: rgba(255,255,255,.8) !important;
            font-family: var(--font) !important;
            font-weight: 700 !important;
            font-size: .85rem !important;
            padding: 10px 14px !important;
            height: auto !important;
            line-height: 1.2 !important;
            display: flex !important;
            align-items: center !important;
            gap: 8px !important;
            transition: all .15s !important;
        }

        .sidenav .btn-logout-side:hover {
            background: rgba(255, 255, 255, .22) !important;
            color: #fff !important;
        }

        /* ── main contenido ── */
        main {
            padding-top: 0;
        }
    </style>

    <!-- ═══════════════════════════════════
         NAVBAR DESKTOP
    ═══════════════════════════════════════ -->
    <nav class="pos-nav">
        <div class="nav-wrapper">
            <!-- Brand / Logo -->
            <a href="dashboard.php" class="brand-logo">
                <div class="logo-icon">
                    <i class="material-icons">point_of_sale</i>
                </div>
                <div class="logo-text">
                    Punto de Venta
                    <span>Sistema POS</span>
                </div>
            </a>

            <!-- Menú hamburger -->
            <a href="#" data-target="mobile-demo" class="sidenav-trigger" style="color:#fff;">
                <i class="material-icons">menu</i>
            </a>

            <!-- Links desktop -->
            <ul class="right hide-on-med-and-down" style="display:flex;align-items:center;">
                <?php foreach ($nav_items as $item): ?>
                <li>
                    <a href="<?php echo $item['url']; ?>"
                       class="<?php echo $pagina_actual === $item['url'] ? 'nav-active' : ''; ?>">
                        <i class="material-icons"><?php echo $item['icon']; ?></i>
                        <?php echo $item['label']; ?>
                    </a>
                </li>
                <?php endforeach; ?>

                <!-- Badge de caja -->
                <li>
                    <a href="sesion_caja.php"
                       class="caja-badge <?php echo $caja_abierta ? 'abierta' : 'cerrada'; ?>">
                        <div class="cb-inner">
                            <span class="cb-dot"></span>
                            <i class="material-icons" style="font-size:.95rem;color:#fff;">
                                <?php echo $caja_abierta ? 'lock_open' : 'lock'; ?>
                            </i>
                            <span class="cb-text">
                                <?php echo $caja_abierta ? 'Caja Abierta' : 'Caja Cerrada'; ?>
                            </span>
                        </div>
                    </a>
                </li>

                <!-- Cerrar sesión -->
                <li style="display:flex;align-items:center;">
                    <a href="logout.php" class="btn-logout">
                        <i class="material-icons">exit_to_app</i>
                        Salir
                    </a>
                </li>
            </ul>
        </div>
    </nav>

    <!-- ═══════════════════════════════════
         SIDENAV MÓVIL
    ═══════════════════════════════════════ -->
    <ul class="sidenav" id="mobile-demo">
        <!-- Header del sidenav -->
        <li>
            <div class="sidenav-header">
                <div class="sh-brand">
                    <div class="sh-icon"><i class="material-icons">point_of_sale</i></div>
                    <div class="sh-text">
                        Punto de Venta
                        <span>Sistema POS</span>
                    </div>
                </div>
            </div>
        </li>

        <?php foreach ($nav_items as $item): ?>
        <li>
            <a href="<?php echo $item['url']; ?>"
               class="<?php echo $pagina_actual === $item['url'] ? 'active-side' : ''; ?>">
                <i class="material-icons"><?php echo $item['icon']; ?></i>
                <?php echo $item['label']; ?>
            </a>
        </li>
        <?php endforeach; ?>

        <li><div class="side-divider"></div></li>

        <!-- Badge caja móvil -->
        <li>
            <a href="sesion_caja.php" class="caja-side <?php echo $caja_abierta ? 'ab' : 'ce'; ?>">
                <div class="cs-top">
                    <span class="cs-dot"></span>
                    <i class="material-icons" style="font-size:1rem;color:rgba(255,255,255,.9);">
                        <?php echo $caja_abierta ? 'lock_open' : 'lock'; ?>
                    </i>
                    <strong style="color:#fff;">
                        <?php echo $caja_abierta ? 'Caja Abierta' : 'Caja Cerrada'; ?>
                    </strong>
                </div>
                <span style="font-size:.72rem;color:rgba(255,255,255,.55);font-weight:600;">
                    <?php echo $caja_abierta ? 'Toca para ver sesión activa' : 'Toca para abrir caja'; ?>
                </span>
            </a>
        </li>

        <li><div class="side-divider"></div></li>

        <li>
            <a href="logout.php" class="btn-logout-side">
                <i class="material-icons" style="color:rgba(255,255,255,.7)!important;">exit_to_app</i>
                Cerrar Sesión
            </a>
        </li>
    </ul>

    <main>