<?php
require_once 'config/database.php';
require_once 'includes/header.php';

// ============================================
// VERIFICAR PERMISOS
// ============================================
if ($_SESSION['puesto'] == 'Solo lectura') {
    $_SESSION['error'] = 'No tienes permisos para realizar cortes de caja';
    header("Location: dashboard.php");
    exit();
}

$error = '';
$mensaje = '';
$usuario_id = $_SESSION['user_id'];
$fecha_hoy = date('Y-m-d');

// ============================================
// FUNCIÓN: VERIFICAR SESIÓN ACTIVA
// ============================================
function verificarSesionActiva($conn, $usuario_id) {
    $stmt = $conn->prepare("SELECT * FROM sesion_caja WHERE usuario_id = :user_id AND estatus = 'abierta' LIMIT 1");
    $stmt->execute([':user_id' => $usuario_id]);
    return $stmt->fetch();
}

// Obtener sesión activa
$sesion_activa = verificarSesionActiva($conn, $usuario_id);

// ============================================
// VERIFICAR SI YA EXISTE CORTE HOY
// ============================================
$stmt_corte_hoy = $conn->prepare("SELECT * FROM corte_caja WHERE DATE(fecha) = :fecha AND usuario_id = :usuario_id");
$stmt_corte_hoy->execute([':fecha' => $fecha_hoy, ':usuario_id' => $usuario_id]);
$corte_existente = $stmt_corte_hoy->fetch();

// ============================================
// OBTENER VENTAS DE HOY
// ============================================
$ventas_hoy_stmt = $conn->prepare("SELECT v.*, u.nombre as vendedor 
                                   FROM venta v 
                                   JOIN usuario u ON v.usuario_id = u.id 
                                   WHERE DATE(v.fecha) = :fecha 
                                   ORDER BY v.fecha DESC");
$ventas_hoy_stmt->execute([':fecha' => $fecha_hoy]);
$ventas_hoy_result = $ventas_hoy_stmt->fetchAll();

// Totales del día
$total_hoy_stmt = $conn->prepare("SELECT COALESCE(SUM(total), 0) as total, COUNT(*) as cantidad 
                                  FROM venta 
                                  WHERE DATE(fecha) = :fecha");
$total_hoy_stmt->execute([':fecha' => $fecha_hoy]);
$totales_hoy = $total_hoy_stmt->fetch();

// ============================================
// PROCESAR CORTE DE CAJA
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['realizar_corte'])) {
    try {
        // VALIDACIÓN CRÍTICA: Verificar que hay sesión activa
        if (!$sesion_activa) {
            throw new Exception("No puedes realizar un corte sin tener una sesión de caja activa. Inicia sesión primero.");
        }
        
        // Verificar que la sesión pertenece al usuario
        if ($sesion_activa['usuario_id'] != $usuario_id) {
            throw new Exception("La sesión activa no pertenece a tu usuario");
        }
        
        // Iniciar transacción
        $conn->beginTransaction();
        
        // Obtener ventas del día para ESTA sesión
        $stmt = $conn->prepare("SELECT COUNT(*) as cantidad, COALESCE(SUM(total), 0) as total 
                               FROM venta 
                               WHERE DATE(fecha) = :fecha 
                               AND sesion_caja_id = :sesion_id");
        $stmt->execute([
            ':fecha' => $fecha_hoy,
            ':sesion_id' => $sesion_activa['id']
        ]);
        $result = $stmt->fetch();
        
        $total_ventas = $result['total'];
        $cantidad_ventas = $result['cantidad'];
        
        if ($cantidad_ventas == 0) {
            throw new Exception("No hay ventas en esta sesión para realizar el corte");
        }
        
        // Verificar si ya existe corte para hoy
        if ($corte_existente) {
            throw new Exception("Ya se realizó el corte de caja para hoy");
        }
        
        // Insertar corte vinculado a la sesión
        $stmt = $conn->prepare("INSERT INTO corte_caja 
                               (usuario_id, total_ventas, cantidad_ventas, sesion_caja_id, fecha) 
                               VALUES (:usuario_id, :total_ventas, :cantidad_ventas, :sesion_id, NOW())");
        $stmt->execute([
            ':usuario_id' => $usuario_id,
            ':total_ventas' => $total_ventas,
            ':cantidad_ventas' => $cantidad_ventas,
            ':sesion_id' => $sesion_activa['id']
        ]);
        
        // IMPORTANTE: Cerrar la sesión automáticamente al hacer corte
        $stmt_cerrar = $conn->prepare("UPDATE sesion_caja 
                                       SET fecha_cierre = NOW(),
                                           monto_final = monto_inicial + :total_ventas,
                                           ventas_durante_sesion = :cantidad,
                                           total_ventas = :total_ventas,
                                           estatus = 'cerrada'
                                       WHERE id = :sesion_id");
        $stmt_cerrar->execute([
            ':total_ventas' => $total_ventas,
            ':cantidad' => $cantidad_ventas,
            ':sesion_id' => $sesion_activa['id']
        ]);
        
        $conn->commit();
        
        $_SESSION['success'] = "Corte de caja realizado exitosamente. Ventas: " . $cantidad_ventas . " | Total: $" . number_format($total_ventas, 2);
        header("Location: corte_caja.php");
        exit();
        
    } catch (Exception $e) {
        if ($conn->inTransaction()) {
            $conn->rollBack();
        }
        $error = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        error_log("Error en corte de caja: " . $e->getMessage());
    }
}

// ============================================
// OBTENER HISTORIAL DE CORTES
// ============================================
$cortes = $conn->query("SELECT c.*, u.nombre as usuario_nombre 
                        FROM corte_caja c 
                        JOIN usuario u ON c.usuario_id = u.id 
                        ORDER BY c.fecha DESC 
                        LIMIT 10")->fetchAll();

// Mostrar mensajes de sesión
if (isset($_SESSION['success'])) {
    $mensaje = $_SESSION['success'];
    unset($_SESSION['success']);
}

if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}
?>

<div class="container">
    <h4>Corte de Caja</h4>
    <p class="grey-text">Fecha: <?php echo date('d/m/Y H:i:s'); ?></p>
    <p class="grey-text">Usuario: <?php echo htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8'); ?></p>
    
    <!-- Mensajes -->
    <?php if($mensaje): ?>
        <div class="card green lighten-4">
            <div class="card-content green-text">
                <i class="material-icons left">check_circle</i>
                <?php echo $mensaje; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <?php if($error): ?>
        <div class="card red lighten-4">
            <div class="card-content red-text">
                <i class="material-icons left">error</i>
                <?php echo $error; ?>
            </div>
        </div>
    <?php endif; ?>
    
    <!-- Alerta si NO hay sesión activa -->
    <?php if(!$sesion_activa): ?>
        <div class="card orange lighten-4">
            <div class="card-content orange-text text-darken-3">
                <i class="material-icons left">warning</i>
                <strong>¡ATENCIÓN!</strong> No tienes una sesión de caja activa. 
                Para realizar un corte, primero debes <a href="sesion_caja.php" class="orange-text text-darken-4"><strong><u>iniciar sesión de caja</u></strong></a>.
            </div>
        </div>
    <?php endif; ?>
    
    <div class="row">
        <!-- Resumen del día -->
        <div class="col s12 m6">
            <div class="card <?php echo $corte_existente ? 'grey darken-1' : ($sesion_activa ? 'blue darken-1' : 'orange darken-1'); ?>">
                <div class="card-content white-text">
                    <span class="card-title">
                        <?php if($corte_existente): ?>
                            <i class="material-icons left">check_circle</i> CORTE REALIZADO
                        <?php elseif($sesion_activa): ?>
                            <i class="material-icons left">pending</i> Pendiente de Corte
                        <?php else: ?>
                            <i class="material-icons left">lock</i> SIN SESIÓN ACTIVA
                        <?php endif; ?>
                    </span>
                    
                    <div class="row" style="margin-bottom: 0;">
                        <div class="col s6">
                            <h5>Ventas de Hoy</h5>
                            <h3><?php echo $totales_hoy['cantidad']; ?></h3>
                        </div>
                        <div class="col s6">
                            <h5>Monto Total</h5>
                            <h3>$<?php echo number_format($totales_hoy['total'], 2); ?></h3>
                        </div>
                    </div>
                    
                    <?php if($sesion_activa && !$corte_existente): ?>
                        <div class="row" style="margin-top: 20px; background: rgba(255,255,255,0.1); padding: 10px; border-radius: 5px;">
                            <div class="col s12">
                                <h6>Sesión Activa #<?php echo $sesion_activa['id']; ?></h6>
                                <p>Iniciada: <?php echo date('H:i:s', strtotime($sesion_activa['fecha_apertura'])); ?></p>
                                <p>Monto Inicial: $<?php echo number_format($sesion_activa['monto_inicial'], 2); ?></p>
                            </div>
                        </div>
                    <?php elseif($corte_existente): ?>
                        <div class="row" style="margin-top: 20px; background: rgba(255,255,255,0.1); padding: 10px; border-radius: 5px;">
                            <div class="col s12">
                                <h6>Corte Realizado</h6>
                                <p>Hora: <?php echo date('H:i:s', strtotime($corte_existente['fecha'])); ?></p>
                                <p>Monto: $<?php echo number_format($corte_existente['total_ventas'], 2); ?></p>
                            </div>
                        </div>
                    <?php endif; ?>
                </div>
                
                <div class="card-action">
                    <?php if(!$sesion_activa): ?>
                        <!-- Sin sesión activa -->
                        <a href="sesion_caja.php" class="btn-large waves-effect waves-light orange">
                            <i class="material-icons left">lock_open</i>
                            Ir a Iniciar Sesión
                        </a>
                    <?php elseif($corte_existente): ?>
                        <!-- Corte ya realizado -->
                        <button class="btn-large waves-effect waves-light grey" disabled>
                            <i class="material-icons left">check</i>
                            Corte Completado
                        </button>
                        <button type="button" class="btn-large waves-effect waves-light blue" onclick="verDetalleCorte()">
                            <i class="material-icons left">visibility</i>
                            Ver Detalle
                        </button>
                    <?php elseif($totales_hoy['cantidad'] > 0): ?>
                        <!-- Listo para corte -->
                        <form method="POST" style="margin: 0;" onsubmit="return confirmarCorte()">
                            <button class="btn-large waves-effect waves-light green" type="submit" name="realizar_corte">
                                <i class="material-icons left">attach_money</i>
                                Realizar Corte
                            </button>
                            <button type="button" class="btn-large waves-effect waves-light blue" onclick="generarPreCorte()">
                                <i class="material-icons left">receipt</i>
                                Pre-Corte
                            </button>
                        </form>
                    <?php else: ?>
                        <!-- No hay ventas -->
                        <button class="btn-large waves-effect waves-light grey" disabled>
                            <i class="material-icons left">info</i>
                            No hay ventas hoy
                        </button>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        
        <!-- Historial de cortes -->
        <div class="col s12 m6">
            <div class="card">
                <div class="card-content">
                    <span class="card-title">Historial de Cortes</span>
                    
                    <table class="striped highlight responsive-table">
                        <thead>
                            <tr>
                                <th>Fecha</th>
                                <th>Ventas</th>
                                <th>Total</th>
                                <th>Usuario</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php if(empty($cortes)): ?>
                                <tr>
                                    <td colspan="4" class="center-align grey-text">No hay cortes registrados</td>
                                </tr>
                            <?php else: ?>
                                <?php foreach($cortes as $corte): ?>
                                    <tr style="cursor: pointer;" onclick="verDetalleCorteEspecifico(<?php echo $corte['id']; ?>)">
                                        <td><?php echo date('d/m H:i', strtotime($corte['fecha'])); ?></td>
                                        <td><?php echo $corte['cantidad_ventas']; ?></td>
                                        <td><strong>$<?php echo number_format($corte['total_ventas'], 2); ?></strong></td>
                                        <td><?php echo htmlspecialchars($corte['usuario_nombre'], ENT_QUOTES, 'UTF-8'); ?></td>
                                    </tr>
                                <?php endforeach; ?>
                            <?php endif; ?>
                        </tbody>
                    </table>
                    
                    <div class="center-align" style="margin-top: 15px;">
                        <a href="historial_cortes.php" class="btn blue waves-effect waves-light">
                            <i class="material-icons left">history</i>
                            Ver Historial Completo
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Ventas del día -->
    <div class="card">
        <div class="card-content">
            <span class="card-title">
                Ventas del Día de Hoy 
                <span class="badge blue"><?php echo $totales_hoy['cantidad']; ?></span>
            </span>
            
            <?php if($corte_existente): ?>
                <div class="chip green white-text">
                    <i class="material-icons tiny">check</i>
                    Incluidas en corte
                </div>
            <?php elseif($sesion_activa): ?>
                <div class="chip orange white-text">
                    <i class="material-icons tiny">access_time</i>
                    Pendientes de corte
                </div>
            <?php else: ?>
                <div class="chip red white-text">
                    <i class="material-icons tiny">lock</i>
                    Sin sesión activa
                </div>
            <?php endif; ?>
            
            <table class="striped responsive-table">
                <thead>
                    <tr>
                        <th>Folio</th>
                        <th>Hora</th>
                        <th>Vendedor</th>
                        <th>Total</th>
                        <th>Método Pago</th>
                        <th>Detalles</th>
                    </tr>
                </thead>
                <tbody>
                    <?php if(empty($ventas_hoy_result)): ?>
                        <tr>
                            <td colspan="6" class="center-align grey-text">No hay ventas registradas hoy</td>
                        </tr>
                    <?php else: ?>
                        <?php foreach($ventas_hoy_result as $venta): ?>
                            <tr class="<?php echo $corte_existente ? 'green lighten-5' : ''; ?>">
                                <td><strong>#<?php echo str_pad($venta['folio'], 6, '0', STR_PAD_LEFT); ?></strong></td>
                                <td><?php echo date('H:i:s', strtotime($venta['fecha'])); ?></td>
                                <td><?php echo htmlspecialchars($venta['vendedor'], ENT_QUOTES, 'UTF-8'); ?></td>
                                <td><strong>$<?php echo number_format($venta['total'], 2); ?></strong></td>
                                <td>
                                    <span class="chip <?php echo $venta['metodo_pago'] == 'efectivo' ? 'green' : ($venta['metodo_pago'] == 'tarjeta' ? 'blue' : 'purple'); ?> white-text">
                                        <?php echo ucfirst($venta['metodo_pago']); ?>
                                    </span>
                                </td>
                                <td>
                                    <a href="#modal_detalle_<?php echo $venta['folio']; ?>" 
                                       class="btn btn-small blue modal-trigger">
                                        <i class="material-icons">visibility</i>
                                    </a>
                                    <a href="ticket.php?folio=<?php echo $venta['folio']; ?>" 
                                       target="_blank" class="btn btn-small green">
                                        <i class="material-icons">receipt</i>
                                    </a>
                                </td>
                            </tr>
                            
                            <!-- Modal para detalles -->
                            <div id="modal_detalle_<?php echo $venta['folio']; ?>" class="modal modal-fixed-footer">
                                <div class="modal-content">
                                    <h5>Detalles de Venta #<?php echo $venta['folio']; ?></h5>
                                    <div class="row">
                                        <div class="col s6">
                                            <p><strong>Fecha:</strong> <?php echo date('d/m/Y H:i:s', strtotime($venta['fecha'])); ?></p>
                                            <p><strong>Vendedor:</strong> <?php echo htmlspecialchars($venta['vendedor'], ENT_QUOTES, 'UTF-8'); ?></p>
                                        </div>
                                        <div class="col s6">
                                            <p><strong>Método de Pago:</strong> <?php echo ucfirst($venta['metodo_pago']); ?></p>
                                            <p><strong>Total:</strong> $<?php echo number_format($venta['total'], 2); ?></p>
                                        </div>
                                    </div>
                                    
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
                                                    <td><?php echo htmlspecialchars($detalle['producto_nombre'], ENT_QUOTES, 'UTF-8'); ?></td>
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
                                    <a href="ticket.php?folio=<?php echo $venta['folio']; ?>" 
                                       target="_blank" class="btn blue">Imprimir Ticket</a>
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
            <div id="reporte_corte" style="font-family: monospace; background: white; padding: 20px; border: 1px solid #ddd;">
                <h5 class="center-align" style="margin-bottom: 30px;">REPORTE DE CIERRE DE CAJA</h5>
                
                <div style="margin-bottom: 20px;">
                    <p><strong>Fecha:</strong> <?php echo date('d/m/Y'); ?></p>
                    <p><strong>Hora:</strong> <?php echo date('H:i:s'); ?></p>
                    <p><strong>Usuario:</strong> <?php echo htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8'); ?></p>
                    <p><strong>Estatus:</strong> <?php echo $corte_existente ? 'CORTE REALIZADO' : ($sesion_activa ? 'PENDIENTE DE CORTE' : 'SIN SESIÓN'); ?></p>
                </div>
                
                <hr style="border: 1px solid #000; margin: 20px 0;">
                
                <div style="margin-bottom: 20px;">
                    <h6>RESUMEN DEL DÍA</h6>
                    <p>Total de Ventas: <?php echo $totales_hoy['cantidad']; ?></p>
                    <p>Monto Total: $<?php echo number_format($totales_hoy['total'], 2); ?></p>
                </div>
                
                <?php if($sesion_activa): ?>
                    <hr style="border: 1px dashed #000; margin: 20px 0;">
                    <div style="margin-bottom: 20px;">
                        <h6>SESIÓN ACTIVA</h6>
                        <p>ID Sesión: #<?php echo $sesion_activa['id']; ?></p>
                        <p>Hora Apertura: <?php echo date('H:i:s', strtotime($sesion_activa['fecha_apertura'])); ?></p>
                        <p>Monto Inicial: $<?php echo number_format($sesion_activa['monto_inicial'], 2); ?></p>
                    </div>
                <?php endif; ?>
                
                <?php if($corte_existente): ?>
                    <hr style="border: 1px dashed #000; margin: 20px 0;">
                    <div style="margin-bottom: 20px;">
                        <h6>INFORMACIÓN DEL CORTE</h6>
                        <p>Hora del Corte: <?php echo date('H:i:s', strtotime($corte_existente['fecha'])); ?></p>
                        <p>Ventas Incluidas: <?php echo $corte_existente['cantidad_ventas']; ?></p>
                        <p>Monto del Corte: $<?php echo number_format($corte_existente['total_ventas'], 2); ?></p>
                    </div>
                <?php endif; ?>
                
                <hr style="border: 1px solid #000; margin: 20px 0;">
                
                <div>
                    <p style="margin-top: 40px;">Firma del Responsable:</p>
                    <br><br>
                    <p>_________________________________</p>
                    <p><?php echo htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8'); ?></p>
                </div>
            </div>
            
            <div class="center-align" style="margin-top: 20px;">
                <button class="btn blue" onclick="imprimirReporte()">
                    <i class="material-icons left">print</i>
                    Imprimir Reporte
                </button>
                <?php if($sesion_activa && !$corte_existente && $totales_hoy['cantidad'] > 0): ?>
                    <button class="btn green" onclick="generarPreCorte()">
                        <i class="material-icons left">receipt</i>
                        Generar Pre-Corte
                    </button>
                <?php endif; ?>
                <button class="btn purple" onclick="exportarAExcel()">
                    <i class="material-icons left">table_chart</i>
                    Exportar Excel
                </button>
            </div>
        </div>
    </div>
</div>

<script>
// Inicializar modales
document.addEventListener('DOMContentLoaded', function() {
    var elems = document.querySelectorAll('.modal');
    M.Modal.init(elems);
});

function confirmarCorte() {
    const cantidad = <?php echo $totales_hoy['cantidad']; ?>;
    const total = <?php echo $totales_hoy['total']; ?>;
    
    return confirm(`¿Está seguro de realizar el corte de caja?\n\nVentas a incluir: ${cantidad}\nMonto total: $${total.toFixed(2)}\n\nEsta acción cerrará tu sesión de caja actual.`);
}

function imprimirReporte() {
    const contenido = document.getElementById('reporte_corte').innerHTML;
    const titulo = "Reporte de Corte de Caja - <?php echo date('d/m/Y'); ?>";
    
    const ventana = window.open('', '_blank', 'width=800,height=600');
    ventana.document.write(`
        <html>
        <head>
            <title>${titulo}</title>
            <style>
                body { font-family: 'Arial', sans-serif; margin: 40px; color: #333; }
                h5 { text-align: center; margin-bottom: 30px; }
                hr { border: 1px solid #000; margin: 20px 0; }
                p { margin: 8px 0; }
                @media print { body { margin: 20px; } .no-print { display: none; } }
            </style>
        </head>
        <body>
            <div class="no-print" style="text-align: center; margin-bottom: 20px;">
                <button onclick="window.print()" style="padding: 10px 20px; background: #2196F3; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    Imprimir Reporte
                </button>
                <button onclick="window.close()" style="padding: 10px 20px; background: #f44336; color: white; border: none; border-radius: 4px; cursor: pointer; margin-left: 10px;">
                    Cerrar
                </button>
            </div>
            ${contenido}
        </body>
        </html>
    `);
    ventana.document.close();
}

function generarPreCorte() {
    const ventasPendientes = <?php echo $totales_hoy['cantidad']; ?>;
    const totalPendiente = <?php echo $totales_hoy['total']; ?>;
    
    let contenido = `
        <h5>PRE-CORTE DE CAJA</h5>
        <p><strong>Fecha:</strong> ${new Date().toLocaleDateString('es-MX')}</p>
        <p><strong>Hora:</strong> ${new Date().toLocaleTimeString('es-MX', {hour12: false})}</p>
        <p><strong>Usuario:</strong> <?php echo htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8'); ?></p>
        <hr>
        <div style="background: #e8f5e9; padding: 15px; border-radius: 5px;">
            <h6>VENTAS PENDIENTES DE CORTE</h6>
            <p>Cantidad: ${ventasPendientes}</p>
            <p>Monto Total: $${totalPendiente.toFixed(2)}</p>
        </div>
        <hr>
        <p>Este es un pre-corte. Para finalizar el corte de caja, presione "Realizar Corte" en el sistema.</p>
        <p style="margin-top: 50px;">Firma: _________________________</p>
    `;
    
    const ventana = window.open('', '_blank', 'width=600,height=400');
    ventana.document.write(`
        <html>
        <head>
            <title>Pre-Corte de Caja</title>
            <style>
                body { font-family: Arial, sans-serif; padding: 20px; }
                h5 { text-align: center; }
                hr { border: 1px solid #ccc; }
            </style>
        </head>
        <body>
            ${contenido}
            <div style="text-align: center; margin-top: 20px;">
                <button onclick="window.print()" style="padding: 10px 20px; background: #2196F3; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    Imprimir Pre-Corte
                </button>
            </div>
        </body>
        </html>
    `);
    ventana.document.close();
}

function verDetalleCorte() {
    window.location.href = 'detalle_corte.php?fecha=<?php echo $fecha_hoy; ?>';
}

function verDetalleCorteEspecifico(corteId) {
    window.location.href = `detalle_corte.php?id=${corteId}`;
}

function exportarAExcel() {
    let csvContent = "data:text/csv;charset=utf-8,";
    csvContent += "Reporte de Corte de Caja\r\n";
    csvContent += "Fecha,Hora,Usuario,Ventas,Total\r\n";
    csvContent += `"<?php echo date('d/m/Y'); ?>","<?php echo date('H:i:s'); ?>","<?php echo htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8'); ?>","<?php echo $totales_hoy['cantidad']; ?>","<?php echo $totales_hoy['total']; ?>"\r\n`;
    
    const encodedUri = encodeURI(csvContent);
    const link = document.createElement("a");
    link.setAttribute("href", encodedUri);
    link.setAttribute("download", "corte_caja_<?php echo date('Y-m-d'); ?>.csv");
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
</script>

<?php require_once 'includes/footer.php'; ?>