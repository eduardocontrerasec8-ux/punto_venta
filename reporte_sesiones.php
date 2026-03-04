<?php
require_once 'config/database.php';
require_once 'includes/header.php';

// Obtener todas las sesiones
$sesiones = $conn->query("SELECT sc.*, u.nombre as usuario_nombre 
                         FROM sesion_caja sc 
                         JOIN usuario u ON sc.usuario_id = u.id 
                         ORDER BY sc.fecha_apertura DESC")->fetchAll();

// Totales generales
$totales = $conn->query("SELECT 
                        COUNT(*) as total_sesiones,
                        SUM(total_ventas) as total_ventas,
                        SUM(ventas_durante_sesion) as total_transacciones
                        FROM sesion_caja")->fetch();
?>
<div class="container">
    <h4>Reporte de Sesiones de Caja</h4>
    
    <div class="card blue-grey darken-1 white-text">
        <div class="card-content">
            <div class="row">
                <div class="col s4 center-align">
                    <h5><?php echo $totales['total_sesiones']; ?></h5>
                    <p>Sesiones Totales</p>
                </div>
                <div class="col s4 center-align">
                    <h5>$<?php echo number_format($totales['total_ventas'], 2); ?></h5>
                    <p>Total Ventas</p>
                </div>
                <div class="col s4 center-align">
                    <h5><?php echo $totales['total_transacciones']; ?></h5>
                    <p>Transacciones</p>
                </div>
            </div>
        </div>
    </div>
    
    <table class="striped responsive-table">
        <thead>
            <tr>
                <th>Usuario</th>
                <th>Apertura</th>
                <th>Cierre</th>
                <th>Duración</th>
                <th>Monto Inicial</th>
                <th>Ventas</th>
                <th>Total Ventas</th>
                <th>Monto Final</th>
                <th>Estado</th>
            </tr>
        </thead>
        <tbody>
            <?php foreach($sesiones as $sesion): ?>
                <tr>
                    <td><?php echo $sesion['usuario_nombre']; ?></td>
                    <td><?php echo date('d/m/Y H:i:s', strtotime($sesion['fecha_apertura'])); ?></td>
                    <td><?php echo $sesion['fecha_cierre'] ? date('d/m/Y H:i:s', strtotime($sesion['fecha_cierre'])) : '-'; ?></td>
                    <td>
                        <?php if($sesion['fecha_cierre']): 
                            $diferencia = strtotime($sesion['fecha_cierre']) - strtotime($sesion['fecha_apertura']);
                            echo gmdate('H:i:s', $diferencia);
                        else: ?>
                            En curso
                        <?php endif; ?>
                    </td>
                    <td>$<?php echo number_format($sesion['monto_inicial'], 2); ?></td>
                    <td><?php echo $sesion['ventas_durante_sesion']; ?></td>
                    <td>$<?php echo number_format($sesion['total_ventas'], 2); ?></td>
                    <td>$<?php echo number_format($sesion['monto_final'], 2); ?></td>
                    <td>
                        <?php if($sesion['estatus'] == 'abierta'): ?>
                            <span class="badge green white-text">Abierta</span>
                        <?php else: ?>
                            <span class="badge grey white-text">Cerrada</span>
                        <?php endif; ?>
                    </td>
                </tr>
            <?php endforeach; ?>
        </tbody>
    </table>
</div>
<?php require_once 'includes/footer.php'; ?>