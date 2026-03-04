<?php
require_once 'config/database.php';
require_once 'includes/header.php';

// Verificar si el usuario está logueado
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit();
}

// Obtener estadísticas
$ventas_hoy = $conn->query("SELECT COUNT(*) as count FROM venta WHERE DATE(fecha) = CURRENT_DATE")->fetch()['count'];
$total_hoy = $conn->query("SELECT COALESCE(SUM(total), 0) as total FROM venta WHERE DATE(fecha) = CURRENT_DATE")->fetch()['total'];
$productos_bajo_stock = $conn->query("SELECT COUNT(*) as count FROM producto WHERE stock < stock_minimo")->fetch()['count'];
?>
<div class="container">
    <h4 class="center-align">Dashboard</h4>
    <p class="center-align">Bienvenido, <?php echo $_SESSION['nombre']; ?> (<?php echo $_SESSION['puesto']; ?>)</p>
    
    <div class="row">
        <!-- Tarjetas de estadísticas -->
        <div class="col s12 m6 l3">
            <div class="card blue darken-1">
                <div class="card-content white-text">
                    <span class="card-title">Ventas Hoy</span>
                    <h4><?php echo $ventas_hoy; ?></h4>
                </div>
            </div>
        </div>
        
        <div class="col s12 m6 l3">
            <div class="card green darken-1">
                <div class="card-content white-text">
                    <span class="card-title">Total Hoy</span>
                    <h4>$<?php echo number_format($total_hoy, 2); ?></h4>
                </div>
            </div>
        </div>
        
        <div class="col s12 m6 l3">
            <div class="card orange darken-1">
                <div class="card-content white-text">
                    <span class="card-title">Bajo Stock</span>
                    <h4><?php echo $productos_bajo_stock; ?></h4>
                </div>
            </div>
        </div>
        
        <div class="col s12 m6 l3">
            <div class="card purple darken-1">
                <div class="card-content white-text">
                    <span class="card-title">Usuarios</span>
                    <h4><?php echo $conn->query("SELECT COUNT(*) as count FROM usuario")->fetch()['count']; ?></h4>
                </div>
            </div>
        </div>
    </div>
    
    <div class="row">
        <!-- Menú de navegación -->
        <div class="col s12 m4">
            <div class="card">
                <div class="card-content">
                    <span class="card-title">Punto de Venta</span>
                    <p>Realizar ventas rápidas</p>
                </div>
                <div class="card-action">
                    <a href="ventas.php" class="blue-text">Ir a Ventas</a>
                </div>
            </div>
        </div>
        
        <div class="col s12 m4">
            <div class="card">
                <div class="card-content">
                    <span class="card-title">Inventario</span>
                    <p>Gestionar productos</p>
                </div>
                <div class="card-action">
                    <a href="productos.php" class="green-text">Ver Productos</a>
                </div>
            </div>
        </div>
        
        <div class="col s12 m4">
            <div class="card">
                <div class="card-content">
                    <span class="card-title">Corte de Caja</span>
                    <p>Generar reportes diarios</p>
                </div>
                <div class="card-action">
                    <a href="corte_caja.php" class="orange-text">Realizar Corte</a>
                </div>
            </div>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>