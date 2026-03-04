<?php
require_once 'config/database.php';
require_once 'includes/header.php';

// Verificar permisos (solo administrador y operador pueden vender)
if ($_SESSION['puesto'] == 'Solo lectura') {
    echo "<script>alert('No tienes permisos para realizar ventas'); window.location.href='dashboard.php';</script>";
    exit();
}

// Procesar venta
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['finalizar_venta'])) {
    try {
        $conn->beginTransaction();
        
        $usuario_id = $_SESSION['user_id'];
        $total = $_POST['total_venta'];
        
        // Crear venta
        $stmt = $conn->prepare("INSERT INTO venta (usuario_id, total) VALUES (:usuario_id, :total) RETURNING folio");
        $stmt->execute([':usuario_id' => $usuario_id, ':total' => $total]);
        $folio = $stmt->fetchColumn();
        
        // Procesar detalles
        $productos = json_decode($_POST['productos_json'], true);
        
        foreach ($productos as $producto) {
            // Insertar detalle
            $stmt = $conn->prepare("INSERT INTO detalle_venta (folio, codigo_barras, cantidad, precio_unitario, subtotal) 
                                   VALUES (:folio, :codigo, :cantidad, :precio, :subtotal)");
            $stmt->execute([
                ':folio' => $folio,
                ':codigo' => $producto['codigo'],
                ':cantidad' => $producto['cantidad'],
                ':precio' => $producto['precio'],
                ':subtotal' => $producto['subtotal']
            ]);
            
            // Actualizar stock
            $stmt = $conn->prepare("UPDATE producto SET stock = stock - :cantidad WHERE codigo_barras = :codigo");
            $stmt->execute([
                ':cantidad' => $producto['cantidad'],
                ':codigo' => $producto['codigo']
            ]);
        }
        
        $conn->commit();
        $mensaje = "Venta realizada exitosamente. Folio: #" . $folio;
        
    } catch (Exception $e) {
        $conn->rollBack();
        $error = "Error al procesar la venta: " . $e->getMessage();
    }
}

// Obtener productos para el select
$productos = $conn->query("SELECT * FROM producto WHERE stock > 0 ORDER BY nombre")->fetchAll();
?>
<div class="container">
    <h4>Punto de Venta</h4>
    
    <?php if(isset($mensaje)): ?>
        <div class="card green lighten-4">
            <div class="card-content green-text">
                <i class="material-icons left">check_circle</i>
                <?php echo $mensaje; ?>
                <a href="ventas.php" class="btn green right">Nueva Venta</a>
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
        <!-- Panel de búsqueda y selección de productos -->
        <div class="col s12 m4">
            <div class="card">
                <div class="card-content">
                    <span class="card-title">Agregar Producto</span>
                    
                    <div class="input-field">
                        <select id="select_producto">
                            <option value="" disabled selected>Seleccionar producto</option>
                            <?php foreach($productos as $producto): ?>
                                <option value="<?php echo $producto['codigo_barras']; ?>"
                                        data-precio="<?php echo $producto['precio_venta']; ?>"
                                        data-nombre="<?php echo htmlspecialchars($producto['nombre']); ?>"
                                        data-stock="<?php echo $producto['stock']; ?>">
                                    <?php echo $producto['nombre']; ?> - $<?php echo number_format($producto['precio_venta'], 2); ?> (Stock: <?php echo $producto['stock']; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <label>Producto</label>
                    </div>
                    
                    <div class="input-field">
                        <input type="number" id="cantidad" value="1" min="1">
                        <label for="cantidad">Cantidad</label>
                    </div>
                    
                    <button class="btn waves-effect waves-light blue" onclick="agregarProducto()">
                        <i class="material-icons left">add_shopping_cart</i>
                        Agregar
                    </button>
                    
                    <button class="btn waves-effect waves-light red right" onclick="limpiarCarrito()">
                        <i class="material-icons left">remove_shopping_cart</i>
                        Limpiar
                    </button>
                </div>
            </div>
            
            <!-- Métodos de pago -->
            <div class="card">
                <div class="card-content">
                    <span class="card-title">Método de Pago</span>
                    <p>
                        <label>
                            <input name="metodo_pago" type="radio" value="efectivo" checked />
                            <span>Efectivo</span>
                        </label>
                    </p>
                    <p>
                        <label>
                            <input name="metodo_pago" type="radio" value="tarjeta" />
                            <span>Tarjeta</span>
                        </label>
                    </p>
                    <p>
                        <label>
                            <input name="metodo_pago" type="radio" value="transferencia" />
                            <span>Transferencia</span>
                        </label>
                    </p>
                </div>
            </div>
        </div>
        
        <!-- Carrito de compras -->
        <div class="col s12 m8">
            <div class="card">
                <div class="card-content">
                    <span class="card-title">Detalles de la Venta</span>
                    
                    <table class="striped" id="tabla_carrito">
                        <thead>
                            <tr>
                                <th>Producto</th>
                                <th>Precio</th>
                                <th>Cantidad</th>
                                <th>Subtotal</th>
                                <th>Acciones</th>
                            </tr>
                        </thead>
                        <tbody id="carrito_body">
                            <!-- Los productos se agregarán aquí dinámicamente -->
                        </tbody>
                        <tfoot>
                            <tr>
                                <td colspan="3" class="right-align"><strong>Total:</strong></td>
                                <td><strong id="total_venta">$0.00</strong></td>
                                <td></td>
                            </tr>
                        </tfoot>
                    </table>
                    
                    <div class="center-align" style="margin-top: 20px;">
                        <form method="POST" id="form_venta">
                            <input type="hidden" name="total_venta" id="input_total">
                            <input type="hidden" name="productos_json" id="productos_json">
                            
                            <button class="btn-large waves-effect waves-light green" type="submit" name="finalizar_venta" id="btn_finalizar" disabled>
                                <i class="material-icons left">check</i>
                                Finalizar Venta
                            </button>
                        </form>
                    </div>
                </div>
            </div>
            
            <!-- Ticket preview -->
            <div class="card">
                <div class="card-content">
                    <span class="card-title">Vista Previa del Ticket</span>
                    <div id="ticket_preview" style="font-family: monospace; background: #f5f5f5; padding: 10px;">
                        <p>================================</p>
                        <p>      TIENDA DE CONVENIENCIA     </p>
                        <p>================================</p>
                        <p>Fecha: <?php echo date('d/m/Y H:i:s'); ?></p>
                        <p>Vendedor: <?php echo $_SESSION['nombre']; ?></p>
                        <p>--------------------------------</p>
                        <div id="ticket_productos"></div>
                        <p>--------------------------------</p>
                        <p>TOTAL: <span id="ticket_total">$0.00</span></p>
                        <p>================================</p>
                        <p>¡GRACIAS POR SU COMPRA!</p>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<script>
let carrito = [];

function agregarProducto() {
    const select = document.getElementById('select_producto');
    const producto = select.options[select.selectedIndex];
    const cantidad = parseInt(document.getElementById('cantidad').value);
    
    if (!producto.value) {
        M.toast({html: 'Selecciona un producto', classes: 'red'});
        return;
    }
    
    if (cantidad < 1) {
        M.toast({html: 'Cantidad inválida', classes: 'red'});
        return;
    }
    
    const stock = parseInt(producto.dataset.stock);
    if (cantidad > stock) {
        M.toast({html: 'Stock insuficiente. Disponible: ' + stock, classes: 'red'});
        return;
    }
    
    const codigo = producto.value;
    const precio = parseFloat(producto.dataset.precio);
    const nombre = producto.dataset.nombre;
    const subtotal = precio * cantidad;
    
    // Verificar si ya existe en el carrito
    const index = carrito.findIndex(item => item.codigo === codigo);
    if (index !== -1) {
        const nuevaCantidad = carrito[index].cantidad + cantidad;
        if (nuevaCantidad > stock) {
            M.toast({html: 'Stock insuficiente', classes: 'red'});
            return;
        }
        carrito[index].cantidad = nuevaCantidad;
        carrito[index].subtotal = precio * nuevaCantidad;
    } else {
        carrito.push({
            codigo: codigo,
            nombre: nombre,
            precio: precio,
            cantidad: cantidad,
            subtotal: subtotal
        });
    }
    
    actualizarCarrito();
    document.getElementById('cantidad').value = 1;
    select.selectedIndex = 0;
    M.FormSelect.init(select);
}

function eliminarProducto(index) {
    carrito.splice(index, 1);
    actualizarCarrito();
}

function actualizarCarrito() {
    const tbody = document.getElementById('carrito_body');
    const totalElement = document.getElementById('total_venta');
    const inputTotal = document.getElementById('input_total');
    const productosJson = document.getElementById('productos_json');
    const btnFinalizar = document.getElementById('btn_finalizar');
    const ticketProductos = document.getElementById('ticket_productos');
    const ticketTotal = document.getElementById('ticket_total');
    
    // Limpiar tabla
    tbody.innerHTML = '';
    ticketProductos.innerHTML = '';
    
    let total = 0;
    
    // Llenar tabla
    carrito.forEach((item, index) => {
        const row = tbody.insertRow();
        row.innerHTML = `
            <td>${item.nombre}</td>
            <td>$${item.precio.toFixed(2)}</td>
            <td>${item.cantidad}</td>
            <td>$${item.subtotal.toFixed(2)}</td>
            <td>
                <button class="btn red btn-small" onclick="eliminarProducto(${index})">
                    <i class="material-icons">delete</i>
                </button>
            </td>
        `;
        
        // Ticket preview
        ticketProductos.innerHTML += `
            <p>${item.nombre.substring(0, 20)}</p>
            <p>${item.cantidad} x $${item.precio.toFixed(2)} = $${item.subtotal.toFixed(2)}</p>
        `;
        
        total += item.subtotal;
    });
    
    // Actualizar totales
    totalElement.textContent = '$' + total.toFixed(2);
    inputTotal.value = total;
    ticketTotal.textContent = '$' + total.toFixed(2);
    
    // Actualizar JSON
    productosJson.value = JSON.stringify(carrito);
    
    // Habilitar/deshabilitar botón
    btnFinalizar.disabled = carrito.length === 0;
}

function limpiarCarrito() {
    if (confirm('¿Está seguro de limpiar el carrito?')) {
        carrito = [];
        actualizarCarrito();
    }
}

// Inicializar select
document.addEventListener('DOMContentLoaded', function() {
    M.FormSelect.init(document.querySelectorAll('select'));
});
</script>

<?php require_once 'includes/footer.php'; ?>