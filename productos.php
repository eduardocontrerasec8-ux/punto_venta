<?php
require_once 'config/database.php';
require_once 'includes/header.php';

// Agregar producto
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['agregar'])) {
    $codigo_barras = $_POST['codigo_barras'];
    $nombre = $_POST['nombre'];
    $precio_compra = $_POST['precio_compra'];
    $precio_venta = $_POST['precio_venta'];
    $stock = $_POST['stock'];
    $stock_minimo = $_POST['stock_minimo'];
    $proveedor_id = $_POST['proveedor_id'];
    
    $stmt = $conn->prepare("INSERT INTO producto (codigo_barras, nombre, precio_compra, precio_venta, stock, stock_minimo, proveedor_id) 
                            VALUES (:codigo, :nombre, :compra, :venta, :stock, :minimo, :proveedor)");
    $stmt->execute([
        ':codigo' => $codigo_barras,
        ':nombre' => $nombre,
        ':compra' => $precio_compra,
        ':venta' => $precio_venta,
        ':stock' => $stock,
        ':minimo' => $stock_minimo,
        ':proveedor' => $proveedor_id
    ]);
}

// Obtener productos
$productos = $conn->query("SELECT p.*, pr.nombre as proveedor_nombre 
                          FROM producto p 
                          LEFT JOIN proveedor pr ON p.proveedor_id = pr.id")->fetchAll();

// Obtener proveedores para el select
$proveedores = $conn->query("SELECT * FROM proveedor")->fetchAll();
?>
<div class="container">
    <h4>Gestión de Productos</h4>
    
    <!-- Formulario para agregar producto -->
    <div class="card">
        <div class="card-content">
            <span class="card-title">Agregar Producto</span>
            <form method="POST">
                <div class="row">
                    <div class="input-field col s12 m6">
                        <input id="codigo_barras" type="text" name="codigo_barras" required>
                        <label for="codigo_barras">Código de Barras</label>
                    </div>
                    <div class="input-field col s12 m6">
                        <input id="nombre" type="text" name="nombre" required>
                        <label for="nombre">Nombre del Producto</label>
                    </div>
                </div>
                <div class="row">
                    <div class="input-field col s6">
                        <input id="precio_compra" type="number" step="0.01" name="precio_compra" required>
                        <label for="precio_compra">Precio de Compra</label>
                    </div>
                    <div class="input-field col s6">
                        <input id="precio_venta" type="number" step="0.01" name="precio_venta" required>
                        <label for="precio_venta">Precio de Venta</label>
                    </div>
                </div>
                <div class="row">
                    <div class="input-field col s6">
                        <input id="stock" type="number" name="stock" required>
                        <label for="stock">Stock Inicial</label>
                    </div>
                    <div class="input-field col s6">
                        <input id="stock_minimo" type="number" name="stock_minimo" value="5">
                        <label for="stock_minimo">Stock Mínimo</label>
                    </div>
                </div>
                <div class="row">
                    <div class="input-field col s12">
                        <select name="proveedor_id">
                            <option value="">Seleccionar Proveedor</option>
                            <?php foreach($proveedores as $proveedor): ?>
                                <option value="<?php echo $proveedor['id']; ?>">
                                    <?php echo $proveedor['nombre']; ?>
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label>Proveedor</label>
                    </div>
                </div>
                <button class="btn waves-effect waves-light" type="submit" name="agregar">
                    Agregar Producto
                    <i class="material-icons right">add</i>
                </button>
            </form>
        </div>
    </div>
    
    <!-- Tabla de productos -->
    <div class="card">
        <div class="card-content">
            <span class="card-title">Lista de Productos</span>
            <table class="striped responsive-table">
                <thead>
                    <tr>
                        <th>Código</th>
                        <th>Nombre</th>
                        <th>Precio Compra</th>
                        <th>Precio Venta</th>
                        <th>Stock</th>
                        <th>Proveedor</th>
                        <th>Estado</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach($productos as $producto): ?>
                        <tr class="<?php echo $producto['stock'] < $producto['stock_minimo'] ? 'red lighten-4' : ''; ?>">
                            <td><?php echo $producto['codigo_barras']; ?></td>
                            <td><?php echo $producto['nombre']; ?></td>
                            <td>$<?php echo number_format($producto['precio_compra'], 2); ?></td>
                            <td>$<?php echo number_format($producto['precio_venta'], 2); ?></td>
                            <td><?php echo $producto['stock']; ?></td>
                            <td><?php echo $producto['proveedor_nombre'] ?? 'N/A'; ?></td>
                            <td>
                                <?php if($producto['stock'] < $producto['stock_minimo']): ?>
                                    <span class="red-text">Bajo Stock</span>
                                <?php else: ?>
                                    <span class="green-text">OK</span>
                                <?php endif; ?>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    var elems = document.querySelectorAll('select');
    var instances = M.FormSelect.init(elems);
});
</script>

<?php require_once 'includes/footer.php'; ?>