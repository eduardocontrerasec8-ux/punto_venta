<?php
require_once 'config/database.php';
require_once 'includes/header.php';

$mensaje = '';
$tipo_mensaje = '';

// Agregar producto
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['agregar'])) {
    $codigo_barras = $_POST['codigo_barras'];
    $nombre        = $_POST['nombre'];
    $precio_compra = $_POST['precio_compra'];
    $precio_venta  = $_POST['precio_venta'];
    $stock         = $_POST['stock'];
    $stock_minimo  = $_POST['stock_minimo'];
    $proveedor_id  = $_POST['proveedor_id'];
    $categoria_id  = $_POST['categoria_id'];
    try {
        $conn->beginTransaction();
        $stmt = $conn->prepare("INSERT INTO producto (codigo_barras, nombre, precio_compra, precio_venta, stock, stock_minimo, proveedor_id, categoria_id) VALUES (:codigo, :nombre, :compra, :venta, :stock, :minimo, :proveedor, :categoria)");
        $stmt->execute([':codigo'=>$codigo_barras,':nombre'=>$nombre,':compra'=>$precio_compra,':venta'=>$precio_venta,':stock'=>$stock,':minimo'=>$stock_minimo,':proveedor'=>$proveedor_id,':categoria'=>$categoria_id]);
        $stmt_mov = $conn->prepare("INSERT INTO inventario_movimiento (producto_id, tipo, cantidad, motivo, usuario_id) VALUES (:producto_id, 'entrada', :cantidad, 'Stock inicial', :usuario_id)");
        $stmt_mov->execute([':producto_id'=>$codigo_barras,':cantidad'=>$stock,':usuario_id'=>isset($_SESSION['usuario_id'])?$_SESSION['usuario_id']:1]);
        $conn->commit();
        $mensaje = 'Producto agregado correctamente'; $tipo_mensaje = 'success';
    } catch (PDOException $e) {
        $conn->rollBack();
        $mensaje = $e->getCode()=='23505' ? 'El código de barras ya existe en el sistema' : 'Error al agregar: '.$e->getMessage();
        $tipo_mensaje = 'error';
    }
}

// Actualizar producto
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['actualizar'])) {
    try {
        $stmt = $conn->prepare("UPDATE producto SET nombre=:nombre, precio_compra=:compra, precio_venta=:venta, stock_minimo=:minimo, proveedor_id=:proveedor, categoria_id=:categoria WHERE codigo_barras=:codigo");
        $stmt->execute([':codigo'=>$_POST['codigo_barras'],':nombre'=>$_POST['nombre'],':compra'=>$_POST['precio_compra'],':venta'=>$_POST['precio_venta'],':minimo'=>$_POST['stock_minimo'],':proveedor'=>$_POST['proveedor_id'],':categoria'=>$_POST['categoria_id']]);
        $mensaje = 'Producto actualizado correctamente'; $tipo_mensaje = 'success';
    } catch (PDOException $e) {
        $mensaje = 'Error al actualizar: '.$e->getMessage(); $tipo_mensaje = 'error';
    }
}

// Agregar cantidad
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['agregar_cantidad'])) {
    try {
        $conn->beginTransaction();
        $conn->prepare("UPDATE producto SET stock=stock+:cantidad WHERE codigo_barras=:codigo")->execute([':cantidad'=>$_POST['cantidad'],':codigo'=>$_POST['codigo_barras']]);
        $conn->prepare("INSERT INTO inventario_movimiento (producto_id, tipo, cantidad, motivo, usuario_id) VALUES (:pid,'entrada',:cantidad,:motivo,:uid)")->execute([':pid'=>$_POST['codigo_barras'],':cantidad'=>$_POST['cantidad'],':motivo'=>$_POST['motivo'],':uid'=>isset($_SESSION['usuario_id'])?$_SESSION['usuario_id']:1]);
        $conn->commit();
        $mensaje = 'Cantidad agregada correctamente'; $tipo_mensaje = 'success';
    } catch (PDOException $e) {
        $conn->rollBack(); $mensaje = 'Error: '.$e->getMessage(); $tipo_mensaje = 'error';
    }
}

// Eliminar
if (isset($_GET['eliminar'])) {
    $codigo_barras = $_GET['eliminar'];
    try {
        $check = $conn->prepare("SELECT COUNT(*) as total FROM detalle_venta WHERE codigo_barras=:codigo");
        $check->execute([':codigo'=>$codigo_barras]);
        if ($check->fetch(PDO::FETCH_ASSOC)['total'] > 0) {
            $mensaje = 'No se puede eliminar: el producto tiene ventas asociadas'; $tipo_mensaje = 'error';
        } else {
            $conn->prepare("DELETE FROM producto WHERE codigo_barras=:codigo")->execute([':codigo'=>$codigo_barras]);
            $mensaje = 'Producto eliminado correctamente'; $tipo_mensaje = 'success';
        }
    } catch (PDOException $e) {
        $mensaje = 'Error al eliminar: '.$e->getMessage(); $tipo_mensaje = 'error';
    }
}

$productos   = $conn->query("SELECT p.*, pr.nombre as proveedor_nombre, c.nombre as categoria_nombre FROM producto p LEFT JOIN proveedor pr ON p.proveedor_id=pr.id LEFT JOIN categoria c ON p.categoria_id=c.id ORDER BY p.nombre")->fetchAll();
$proveedores = $conn->query("SELECT * FROM proveedor ORDER BY nombre")->fetchAll();
$categorias  = $conn->query("SELECT * FROM categoria ORDER BY nombre")->fetchAll();

// Stats inventario
$total_prods = count($productos);
$stock_total = $bajos = $agotados = $val_compra = $val_venta = 0;
foreach ($productos as $p) {
    $stock_total += $p['stock'];
    $val_compra  += $p['stock'] * $p['precio_compra'];
    $val_venta   += $p['stock'] * $p['precio_venta'];
    if ($p['stock'] == 0) $agotados++;
    elseif ($p['stock'] < $p['stock_minimo']) $bajos++;
}
$ganancia = $val_venta - $val_compra;
$margen   = $val_compra > 0 ? ($ganancia / $val_compra) * 100 : 0;
?>

<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">

<style>
:root {
  --azul1: #0d47a1;
  --azul2: #1565c0;
  --azul3: #1976d2;
  --azul4: #42a5f5;
  --azul5: #bbdefb;
  --azul6: #e8f0fe;
  --verde: #2e7d32;
  --verde2: #43a047;
  --verde3: #e8f5e9;
  --naranja: #e65100;
  --naranja2: #fff3e0;
  --rojo: #c62828;
  --rojo2: #ffebee;
  --texto: #1a237e;
  --muted: #5c6bc0;
  --borde: #c5cae9;
  --fondo: #f0f4ff;
  --blanco: #ffffff;
  --sombra: 0 4px 20px rgba(21,101,192,.13);
  --sombra2: 0 8px 32px rgba(21,101,192,.2);
  --r: 14px;
  --font: 'Nunito', sans-serif;
}

body { font-family: var(--font); background: var(--fondo); }

/* ── Page header ── */
.pg-header {
  background: linear-gradient(135deg, var(--azul1) 0%, var(--azul2) 55%, var(--azul3) 100%);
  border-radius: var(--r);
  padding: 24px 28px;
  margin: 24px 0 20px;
  display: flex;
  align-items: center;
  justify-content: space-between;
  box-shadow: var(--sombra2);
  position: relative;
  overflow: hidden;
}
.pg-header::after {
  content: '';
  position: absolute;
  right: -40px; top: -40px;
  width: 160px; height: 160px;
  border-radius: 50%;
  background: rgba(255,255,255,.07);
}
.pg-header::before {
  content: '';
  position: absolute;
  right: 60px; bottom: -30px;
  width: 100px; height: 100px;
  border-radius: 50%;
  background: rgba(255,255,255,.05);
}
.pg-header h4 {
  color: #fff;
  font-size: 1.6rem;
  font-weight: 900;
  margin: 0;
  display: flex;
  align-items: center;
  gap: 10px;
}
.pg-header h4 i { font-size: 2rem; opacity: .9; }
.pg-header p { color: rgba(255,255,255,.7); margin: 4px 0 0; font-size: .85rem; font-weight: 600; }
.pg-badge {
  background: rgba(255,255,255,.2);
  border: 1px solid rgba(255,255,255,.35);
  border-radius: 20px;
  color: #fff;
  padding: 6px 18px;
  font-size: .8rem;
  font-weight: 800;
  display: flex;
  align-items: center;
  gap: 6px;
  position: relative;
  z-index: 1;
}

/* ── Mensajes ── */
.msg-box {
  border-radius: 10px;
  padding: 14px 18px;
  margin-bottom: 16px;
  display: flex;
  align-items: center;
  gap: 12px;
  font-weight: 700;
  font-size: .92rem;
  animation: slideIn .3s ease;
}
@keyframes slideIn { from{transform:translateY(-10px);opacity:0} to{transform:translateY(0);opacity:1} }
.msg-ok  { background: var(--verde3); color: var(--verde);  border: 2px solid #a5d6a7; }
.msg-err { background: var(--rojo2);  color: var(--rojo);   border: 2px solid #ffcdd2; }
.msg-box i { font-size: 1.4rem; }

/* ── Tabs mejorados ── */
.tabs-wrapper {
  background: var(--blanco);
  border-radius: var(--r);
  box-shadow: var(--sombra);
  overflow: hidden;
  margin-bottom: 20px;
}
.tabs-wrapper .tabs {
  background: linear-gradient(135deg, var(--azul1), var(--azul2));
  border-radius: 0;
  height: 56px;
}
.tabs-wrapper .tabs .tab a {
  color: rgba(255,255,255,.65) !important;
  font-weight: 700;
  font-size: .85rem;
  height: 56px;
  line-height: 56px;
  letter-spacing: .03em;
}
.tabs-wrapper .tabs .tab a:hover,
.tabs-wrapper .tabs .tab a.active {
  color: #fff !important;
  background: rgba(255,255,255,.1) !important;
}
.tabs-wrapper .tabs .indicator {
  background-color: #fff;
  height: 3px;
  bottom: 0;
}

/* ── Cards ── */
.prod-card {
  background: var(--blanco);
  border-radius: var(--r);
  box-shadow: var(--sombra);
  border: 1px solid var(--borde);
  overflow: hidden;
  margin-top: 0 !important;
}
.prod-card .card-header {
  background: linear-gradient(135deg, var(--azul6), #dce8ff);
  padding: 18px 22px;
  border-bottom: 2px solid var(--borde);
  display: flex;
  align-items: center;
  gap: 10px;
}
.prod-card .card-header i { color: var(--azul2); font-size: 1.4rem; }
.prod-card .card-header h5 { margin: 0; color: var(--texto); font-size: 1rem; font-weight: 800; }
.prod-card .card-body { padding: 22px; }

/* ── Formularios ── */
.field-group {
  background: var(--fondo);
  border: 2px solid var(--borde);
  border-radius: 10px;
  padding: 16px 18px 8px;
  margin-bottom: 16px;
  transition: border-color .2s;
}
.field-group:focus-within {
  border-color: var(--azul3);
  background: #fff;
}
.field-group label.flbl {
  display: block;
  font-size: .68rem;
  font-weight: 800;
  letter-spacing: .07em;
  text-transform: uppercase;
  color: var(--muted);
  margin-bottom: 4px;
}
.field-group .field-icon {
  display: flex;
  align-items: center;
  gap: 10px;
}
.field-group .field-icon i { color: var(--azul3); font-size: 1.1rem; flex-shrink: 0; }
.field-group input,
.field-group select,
.field-group textarea {
  border: none !important;
  background: transparent !important;
  box-shadow: none !important;
  margin: 0 !important;
  padding: 0 !important;
  height: auto !important;
  font-family: var(--font) !important;
  font-size: .95rem !important;
  font-weight: 600 !important;
  color: var(--texto) !important;
  width: 100%;
}
.field-group input:focus,
.field-group select:focus { outline: none; }
.field-group .helper { font-size: .72rem; color: var(--muted); margin-top: 3px; }

/* Selects en Materialize dentro de field-group */
.field-group .select-wrapper input.select-dropdown {
  border-bottom: none !important;
}

/* ── Botones ── */
.btn-pri {
  background: linear-gradient(135deg, var(--azul2), var(--azul3));
  color: #fff;
  border: none;
  border-radius: 9px;
  padding: 12px 26px;
  font-size: .88rem;
  font-weight: 800;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 7px;
  font-family: var(--font);
  transition: .17s;
  box-shadow: 0 4px 14px rgba(21,101,192,.3);
  text-decoration: none;
}
.btn-pri:hover { transform: translateY(-2px); box-shadow: 0 7px 20px rgba(21,101,192,.4); color: #fff; }
.btn-sec {
  background: var(--blanco);
  color: var(--muted);
  border: 2px solid var(--borde);
  border-radius: 9px;
  padding: 11px 22px;
  font-size: .88rem;
  font-weight: 700;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  gap: 7px;
  font-family: var(--font);
  transition: .15s;
}
.btn-sec:hover { border-color: var(--azul3); color: var(--azul2); }
.btn-sm {
  width: 30px; height: 30px;
  border-radius: 7px;
  border: none;
  cursor: pointer;
  display: inline-flex;
  align-items: center;
  justify-content: center;
  transition: .13s;
  text-decoration: none;
}
.btn-sm i { font-size: .88rem; }
.btn-sm.add  { background: var(--verde3); color: var(--verde2); border: 1.5px solid #a5d6a7; }
.btn-sm.add:hover  { background: var(--verde2); color: #fff; }
.btn-sm.edit { background: var(--azul6); color: var(--azul2); border: 1.5px solid var(--borde); }
.btn-sm.edit:hover { background: var(--azul2); color: #fff; }
.btn-sm.del  { background: var(--rojo2); color: var(--rojo); border: 1.5px solid #ffcdd2; }
.btn-sm.del:hover  { background: var(--rojo); color: #fff; }

/* ── Buscador ── */
.search-box {
  position: relative;
  margin-bottom: 18px;
}
.search-box i {
  position: absolute;
  left: 14px;
  top: 50%;
  transform: translateY(-50%);
  color: var(--muted);
  font-size: 1.1rem;
}
.search-box input {
  width: 100%;
  border: 2px solid var(--borde);
  border-radius: 10px;
  padding: 11px 16px 11px 42px;
  font-size: .9rem;
  font-family: var(--font);
  font-weight: 600;
  outline: none;
  transition: .15s;
  color: var(--texto);
  background: var(--blanco);
}
.search-box input:focus { border-color: var(--azul3); box-shadow: 0 0 0 3px rgba(66,165,245,.15); }
.search-box input::placeholder { color: #aaa; font-weight: 400; }

/* ── Tabla ── */
.tabla-wrap { overflow-x: auto; border-radius: 10px; border: 1px solid var(--borde); }
table.prod-table { width: 100%; border-collapse: collapse; }
table.prod-table thead th {
  background: linear-gradient(135deg, #283593, var(--azul1));
  color: rgba(255,255,255,.78);
  padding: 11px 14px;
  font-size: .7rem;
  font-weight: 800;
  letter-spacing: .08em;
  text-transform: uppercase;
  white-space: nowrap;
}
table.prod-table tbody tr { border-bottom: 1px solid #e8edf8; transition: .08s; }
table.prod-table tbody tr:hover { background: rgba(66,165,245,.07); }
table.prod-table tbody tr.row-agotado { background: #fff8f8; }
table.prod-table tbody tr.row-bajo    { background: #fffdf5; }
table.prod-table td { padding: 10px 14px; font-size: .88rem; vertical-align: middle; color: var(--texto); }
.cod-badge {
  font-family: 'Courier New', monospace;
  font-size: .78rem;
  font-weight: 700;
  background: var(--azul6);
  color: var(--azul2);
  border: 1px solid var(--borde);
  border-radius: 5px;
  padding: 2px 7px;
  display: inline-block;
}
.nombre-cell { font-weight: 700; }
.precio-cell { font-weight: 800; color: var(--verde); }
.precio-compra-cell { color: var(--muted); font-size: .82rem; }
.stock-pill {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  border-radius: 20px;
  padding: 3px 11px;
  font-size: .8rem;
  font-weight: 800;
}
.stock-ok     { background: var(--verde3); color: var(--verde);   }
.stock-bajo   { background: var(--naranja2); color: var(--naranja); }
.stock-agotado{ background: var(--rojo2);  color: var(--rojo);    }
.min-text { font-size: .7rem; color: #aaa; margin-top: 2px; }

.est-badge {
  display: inline-flex;
  align-items: center;
  gap: 4px;
  font-size: .77rem;
  font-weight: 800;
  padding: 3px 9px;
  border-radius: 6px;
}
.est-ok  { background: var(--verde3); color: var(--verde); }
.est-bajo{ background: var(--naranja2); color: var(--naranja); }
.est-ago { background: var(--rojo2); color: var(--rojo); }

.acciones-cell { display: flex; gap: 5px; align-items: center; }

/* ── Stats inventario ── */
.stat-grid {
  display: grid;
  grid-template-columns: repeat(4, 1fr);
  gap: 14px;
  margin-bottom: 20px;
}
@media(max-width:900px){ .stat-grid{ grid-template-columns:repeat(2,1fr); } }
.stat-card {
  background: var(--blanco);
  border-radius: 12px;
  padding: 18px;
  box-shadow: var(--sombra);
  border: 1px solid var(--borde);
  display: flex;
  align-items: center;
  gap: 14px;
  transition: .15s;
}
.stat-card:hover { transform: translateY(-2px); box-shadow: var(--sombra2); }
.stat-ico {
  width: 52px; height: 52px;
  border-radius: 12px;
  display: flex;
  align-items: center;
  justify-content: center;
  flex-shrink: 0;
}
.stat-ico i { font-size: 1.6rem; }
.stat-val { font-size: 1.6rem; font-weight: 900; line-height: 1; }
.stat-lbl { font-size: .75rem; font-weight: 700; color: var(--muted); margin-top: 3px; }

.val-grid {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 14px;
  margin-top: 4px;
}
.val-card {
  background: var(--blanco);
  border-radius: 12px;
  padding: 22px;
  box-shadow: var(--sombra);
  border: 1px solid var(--borde);
  text-align: center;
}
.val-card .val-ico { font-size: 2.2rem; margin-bottom: 8px; display: block; }
.val-card .val-amt { font-size: 1.8rem; font-weight: 900; }
.val-card .val-lbl { font-size: .78rem; font-weight: 700; color: var(--muted); margin-top: 4px; }
.val-card .val-sub { font-size: .82rem; font-weight: 700; margin-top: 8px; }

/* ── Categorías ── */
.cat-list { list-style: none; padding: 0; margin: 0; }
.cat-item {
  display: flex;
  align-items: center;
  justify-content: space-between;
  padding: 12px 16px;
  border-bottom: 1px solid var(--borde);
  transition: .1s;
}
.cat-item:last-child { border-bottom: none; }
.cat-item:hover { background: var(--azul6); }
.cat-item .cat-name { font-weight: 700; display: flex; align-items: center; gap: 8px; }
.cat-item .cat-name i { color: var(--azul3); font-size: 1rem; }
.cat-item .cat-cnt {
  background: var(--azul6);
  color: var(--azul2);
  border-radius: 12px;
  padding: 2px 10px;
  font-size: .72rem;
  font-weight: 800;
}
.cat-item .cat-desc { font-size: .75rem; color: var(--muted); margin-top: 2px; }
.cat-item .cat-actions { display: flex; gap: 6px; }

/* ── Reporte panel ── */
.rep-panel {
  background: linear-gradient(135deg, var(--azul6), #dce8ff);
  border: 2px solid var(--borde);
  border-radius: 12px;
  padding: 20px;
  margin-bottom: 20px;
}
.rep-panel h6 { font-weight: 800; color: var(--texto); margin-bottom: 14px; display: flex; align-items: center; gap: 7px; }
.rep-panel h6 i { color: var(--azul3); }

/* ── Modales ── */
.modal {
  border-radius: var(--r) !important;
  max-height: 88% !important;
  overflow-y: auto !important;
}
.modal .modal-header {
  background: linear-gradient(135deg, var(--azul1), var(--azul2));
  padding: 18px 24px;
  color: #fff;
}
.modal .modal-header h5 { margin: 0; font-size: 1rem; font-weight: 800; display: flex; align-items: center; gap: 8px; }
.modal .modal-header p { margin: 3px 0 0; font-size: .8rem; opacity: .7; }
.modal .modal-body { padding: 22px 24px; }
.modal .modal-body .info-row {
  display: flex;
  gap: 16px;
  background: var(--azul6);
  border-radius: 8px;
  padding: 12px 14px;
  margin-bottom: 16px;
}
.modal .modal-body .info-row .ir {
  font-size: .8rem; color: var(--muted); font-weight: 600;
}
.modal .modal-body .info-row .ir strong { color: var(--texto); font-size: .9rem; display: block; margin-top: 2px; }
.modal-footer-custom {
  padding: 14px 24px;
  border-top: 2px solid var(--borde);
  display: flex;
  justify-content: flex-end;
  gap: 10px;
  background: var(--fondo);
}

/* Tabs contenido sin padding extra */
.tab-content { padding: 20px 0 0; }
</style>

<div class="container" style="padding-bottom:40px;">

  <!-- Page Header -->
  <div class="pg-header">
    <div>
      <h4><i class="material-icons">inventory_2</i> Gestión de Productos</h4>
      <p><?php echo $total_prods; ?> productos registrados en el sistema</p>
    </div>
    <div class="pg-badge">
      <i class="material-icons" style="font-size:1rem;">storefront</i>
      Inventario Activo
    </div>
  </div>

  <!-- Mensaje -->
  <?php if ($mensaje): ?>
    <div class="msg-box <?php echo $tipo_mensaje === 'success' ? 'msg-ok' : 'msg-err'; ?>">
      <i class="material-icons"><?php echo $tipo_mensaje === 'success' ? 'check_circle' : 'error'; ?></i>
      <?php echo htmlspecialchars($mensaje, ENT_QUOTES, 'UTF-8'); ?>
    </div>
  <?php endif; ?>

  <!-- Tabs -->
  <div class="tabs-wrapper">
    <ul class="tabs" id="main-tabs">
      <li class="tab col s3"><a class="active" href="#tab-agregar"><i class="material-icons left">add_circle</i>Agregar</a></li>
      <li class="tab col s3"><a href="#tab-lista"><i class="material-icons left">view_list</i>Productos</a></li>
      <li class="tab col s3"><a href="#tab-categorias"><i class="material-icons left">category</i>Categorías</a></li>
      <li class="tab col s3"><a href="#tab-inventario"><i class="material-icons left">analytics</i>Inventario</a></li>
    </ul>
  </div>

  <!-- ══ TAB 1: AGREGAR ══ -->
  <div id="tab-agregar" class="tab-content">
    <div class="prod-card">
      <div class="card-header">
        <i class="material-icons">add_box</i>
        <h5>Agregar Nuevo Producto</h5>
      </div>
      <div class="card-body">
        <form method="POST" id="formAgregarProducto">
          <div class="row" style="margin-bottom:0;">

            <div class="col s12 m6">
              <div class="field-group">
                <label class="flbl">Código de Barras *</label>
                <div class="field-icon">
                  <i class="material-icons">qr_code</i>
                  <input type="text" name="codigo_barras" required placeholder="Ej: 7501000000001">
                </div>
                <div class="helper">Identificador único del producto</div>
              </div>
            </div>

            <div class="col s12 m6">
              <div class="field-group">
                <label class="flbl">Nombre del Producto *</label>
                <div class="field-icon">
                  <i class="material-icons">label</i>
                  <input type="text" name="nombre" required placeholder="Nombre completo del producto">
                </div>
              </div>
            </div>

            <div class="col s12 m6">
              <div class="field-group">
                <label class="flbl">Precio de Compra *</label>
                <div class="field-icon">
                  <i class="material-icons">attach_money</i>
                  <input type="number" id="precio_compra" step="0.01" name="precio_compra" required min="0" placeholder="0.00">
                </div>
              </div>
            </div>

            <div class="col s12 m6">
              <div class="field-group">
                <label class="flbl">Precio de Venta *</label>
                <div class="field-icon">
                  <i class="material-icons">monetization_on</i>
                  <input type="number" id="precio_venta" step="0.01" name="precio_venta" required min="0" placeholder="0.00">
                </div>
              </div>
            </div>

            <div class="col s12 m6">
              <div class="field-group">
                <label class="flbl">Stock Inicial *</label>
                <div class="field-icon">
                  <i class="material-icons">layers</i>
                  <input type="number" name="stock" required min="0" placeholder="0">
                </div>
              </div>
            </div>

            <div class="col s12 m6">
              <div class="field-group">
                <label class="flbl">Stock Mínimo *</label>
                <div class="field-icon">
                  <i class="material-icons">warning_amber</i>
                  <input type="number" id="stock_minimo" name="stock_minimo" required min="0" value="5">
                </div>
                <div class="helper">Alerta cuando el stock baje de este valor</div>
              </div>
            </div>

            <div class="col s12 m6">
              <div class="field-group">
                <label class="flbl">Proveedor</label>
                <div class="field-icon">
                  <i class="material-icons">local_shipping</i>
                  <select name="proveedor_id">
                    <option value="">Sin proveedor</option>
                    <?php foreach ($proveedores as $p): ?>
                      <option value="<?php echo $p['id']; ?>"><?php echo htmlspecialchars($p['nombre']); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
            </div>

            <div class="col s12 m6">
              <div class="field-group">
                <label class="flbl">Categoría *</label>
                <div class="field-icon">
                  <i class="material-icons">category</i>
                  <select name="categoria_id" required>
                    <option value="" disabled selected>Seleccionar…</option>
                    <?php foreach ($categorias as $c): ?>
                      <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['nombre']); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
            </div>

            <div class="col s12" style="margin-top:8px;display:flex;gap:10px;justify-content:flex-end;">
              <button type="reset" class="btn-sec">
                <i class="material-icons">clear</i> Limpiar
              </button>
              <button type="submit" name="agregar" class="btn-pri">
                <i class="material-icons">save</i> Guardar Producto
              </button>
            </div>

          </div>
        </form>
      </div>
    </div>
  </div>

  <!-- ══ TAB 2: LISTA ══ -->
  <div id="tab-lista" class="tab-content">
    <div class="prod-card">
      <div class="card-header">
        <i class="material-icons">view_list</i>
        <h5>Lista de Productos</h5>
        <span style="margin-left:auto;background:var(--azul2);color:#fff;border-radius:20px;padding:3px 13px;font-size:.78rem;font-weight:800;">
          <?php echo $total_prods; ?> productos
        </span>
      </div>
      <div class="card-body">

        <div class="search-box">
          <i class="material-icons">search</i>
          <input type="text" id="buscar_producto" placeholder="Buscar por nombre, código o categoría…">
        </div>

        <div class="tabla-wrap">
          <table class="prod-table" id="tabla_productos">
            <thead>
              <tr>
                <th>Código</th>
                <th>Producto</th>
                <th>P. Compra</th>
                <th>P. Venta</th>
                <th>Stock</th>
                <th>Categoría</th>
                <th>Estado</th>
                <th>Acciones</th>
              </tr>
            </thead>
            <tbody>
              <?php foreach ($productos as $prod):
                if ($prod['stock'] == 0) {
                  $fila = 'row-agotado';
                  $estadoClass = 'est-ago';
                  $estadoIcon  = 'block';
                  $estadoTxt   = 'Agotado';
                  $stockClass  = 'stock-agotado';
                } elseif ($prod['stock'] < $prod['stock_minimo']) {
                  $fila = 'row-bajo';
                  $estadoClass = 'est-bajo';
                  $estadoIcon  = 'warning';
                  $estadoTxt   = 'Bajo Stock';
                  $stockClass  = 'stock-bajo';
                } else {
                  $fila = '';
                  $estadoClass = 'est-ok';
                  $estadoIcon  = 'check_circle';
                  $estadoTxt   = 'OK';
                  $stockClass  = 'stock-ok';
                }
                $cod = htmlspecialchars($prod['codigo_barras'], ENT_QUOTES, 'UTF-8');
              ?>
              <tr class="<?php echo $fila; ?>"
                  data-nombre="<?php echo strtolower($prod['nombre']); ?>"
                  data-codigo="<?php echo $prod['codigo_barras']; ?>"
                  data-categoria="<?php echo strtolower($prod['categoria_nombre'] ?? ''); ?>">
                <td><span class="cod-badge"><?php echo $cod; ?></span></td>
                <td class="nombre-cell"><?php echo htmlspecialchars($prod['nombre']); ?></td>
                <td class="precio-compra-cell">$<?php echo number_format($prod['precio_compra'], 2); ?></td>
                <td class="precio-cell">$<?php echo number_format($prod['precio_venta'], 2); ?></td>
                <td>
                  <span class="stock-pill <?php echo $stockClass; ?>">
                    <i class="material-icons" style="font-size:.75rem;"><?php echo $estadoIcon; ?></i>
                    <?php echo $prod['stock']; ?>
                  </span>
                  <?php if ($prod['stock_minimo'] > 0): ?>
                    <div class="min-text">Mín: <?php echo $prod['stock_minimo']; ?></div>
                  <?php endif; ?>
                </td>
                <td><?php echo htmlspecialchars($prod['categoria_nombre'] ?? 'Sin categoría'); ?></td>
                <td>
                  <span class="est-badge <?php echo $estadoClass; ?>">
                    <i class="material-icons" style="font-size:.78rem;"><?php echo $estadoIcon; ?></i>
                    <?php echo $estadoTxt; ?>
                  </span>
                </td>
                <td>
                  <div class="acciones-cell">
                    <a href="#modal-add-<?php echo $cod; ?>" class="btn-sm add modal-trigger tooltipped" data-position="top" data-tooltip="Agregar stock">
                      <i class="material-icons">add</i>
                    </a>
                    <a href="#modal-edit-<?php echo $cod; ?>" class="btn-sm edit modal-trigger tooltipped" data-position="top" data-tooltip="Editar">
                      <i class="material-icons">edit</i>
                    </a>
                    <a href="?eliminar=<?php echo $cod; ?>" class="btn-sm del tooltipped" data-position="top" data-tooltip="Eliminar"
                       onclick="return confirm('¿Eliminar \'<?php echo addslashes($prod['nombre']); ?>\'?')">
                      <i class="material-icons">delete</i>
                    </a>
                  </div>
                </td>
              </tr>

              <!-- Modal agregar stock -->
              <div id="modal-add-<?php echo $cod; ?>" class="modal">
                <div class="modal-header">
                  <h5><i class="material-icons">add_box</i> Agregar Stock</h5>
                  <p>Incrementar inventario del producto</p>
                </div>
                <div class="modal-body">
                  <div class="info-row">
                    <div class="ir"><span>Producto</span><strong><?php echo htmlspecialchars($prod['nombre']); ?></strong></div>
                    <div class="ir"><span>Código</span><strong><?php echo $cod; ?></strong></div>
                    <div class="ir"><span>Stock actual</span><strong style="color:var(--azul2);"><?php echo $prod['stock']; ?> unidades</strong></div>
                  </div>
                  <form method="POST">
                    <input type="hidden" name="codigo_barras" value="<?php echo $cod; ?>">
                    <div class="field-group">
                      <label class="flbl">Cantidad a agregar *</label>
                      <div class="field-icon">
                        <i class="material-icons">add_circle</i>
                        <input type="number" name="cantidad" min="1" required placeholder="0">
                      </div>
                    </div>
                    <div class="field-group">
                      <label class="flbl">Motivo *</label>
                      <div class="field-icon">
                        <i class="material-icons">description</i>
                        <input type="text" name="motivo" required value="Compra" placeholder="Ej: Compra, Ajuste, Devolución">
                      </div>
                    </div>
                    <div class="modal-footer-custom">
                      <a href="#!" class="modal-close btn-sec"><i class="material-icons">cancel</i> Cancelar</a>
                      <button type="submit" name="agregar_cantidad" class="btn-pri"><i class="material-icons">check</i> Confirmar</button>
                    </div>
                  </form>
                </div>
              </div>

              <!-- Modal editar -->
              <div id="modal-edit-<?php echo $cod; ?>" class="modal">
                <div class="modal-header">
                  <h5><i class="material-icons">edit</i> Editar Producto</h5>
                  <p>Código: <?php echo $cod; ?></p>
                </div>
                <div class="modal-body">
                  <form method="POST">
                    <input type="hidden" name="codigo_barras" value="<?php echo $cod; ?>">
                    <div class="field-group">
                      <label class="flbl">Nombre del Producto *</label>
                      <div class="field-icon">
                        <i class="material-icons">label</i>
                        <input type="text" name="nombre" value="<?php echo htmlspecialchars($prod['nombre']); ?>" required>
                      </div>
                    </div>
                    <div class="row" style="margin:0;">
                      <div class="col s6" style="padding-left:0;">
                        <div class="field-group">
                          <label class="flbl">Precio Compra *</label>
                          <div class="field-icon">
                            <i class="material-icons">attach_money</i>
                            <input type="number" step="0.01" name="precio_compra" value="<?php echo $prod['precio_compra']; ?>" required min="0">
                          </div>
                        </div>
                      </div>
                      <div class="col s6" style="padding-right:0;">
                        <div class="field-group">
                          <label class="flbl">Precio Venta *</label>
                          <div class="field-icon">
                            <i class="material-icons">monetization_on</i>
                            <input type="number" step="0.01" name="precio_venta" value="<?php echo $prod['precio_venta']; ?>" required min="0">
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="field-group">
                      <label class="flbl">Stock Mínimo *</label>
                      <div class="field-icon">
                        <i class="material-icons">warning_amber</i>
                        <input type="number" name="stock_minimo" value="<?php echo $prod['stock_minimo']; ?>" required min="0">
                      </div>
                    </div>
                    <div class="row" style="margin:0;">
                      <div class="col s6" style="padding-left:0;">
                        <div class="field-group">
                          <label class="flbl">Proveedor</label>
                          <div class="field-icon">
                            <i class="material-icons">local_shipping</i>
                            <select name="proveedor_id">
                              <option value="">Sin proveedor</option>
                              <?php foreach ($proveedores as $pv): ?>
                                <option value="<?php echo $pv['id']; ?>" <?php echo $pv['id']==$prod['proveedor_id']?'selected':''; ?>>
                                  <?php echo htmlspecialchars($pv['nombre']); ?>
                                </option>
                              <?php endforeach; ?>
                            </select>
                          </div>
                        </div>
                      </div>
                      <div class="col s6" style="padding-right:0;">
                        <div class="field-group">
                          <label class="flbl">Categoría *</label>
                          <div class="field-icon">
                            <i class="material-icons">category</i>
                            <select name="categoria_id" required>
                              <?php foreach ($categorias as $cat): ?>
                                <option value="<?php echo $cat['id']; ?>" <?php echo $cat['id']==$prod['categoria_id']?'selected':''; ?>>
                                  <?php echo htmlspecialchars($cat['nombre']); ?>
                                </option>
                              <?php endforeach; ?>
                            </select>
                          </div>
                        </div>
                      </div>
                    </div>
                    <div class="modal-footer-custom">
                      <a href="#!" class="modal-close btn-sec"><i class="material-icons">cancel</i> Cancelar</a>
                      <button type="submit" name="actualizar" class="btn-pri"><i class="material-icons">save</i> Guardar Cambios</button>
                    </div>
                  </form>
                </div>
              </div>

              <?php endforeach; ?>
            </tbody>
          </table>
        </div>
      </div>
    </div>
  </div>

  <!-- ══ TAB 3: CATEGORÍAS ══ -->
  <div id="tab-categorias" class="tab-content">
    <?php if (isset($_GET['categoria_mensaje'])): ?>
      <div class="msg-box <?php echo ($_GET['categoria_tipo']??'success')==='success'?'msg-ok':'msg-err'; ?>">
        <i class="material-icons"><?php echo ($_GET['categoria_tipo']??'success')==='success'?'check_circle':'error'; ?></i>
        <?php echo htmlspecialchars(urldecode($_GET['categoria_mensaje']), ENT_QUOTES, 'UTF-8'); ?>
      </div>
    <?php endif; ?>

    <div class="row">
      <div class="col s12 m5">
        <div class="prod-card">
          <div class="card-header">
            <i class="material-icons">create_new_folder</i>
            <h5>Nueva Categoría</h5>
          </div>
          <div class="card-body">
            <form method="POST" action="procesar_categoria.php">
              <div class="field-group">
                <label class="flbl">Nombre *</label>
                <div class="field-icon">
                  <i class="material-icons">folder</i>
                  <input type="text" name="nombre" required placeholder="Nombre de la categoría">
                </div>
              </div>
              <div class="field-group">
                <label class="flbl">Descripción</label>
                <div class="field-icon" style="align-items:flex-start;">
                  <i class="material-icons" style="margin-top:2px;">description</i>
                  <textarea name="descripcion" rows="3" style="resize:vertical;border:none;background:transparent;width:100%;font-family:var(--font);font-size:.92rem;color:var(--texto);" placeholder="Descripción opcional…"></textarea>
                </div>
              </div>
              <div style="text-align:right;">
                <button type="submit" name="agregar_categoria" class="btn-pri">
                  <i class="material-icons">add</i> Crear Categoría
                </button>
              </div>
            </form>
          </div>
        </div>
      </div>

      <div class="col s12 m7">
        <div class="prod-card">
          <div class="card-header">
            <i class="material-icons">folder_open</i>
            <h5>Categorías Existentes</h5>
            <span style="margin-left:auto;background:var(--azul2);color:#fff;border-radius:20px;padding:3px 13px;font-size:.78rem;font-weight:800;"><?php echo count($categorias); ?></span>
          </div>
          <div class="card-body" style="padding:0;">
            <ul class="cat-list">
              <?php foreach ($categorias as $cat):
                $stm = $conn->prepare("SELECT COUNT(*) as t FROM producto WHERE categoria_id=:id");
                $stm->execute([':id'=>$cat['id']]);
                $np = $stm->fetch(PDO::FETCH_ASSOC)['t'];
              ?>
              <li class="cat-item">
                <div>
                  <div class="cat-name">
                    <i class="material-icons">folder</i>
                    <?php echo htmlspecialchars($cat['nombre']); ?>
                    <span class="cat-cnt"><?php echo $np; ?> productos</span>
                  </div>
                  <?php if ($cat['descripcion']): ?>
                    <div class="cat-desc"><?php echo htmlspecialchars($cat['descripcion']); ?></div>
                  <?php endif; ?>
                </div>
                <div class="cat-actions">
                  <a href="#modal-cat-<?php echo $cat['id']; ?>" class="btn-sm edit modal-trigger">
                    <i class="material-icons">edit</i>
                  </a>
                  <a href="procesar_categoria.php?eliminar=<?php echo $cat['id']; ?>" class="btn-sm del"
                     onclick="return confirm('¿Eliminar categoría \'<?php echo addslashes($cat['nombre']); ?>\'?\nLos productos quedarán sin categoría.')">
                    <i class="material-icons">delete</i>
                  </a>
                </div>
              </li>

              <!-- Modal editar categoría -->
              <div id="modal-cat-<?php echo $cat['id']; ?>" class="modal">
                <div class="modal-header">
                  <h5><i class="material-icons">edit</i> Editar Categoría</h5>
                </div>
                <div class="modal-body">
                  <form method="POST" action="procesar_categoria.php">
                    <input type="hidden" name="id" value="<?php echo $cat['id']; ?>">
                    <div class="field-group">
                      <label class="flbl">Nombre *</label>
                      <div class="field-icon">
                        <i class="material-icons">folder</i>
                        <input type="text" name="nombre" value="<?php echo htmlspecialchars($cat['nombre']); ?>" required>
                      </div>
                    </div>
                    <div class="field-group">
                      <label class="flbl">Descripción</label>
                      <div class="field-icon" style="align-items:flex-start;">
                        <i class="material-icons" style="margin-top:2px;">description</i>
                        <textarea name="descripcion" rows="3" style="resize:vertical;border:none;background:transparent;width:100%;font-family:var(--font);font-size:.92rem;color:var(--texto);"><?php echo htmlspecialchars($cat['descripcion']); ?></textarea>
                      </div>
                    </div>
                    <div class="modal-footer-custom">
                      <a href="#!" class="modal-close btn-sec"><i class="material-icons">cancel</i> Cancelar</a>
                      <button type="submit" name="editar_categoria" class="btn-pri"><i class="material-icons">save</i> Guardar</button>
                    </div>
                  </form>
                </div>
              </div>
              <?php endforeach; ?>
            </ul>
          </div>
        </div>
      </div>
    </div>
  </div>

  <!-- ══ TAB 4: INVENTARIO ══ -->
  <div id="tab-inventario" class="tab-content">

    <!-- Stats -->
    <div class="stat-grid">
      <div class="stat-card">
        <div class="stat-ico" style="background:var(--azul6);">
          <i class="material-icons" style="color:var(--azul2);">inventory_2</i>
        </div>
        <div>
          <div class="stat-val" style="color:var(--azul2);"><?php echo number_format($total_prods); ?></div>
          <div class="stat-lbl">Total Productos</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-ico" style="background:var(--verde3);">
          <i class="material-icons" style="color:var(--verde2);">layers</i>
        </div>
        <div>
          <div class="stat-val" style="color:var(--verde2);"><?php echo number_format($stock_total); ?></div>
          <div class="stat-lbl">Unidades en Stock</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-ico" style="background:var(--naranja2);">
          <i class="material-icons" style="color:var(--naranja);">warning</i>
        </div>
        <div>
          <div class="stat-val" style="color:var(--naranja);"><?php echo $bajos; ?></div>
          <div class="stat-lbl">Bajo Stock</div>
        </div>
      </div>
      <div class="stat-card">
        <div class="stat-ico" style="background:var(--rojo2);">
          <i class="material-icons" style="color:var(--rojo);">cancel</i>
        </div>
        <div>
          <div class="stat-val" style="color:var(--rojo);"><?php echo $agotados; ?></div>
          <div class="stat-lbl">Agotados</div>
        </div>
      </div>
    </div>

    <!-- Valores -->
    <div class="val-grid" style="margin-bottom:20px;">
      <div class="val-card">
        <span class="val-ico" style="color:var(--azul2);">💰</span>
        <div class="val-amt" style="color:var(--azul2);">$<?php echo number_format($val_compra, 2); ?></div>
        <div class="val-lbl">Valor de Inventario (Costo)</div>
        <div class="val-sub" style="color:var(--muted);">Costo total del stock actual</div>
      </div>
      <div class="val-card">
        <span class="val-ico" style="color:var(--verde);">📈</span>
        <div class="val-amt" style="color:var(--verde);">$<?php echo number_format($val_venta, 2); ?></div>
        <div class="val-lbl">Valor Potencial de Venta</div>
        <div style="border-top:2px solid var(--borde);margin:10px 0 8px;"></div>
        <div class="val-sub" style="color:var(--verde);">
          Ganancia potencial: <strong>$<?php echo number_format($ganancia, 2); ?></strong><br>
          Margen: <strong><?php echo number_format($margen, 1); ?>%</strong>
        </div>
      </div>
    </div>

    <!-- Reportes -->
    <div class="prod-card">
      <div class="card-header">
        <i class="material-icons">assessment</i>
        <h5>Generar Reportes</h5>
      </div>
      <div class="card-body">
        <form method="GET" action="generar_reporte.php" target="_blank">
          <div class="row" style="margin-bottom:0;">
            <div class="col s12 m4">
              <div class="field-group">
                <label class="flbl">Filtrar por categoría</label>
                <div class="field-icon">
                  <i class="material-icons">category</i>
                  <select name="categoria_id">
                    <option value="">Todas las categorías</option>
                    <?php foreach ($categorias as $c): ?>
                      <option value="<?php echo $c['id']; ?>"><?php echo htmlspecialchars($c['nombre']); ?></option>
                    <?php endforeach; ?>
                  </select>
                </div>
              </div>
            </div>
            <div class="col s12 m4">
              <div class="field-group">
                <label class="flbl">Tipo de reporte</label>
                <div class="field-icon">
                  <i class="material-icons">list_alt</i>
                  <select name="tipo_reporte">
                    <option value="inventario">Inventario General</option>
                    <option value="bajos_stock">Productos Bajos en Stock</option>
                    <option value="agotados">Productos Agotados</option>
                  </select>
                </div>
              </div>
            </div>
            <div class="col s12 m4">
              <div class="field-group">
                <label class="flbl">Formato</label>
                <div class="field-icon">
                  <i class="material-icons">file_download</i>
                  <select name="formato">
                    <option value="pdf">PDF</option>
                    <option value="excel">Excel</option>
                    <option value="imprimir">Imprimir</option>
                  </select>
                </div>
              </div>
            </div>
            <div class="col s12" style="display:flex;gap:10px;margin-top:4px;flex-wrap:wrap;">
              <button type="submit" class="btn-pri">
                <i class="material-icons">picture_as_pdf</i> Generar Reporte
              </button>
              <a href="inventario_detalle.php" class="btn-sec">
                <i class="material-icons">list</i> Ver Detalles
              </a>
              <a href="movimientos_inventario.php" class="btn-sec">
                <i class="material-icons">swap_vert</i> Movimientos
              </a>
            </div>
          </div>
        </form>
      </div>
    </div>

  </div><!-- /tab-inventario -->
</div><!-- /container -->

<script>
document.addEventListener('DOMContentLoaded', function () {
  M.FormSelect.init(document.querySelectorAll('select'));
  M.Tabs.init(document.querySelectorAll('.tabs'), { swipeable: false });
  M.Modal.init(document.querySelectorAll('.modal'));
  M.Tooltip.init(document.querySelectorAll('.tooltipped'));

  // Re-init selects inside modals when they open
  document.querySelectorAll('.modal').forEach(modal => {
    M.Modal.getInstance(modal) && modal.addEventListener('modal:open', () => {
      M.FormSelect.init(modal.querySelectorAll('select'));
    });
  });

  // Búsqueda
  const buscar = document.getElementById('buscar_producto');
  if (buscar) {
    buscar.addEventListener('keyup', function () {
      const q = this.value.toLowerCase().trim();
      document.querySelectorAll('#tabla_productos tbody tr').forEach(row => {
        const n = row.dataset.nombre || '', c = row.dataset.codigo || '', cat = row.dataset.categoria || '';
        row.style.display = (!q || n.includes(q) || c.includes(q) || cat.includes(q)) ? '' : 'none';
      });
    });
  }

  // Hash tab
  const hash = window.location.hash;
  if (hash) {
    const tabInst = M.Tabs.getInstance(document.querySelector('.tabs'));
    if (tabInst) tabInst.select(hash.substring(1));
  }

  // Validación formulario
  const form = document.getElementById('formAgregarProducto');
  if (form) {
    form.addEventListener('submit', function (e) {
      const pc = parseFloat(document.getElementById('precio_compra').value);
      const pv = parseFloat(document.getElementById('precio_venta').value);
      if (pv <= pc) {
        e.preventDefault();
        M.toast({ html: '⚠️ El precio de venta debe ser mayor al precio de compra', classes: 'red' });
        return;
      }
      if (parseInt(document.getElementById('stock_minimo').value) < 0) {
        e.preventDefault();
        M.toast({ html: '⚠️ El stock mínimo no puede ser negativo', classes: 'red' });
      }
    });
  }
});
</script>

<?php require_once 'includes/footer.php'; ?>