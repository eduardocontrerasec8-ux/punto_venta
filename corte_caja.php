<?php
require_once 'config/database.php';
require_once 'includes/header.php';

// Verificar permisos
if ($_SESSION['puesto'] != 'Administrador') {
    echo "<script>alert('No tienes permisos para realizar corte de caja'); window.location.href='dashboard.php';</script>";
    exit();
}

// Realizar corte de caja
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['realizar_corte'])) {
    $usuario_id = $_SESSION['user_id'];
    $fecha = date('Y-m-d');
    
    // Obtener ventas del día
    $stmt = $conn->prepare("SELECT COUNT(*) as cantidad, COALESCE(SUM(total), 0) as total 
                           FROM venta WHERE DATE(fecha) = :fecha");
    $stmt->execute([':fecha' => $fecha]);
    $result = $stmt->fetch();
    
    $total_ventas = $result['total'];
    $cantidad_ventas = $result['cantidad'];
    
    // Verificar si ya existe corte para hoy
    $stmt = $conn->prepare("SELECT COUNT(*) FROM corte_caja WHERE DATE(fecha) = :fecha");
    $stmt->execute([':fecha' => $fecha]);
    if ($stmt->fetchColumn() > 0) {
        $error = "Ya se realizó el corte de caja para hoy";
    } else {
        // Insertar corte
        $stmt = $conn->prepare("INSERT INTO corte_caja (usuario_id, total_ventas, cantidad_ventas) 
                               VALUES (:usuario_id, :total_ventas, :cantidad_ventas)");
        $stmt->execute([
            ':usuario_id' => $usuario_id,
            ':total_ventas' => $total_ventas,
            ':cantidad_ventas' => $cantidad_ventas
        ]);
        
        $mensaje = "Corte de caja realizado exitosamente. Total: $" . number_format($total_ventas, 2);
    }
}

// Obtener cortes realizados
$cortes = $conn->query("SELECT c.*, u.nombre as usuario_nombre 
                        FROM corte_caja c 
                        JOIN usuario u ON c.usuario_id = u.id 
                        ORDER BY c.fecha DESC 
                        LIMIT 10")->fetchAll();

// Obtener ventas de hoy para preview
$fecha_hoy = date('Y-m-d');
$ventas_hoy = $conn->prepare("SELECT v.*, u.nombre as vendedor 
                             FROM venta v 
                             JOIN usuario u ON v.usuario_id = u.id 
                             WHERE DATE(v.fecha) = :fecha 
                             ORDER BY v.fecha DESC");
$ventas_hoy->execute([':fecha' => $fecha_hoy]);
$ventas_hoy_result = $ventas_hoy->fetchAll();

// Totales
$total_hoy = $conn->prepare("SELECT COALESCE(SUM(total), 0) as total FROM venta WHERE DATE(fecha) = :fecha");
$total_hoy->execute([':fecha' => $fecha_hoy]);
$total_hoy_result = $total_hoy->fetch();

$cantidad_hoy = $conn->prepare("SELECT COUNT(*) as cantidad FROM venta WHERE DATE(fecha) = :fecha");
$cantidad_hoy->execute([':fecha' => $fecha_hoy]);
$cantidad_hoy_result = $cantidad_hoy->fetch();
?>
<div class="container">
    <h4>Corte de Caja</h4>
    <p class="grey-text">Fecha: <?php echo date('d/m/Y'); ?></p>
    
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
        <!-- Resumen del día -->
        <div class="col s12 m6">
            <div class="card blue darken-1">
                <div class="card-content white-text">
                    <span class="card-title">Resumen del Día</span>
                    <div class="row" style="margin-bottom: 0;">
                        <div class="col s6">
                            <h5>Ventas</h5>
                            <h3><?php echo $cantidad_hoy_result['cantidad']; ?></h3>
                        </div>
                        <div class="col s6">
                            <h5>Total</h5>
                            <h3>$<?php echo number_format($total_hoy_result['total'], 2); ?></h3>
                        </div>
                    </div>
                </div>
                <div class="card-action">
                    <form method="POST" style="margin: 0;">
                        <button class="btn-large waves-effect waves-light green" type="submit" name="realizar_corte">
                            <i class="material-icons left">attach_money</i>
                            Realizar Corte
                        </button>
                    </form>
                </div>
            </div>
        </div>
        
        <!-- Historial de cortes -->
        <div class="col s12 m6">
            <div class="card">
                <div class="card-content">
                    <span class="card-title">Cortes Recientes</span>
                    
                    <table class="striped">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Ventas</th>
                                <th>Total</th>
                                <th>Usuario</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach($cortes as $corte): ?>
                                <tr>
                                    <td><?php echo date('d/m/Y H:i', strtotime($corte['fecha'])); ?></td>
                                    <td><?php echo $corte['cantidad_ventas']; ?></td>
                                    <td>$<?php echo number_format($corte['total_ventas'], 2); ?></td>
                                    <td><?php echo $corte['usuario_nombre']; ?></td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Ventas del día -->
    <div class="card">
        <div class="card-content">
            <span class="card-title">Ventas del Día de Hoy</span>
            
            <table class="striped">
                <thead>
                    <tr>
                        <th>Folio</th>
                        <th>Hora</th>
                        <th>Vendedor</th>
                        <th>Total</th>
                        <th>Detalles</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($ventas_hoy_result)): ?>
                        <tr>
                            <td colspan="5" class="center-align">No hay ventas registradas hoy</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($ventas_hoy_result as $venta): ?>
                            <tr>
                                <td>#<?php echo str_pad($venta['folio'], 6, '0', STR_PAD_LEFT); ?></td>
                                <td><?php echo date('H:i:s', strtotime($venta['fecha'])); ?></td>
                                <td><?php echo $venta['vendedor']; ?></td>
                                <td>$<?php echo number_format($venta['total'], 2); ?></td>
                                <td>
                                    <a href="#modal_detalle_<?php echo $venta['folio']; ?>" 
                                       class="btn btn-small blue modal-trigger">
                                        <i class="material-icons">visibility</i>
                                    </a>
                                </td>
                            </tr>
                            
                            <!-- Modal para detalles -->
                            <div id="modal_detalle_<?php echo $venta['folio']; ?>" class="modal">
                                <div class="modal-content">
                                    <h5>Detalles de Venta #<?php echo $venta['folio']; ?></h5>
                                    <?php
                                    $stmt = $conn->prepare("SELECT dv.*, p.nombre as producto_nombre 
                                                          FROM detalle_venta dv 
                                                          JOIN producto p ON dv.codigo_barras = p.codigo_barras 
                                                          WHERE dv.folio = :folio");
                                    $stmt->execute([':folio' => $venta['folio']]);
                                    $detalles = $stmt->fetchAll();
                                    ?>
                                    <table class="striped">
                                        <thead>
                                            <tr>
                                                <th>Producto</th>
                                                <th>Cantidad</th>
                                                <th>Precio Unitario</th>
                                                <th>Subtotal</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach($detalles as $detalle): ?>
                                                <tr>
                                                    <td><?php echo $detalle['producto_nombre']; ?></td>
                                                    <td><?php echo $detalle['cantidad']; ?></td>
                                                    <td>$<?php echo number_format($detalle['precio_unitario'], 2); ?></td>
                                                    <td>$<?php echo number_format($detalle['subtotal'], 2); ?></td>
                                                </tr>
                                            <?php endforeach; ?>
                                            <tr class="grey lighten-2">
                                                <td colspan="3" class="right-align"><strong>TOTAL:</strong></td>
                                                <td><strong>$<?php echo number_format($venta['total'], 2); ?></strong></td>
                                            </tr>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="modal-footer">
                                    <a href="#!" class="modal-close btn grey">Cerrar</a>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
    
    <!-- Reporte imprimible -->
    <div class="card">
        <div class="card-content">
            <span class="card-title">Reporte de Cierre</span>
            <div id="reporte_corte" style="font-family: monospace; background: #f5f5f5; padding: 15px;">
                <h5 class="center-align">REPORTE DE CIERRE DE CAJA</h5>
                <p>Fecha: <?php echo date('d/m/Y'); ?></p>
                <p>Hora: <?php echo date('H:i:s'); ?></p>
                <p>Usuario: <?php echo $_SESSION['nombre']; ?></p>
                <hr>
                <p>Total de Ventas: <?php echo $cantidad_hoy_result['cantidad']; ?></p>
                <p>Monto Total: $<?php echo number_format($total_hoy_result['total'], 2); ?></p>
                <hr>
                <p>Firma del Responsable:</p>
                <br><br>
                <p>_________________________________</p>
                <p><?php echo $_SESSION['nombre']; ?></p>
            </div>
            <div class="center-align" style="margin-top: 20px;">
                <button class="btn blue" onclick="imprimirReporte()">
                    <i class="material-icons left">print</i>
                    Imprimir Reporte
                </button>
                <button class="btn green" onclick="exportarPDF()">
                    <i class="material-icons left">picture_as_pdf</i>
                    Exportar PDF
                </button>
            </div>
        </div>
    </div>
</div>

<script>
function imprimirReporte() {
    const contenido = document.getElementById('reporte_corte').innerHTML;
    const ventana = window.open('', '', 'width=600,height=400');
    ventana.document.write('<html><head><title>Reporte de Corte</title>');
    ventana.document.write('<style>body{font-family: monospace;}</style></head><body>');
    ventana.document.write(contenido);
    ventana.document.write('</body></html>');
    ventana.document.close();
    ventana.print();
}

function exportarPDF() {
    alert('Para exportar a PDF, se recomienda usar una biblioteca como jsPDF. Esta función requiere configuración adicional.');
    // Implementación básica con jsPDF:
    // const doc = new jsPDF();
    // doc.text(document.getElementById('reporte_corte').textContent, 10, 10);
    // doc.save('corte_caja_' + new Date().toLocaleDateString() + '.pdf');
}

// Inicializar modales
document.addEventListener('DOMContentLoaded', function() {
    var elems = document.querySelectorAll('.modal');
    M.Modal.init(elems);
});
</script>

<?php require_once 'includes/footer.php'; ?>