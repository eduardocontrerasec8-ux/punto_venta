<?php
require_once 'config/database.php';

// ============================================
// ⚠️  TODO el procesamiento POST va AQUÍ,
//     ANTES de incluir header.php (que envía HTML)
// ============================================


// Verificar permisos antes de cualquier output
if (!in_array($_SESSION['puesto'], ['Administrador', 'Cajero'])) {
    $_SESSION['error'] = 'No tienes permisos para gestionar la caja';
    header("Location: dashboard.php");
    exit();
}

$usuario_id = $_SESSION['user_id'];

// Función auxiliar
function obtenerSesionActiva($conn, $usuario_id) {
    $stmt = $conn->prepare("SELECT * FROM sesion_caja WHERE usuario_id = :user_id AND estatus = 'abierta' LIMIT 1");
    $stmt->execute([':user_id' => $usuario_id]);
    return $stmt->fetch();
}

$sesion_activa_result = obtenerSesionActiva($conn, $usuario_id);

// ============================================
// PROCESAR APERTURA DE CAJA
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['iniciar_corte'])) {
    try {
        if (!isset($_POST['monto_inicial']) || !is_numeric($_POST['monto_inicial'])) {
            throw new Exception("El monto inicial debe ser un número válido");
        }

        $monto_inicial = floatval($_POST['monto_inicial']);

        if ($monto_inicial < 0) {
            throw new Exception("El monto inicial no puede ser negativo");
        }

        if ($sesion_activa_result) {
            throw new Exception("Ya tienes una sesión de caja activa. Debes cerrarla primero.");
        }

        $conn->beginTransaction();

        $stmt = $conn->prepare("INSERT INTO sesion_caja (usuario_id, monto_inicial, fecha_apertura, estatus) 
                               VALUES (:usuario_id, :monto_inicial, NOW(), 'abierta')");
        $stmt->execute([
            ':usuario_id'    => $usuario_id,
            ':monto_inicial' => $monto_inicial
        ]);

        $conn->commit();

        $_SESSION['success'] = "Sesión de caja iniciada correctamente con monto inicial: $" . number_format($monto_inicial, 2);
        header("Location: sesion_caja.php");
        exit();

    } catch (Exception $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        $_SESSION['error'] = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        header("Location: sesion_caja.php");
        exit();
    }
}

// ============================================
// PROCESAR CIERRE DE CAJA
// ============================================
if ($_SERVER['REQUEST_METHOD'] == 'POST' && isset($_POST['cerrar_corte'])) {
    try {
        if (!$sesion_activa_result) {
            throw new Exception("No hay sesión activa para cerrar");
        }

        $conn->beginTransaction();

        $ventas_sesion = $conn->prepare("SELECT COUNT(*) as cantidad, COALESCE(SUM(total), 0) as total 
                                        FROM venta 
                                        WHERE sesion_caja_id = :sesion_id");
        $ventas_sesion->execute([':sesion_id' => $sesion_activa_result['id']]);
        $ventas_result = $ventas_sesion->fetch();

        $monto_final   = $sesion_activa_result['monto_inicial'] + $ventas_result['total'];
        $observaciones = isset($_POST['observaciones']) ? trim($_POST['observaciones']) : '';

        $stmt = $conn->prepare("UPDATE sesion_caja 
                               SET fecha_cierre = NOW(),
                                   monto_final = :monto_final,
                                   ventas_durante_sesion = :ventas,
                                   total_ventas = :total_ventas,
                                   estatus = 'cerrada',
                                   observaciones = :observaciones
                               WHERE id = :id");
        $stmt->execute([
            ':monto_final'   => $monto_final,
            ':ventas'        => $ventas_result['cantidad'],
            ':total_ventas'  => $ventas_result['total'],
            ':observaciones' => $observaciones,
            ':id'            => $sesion_activa_result['id']
        ]);

        $conn->commit();

        $_SESSION['success'] = "Sesión de caja cerrada correctamente. Total: $" . number_format($monto_final, 2);
        header("Location: sesion_caja.php");
        exit();

    } catch (Exception $e) {
        if ($conn->inTransaction()) $conn->rollBack();
        $_SESSION['error'] = htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
        header("Location: sesion_caja.php");
        exit();
    }
}

// ============================================
// A PARTIR DE AQUÍ ya podemos emitir HTML
// ============================================
require_once 'includes/header.php';

// Leer mensajes flash
$success = '';
$error   = '';

if (isset($_SESSION['success'])) { $success = $_SESSION['success']; unset($_SESSION['success']); }
if (isset($_SESSION['error']))   { $error   = $_SESSION['error'];   unset($_SESSION['error']);   }

// Re-obtener sesión (pudo haber cambiado tras el redirect)
$sesion_activa_result = obtenerSesionActiva($conn, $usuario_id);

// Historial
$historial = $conn->prepare("SELECT sc.*, u.nombre as usuario_nombre 
                            FROM sesion_caja sc 
                            JOIN usuario u ON sc.usuario_id = u.id 
                            WHERE sc.usuario_id = :user_id 
                            ORDER BY sc.fecha_apertura DESC 
                            LIMIT 10");
$historial->execute([':user_id' => $usuario_id]);
$historial_result = $historial->fetchAll();

// Ventas de la sesión actual
$ventas_actual = ['cantidad' => 0, 'total' => 0];
if ($sesion_activa_result) {
    $ventas_actuales = $conn->prepare("SELECT COUNT(*) as cantidad, COALESCE(SUM(total), 0) as total 
                                      FROM venta WHERE sesion_caja_id = :sesion_id");
    $ventas_actuales->execute([':sesion_id' => $sesion_activa_result['id']]);
    $ventas_actual = $ventas_actuales->fetch();
}

$caja_abierta   = (bool)$sesion_activa_result;
$monto_estimado = $caja_abierta
    ? $sesion_activa_result['monto_inicial'] + $ventas_actual['total']
    : 0;
?>

<style>
.caja-status-card {
  border-radius: 16px !important;
  overflow: hidden;
  box-shadow: 0 8px 32px rgba(21,101,192,.2) !important;
}
.caja-status-card .status-header {
  padding: 24px 26px 18px;
  position: relative;
  overflow: hidden;
}
.caja-status-card .status-header::before {
  content: '';
  position: absolute;
  right: -30px; top: -30px;
  width: 120px; height: 120px;
  border-radius: 50%;
  background: rgba(255,255,255,.08);
  pointer-events: none;
}
.caja-status-card .status-header::after {
  content: '';
  position: absolute;
  right: 50px; bottom: -20px;
  width: 80px; height: 80px;
  border-radius: 50%;
  background: rgba(255,255,255,.06);
  pointer-events: none;
}
.status-title {
  display: flex;
  align-items: center;
  gap: 10px;
  font-family: var(--font, 'Nunito', sans-serif);
  font-size: 1.05rem;
  font-weight: 800;
  color: #fff;
  margin-bottom: 16px;
  position: relative;
  z-index: 1;
}
.status-title i { font-size: 1.5rem; }
.estado-badge {
  padding: 3px 13px;
  border-radius: 20px;
  font-size: .72rem;
  font-weight: 800;
  letter-spacing: .05em;
  font-family: var(--font, 'Nunito', sans-serif);
}
.estado-abierta { background: rgba(255,255,255,.25); color: #fff; border: 1px solid rgba(255,255,255,.4); }
.estado-cerrada { background: rgba(255,255,255,.15); color: rgba(255,255,255,.8); }

.stat-row {
  display: grid;
  grid-template-columns: 1fr 1fr;
  gap: 10px;
  position: relative;
  z-index: 1;
}
.stat-box {
  background: rgba(255,255,255,.15);
  border-radius: 10px;
  padding: 12px 14px;
  backdrop-filter: blur(4px);
}
.stat-box .sb-label {
  font-size: .68rem;
  font-weight: 700;
  opacity: .7;
  letter-spacing: .06em;
  text-transform: uppercase;
  color: #fff;
  margin-bottom: 4px;
}
.stat-box .sb-val {
  font-size: 1.4rem;
  font-weight: 900;
  color: #fff;
  line-height: 1.1;
  font-family: var(--font, 'Nunito', sans-serif);
}
.stat-box.highlight {
  background: rgba(255,255,255,.25);
  grid-column: 1 / -1;
}
.stat-box.highlight .sb-val { font-size: 2rem; }

.caja-acciones {
  padding: 16px 22px;
  background: rgba(0,0,0,.1);
  display: flex;
  gap: 10px;
  flex-wrap: wrap;
}

.caja-closed-msg {
  padding: 30px 20px;
  text-align: center;
  position: relative;
  z-index: 1;
}
.caja-closed-msg i { font-size: 3.5rem; opacity: .4; color: #fff; }
.caja-closed-msg h5 { color: #fff; font-weight: 900; margin: 8px 0 4px; font-family: var(--font,'Nunito',sans-serif); }
.caja-closed-msg p  { color: rgba(255,255,255,.65); font-size: .85rem; margin: 0; }

/* Historial */
.hist-card { border-radius: 14px !important; box-shadow: var(--sombra, 0 4px 20px rgba(21,101,192,.13)) !important; }

/* Instrucciones */
.instruc-card {
  border-radius: 14px !important;
  background: var(--azul6, #e8f0fe) !important;
  border: 2px solid var(--borde, #c5cae9) !important;
  box-shadow: none !important;
}
.instruc-card li {
  padding: 6px 0;
  color: var(--texto, #1a237e);
  font-size: .9rem;
  font-family: var(--font, 'Nunito', sans-serif);
  border-bottom: 1px solid rgba(92,107,192,.12);
}
.instruc-card li:last-child { border-bottom: none; }
.instruc-card li strong { color: var(--azul2, #1565c0); }

/* Modales mejorados */
.modal-header-green { background: linear-gradient(135deg, #1b5e20, #2e7d32, #43a047); padding: 18px 24px; border-radius: 14px 14px 0 0; }
.modal-header-red   { background: linear-gradient(135deg, #b71c1c, #c62828, #e53935); padding: 18px 24px; border-radius: 14px 14px 0 0; }
.modal-header-green h5,
.modal-header-red   h5 { margin: 0; color: #fff; font-weight: 800; display: flex; align-items: center; gap: 8px; font-family: var(--font,'Nunito',sans-serif); }
.modal-header-green p,
.modal-header-red   p  { margin: 3px 0 0; color: rgba(255,255,255,.65); font-size: .8rem; }
.modal-body-pad { padding: 20px 24px; }
.modal-foot-pad { padding: 14px 24px; background: var(--fondo, #f0f4ff); display: flex; justify-content: flex-end; gap: 10px; border-top: 2px solid var(--borde, #c5cae9); border-radius: 0 0 14px 14px; }

.resumen-modal {
  background: linear-gradient(135deg, #37474f, #455a64);
  border-radius: 10px;
  padding: 16px 18px;
  margin-bottom: 16px;
  color: #fff;
}
.resumen-modal .rm-grid { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; margin-top: 10px; }
.resumen-modal .rm-item .rm-lbl { font-size: .68rem; opacity: .65; text-transform: uppercase; letter-spacing: .06em; font-weight: 700; }
.resumen-modal .rm-item .rm-val { font-size: 1.1rem; font-weight: 900; margin-top: 2px; font-family: var(--font,'Nunito',sans-serif); }

/* Dot animado */
.pulse-dot {
  display: inline-block;
  width: 8px; height: 8px;
  border-radius: 50%;
  background: #69f0ae;
  box-shadow: 0 0 6px rgba(105,240,174,.7);
  animation: blink 2s infinite;
  margin-right: 4px;
}
@keyframes blink { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.4;transform:scale(.8)} }
</style>

<div class="container" style="padding-bottom: 40px;">

  <!-- Page Header -->
  <div class="pg-header" style="margin-top: 24px;">
    <div>
      <h4><i class="material-icons">point_of_sale</i> Gestión de Caja</h4>
      <p>Usuario: <?php echo htmlspecialchars($_SESSION['nombre'], ENT_QUOTES, 'UTF-8'); ?></p>
    </div>
    <div class="pg-badge">
      <?php if ($caja_abierta): ?>
        <span class="pulse-dot"></span> CAJA ABIERTA
      <?php else: ?>
        <i class="material-icons" style="font-size:1rem;">lock</i> CAJA CERRADA
      <?php endif; ?>
    </div>
  </div>

  <!-- Mensajes flash -->
  <?php if ($success): ?>
    <div class="msg-box msg-ok"><i class="material-icons">check_circle</i><?php echo htmlspecialchars($success, ENT_QUOTES, 'UTF-8'); ?></div>
  <?php endif; ?>
  <?php if ($error): ?>
    <div class="msg-box msg-err"><i class="material-icons">error</i><?php echo htmlspecialchars($error, ENT_QUOTES, 'UTF-8'); ?></div>
  <?php endif; ?>

  <div class="row">

    <!-- ══ ESTADO DE CAJA ══ -->
    <div class="col s12 m6">
      <div class="caja-status-card card"
           style="background: linear-gradient(135deg, <?php echo $caja_abierta ? '#1b5e20, #2e7d32, #388e3c' : '#b71c1c, #c62828, #d32f2f'; ?>);">

        <div class="status-header">
          <div class="status-title">
            <i class="material-icons"><?php echo $caja_abierta ? 'lock_open' : 'lock'; ?></i>
            Estado de Caja
            <?php if ($caja_abierta): ?>
              <span class="estado-badge estado-abierta"><span class="pulse-dot" style="width:6px;height:6px;margin-right:3px;"></span>ABIERTA</span>
            <?php else: ?>
              <span class="estado-badge estado-cerrada">CERRADA</span>
            <?php endif; ?>
          </div>

          <?php if ($caja_abierta): ?>
            <div class="stat-row">
              <div class="stat-box">
                <div class="sb-label">Monto Inicial</div>
                <div class="sb-val">$<?php echo number_format($sesion_activa_result['monto_inicial'], 2); ?></div>
              </div>
              <div class="stat-box">
                <div class="sb-label">Apertura</div>
                <div class="sb-val" style="font-size:1.1rem;"><?php echo date('H:i:s', strtotime($sesion_activa_result['fecha_apertura'])); ?></div>
              </div>
              <div class="stat-box">
                <div class="sb-label">Ventas Realizadas</div>
                <div class="sb-val"><?php echo $ventas_actual['cantidad']; ?></div>
              </div>
              <div class="stat-box">
                <div class="sb-label">Total Ventas</div>
                <div class="sb-val">$<?php echo number_format($ventas_actual['total'], 2); ?></div>
              </div>
              <div class="stat-box highlight">
                <div class="sb-label">💰 Estimado en Caja</div>
                <div class="sb-val">$<?php echo number_format($monto_estimado, 2); ?></div>
              </div>
            </div>
          <?php else: ?>
            <div class="caja-closed-msg">
              <i class="material-icons">lock</i>
              <h5>CAJA CERRADA</h5>
              <p>Para realizar ventas, inicia una sesión de caja</p>
            </div>
          <?php endif; ?>
        </div>

        <div class="caja-acciones">
          <?php if ($caja_abierta): ?>
            <button class="btn-pri modal-trigger" data-target="modal_cerrar_caja"
                    style="background: linear-gradient(135deg,#b71c1c,#d32f2f);">
              <i class="material-icons">lock</i> Cerrar Caja
            </button>
            <a href="ventas.php" class="btn-sec" style="color:#fff;border-color:rgba(255,255,255,.4);">
              <i class="material-icons">point_of_sale</i> Ir a Ventas
            </a>
          <?php else: ?>
            <button class="btn-pri modal-trigger" data-target="modal_apertura"
                    style="background:linear-gradient(135deg,#e8f5e9,#c8e6c9);color:#1b5e20;box-shadow:0 4px 14px rgba(27,94,32,.3);">
              <i class="material-icons">lock_open</i> Iniciar Caja
            </button>
            <span class="btn-sec" style="opacity:.45;cursor:not-allowed;">
              <i class="material-icons">block</i> Ventas Bloqueadas
            </span>
          <?php endif; ?>
        </div>
      </div>

      <!-- Instrucciones -->
      <div class="card instruc-card">
        <div class="card-content">
          <span class="card-title" style="color:var(--azul2);font-weight:800;display:flex;align-items:center;gap:8px;font-family:var(--font,'Nunito',sans-serif);">
            <i class="material-icons">info</i> Instrucciones
          </span>
          <ol class="instruc-card" style="padding-left:18px;margin:0;">
            <li><strong>Iniciar Caja:</strong> Registra el efectivo con que comienzas el turno</li>
            <li><strong>Durante la Sesión:</strong> Todas las ventas quedan ligadas a esta sesión</li>
            <li><strong>Cerrar Caja:</strong> Al terminar, genera el reporte automático del turno</li>
            <li><strong>Una sesión por usuario</strong> a la vez — cierra antes de abrir otra</li>
          </ol>
        </div>
      </div>
    </div>

    <!-- ══ HISTORIAL ══ -->
    <div class="col s12 m6">
      <div class="card hist-card">
        <div class="card-content">
          <span class="card-title" style="display:flex;align-items:center;justify-content:space-between;font-family:var(--font,'Nunito',sans-serif);font-weight:800;color:var(--texto);">
            <span><i class="material-icons left">history</i>Historial de Sesiones</span>
            <span style="font-size:.78rem;background:var(--azul6);color:var(--azul2);border-radius:12px;padding:3px 12px;font-weight:800;">
              <?php echo count($historial_result); ?> registros
            </span>
          </span>

          <div class="tabla-wrap" style="border-radius:10px;border:1px solid var(--borde);overflow:hidden;margin-top:12px;">
            <table class="prod-table" style="width:100%;border-collapse:collapse;">
              <thead>
                <tr>
                  <th>Fecha</th>
                  <th>Apertura</th>
                  <th>Cierre</th>
                  <th>Ventas</th>
                  <th>Total</th>
                  <th>Estado</th>
                </tr>
              </thead>
              <tbody>
                <?php if (empty($historial_result)): ?>
                  <tr>
                    <td colspan="6" style="text-align:center;padding:24px;color:var(--muted);font-family:var(--font,'Nunito',sans-serif);">
                      <i class="material-icons" style="display:block;font-size:2rem;opacity:.35;margin-bottom:6px;">inbox</i>
                      No hay sesiones registradas
                    </td>
                  </tr>
                <?php else: ?>
                  <?php foreach ($historial_result as $ses): ?>
                    <tr style="border-bottom:1px solid #e8edf8;font-family:var(--font,'Nunito',sans-serif);">
                      <td style="font-size:.85rem;font-weight:700;"><?php echo date('d/m/Y', strtotime($ses['fecha_apertura'])); ?></td>
                      <td style="font-size:.83rem;"><?php echo date('H:i', strtotime($ses['fecha_apertura'])); ?></td>
                      <td style="font-size:.83rem;"><?php echo $ses['fecha_cierre'] ? date('H:i', strtotime($ses['fecha_cierre'])) : '—'; ?></td>
                      <td style="font-weight:800;"><?php echo $ses['ventas_durante_sesion'] ?? '0'; ?></td>
                      <td style="font-weight:900;color:var(--verde2);">$<?php echo number_format($ses['total_ventas'] ?? 0, 2); ?></td>
                      <td>
                        <?php if ($ses['estatus'] == 'abierta'): ?>
                          <span class="pill pill-ok" style="font-size:.7rem;padding:2px 8px;"><span class="pulse-dot" style="width:5px;height:5px;margin-right:2px;"></span>Abierta</span>
                        <?php else: ?>
                          <span style="background:#eceff1;color:#546e7a;border-radius:10px;padding:2px 8px;font-size:.7rem;font-weight:800;">Cerrada</span>
                        <?php endif; ?>
                      </td>
                    </tr>
                  <?php endforeach; ?>
                <?php endif; ?>
              </tbody>
            </table>
          </div>

          <div style="text-align:right;margin-top:14px;">
            <a href="reporte_sesiones.php" class="btn-sec" style="font-size:.82rem;padding:8px 16px;">
              <i class="material-icons" style="font-size:.9rem;">assignment</i> Ver reporte completo
            </a>
          </div>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- ══ MODAL APERTURA ══ -->
<div id="modal_apertura" class="modal" style="border-radius:16px!important;max-width:480px;">
  <div class="modal-header-green">
    <h5><i class="material-icons">lock_open</i> Iniciar Sesión de Caja</h5>
    <p>Ingresa el efectivo con el que comienzas el turno</p>
  </div>
  <div class="modal-body-pad">
    <form method="POST" id="form_apertura">
      <div class="field-group">
        <label class="flbl">Monto Inicial en Efectivo *</label>
        <div class="field-icon">
          <i class="material-icons">attach_money</i>
          <input id="monto_inicial" name="monto_inicial" type="number" step="0.01" min="0" required placeholder="0.00">
        </div>
        <div class="helper">Efectivo físico con el que inicias la caja</div>
      </div>
      <div style="display:flex;align-items:center;gap:8px;padding:10px 0;font-family:var(--font,'Nunito',sans-serif);">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:700;color:var(--texto);">
          <input type="checkbox" required class="filled-in" id="check_apertura">
          <span>Confirmo que el monto ingresado es correcto</span>
        </label>
      </div>
    </form>
  </div>
  <div class="modal-foot-pad">
    <button class="modal-close btn-sec"><i class="material-icons">close</i> Cancelar</button>
    <button type="submit" form="form_apertura" name="iniciar_corte" class="btn-pri"
            style="background:linear-gradient(135deg,#2e7d32,#43a047);box-shadow:0 4px 14px rgba(46,125,50,.35);">
      <i class="material-icons">check</i> Iniciar Caja
    </button>
  </div>
</div>

<!-- ══ MODAL CIERRE ══ -->
<div id="modal_cerrar_caja" class="modal" style="border-radius:16px!important;max-width:520px;">
  <div class="modal-header-red">
    <h5><i class="material-icons">lock</i> Cerrar Sesión de Caja</h5>
    <p>Genera el reporte y cierra el turno actual</p>
  </div>
  <div class="modal-body-pad">
    <?php if ($sesion_activa_result): ?>
    <div class="resumen-modal">
      <div style="font-size:.72rem;font-weight:800;opacity:.65;text-transform:uppercase;letter-spacing:.07em;">Resumen de la sesión</div>
      <div class="rm-grid">
        <div class="rm-item"><div class="rm-lbl">Monto Inicial</div><div class="rm-val">$<?php echo number_format($sesion_activa_result['monto_inicial'], 2); ?></div></div>
        <div class="rm-item"><div class="rm-lbl">Total Ventas</div><div class="rm-val">$<?php echo number_format($ventas_actual['total'], 2); ?></div></div>
        <div class="rm-item"><div class="rm-lbl">Ventas Realizadas</div><div class="rm-val"><?php echo $ventas_actual['cantidad']; ?></div></div>
        <div class="rm-item"><div class="rm-lbl">Monto Final Estimado</div><div class="rm-val" style="color:#69f0ae;">$<?php echo number_format($monto_estimado, 2); ?></div></div>
      </div>
    </div>
    <?php endif; ?>

    <form method="POST" id="form_cierre">
      <div class="field-group">
        <label class="flbl">Observaciones (opcional)</label>
        <div class="field-icon" style="align-items:flex-start;">
          <i class="material-icons" style="margin-top:2px;">note</i>
          <textarea name="observaciones" rows="3" maxlength="500"
                    style="resize:vertical;border:none;background:transparent;width:100%;font-family:var(--font,'Nunito',sans-serif);font-size:.92rem;color:var(--texto);"
                    placeholder="Notas del turno… (máx. 500 caracteres)"></textarea>
        </div>
      </div>
      <div style="display:flex;align-items:center;gap:8px;padding:10px 0;font-family:var(--font,'Nunito',sans-serif);">
        <label style="display:flex;align-items:center;gap:8px;cursor:pointer;font-weight:700;color:var(--texto);">
          <input type="checkbox" required class="filled-in" id="check_confirmar">
          <span>Confirmo que el efectivo en caja coincide con el monto estimado</span>
        </label>
      </div>
    </form>
  </div>
  <div class="modal-foot-pad">
    <button class="modal-close btn-sec"><i class="material-icons">close</i> Cancelar</button>
    <button type="submit" form="form_cierre" name="cerrar_corte" class="btn-pri"
            style="background:linear-gradient(135deg,#b71c1c,#e53935);box-shadow:0 4px 14px rgba(198,40,40,.35);">
      <i class="material-icons">lock</i> Cerrar Caja
    </button>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function () {
  M.Modal.init(document.querySelectorAll('.modal'), {
    dismissible: false,
    onOpenEnd: function (el) {
      if (el.id === 'modal_apertura') document.getElementById('monto_inicial').focus();
    }
  });

  // Validación apertura
  document.getElementById('form_apertura').addEventListener('submit', function (e) {
    const monto = parseFloat(document.getElementById('monto_inicial').value);
    if (isNaN(monto) || monto < 0) {
      e.preventDefault();
      M.toast({ html: '⚠️ Ingresa un monto válido (mayor o igual a 0)', classes: 'red' });
      return;
    }
    if (!confirm(`¿Confirmas iniciar la caja con $${monto.toFixed(2)}?`)) {
      e.preventDefault();
    }
  });

  // Validación cierre
  document.getElementById('form_cierre').addEventListener('submit', function (e) {
    if (!document.getElementById('check_confirmar').checked) {
      e.preventDefault();
      M.toast({ html: '⚠️ Debes confirmar que el monto coincide', classes: 'orange' });
      return;
    }
    if (!confirm('¿Seguro que deseas cerrar la caja? Esta acción no se puede deshacer.')) {
      e.preventDefault();
    }
  });

  // Prevenir doble envío
  let submitting = false;
  document.querySelectorAll('form').forEach(f => {
    f.addEventListener('submit', function () {
      if (submitting) return false;
      submitting = true;
      setTimeout(() => submitting = false, 4000);
    });
  });
});
</script>

<?php require_once 'includes/footer.php'; ?>