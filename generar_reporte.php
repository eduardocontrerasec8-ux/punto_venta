<?php
require_once 'config/database.php';

/* session_start();
if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
} */

// Obtener parámetros
$categoria_id = $_GET['categoria_id'] ?? null;
$formato = $_GET['formato'] ?? 'pdf';
$tipo_reporte = $_GET['tipo_reporte'] ?? 'inventario';

// Consultar productos según filtros
$sql = "SELECT p.*, c.nombre as categoria_nombre, pr.nombre as proveedor_nombre 
        FROM producto p 
        LEFT JOIN categoria c ON p.categoria_id = c.id
        LEFT JOIN proveedor pr ON p.proveedor_id = pr.id
        WHERE 1=1";

if ($categoria_id) {
    $sql .= " AND p.categoria_id = :categoria_id";
}

// Filtrar por tipo de reporte
if ($tipo_reporte == 'bajos_stock') {
    $sql .= " AND p.stock < p.stock_minimo AND p.stock > 0";
} elseif ($tipo_reporte == 'agotados') {
    $sql .= " AND p.stock = 0";
}

$sql .= " ORDER BY c.nombre, p.nombre";

$stmt = $conn->prepare($sql);
if ($categoria_id) {
    $stmt->execute([':categoria_id' => $categoria_id]);
} else {
    $stmt->execute();
}
$productos = $stmt->fetchAll(PDO::FETCH_ASSOC);

// Obtener información de la categoría si se filtró
$categoria_info = null;
if ($categoria_id) {
    $stmt_cat = $conn->prepare("SELECT nombre FROM categoria WHERE id = :id");
    $stmt_cat->execute([':id' => $categoria_id]);
    $categoria_info = $stmt_cat->fetch(PDO::FETCH_ASSOC);
}

if ($formato == 'pdf') {
    // Generar PDF usando una versión simple (puedes usar TCPDF o FPDF más adelante)
    require_once 'config/database.php'; // Solo para conexión
    
    // Crear contenido HTML para PDF
    $html = '<!DOCTYPE html>
    <html>
    <head>
        <meta charset="UTF-8">
        <title>Reporte de Inventario</title>
        <style>
            body { font-family: Arial, sans-serif; }
            h1 { color: #333; text-align: center; }
            h2 { color: #666; }
            table { width: 100%; border-collapse: collapse; margin-top: 20px; }
            th { background-color: #f2f2f2; text-align: left; padding: 8px; border: 1px solid #ddd; }
            td { padding: 8px; border: 1px solid #ddd; }
            .header { text-align: center; margin-bottom: 30px; }
            .footer { margin-top: 30px; text-align: center; font-size: 12px; color: #666; }
            .red { color: red; }
            .green { color: green; }
            .orange { color: orange; }
        </style>
    </head>
    <body>
        <div class="header">
            <h1>REPORTE DE INVENTARIO</h1>';
    
    if ($categoria_info) {
        $html .= '<h2>Categoría: ' . htmlspecialchars($categoria_info['nombre']) . '</h2>';
    }
    
    if ($tipo_reporte != 'inventario') {
        $html .= '<h2>Tipo: ' . 
                 ($tipo_reporte == 'bajos_stock' ? 'Productos Bajos en Stock' : 'Productos Agotados') . 
                 '</h2>';
    }
    
    $html .= '<p>Fecha del reporte: ' . date('d/m/Y H:i:s') . '</p>
        </div>';
    
    $html .= '<table>
            <thead>
                <tr>
                    <th>Código</th>
                    <th>Producto</th>
                    <th>Categoría</th>
                    <th>Stock</th>
                    <th>Mínimo</th>
                    <th>Precio Compra</th>
                    <th>Precio Venta</th>
                    <th>Estado</th>
                </tr>
            </thead>
            <tbody>';
    
    $total_valor = 0;
    foreach ($productos as $producto) {
        // Determinar estado
        if ($producto['stock'] == 0) {
            $estado = 'Agotado';
            $estado_class = 'red';
        } elseif ($producto['stock'] < $producto['stock_minimo']) {
            $estado = 'Bajo Stock';
            $estado_class = 'orange';
        } else {
            $estado = 'Normal';
            $estado_class = 'green';
        }
        
        $valor = $producto['stock'] * $producto['precio_compra'];
        $total_valor += $valor;
        
        $html .= '<tr>
                <td>' . $producto['codigo_barras'] . '</td>
                <td>' . htmlspecialchars($producto['nombre']) . '</td>
                <td>' . htmlspecialchars($producto['categoria_nombre'] ?? 'Sin categoría') . '</td>
                <td>' . $producto['stock'] . '</td>
                <td>' . $producto['stock_minimo'] . '</td>
                <td>$' . number_format($producto['precio_compra'], 2) . '</td>
                <td>$' . number_format($producto['precio_venta'], 2) . '</td>
                <td class="' . $estado_class . '">' . $estado . '</td>
            </tr>';
    }
    
    $html .= '</tbody>
        </table>';
    
    $html .= '<div class="footer">
            <p>Total de productos: ' . count($productos) . '</p>
            <p>Valor total del inventario (a costo): $' . number_format($total_valor, 2) . '</p>
            <p>Reporte generado por: ' . htmlspecialchars($_SESSION['usuario_nombre'] ?? 'Sistema') . '</p>
        </div>
    </body>
    </html>';
    
    // Usar mPDF o similar para generar PDF
    // Por ahora, solo mostramos HTML que se puede imprimir como PDF
    echo $html;
    exit();
    
} elseif ($formato == 'excel') {
    // Generar Excel
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="reporte_inventario_' . date('Y-m-d') . '.xls"');
    
    echo '<table border="1">';
    echo '<tr><th colspan="8">REPORTE DE INVENTARIO</th></tr>';
    
    if ($categoria_info) {
        echo '<tr><td colspan="8"><strong>Categoría:</strong> ' . htmlspecialchars($categoria_info['nombre']) . '</td></tr>';
    }
    
    if ($tipo_reporte != 'inventario') {
        $tipo_texto = $tipo_reporte == 'bajos_stock' ? 'Productos Bajos en Stock' : 'Productos Agotados';
        echo '<tr><td colspan="8"><strong>Tipo:</strong> ' . $tipo_texto . '</td></tr>';
    }
    
    echo '<tr><td colspan="8"><strong>Fecha:</strong> ' . date('d/m/Y H:i:s') . '</td></tr>';
    echo '<tr><th>Código</th><th>Producto</th><th>Categoría</th><th>Stock</th><th>Mínimo</th><th>Precio Compra</th><th>Precio Venta</th><th>Estado</th></tr>';
    
    foreach ($productos as $producto) {
        // Determinar estado
        if ($producto['stock'] == 0) {
            $estado = 'Agotado';
        } elseif ($producto['stock'] < $producto['stock_minimo']) {
            $estado = 'Bajo Stock';
        } else {
            $estado = 'Normal';
        }
        
        echo '<tr>';
        echo '<td>' . $producto['codigo_barras'] . '</td>';
        echo '<td>' . htmlspecialchars($producto['nombre']) . '</td>';
        echo '<td>' . htmlspecialchars($producto['categoria_nombre'] ?? 'Sin categoría') . '</td>';
        echo '<td>' . $producto['stock'] . '</td>';
        echo '<td>' . $producto['stock_minimo'] . '</td>';
        echo '<td>$' . number_format($producto['precio_compra'], 2) . '</td>';
        echo '<td>$' . number_format($producto['precio_venta'], 2) . '</td>';
        echo '<td>' . $estado . '</td>';
        echo '</tr>';
    }
    
    echo '</table>';
    exit();
    
} elseif ($formato == 'imprimir') {
    // Vista para imprimir
    require_once 'includes/header.php';
    ?>
    <div class="container">
        <div class="card">
            <div class="card-content">
                <div class="row">
                    <div class="col s12 center-align">
                        <h4>Reporte de Inventario</h4>
                        <?php if ($categoria_info): ?>
                            <h5>Categoría: <?php echo htmlspecialchars($categoria_info['nombre']); ?></h5>
                        <?php endif; ?>
                        <?php if ($tipo_reporte != 'inventario'): ?>
                            <h5>
                                <?php 
                                echo $tipo_reporte == 'bajos_stock' 
                                    ? 'Productos Bajos en Stock' 
                                    : 'Productos Agotados'; 
                                ?>
                            </h5>
                        <?php endif; ?>
                        <p>Fecha: <?php echo date('d/m/Y H:i:s'); ?></p>
                    </div>
                </div>
                
                <table class="striped">
                    <thead>
                        <tr>
                            <th>Código</th>
                            <th>Producto</th>
                            <th>Categoría</th>
                            <th>Stock</th>
                            <th>Mínimo</th>
                            <th>Precio Compra</th>
                            <th>Precio Venta</th>
                            <th>Estado</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php 
                        $total_valor = 0;
                        foreach ($productos as $producto): 
                            // Determinar estado
                            if ($producto['stock'] == 0) {
                                $estado = 'Agotado';
                                $estado_color = 'red-text';
                            } elseif ($producto['stock'] < $producto['stock_minimo']) {
                                $estado = 'Bajo Stock';
                                $estado_color = 'orange-text';
                            } else {
                                $estado = 'Normal';
                                $estado_color = 'green-text';
                            }
                            
                            $valor = $producto['stock'] * $producto['precio_compra'];
                            $total_valor += $valor;
                        ?>
                            <tr>
                                <td><?php echo $producto['codigo_barras']; ?></td>
                                <td><?php echo htmlspecialchars($producto['nombre']); ?></td>
                                <td><?php echo htmlspecialchars($producto['categoria_nombre'] ?? 'Sin categoría'); ?></td>
                                <td><?php echo $producto['stock']; ?></td>
                                <td><?php echo $producto['stock_minimo']; ?></td>
                                <td>$<?php echo number_format($producto['precio_compra'], 2); ?></td>
                                <td>$<?php echo number_format($producto['precio_venta'], 2); ?></td>
                                <td class="<?php echo $estado_color; ?>">
                                    <?php echo $estado; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                    <tfoot>
                        <tr>
                            <td colspan="8" class="center-align">
                                <strong>Total productos: <?php echo count($productos); ?></strong> | 
                                <strong>Valor total: $<?php echo number_format($total_valor, 2); ?></strong>
                            </td>
                        </tr>
                    </tfoot>
                </table>
                
                <div class="row" style="margin-top: 20px;">
                    <div class="col s12 center-align">
                        <button onclick="window.print()" class="btn waves-effect waves-light">
                            <i class="material-icons left">print</i> Imprimir Reporte
                        </button>
                        <a href="productos.php#inventario" class="btn waves-effect waves-light grey">
                            <i class="material-icons left">arrow_back</i> Volver
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <style>
        @media print {
            .btn, .tabs, nav { display: none !important; }
            .card { box-shadow: none; border: 1px solid #ddd; }
            .container { width: 100%; max-width: 100%; }
            h4, h5 { text-align: center; }
            body { font-size: 12px; }
        }
    </style>
    
    <script>
    document.addEventListener('DOMContentLoaded', function() {
        // Auto-imprimir si se desea
        // window.print();
    });
    </script>
    <?php
    require_once 'includes/footer.php';
}
?>