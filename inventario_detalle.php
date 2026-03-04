<?php
require_once 'config/database.php';
require_once 'includes/header.php';

/* if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
} */

// Obtener productos con información detallada
$productos = $conn->query("SELECT p.*, 
                                  c.nombre as categoria_nombre,
                                  pr.nombre as proveedor_nombre,
                                  (SELECT SUM(cantidad) FROM inventario_movimiento 
                                   WHERE producto_id = p.codigo_barras AND tipo = 'entrada') as total_entradas,
                                  (SELECT SUM(cantidad) FROM inventario_movimiento 
                                   WHERE producto_id = p.codigo_barras AND tipo = 'salida') as total_salidas
                           FROM producto p
                           LEFT JOIN categoria c ON p.categoria_id = c.id
                           LEFT JOIN proveedor pr ON p.proveedor_id = pr.id
                           ORDER BY p.stock ASC, p.nombre")->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="container">
    <h4>Detalle de Inventario</h4>
    
    <div class="card">
        <div class="card-content">
            <div class="row">
                <div class="col s12">
                    <a href="productos.php#inventario" class="btn waves-effect waves-light">
                        <i class="material-icons left">arrow_back</i> Volver
                    </a>
                    <a href="generar_reporte.php?formato=pdf&tipo_reporte=inventario" 
                       class="btn waves-effect waves-light blue" target="_blank">
                        <i class="material-icons left">picture_as_pdf</i> PDF
                    </a>
                </div>
            </div>
            
            <table class="striped responsive-table">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Producto</th>
                        <th>Categoría</th>
                        <th>Stock Actual</th>
                        <th>Stock Mínimo</th>
                        <th>Entradas</th>
                        <th>Salidas</th>
                        <th>Costo Unitario</th>
                        <th>Valor Total</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php 
                    $valor_total_inventario = 0;
                    foreach($productos as $producto): 
                        $total_entradas = $producto['total_entradas'] ?? 0;
                        $total_salidas = $producto['total_salidas'] ?? 0;
                        $valor_producto = $producto['stock'] * $producto['precio_compra'];
                        $valor_total_inventario += $valor_producto;
                        
                        // Determinar estado
                        if ($producto['stock'] == 0) {
                            $estado = 'Agotado';
                            $color = 'red';
                            $icono = 'cancel';
                        } elseif ($producto['stock'] < $producto['stock_minimo']) {
                            $estado = 'Bajo Stock';
                            $color = 'orange';
                            $icono = 'warning';
                        } else {
                            $estado = 'Normal';
                            $color = 'green';
                            $icono = 'check_circle';
                        }
                    ?>
                        <tr class="<?php echo $color; ?> lighten-5">
                            <td><?php echo $producto['codigo_barras']; ?></td>
                            <td><?php echo htmlspecialchars($producto['nombre']); ?></td>
                            <td><?php echo htmlspecialchars($producto['categoria_nombre'] ?? 'Sin categoría'); ?></td>
                            <td><strong><?php echo $producto['stock']; ?></strong></td>
                            <td><?php echo $producto['stock_minimo']; ?></td>
                            <td><?php echo $total_entradas; ?></td>
                            <td><?php echo $total_salidas; ?></td>
                            <td>$<?php echo number_format($producto['precio_compra'], 2); ?></td>
                            <td>$<?php echo number_format($valor_producto, 2); ?></td>
                            <td>
                                <span class="<?php echo $color; ?>-text">
                                    <i class="material-icons tiny"><?php echo $icono; ?></i>
                                    <?php echo $estado; ?>
                                </span>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
                <tfoot>
                    <tr class="grey lighten-2">
                        <td colspan="7"><strong>Total Valor del Inventario:</strong></td>
                        <td colspan="3"><strong>$<?php echo number_format($valor_total_inventario, 2); ?></strong></td>
                    </tr>
                </tfoot>
            </table>
        </div>
    </div>
</div>

<?php require_once 'includes/footer.php'; ?>