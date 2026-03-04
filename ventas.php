<?php
ob_star();
require_once 'config/database.php';
require_once 'includes/header.php'; // Solo PHP + navbar del sitio (lo cubriremos con fixed)

if ($_SESSION['puesto'] == 'Solo lectura') {
    $_SESSION['error'] = 'No tienes permisos para realizar ventas';
    header("Location: dashboard.php"); exit();
}

$sesion_activa = $conn->prepare("SELECT * FROM sesion_caja WHERE usuario_id = :user_id AND estatus = 'abierta'");
$sesion_activa->execute([':user_id' => $_SESSION['user_id']]);
$sesion_activa_result = $sesion_activa->fetch();
if (!$sesion_activa_result) {
    $_SESSION['error'] = 'Debes iniciar una sesión de caja para realizar ventas';
    header("Location: sesion_caja.php"); exit();
}

$error = '';
$folio_generado = null;

if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['finalizar_venta'])) {
    try {
        if (!isset($_POST['total_venta']) || !isset($_POST['productos_json']))
            throw new Exception("Datos incompletos");

        $usuario_id  = $_SESSION['user_id'];
        $total_venta = floatval($_POST['total_venta']);
        $metodo_pago = $_POST['metodo_pago'] ?? 'efectivo';
        $productos   = json_decode($_POST['productos_json'], true);

        if (empty($productos))  throw new Exception("Carrito vacío");
        if ($total_venta <= 0)  throw new Exception("Total inválido");
        if (!in_array($metodo_pago, ['efectivo','tarjeta','transferencia','mixto']))
            throw new Exception("Método de pago inválido");

        $conn->beginTransaction();
        $folio = $conn->query("SELECT COALESCE(MAX(folio),0)+1 FROM venta")->fetchColumn();

        $conn->prepare("INSERT INTO venta (folio,usuario_id,total,metodo_pago,sesion_caja_id,fecha) VALUES (?,?,?,?,?,NOW())")
             ->execute([$folio,$usuario_id,$total_venta,$metodo_pago,$sesion_activa_result['id']]);

        foreach ($productos as $p) {
            if (!empty($p['por_monto'])) {
                $conn->prepare("INSERT INTO detalle_venta (folio,codigo_barras,cantidad,precio_unitario,subtotal) VALUES (?,?,?,?,?)")
                     ->execute([$folio,$p['codigo'],$p['cantidad'],$p['precio'],$p['subtotal']]);
                continue;
            }
            $st = $conn->prepare("SELECT stock FROM producto WHERE codigo_barras=?");
            $st->execute([$p['codigo']]);
            $row = $st->fetch();
            if (!$row) throw new Exception("Producto no encontrado: ".$p['codigo']);
            if ($row['stock'] < $p['cantidad']) throw new Exception("Stock insuficiente: ".$p['nombre']);
            $conn->prepare("INSERT INTO detalle_venta (folio,codigo_barras,cantidad,precio_unitario,subtotal) VALUES (?,?,?,?,?)")
                 ->execute([$folio,$p['codigo'],$p['cantidad'],$p['precio'],$p['subtotal']]);
            $conn->prepare("UPDATE producto SET stock=stock-? WHERE codigo_barras=?")
                 ->execute([$p['cantidad'],$p['codigo']]);
        }
        $conn->commit();
        $folio_generado = $folio;
    } catch (Exception $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        $error = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    }
}

$productos_db = $conn->query("SELECT * FROM producto WHERE stock > 0 ORDER BY nombre")->fetchAll();
?>

<!-- ══════════════════════════════════════════════════════
     El POS se monta en position:fixed sobre todo el sitio
     cubriendo el navbar de Materialize que trae header.php
═══════════════════════════════════════════════════════ -->
<link href="https://fonts.googleapis.com/css2?family=Nunito:wght@400;600;700;800;900&display=swap" rel="stylesheet">

<style>
/* Reset solo dentro del POS */
#pos-root *,#pos-root *::before,#pos-root *::after{box-sizing:border-box;}

:root{
  --blue1:#1565c0;
  --blue2:#1976d2;
  --blue3:#1e88e5;
  --blue4:#42a5f5;
  --blue5:#bbdefb;
  --teal: #00897b;
  --green:#388e3c;
  --green2:#43a047;
  --amber:#f57f17;
  --amber2:#ffb300;
  --red:  #e53935;
  --bg:   #e8f0fe;
  --panel:#ffffff;
  --card: #f3f6ff;
  --border:#c5cae9;
  --text: #1a237e;
  --muted:#5c6bc0;
  --font: 'Nunito',sans-serif;
  --sh:  0 4px 20px rgba(21,101,192,.15);
  --sh2: 0 8px 32px rgba(21,101,192,.22);
}

/* ── POS CONTENEDOR FIXED — cubre todo ── */
#pos-root{
  position:fixed;
  inset:0;
  z-index:9999;
  display:grid;
  grid-template-rows:52px 1fr;
  grid-template-columns:1fr 370px;
  font-family:var(--font);
  color:var(--text);
  background:var(--bg);
}

/* ── TOPBAR ── */
.pos-top{
  grid-column:1/-1;
  background:linear-gradient(135deg,#0d47a1 0%,#1565c0 55%,#1976d2 100%);
  display:flex;align-items:center;padding:0 18px;gap:14px;
  box-shadow:0 3px 14px rgba(13,71,161,.45);
  z-index:2;
}
.pos-brand{font-weight:900;font-size:1rem;color:#fff;display:flex;align-items:center;gap:8px;letter-spacing:.02em;}
.pos-brand .ico{background:rgba(255,255,255,.2);border-radius:8px;width:32px;height:32px;display:flex;align-items:center;justify-content:center;}
.pos-brand .ico i{font-size:1.2rem;color:#fff;}
.pos-sep{color:rgba(255,255,255,.3);font-size:1.1rem;}
.pos-meta{color:rgba(255,255,255,.72);font-size:.82rem;}
.pos-meta strong{color:#fff;}
.pos-badge{
  background:rgba(255,255,255,.18);color:#fff;
  border:1px solid rgba(255,255,255,.35);border-radius:20px;
  padding:3px 12px;font-size:.71rem;font-weight:800;
  display:flex;align-items:center;gap:6px;
}
.pos-dot{width:7px;height:7px;background:#69f0ae;border-radius:50%;animation:blink 2s infinite;}
@keyframes blink{0%,100%{opacity:1}50%{opacity:.35}}
.pos-top .tr{margin-left:auto;display:flex;gap:10px;}
.pos-top a{
  color:rgba(255,255,255,.8);text-decoration:none;font-size:.77rem;font-weight:700;
  padding:5px 12px;border-radius:6px;border:1px solid rgba(255,255,255,.22);transition:.13s;
  display:flex;align-items:center;gap:4px;
}
.pos-top a:hover{background:rgba(255,255,255,.2);color:#fff;}
.pos-top a i{font-size:.88rem;}

/* ── IZQUIERDA (carrito) ── */
.pos-left{
  display:grid;
  grid-template-rows:auto 1fr auto;
  overflow:hidden;
  background:var(--bg);
}

/* Barra de búsqueda */
.sbar{
  padding:10px 12px;
  background:linear-gradient(135deg,#1565c0,#283593);
  display:flex;gap:8px;align-items:center;
  box-shadow:0 2px 10px rgba(21,101,192,.35);
}
.mpill{display:flex;border:2px solid rgba(255,255,255,.25);border-radius:8px;overflow:hidden;flex-shrink:0;}
.mpill button{
  background:transparent;border:none;color:rgba(255,255,255,.65);
  padding:6px 12px;font-size:.74rem;font-weight:800;cursor:pointer;
  transition:.13s;font-family:var(--font);
}
.mpill button.on{background:#fff;color:var(--blue1);}

.sw{position:relative;flex:1;}
.sw input{
  width:100%;background:rgba(255,255,255,.15);border:2px solid rgba(255,255,255,.25);
  border-radius:8px;color:#fff;padding:8px 12px 8px 36px;
  font-size:.9rem;font-family:var(--font);font-weight:600;outline:none;transition:.14s;
}
.sw input::placeholder{color:rgba(255,255,255,.45);}
.sw input:focus{background:rgba(255,255,255,.25);border-color:rgba(255,255,255,.7);}
.sw .si{position:absolute;left:9px;top:50%;transform:translateY(-50%);color:rgba(255,255,255,.55);font-size:1rem;pointer-events:none;font-family:'Material Icons';}

.drop{
  position:absolute;top:calc(100% + 5px);left:0;right:0;
  background:#fff;border:2px solid var(--blue5);border-radius:10px;
  box-shadow:var(--sh2);z-index:99;display:none;overflow:hidden;
  max-height:260px;overflow-y:auto;
}
.drop::-webkit-scrollbar{width:4px;}
.drop::-webkit-scrollbar-thumb{background:var(--blue5);border-radius:4px;}
.di{padding:10px 14px;display:flex;justify-content:space-between;align-items:center;cursor:pointer;border-bottom:1px solid var(--blue5);transition:.1s;}
.di:last-child{border-bottom:none;}
.di:hover{background:var(--card);}
.dn{font-size:.86rem;font-weight:700;color:var(--text);}
.dm{font-size:.7rem;color:var(--muted);margin-top:1px;}
.dp{font-size:.9rem;font-weight:900;color:var(--green);background:#e8f5e9;border-radius:6px;padding:3px 9px;}

.qw{display:flex;align-items:center;gap:5px;flex-shrink:0;}
.qw input{width:52px;background:rgba(255,255,255,.15);border:2px solid rgba(255,255,255,.3);border-radius:7px;color:#fff;text-align:center;font-size:.95rem;font-weight:800;padding:6px 3px;outline:none;font-family:var(--font);}
.qw input:focus{background:rgba(255,255,255,.28);border-color:#fff;}
.mw input{width:108px;background:rgba(255,193,7,.15);border:2px solid rgba(255,193,7,.5);border-radius:7px;color:#fff;text-align:right;font-size:.9rem;font-weight:800;padding:6px 10px;outline:none;font-family:var(--font);}
.mw input::placeholder{color:rgba(255,255,255,.4);}
.mw input:focus{border-color:#ffb300;}

.qbtn{width:30px;height:30px;background:rgba(255,255,255,.18);border:2px solid rgba(255,255,255,.28);border-radius:7px;color:#fff;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:.12s;}
.qbtn:hover{background:rgba(255,255,255,.35);}
.qbtn i{font-size:.9rem;}

.btn-add{
  background:linear-gradient(135deg,#2e7d32,#43a047);
  color:#fff;border:none;border-radius:8px;padding:8px 16px;
  font-size:.82rem;font-weight:800;cursor:pointer;
  display:flex;align-items:center;gap:5px;white-space:nowrap;
  transition:.14s;font-family:var(--font);
  box-shadow:0 3px 10px rgba(46,125,50,.4);
}
.btn-add:hover{transform:translateY(-1px);box-shadow:0 5px 16px rgba(46,125,50,.5);}
.btn-add i{font-size:.95rem;}

/* Tabla carrito */
.cart-wrap{overflow-y:auto;background:var(--bg);}
.cart-wrap::-webkit-scrollbar{width:5px;}
.cart-wrap::-webkit-scrollbar-thumb{background:var(--border);border-radius:4px;}

table.ct{width:100%;border-collapse:collapse;}
table.ct thead th{
  position:sticky;top:0;z-index:5;
  background:linear-gradient(135deg,#283593,#1a237e);
  padding:9px 12px;text-align:left;
  font-size:.67rem;font-weight:800;letter-spacing:.08em;text-transform:uppercase;
  color:rgba(255,255,255,.72);
}
table.ct tbody tr{border-bottom:1px solid var(--border);transition:.08s;}
table.ct tbody tr:nth-child(even){background:rgba(187,222,251,.2);}
table.ct tbody tr:hover{background:rgba(66,165,245,.1);}
table.ct td{padding:8px 12px;font-size:.87rem;vertical-align:middle;}
.cnum{color:var(--muted);font-size:.7rem;font-weight:700;}
.cname{font-weight:700;color:var(--text);}
.cprice{color:var(--muted);font-size:.78rem;font-weight:600;}
.csub{font-weight:900;color:var(--green2);font-size:.95rem;}
.tag-m{font-size:.58rem;font-weight:800;background:linear-gradient(135deg,#ff6f00,#f57f17);color:#fff;border-radius:4px;padding:2px 5px;margin-left:4px;vertical-align:middle;}

.qc{display:flex;align-items:center;gap:4px;}
.qcbtn{width:24px;height:24px;border-radius:6px;cursor:pointer;display:flex;align-items:center;justify-content:center;transition:.1s;border:none;}
.qcbtn.mi{background:#e3f2fd;color:var(--blue2);}
.qcbtn.mi:hover{background:var(--blue2);color:#fff;}
.qcbtn.pl{background:#e8f5e9;color:var(--green2);}
.qcbtn.pl:hover{background:var(--green2);color:#fff;}
.qcbtn:disabled{opacity:.25;cursor:default;}
.qcbtn i{font-size:.78rem;}
.qcn{width:40px;text-align:center;border:2px solid var(--border);border-radius:6px;color:var(--text);padding:2px;font-size:.85rem;font-weight:800;outline:none;font-family:var(--font);background:#fff;}
.qcn:focus{border-color:var(--blue3);}

.delbtn{background:#ffebee;border:2px solid #ffcdd2;border-radius:6px;cursor:pointer;color:var(--red);width:26px;height:26px;display:flex;align-items:center;justify-content:center;transition:.12s;}
.delbtn:hover{background:var(--red);color:#fff;border-color:var(--red);}
.delbtn i{font-size:.82rem;}

.empty{display:flex;flex-direction:column;align-items:center;justify-content:center;height:100%;color:var(--muted);gap:10px;padding:60px 0;}
.empty i{font-size:3.5rem;color:var(--blue5);}
.empty p{font-size:.9rem;font-weight:600;}

.cart-foot{
  background:linear-gradient(135deg,#1a237e,#283593);
  padding:9px 14px;display:flex;align-items:center;justify-content:space-between;
  font-size:.75rem;color:rgba(255,255,255,.6);
}
.cart-foot strong{color:#fff;}
.kbd{background:rgba(255,255,255,.15);border-radius:4px;padding:1px 6px;font-size:.67rem;font-weight:700;color:rgba(255,255,255,.7);border:1px solid rgba(255,255,255,.2);margin:0 1px;}

/* ── DERECHA (cobro) ── */
.pos-right{
  display:grid;
  grid-template-rows:auto auto 1fr auto;
  background:var(--panel);
  border-left:3px solid var(--blue5);
  box-shadow:-4px 0 20px rgba(21,101,192,.12);
  overflow:hidden;
}

/* Total */
.tbox{
  background:linear-gradient(135deg,#0d47a1,#1565c0,#1976d2);
  padding:18px 18px 14px;text-align:center;position:relative;overflow:hidden;
}
.tbox::before{content:'';position:absolute;top:-30px;right:-30px;width:120px;height:120px;border-radius:50%;background:rgba(255,255,255,.06);}
.tbox::after{content:'';position:absolute;bottom:-20px;left:-20px;width:80px;height:80px;border-radius:50%;background:rgba(255,255,255,.06);}
.tlbl{font-size:.63rem;font-weight:800;letter-spacing:.13em;text-transform:uppercase;color:rgba(255,255,255,.55);position:relative;z-index:1;}
.tamt{font-size:3rem;font-weight:900;color:rgba(255,255,255,.3);line-height:1.05;letter-spacing:-.02em;transition:.3s;position:relative;z-index:1;}
.tamt.lit{color:#fff;text-shadow:0 0 30px rgba(255,255,255,.25);}
.tsub{font-size:.77rem;color:rgba(255,255,255,.48);font-weight:700;margin-top:3px;position:relative;z-index:1;}

/* Métodos de pago */
.mets{display:grid;grid-template-columns:1fr 1fr;gap:6px;padding:12px 12px 0;background:var(--card);}
.mbtn{
  background:#fff;border:2px solid var(--border);border-radius:9px;
  padding:10px 4px;cursor:pointer;text-align:center;
  font-size:.72rem;font-weight:800;color:var(--muted);
  transition:.15s;font-family:var(--font);letter-spacing:.02em;
}
.mbtn i{display:block;font-size:1.35rem;margin-bottom:3px;}
.mbtn:hover{border-color:var(--blue3);color:var(--blue3);background:#e3f2fd;}
.mbtn.aef{border-color:var(--green2);background:#e8f5e9;color:var(--green);}
.mbtn.atk{border-color:var(--blue3);background:#e3f2fd;color:var(--blue2);}
.mbtn.atr{border-color:var(--teal);background:#e0f2f1;color:var(--teal);}
.mbtn.amx{border-color:var(--amber);background:#fff8e1;color:var(--amber);}

/* Área cobro */
.cobro{overflow-y:auto;padding:12px;display:flex;flex-direction:column;gap:10px;background:var(--card);}
.cobro::-webkit-scrollbar{width:4px;}
.cobro::-webkit-scrollbar-thumb{background:var(--border);border-radius:4px;}

.clbl{display:block;font-size:.65rem;font-weight:800;letter-spacing:.07em;text-transform:uppercase;color:var(--muted);margin-bottom:4px;}
.cinp{
  width:100%;background:#fff;border:2px solid var(--border);border-radius:9px;
  color:var(--text);padding:10px 14px;font-size:1.5rem;font-weight:800;
  font-family:var(--font);outline:none;transition:.15s;text-align:right;
}
.cinp:focus{border-color:var(--blue3);box-shadow:0 0 0 3px rgba(66,165,245,.2);}
.cinp.ca:focus{border-color:var(--amber);box-shadow:0 0 0 3px rgba(255,179,0,.2);}

.bills{display:flex;flex-wrap:wrap;gap:5px;margin-top:5px;}
.bb{background:#fff;border:2px solid var(--border);border-radius:7px;color:var(--text);padding:5px 9px;font-size:.77rem;font-weight:800;cursor:pointer;transition:.12s;font-family:var(--font);}
.bb:hover{background:var(--blue1);border-color:var(--blue1);color:#fff;}
.bb.ex{background:linear-gradient(135deg,#e8f5e9,#c8e6c9);border-color:var(--green2);color:var(--green);}
.bb.ex:hover{background:var(--green2);color:#fff;}

.crow{display:flex;justify-content:space-between;align-items:center;border:2px solid var(--border);border-radius:9px;padding:10px 14px;background:#fff;transition:.2s;}
.crow.ok{border-color:var(--green2);background:linear-gradient(135deg,#e8f5e9,#f1f8e9);}
.crow.low{border-color:var(--red);background:#fff5f5;}
.clblr{font-size:.65rem;font-weight:800;letter-spacing:.07em;text-transform:uppercase;color:var(--muted);}
.cval{font-size:1.45rem;font-weight:900;color:var(--muted);font-family:var(--font);}
.crow.ok .cval{color:var(--green);}
.crow.low .cval{color:var(--red);}
.crow .cico{font-size:1.9rem;opacity:.28;}
.crow.ok .cico{opacity:1;color:var(--green);}
.crow.low .cico{opacity:1;color:var(--red);}

.stpanel{text-align:center;padding:20px 14px;color:var(--muted);}
.stico{width:60px;height:60px;border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 10px;}
.stico i{font-size:1.9rem;}
.stpanel p{font-size:.82rem;line-height:1.6;font-weight:600;}
.sttotal{font-size:1.8rem;font-weight:900;margin-top:8px;}

/* Botón cobrar */
.fin{padding:12px;background:#fff;border-top:3px solid var(--blue5);display:flex;flex-direction:column;gap:6px;}
.btnf{
  width:100%;
  background:linear-gradient(135deg,#2e7d32,#388e3c,#43a047);
  color:#fff;border:none;border-radius:10px;padding:16px;
  font-size:1rem;font-weight:900;cursor:pointer;
  display:flex;align-items:center;justify-content:center;gap:8px;
  transition:.17s;font-family:var(--font);letter-spacing:.03em;
  box-shadow:0 4px 16px rgba(46,125,50,.35);
}
.btnf:hover:not(:disabled){background:linear-gradient(135deg,#1b5e20,#2e7d32);transform:translateY(-2px);box-shadow:0 8px 24px rgba(46,125,50,.45);}
.btnf:disabled{background:linear-gradient(135deg,#bdbdbd,#9e9e9e);box-shadow:none;cursor:default;}
.btnl{width:100%;background:#fff;color:var(--muted);border:2px solid var(--border);border-radius:8px;padding:8px;font-size:.78rem;font-weight:700;cursor:pointer;display:flex;align-items:center;justify-content:center;gap:5px;transition:.13s;font-family:var(--font);}
.btnl:hover{border-color:var(--red);color:var(--red);background:#fff5f5;}

/* ── OVERLAY ÉXITO ── */
.ov{position:fixed;inset:0;background:rgba(13,71,161,.55);backdrop-filter:blur(6px);z-index:10000;display:none;align-items:center;justify-content:center;}
.ov.show{display:flex;}
.ovc{background:#fff;border-radius:20px;padding:36px 50px;text-align:center;box-shadow:0 24px 80px rgba(13,71,161,.35);animation:pop .3s cubic-bezier(.34,1.56,.64,1);border-top:6px solid var(--green2);}
@keyframes pop{from{transform:scale(.6);opacity:0}to{transform:scale(1);opacity:1}}
.ov-ico{width:78px;height:78px;background:linear-gradient(135deg,#e8f5e9,#c8e6c9);border-radius:50%;display:flex;align-items:center;justify-content:center;margin:0 auto 14px;}
.ov-ico i{font-size:2.7rem;color:var(--green);}
.ovc h2{font-size:1.45rem;font-weight:900;color:var(--text);margin-bottom:4px;}
.of{font-size:.88rem;color:var(--muted);font-weight:700;margin-bottom:14px;}
.oc-lbl{font-size:.65rem;font-weight:800;letter-spacing:.1em;text-transform:uppercase;color:var(--muted);}
.oc{font-size:2.8rem;font-weight:900;color:var(--green);margin:6px 0 20px;line-height:1;}
.oa{display:flex;gap:10px;justify-content:center;}
.oabtn{border:none;border-radius:9px;padding:11px 22px;font-size:.86rem;font-weight:800;cursor:pointer;display:flex;align-items:center;gap:6px;font-family:var(--font);transition:.14s;}
.oabtn.pr{background:linear-gradient(135deg,var(--blue1),var(--blue3));color:#fff;box-shadow:0 4px 14px rgba(21,101,192,.35);}
.oabtn.pr:hover{transform:translateY(-1px);}
.oabtn.sc{background:#f5f5f5;color:var(--muted);border:2px solid var(--border);}
.oabtn.sc:hover{border-color:var(--blue3);color:var(--blue2);}

/* ── TOAST ── */
.tst{position:fixed;bottom:22px;left:50%;transform:translateX(-50%);border-radius:10px;padding:10px 20px;font-size:.84rem;font-weight:800;box-shadow:0 8px 28px rgba(0,0,0,.18);z-index:10001;display:none;align-items:center;gap:8px;white-space:nowrap;font-family:var(--font);}
.tst.show{display:flex;}
.tst.tok {background:#e8f5e9;color:#2e7d32;border:2px solid #a5d6a7;}
.tst.terr{background:#ffebee;color:#c62828;border:2px solid #ffcdd2;}
.tst.twrn{background:#fff8e1;color:#e65100;border:2px solid #ffe082;}
</style>

<!-- ══════════ POS ROOT ══════════ -->
<div id="pos-root">

  <!-- TOPBAR -->
  <div class="pos-top">
    <div class="pos-brand">
      <div class="ico"><i class="material-icons">point_of_sale</i></div>
      Punto de Venta
    </div>
    <span class="pos-sep">|</span>
    <span class="pos-meta">Cajero: <strong><?php echo htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8'); ?></strong></span>
    <span class="pos-sep">|</span>
    <span class="pos-meta">Sesión <strong>#<?php echo $sesion_activa_result['id']; ?></strong></span>
    <div class="pos-badge"><span class="pos-dot"></span> CAJA ABIERTA</div>
    <div class="tr">
      <a href="dashboard.php"><i class="material-icons">dashboard</i> Dashboard</a>
      <a href="logout.php"><i class="material-icons">exit_to_app</i> Salir</a>
    </div>
  </div>

  <!-- CARRITO -->
  <div class="pos-left">

    <div class="sbar">
      <div class="mpill">
        <button id="bmq" class="on" onclick="setModo('cantidad')">📦 Unidades</button>
        <button id="bmm" onclick="setModo('monto')">💲 Monto</button>
      </div>
      <div class="sw">
        <span class="si">search</span>
        <input type="text" id="buscar" placeholder="Buscar producto o escanear código de barras…" autocomplete="off" autofocus>
        <div class="drop" id="drop"></div>
      </div>
      <div class="qw" id="cq">
        <button class="qbtn" onclick="ajQ(-1)"><i class="material-icons">remove</i></button>
        <input type="number" id="iq" value="1" min="1">
        <button class="qbtn" onclick="ajQ(1)"><i class="material-icons">add</i></button>
      </div>
      <div class="mw" id="cm" style="display:none;">
        <input type="number" id="im" step="0.01" min="0.01" placeholder="$ monto…">
      </div>
      <button class="btn-add" onclick="agregar()">
        <i class="material-icons">add_shopping_cart</i> Agregar
      </button>
    </div>

    <div class="cart-wrap">
      <table class="ct">
        <thead>
          <tr><th>#</th><th>Producto</th><th>Precio U.</th><th>Cantidad</th><th>Subtotal</th><th></th></tr>
        </thead>
        <tbody id="tbody">
          <tr><td colspan="6"><div class="empty">
            <i class="material-icons" style="font-size:4rem;">barcode_reader</i>
            <p>Escanea o busca un producto para comenzar</p>
          </div></td></tr>
        </tbody>
      </table>
    </div>

    <div class="cart-foot">
      <span id="resumen">0 artículos</span>
      <span><span class="kbd">F2</span> Agregar &nbsp;<span class="kbd">F12</span> Cobrar &nbsp;<span class="kbd">Esc</span> Nueva venta</span>
      <span>Total: <strong id="ftotal">$0.00</strong></span>
    </div>
  </div>

  <!-- COBRO -->
  <div class="pos-right">

    <div class="tbox">
      <div class="tlbl">Total a cobrar</div>
      <div class="tamt" id="tdisp">$0.00</div>
      <div class="tsub" id="tsub">Agrega productos al carrito</div>
    </div>

    <div class="mets">
      <button class="mbtn aef" id="met-efectivo" onclick="selMet('efectivo','aef')">
        <i class="material-icons">payments</i>Efectivo
      </button>
      <button class="mbtn" id="met-tarjeta" onclick="selMet('tarjeta','atk')">
        <i class="material-icons">credit_card</i>Tarjeta
      </button>
      <button class="mbtn" id="met-transferencia" onclick="selMet('transferencia','atr')">
        <i class="material-icons">account_balance</i>Transf.
      </button>
      <button class="mbtn" id="met-mixto" onclick="selMet('mixto','amx')">
        <i class="material-icons">call_split</i>Mixto
      </button>
    </div>

    <div class="cobro">

      <!-- Efectivo -->
      <div id="p-efectivo">
        <label class="clbl">Monto recibido en efectivo</label>
        <input type="number" class="cinp" id="cef" step="0.01" min="0" placeholder="0.00" oninput="calcCambio()">
        <div class="bills">
          <button class="bb" onclick="addB(10)">+$10</button>
          <button class="bb" onclick="addB(20)">+$20</button>
          <button class="bb" onclick="addB(50)">+$50</button>
          <button class="bb" onclick="addB(100)">+$100</button>
          <button class="bb" onclick="addB(200)">+$200</button>
          <button class="bb" onclick="addB(500)">+$500</button>
          <button class="bb ex" onclick="exacto()">✓ Exacto</button>
        </div>
        <div class="crow" id="cbox" style="margin-top:8px;">
          <div>
            <div class="clblr">Cambio a entregar</div>
            <div class="cval" id="cval">—</div>
          </div>
          <i class="material-icons cico" id="cico">currency_exchange</i>
        </div>
      </div>

      <!-- Tarjeta -->
      <div id="p-tarjeta" style="display:none;">
        <div class="stpanel">
          <div class="stico" style="background:linear-gradient(135deg,#e3f2fd,#bbdefb);">
            <i class="material-icons" style="color:var(--blue2);">credit_card</i>
          </div>
          <p>Pase la tarjeta en el terminal.<br>No requiere ingreso manual.</p>
          <div class="sttotal" style="color:var(--blue2);" id="tt-tk">$0.00</div>
        </div>
      </div>

      <!-- Transferencia -->
      <div id="p-transferencia" style="display:none;">
        <div class="stpanel">
          <div class="stico" style="background:linear-gradient(135deg,#e0f2f1,#b2dfdb);">
            <i class="material-icons" style="color:var(--teal);">account_balance</i>
          </div>
          <p>Verifique la transferencia<br>antes de confirmar la venta.</p>
          <div class="sttotal" style="color:var(--teal);" id="tt-tr">$0.00</div>
        </div>
      </div>

      <!-- Mixto -->
      <div id="p-mixto" style="display:none;">
        <label class="clbl">Monto con tarjeta</label>
        <input type="number" class="cinp ca" id="mx-tk" step="0.01" min="0" placeholder="0.00"
               style="color:var(--amber);" oninput="calcMixtoTk()">
        <label class="clbl" style="margin-top:8px;">Efectivo recibido</label>
        <input type="number" class="cinp" id="mx-ef" step="0.01" min="0" placeholder="0.00" oninput="calcMixto()">
        <div class="bills" style="margin-top:5px;">
          <button class="bb" onclick="addBMx(20)">+$20</button>
          <button class="bb" onclick="addBMx(50)">+$50</button>
          <button class="bb" onclick="addBMx(100)">+$100</button>
          <button class="bb" onclick="addBMx(200)">+$200</button>
        </div>
        <div class="crow" id="cbox-mx" style="margin-top:8px;">
          <div>
            <div class="clblr">Cambio en efectivo</div>
            <div class="cval" id="cval-mx">—</div>
          </div>
          <i class="material-icons cico" id="cico-mx">currency_exchange</i>
        </div>
      </div>

    </div>

    <div class="fin">
      <form method="POST" id="fv" onsubmit="return onSub()">
        <input type="hidden" name="total_venta"    id="h-total" value="0">
        <input type="hidden" name="productos_json" id="h-prods" value="[]">
        <input type="hidden" name="metodo_pago"    id="h-met"   value="efectivo">
        <input type="hidden" name="finalizar_venta" value="1">
        <button type="submit" class="btnf" id="btnf" disabled>
          <i class="material-icons">check_circle</i> COBRAR — F12
        </button>
      </form>
      <button class="btnl" onclick="limpiar()">
        <i class="material-icons" style="font-size:.9rem;">delete_sweep</i> Limpiar carrito
      </button>
    </div>
  </div>
</div>

<!-- Overlay éxito -->
<div class="ov" id="ov">
  <div class="ovc">
    <div class="ov-ico"><i class="material-icons">check_circle</i></div>
    <h2>¡Venta completada!</h2>
    <div class="of" id="of"></div>
    <div class="oc-lbl">CAMBIO A ENTREGAR</div>
    <div class="oc" id="oc">$0.00</div>
    <div class="oa">
      <button class="oabtn sc" onclick="abrirTicket()"><i class="material-icons" style="font-size:.9rem;">receipt</i> Ticket</button>
      <button class="oabtn pr" onclick="nuevaVenta()"><i class="material-icons" style="font-size:.9rem;">refresh</i> Nueva venta</button>
    </div>
  </div>
</div>

<div class="tst" id="tst"></div>

<script>
const DB=<?php echo json_encode(array_map(fn($p)=>[
  'codigo'=>$p['codigo_barras'],'nombre'=>$p['nombre'],
  'precio'=>(float)$p['precio_venta'],'stock'=>(int)$p['stock'],
],$productos_db));?>;

let carrito=[],modo='cantidad',metodo='efectivo',sel=null,ultimoFolio=null;

<?php if($folio_generado):?>
window.addEventListener('DOMContentLoaded',()=>{
  const c=parseFloat(sessionStorage.getItem('pos_cambio')||0);
  mostrarOk(<?php echo $folio_generado;?>,c);
  sessionStorage.removeItem('pos_cambio');
});
<?php endif;?>
<?php if($error):?>
window.addEventListener('DOMContentLoaded',()=>toast('<?php echo addslashes($error);?>','err'));
<?php endif;?>

// Búsqueda
const inp=document.getElementById('buscar'),drop=document.getElementById('drop');
inp.addEventListener('input',()=>{
  const q=inp.value.trim().toLowerCase();
  if(!q){drop.style.display='none';return;}
  const r=DB.filter(p=>p.nombre.toLowerCase().includes(q)||p.codigo.toLowerCase().includes(q)).slice(0,10);
  if(!r.length){drop.style.display='none';return;}
  drop.innerHTML=r.map(p=>`<div class="di" onclick="selProd('${p.codigo}')">
    <div><div class="dn">${p.nombre}</div><div class="dm">Cód: ${p.codigo} · Stock: ${p.stock}</div></div>
    <div class="dp">$${p.precio.toFixed(2)}</div></div>`).join('');
  drop.style.display='block';
});
document.addEventListener('click',e=>{if(!e.target.closest('.sw'))drop.style.display='none';});
inp.addEventListener('keydown',e=>{
  if(e.key==='Enter'){const f=drop.querySelector('.di');if(f){f.click();e.preventDefault();}else{const p=DB.find(x=>x.codigo===inp.value.trim());if(p){selProd(p.codigo);agregar();}}}
  if(e.key==='F2'){e.preventDefault();agregar();}
});

function selProd(c){sel=DB.find(p=>p.codigo===c);inp.value=sel.nombre;drop.style.display='none';if(modo==='cantidad')document.getElementById('iq').focus();else document.getElementById('im').focus();}
function ajQ(d){const i=document.getElementById('iq');i.value=Math.max(1,(parseInt(i.value)||1)+d);}
function setModo(m){
  modo=m;
  document.getElementById('cq').style.display=m==='cantidad'?'flex':'none';
  document.getElementById('cm').style.display=m==='monto'?'flex':'none';
  document.getElementById('bmq').classList.toggle('on',m==='cantidad');
  document.getElementById('bmm').classList.toggle('on',m==='monto');
}

function agregar(){
  if(!sel){toast('Selecciona un producto primero','wrn');inp.focus();return;}
  if(modo==='cantidad'){
    const qty=parseInt(document.getElementById('iq').value)||0;
    if(qty<1){toast('Cantidad inválida','err');return;}
    if(qty>sel.stock){toast(`Stock disponible: ${sel.stock}`,'err');return;}
    const idx=carrito.findIndex(i=>i.codigo===sel.codigo&&!i.por_monto);
    if(idx!==-1){const nq=carrito[idx].cantidad+qty;if(nq>sel.stock){toast(`Stock máx: ${sel.stock}`,'err');return;}carrito[idx].cantidad=nq;carrito[idx].subtotal=carrito[idx].precio*nq;}
    else{carrito.push({codigo:sel.codigo,nombre:sel.nombre,precio:sel.precio,cantidad:qty,subtotal:sel.precio*qty,stock:sel.stock,por_monto:false});}
    toast(`✓ ${sel.nombre} agregado`,'ok');
  } else {
    const monto=parseFloat(document.getElementById('im').value)||0;
    if(monto<=0){toast('Ingresa un monto válido','err');return;}
    if(sel.precio<=0){toast('Producto sin precio','err');return;}
    carrito.push({codigo:sel.codigo,nombre:sel.nombre,precio:sel.precio,cantidad:parseFloat((monto/sel.precio).toFixed(4)),subtotal:monto,stock:sel.stock,por_monto:true});
    toast(`✓ $${monto.toFixed(2)} de ${sel.nombre}`,'ok');
    document.getElementById('im').value='';
  }
  sel=null;inp.value='';document.getElementById('iq').value=1;render();inp.focus();
}

function cambiarQty(idx,v){if(v<1){toast('Mínimo 1','wrn');return;}if(v>carrito[idx].stock){toast(`Stock máx: ${carrito[idx].stock}`,'err');return;}carrito[idx].cantidad=v;carrito[idx].subtotal=carrito[idx].precio*v;render();}
function eliminar(idx){carrito.splice(idx,1);render();}
function limpiar(){if(!carrito.length)return;carrito=[];render();toast('Carrito limpiado','wrn');}

function render(){
  const tb=document.getElementById('tbody');let total=0,items=0;tb.innerHTML='';
  if(!carrito.length){
    tb.innerHTML=`<tr><td colspan="6"><div class="empty"><i class="material-icons" style="font-size:4rem;">barcode_reader</i><p>Escanea o busca un producto para comenzar</p></div></td></tr>`;
  } else {
    carrito.forEach((item,idx)=>{
      const tr=document.createElement('tr');
      tr.innerHTML=`<td class="cnum">${idx+1}</td>
        <td class="cname">${item.nombre}${item.por_monto?'<span class="tag-m">$ MONTO</span>':''}</td>
        <td class="cprice">$${item.precio.toFixed(2)}</td>
        <td>${item.por_monto
          ?`<span style="font-size:.82rem;font-weight:800;color:var(--amber);">${item.cantidad.toFixed(4)} u.</span>`
          :`<div class="qc"><button class="qcbtn mi" onclick="cambiarQty(${idx},${item.cantidad-1})" ${item.cantidad<=1?'disabled':''}><i class="material-icons">remove</i></button><input class="qcn" type="number" value="${item.cantidad}" onchange="cambiarQty(${idx},parseInt(this.value))" min="1" max="${item.stock}"><button class="qcbtn pl" onclick="cambiarQty(${idx},${item.cantidad+1})" ${item.cantidad>=item.stock?'disabled':''}><i class="material-icons">add</i></button></div>`}
        </td>
        <td class="csub">$${item.subtotal.toFixed(2)}</td>
        <td><button class="delbtn" onclick="eliminar(${idx})"><i class="material-icons">close</i></button></td>`;
      tb.appendChild(tr);total+=item.subtotal;items+=item.por_monto?1:item.cantidad;
    });
  }
  const ts='$'+total.toFixed(2);
  document.getElementById('tdisp').textContent=ts;
  document.getElementById('tdisp').classList.toggle('lit',total>0);
  document.getElementById('tsub').textContent=carrito.length?`${carrito.length} producto${carrito.length!==1?'s':''} · ${items} unidad${items!==1?'es':''}`:'Agrega productos al carrito';
  document.getElementById('ftotal').textContent=ts;
  document.getElementById('resumen').textContent=`${carrito.length} artículo${carrito.length!==1?'s':''} · ${items} unid.`;
  document.getElementById('h-total').value=total.toFixed(2);
  document.getElementById('h-prods').value=JSON.stringify(carrito);
  document.getElementById('btnf').disabled=!carrito.length;
  document.getElementById('tt-tk').textContent=ts;
  document.getElementById('tt-tr').textContent=ts;
  calcCambio();calcMixto();
}

// Métodos
function selMet(m,cls){
  metodo=m;
  ['efectivo','tarjeta','transferencia','mixto'].forEach(k=>{
    document.getElementById(`met-${k}`).className='mbtn';
    document.getElementById(`p-${k}`).style.display='none';
  });
  document.getElementById(`met-${m}`).classList.add(cls);
  document.getElementById(`p-${m}`).style.display='block';
  document.getElementById('h-met').value=m;
}

// Cambio
function setcrow(box,val,ico,c,hasVal){
  if(!hasVal){val.textContent='—';box.className='crow';ico.style.opacity='.28';ico.textContent='currency_exchange';return;}
  if(c>=0){val.textContent='$'+c.toFixed(2);box.className='crow ok';ico.style.opacity='1';ico.textContent='savings';}
  else{val.textContent='-$'+Math.abs(c).toFixed(2)+' falta';box.className='crow low';ico.style.opacity='1';ico.textContent='warning';}
}
function calcCambio(){
  const tot=parseFloat(document.getElementById('h-total').value)||0;
  const ef=document.getElementById('cef').value;
  const rec=parseFloat(ef)||0;
  setcrow(document.getElementById('cbox'),document.getElementById('cval'),document.getElementById('cico'),rec-tot,ef!=='');
}
function calcMixtoTk(){
  const tot=parseFloat(document.getElementById('h-total').value)||0;
  const tk=parseFloat(document.getElementById('mx-tk').value)||0;
  const r=Math.max(0,tot-tk);
  document.getElementById('mx-ef').value=r>0?r.toFixed(2):'';
  calcMixto();
}
function calcMixto(){
  const tot=parseFloat(document.getElementById('h-total').value)||0;
  const ef=parseFloat(document.getElementById('mx-ef').value)||0;
  const tk=parseFloat(document.getElementById('mx-tk').value)||0;
  setcrow(document.getElementById('cbox-mx'),document.getElementById('cval-mx'),document.getElementById('cico-mx'),ef+tk-tot,(ef>0||tk>0));
}
function addB(v){const i=document.getElementById('cef');i.value=((parseFloat(i.value)||0)+v).toFixed(2);calcCambio();}
function addBMx(v){const i=document.getElementById('mx-ef');i.value=((parseFloat(i.value)||0)+v).toFixed(2);calcMixto();}
function exacto(){document.getElementById('cef').value=(parseFloat(document.getElementById('h-total').value)||0).toFixed(2);calcCambio();}

function onSub(){
  if(!carrito.length){toast('Carrito vacío','err');return false;}
  const tot=parseFloat(document.getElementById('h-total').value);
  if(metodo==='efectivo'){
    const r=parseFloat(document.getElementById('cef').value)||0;
    if(r<tot){toast('El monto recibido es insuficiente','err');return false;}
    sessionStorage.setItem('pos_cambio',(r-tot).toFixed(2));
  } else if(metodo==='mixto'){
    const ef=parseFloat(document.getElementById('mx-ef').value)||0;
    const tk=parseFloat(document.getElementById('mx-tk').value)||0;
    if((ef+tk)<tot){toast('El monto mixto es insuficiente','err');return false;}
    sessionStorage.setItem('pos_cambio',Math.max(0,ef+tk-tot).toFixed(2));
  } else {
    sessionStorage.setItem('pos_cambio','0');
  }
  const b=document.getElementById('btnf');b.disabled=true;b.innerHTML='<i class="material-icons">hourglass_empty</i> Procesando…';
  return true;
}
function mostrarOk(folio,cambio){
  document.getElementById('of').textContent='Folio #'+String(folio).padStart(6,'0');
  document.getElementById('oc').textContent='$'+parseFloat(cambio).toFixed(2);
  ultimoFolio=folio;document.getElementById('ov').classList.add('show');
}
function nuevaVenta(){
  carrito=[];render();
  ['cef','mx-ef','mx-tk'].forEach(id=>{const e=document.getElementById(id);if(e)e.value='';});
  calcCambio();calcMixto();
  document.getElementById('ov').classList.remove('show');inp.focus();
}
function abrirTicket(){if(ultimoFolio)window.open('ticket.php?folio='+ultimoFolio,'_blank');}

let _tt;
function toast(msg,type='ok'){const el=document.getElementById('tst');el.textContent=msg;el.className=`tst show t${type}`;clearTimeout(_tt);_tt=setTimeout(()=>el.classList.remove('show'),2300);}

document.addEventListener('keydown',e=>{
  if(e.key==='F12'){e.preventDefault();document.getElementById('fv').requestSubmit();}
  if(e.key==='Escape'&&document.getElementById('ov').classList.contains('show'))nuevaVenta();
  if(e.key==='F2'){e.preventDefault();agregar();}
  if(document.activeElement.tagName!=='INPUT'&&e.key==='/'){e.preventDefault();inp.focus();}
});
</script>

<?php require_once 'includes/footer.php'; ?>
