<?php
require_once 'config/database.php';
require_once 'includes/header.php';

if (!isset($_SESSION['usuario_id'])) {
    header('Location: login.php');
    exit();
}

// Filtros
$filtro_producto = $_GET['producto'] ?? '';
$filtro_tipo = $_GET['tipo'] ?? '';
$filtro_fecha = $_GET['fecha'] ?? '';

// Construir consulta con filtros
$sql = "SELECT im.*, p.nombre as producto_nombre, u.nombre as usuario_nombre
        FROM inventario_movimiento im
        JOIN producto p ON im.producto_id = p.codigo_barras
        LEFT JOIN usuario u ON im.usuario_id = u.id
        WHERE 1=1";
        
$params = [];

if ($filtro_producto) {
    $sql .= " AND (p.nombre ILIKE :producto OR p.codigo_barras = :codigo)";
    $params[':producto'] = '%' . $filtro_producto . '%';
    $params[':codigo'] = $filtro_producto;
}

if ($filtro_tipo) {
    $sql .= " AND im.tipo = :tipo";
    $params[':tipo'] = $filtro_tipo;
}

if ($filtro_fecha) {
    $sql .= " AND DATE(im.created_at) = :fecha";
    $params[':fecha'] = $filtro_fecha;
}

$sql .= " ORDER BY im.created_at DESC";

$stmt = $conn->prepare($sql);
$stmt->execute($params);
$movimientos = $stmt->fetchAll(PDO::FETCH_ASSOC);
?>
<div class="container">
    <h4>Movimientos de Inventario</h4>
    
    <div class="card">
        <div class="card-content">
            <!-- Filtros -->
            <form method="GET" class="row">
                <div class="input-field col s12 m4">
                    <input type="text" name="producto" id="producto" value="<?php echo htmlspecialchars($filtro_producto); ?>">
                    <label for="producto">Producto o Código</label>
                </div>
                
                <div class="input-field col s12 m4">
                    <select name="tipo">
                        <option value="">Todos los tipos</option>
                        <option value="entrada" <?php echo $filtro_tipo == 'entrada' ? 'selected' : ''; ?>>Entradas</option>
                        <option value="salida" <?php echo $filtro_tipo == 'salida' ? 'selected' : ''; ?>>Salidas</option>
                        <option value="ajuste" <?php echo $filtro_tipo == 'ajuste' ? 'selected' : ''; ?>>Ajustes</option>
                    </select>
                    <label>Tipo de Movimiento</label>
                </div>
                
                <div class="input-field col s12 m4">
                    <input type="date" name="fecha" id="fecha" value="<?php echo htmlspecialchars($filtro_fecha); ?>">
                    <label for="fecha">Fecha específica</label>
                </div>
                
                <div class="col s12 center-align">
                    <button type="submit" class="btn waves-effect waves-light">
                        <i class="material-icons left">search</i> Filtrar
                    </button>
                    <a href="movimientos_inventario.php" class="btn waves-effect waves-light grey">
                        <i class="material-icons left">clear</i> Limpiar
                    </a>
                </div>
            </form>
            
            <hr>
            
            <!-- Tabla de movimientos -->
            <table class="striped responsive-table">
                <thead>
                    <tr>
                        <th>Fecha/Hora</th>
                        <th>Producto</th>
                        <th>Código</th>
                        <th>Tipo</th>
                        <th>Cantidad</th>
                        <th>Motivo</th>
                        <th>Usuario</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($movimientos as $mov): ?>
                        <tr>
                            <td><?php echo date('d/m/Y H:i', strtotime($mov['created_at'])); ?></td>
                            <td><?php echo htmlspecialchars($mov['producto_nombre']); ?></td>
                            <td><code><?php echo $mov['producto_id']; ?></code></td>
                            <td>
                                <?php if($mov['tipo'] == 'entrada'): ?>
                                    <span class="green-text">
                                        <i class="material-icons tiny">arrow_upward</i> Entrada
                                    </span>
                                <?php elseif($mov['tipo'] == 'salida'): ?>
                                    <span class="red-text">
                                        <i class="material-icons tiny">arrow_downward</i> Salida
                                    </span>
                                <?php else: ?>
                                    <span class="blue-text">
                                        <i class="material-icons tiny">swap_horiz</i> Ajuste
                                    </span>
                                <?php endif; ?>
                            </td>
                            <td><?php echo $mov['cantidad']; ?></td>
                            <td><?php echo htmlspecialchars($mov['motivo']); ?></td>
                            <td><?php echo htmlspecialchars($mov['usuario_nombre'] ?? 'Sistema'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                    
                    <?php if (empty($movimientos)): ?>
                        <tr>
                            <td colspan="7" class="center-align grey-text">
                                No se encontraron movimientos con los filtros aplicados
                            </td>
                        </tr>
                    <?php endif; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    // Inicializar select
    var elems = document.querySelectorAll('select');
    M.FormSelect.init(elems);
    
    // Establecer fecha actual por defecto si no hay filtro
    const fechaInput = document.getElementById('fecha');
    if (fechaInput && !fechaInput.value) {
        const today = new Date().toISOString().split('T')[0];
        fechaInput.value = today;
    }
});
</script>

<?php require_once 'includes/footer.php'; ?>