<?php
// ============================================================
// material-publicitario.php - Gestión de Material Publicitario
// ============================================================
if (ob_get_level() === 0) ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_login();
require_rol('editor');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
}

// ── Auto-crear tabla ─────────────────────────────────────────
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS material_publicitario (
        id               INT AUTO_INCREMENT PRIMARY KEY,
        nombre           VARCHAR(255)  NOT NULL,
        tipo_candidatura ENUM('provincial','distrital') NOT NULL DEFAULT 'provincial',
        tipo_material    ENUM('banner','flyer','folleto','libro_electronico') NOT NULL DEFAULT 'banner',
        formato          ENUM('jpg','png','cdr','ai','psd') NOT NULL DEFAULT 'jpg',
        archivo          VARCHAR(500)  NOT NULL,
        activo           TINYINT(1)   NOT NULL DEFAULT 1,
        fecha_publicacion DATETIME    NOT NULL DEFAULT CURRENT_TIMESTAMP,
        creado_en        TIMESTAMP    DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_activo (activo),
        INDEX idx_tipo   (tipo_material)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
} catch (Exception $e) {}

// ── Constantes de upload ────────────────────────────────────
define('MAT_DIR',     dirname(__DIR__) . '/uploads/material/');
define('MAT_MAX',     10 * 1024 * 1024);
define('MAT_EXTS',    ['jpg','jpeg','png','cdr','ai','psd']);
define('MAT_IMG_EXTS',['jpg','jpeg','png']);
if (!is_dir(MAT_DIR)) @mkdir(MAT_DIR, 0755, true);

// ── Helper: guardar archivo ──────────────────────────────────
function mat_save_file(array $file): string|false {
    if ($file['error'] !== UPLOAD_ERR_OK) return false;
    if ($file['size'] > MAT_MAX)          return false;
    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, MAT_EXTS, true))  return false;
    if ($ext === 'jpeg') $ext = 'jpg';
    $name = 'mat_' . uniqid('', true) . '.' . $ext;
    if (!move_uploaded_file($file['tmp_name'], MAT_DIR . $name)) return false;
    return $name;
}

// ── Acción: crear ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'create') {
    $nombre   = trim($_POST['nombre']           ?? '');
    $candidat = $_POST['tipo_candidatura']       ?? 'provincial';
    $tipo     = $_POST['tipo_material']          ?? 'banner';
    $formato  = $_POST['formato']                ?? 'jpg';
    $valid_c  = ['provincial','distrital'];
    $valid_t  = ['banner','flyer','folleto','libro_electronico'];
    $valid_f  = ['jpg','png','cdr','ai','psd'];
    if (!in_array($candidat,$valid_c,true)) $candidat='provincial';
    if (!in_array($tipo,$valid_t,true))     $tipo='banner';
    if (!in_array($formato,$valid_f,true))  $formato='jpg';

    $archivo_guardado = false;
    if (!empty($_FILES['archivo']['name'])) {
        $archivo_guardado = mat_save_file($_FILES['archivo']);
    }
    if ($nombre && $archivo_guardado) {
        try {
            $pdo->prepare(
                "INSERT INTO material_publicitario
                 (nombre,tipo_candidatura,tipo_material,formato,archivo,activo,fecha_publicacion)
                 VALUES (?,?,?,?,?,1,NOW())"
            )->execute([$nombre, $candidat, $tipo, $formato, $archivo_guardado]);
            log_activity($pdo, 'Creó material publicitario: ' . $nombre, 'material_publicitario');
            header('Location: material-publicitario.php?msg=created'); exit;
        } catch (Exception $e) {
            @unlink(MAT_DIR . $archivo_guardado);
            header('Location: material-publicitario.php?msg=error'); exit;
        }
    }
    header('Location: material-publicitario.php?msg=error'); exit;
}

// ── Acción: editar ───────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'edit') {
    $id       = (int)($_POST['id'] ?? 0);
    $nombre   = trim($_POST['nombre']           ?? '');
    $candidat = $_POST['tipo_candidatura']       ?? 'provincial';
    $tipo     = $_POST['tipo_material']          ?? 'banner';
    $formato  = $_POST['formato']                ?? 'jpg';
    $valid_c  = ['provincial','distrital'];
    $valid_t  = ['banner','flyer','folleto','libro_electronico'];
    $valid_f  = ['jpg','png','cdr','ai','psd'];
    if (!in_array($candidat,$valid_c,true)) $candidat='provincial';
    if (!in_array($tipo,$valid_t,true))     $tipo='banner';
    if (!in_array($formato,$valid_f,true))  $formato='jpg';

    if ($id && $nombre) {
        try {
            $actual = $pdo->prepare("SELECT archivo FROM material_publicitario WHERE id=?");
            $actual->execute([$id]);
            $row = $actual->fetch();
            if (!$row) { header('Location: material-publicitario.php?msg=error'); exit; }

            $nuevo_archivo = $row['archivo'];
            if (!empty($_FILES['archivo']['name']) && $_FILES['archivo']['error'] === UPLOAD_ERR_OK) {
                $saved = mat_save_file($_FILES['archivo']);
                if ($saved) {
                    @unlink(MAT_DIR . $row['archivo']);
                    $nuevo_archivo = $saved;
                }
            }
            $pdo->prepare(
                "UPDATE material_publicitario
                 SET nombre=?,tipo_candidatura=?,tipo_material=?,formato=?,archivo=?
                 WHERE id=?"
            )->execute([$nombre, $candidat, $tipo, $formato, $nuevo_archivo, $id]);
            log_activity($pdo, 'Editó material publicitario: ' . $nombre, 'material_publicitario');
            header('Location: material-publicitario.php?msg=updated'); exit;
        } catch (Exception $e) {
            header('Location: material-publicitario.php?msg=error'); exit;
        }
    }
    header('Location: material-publicitario.php?msg=error'); exit;
}

// ── Acción: eliminar ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        try {
            $r = $pdo->prepare("SELECT nombre,archivo FROM material_publicitario WHERE id=?");
            $r->execute([$id]);
            $row = $r->fetch();
            $pdo->prepare("DELETE FROM material_publicitario WHERE id=?")->execute([$id]);
            if ($row) {
                @unlink(MAT_DIR . $row['archivo']);
                log_activity($pdo, 'Eliminó material: ' . $row['nombre'], 'material_publicitario');
            }
            header('Location: material-publicitario.php?msg=deleted'); exit;
        } catch (Exception $e) {
            header('Location: material-publicitario.php?msg=error'); exit;
        }
    }
    header('Location: material-publicitario.php?msg=error'); exit;
}

// ── Acción: toggle página pública ───────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle_page') {
    $nuevo = ($_POST['estado'] ?? '1') === '1' ? '1' : '0';
    try {
        $pdo->prepare(
            "INSERT INTO configuracion (clave,valor) VALUES ('material_page_active',?)
             ON DUPLICATE KEY UPDATE valor=?"
        )->execute([$nuevo, $nuevo]);
        log_activity($pdo, 'Página material publicitario '.($nuevo==='1'?'activada':'desactivada'), 'material_publicitario');
        header('Location: material-publicitario.php?msg=page_'.($nuevo==='1'?'on':'off')); exit;
    } catch (Exception $e) {
        header('Location: material-publicitario.php?msg=error'); exit;
    }
}

// ── Acción: toggle activo ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        try {
            $r = $pdo->prepare("SELECT nombre,activo FROM material_publicitario WHERE id=?");
            $r->execute([$id]);
            $row = $r->fetch();
            if ($row) {
                $nuevo = $row['activo'] ? 0 : 1;
                $pdo->prepare("UPDATE material_publicitario SET activo=? WHERE id=?")
                    ->execute([$nuevo, $id]);
                log_activity($pdo, ($nuevo?'Publicó':'Ocultó').' material: '.$row['nombre'], 'material_publicitario');
            }
            header('Location: material-publicitario.php?msg=toggled'); exit;
        } catch (Exception $e) {
            header('Location: material-publicitario.php?msg=error'); exit;
        }
    }
    header('Location: material-publicitario.php?msg=error'); exit;
}

// ── Cargar materiales ─────────────────────────────────────────
$filtro_tipo    = $_GET['tipo']      ?? '';
$filtro_cand    = $_GET['candidat']  ?? '';
$filtro_estado  = $_GET['estado']    ?? '';
$filtro_buscar  = trim($_GET['q']    ?? '');
$where = []; $params = [];
$valid_tipos  = ['banner','flyer','folleto','libro_electronico'];
$valid_cands  = ['provincial','distrital'];
if (in_array($filtro_tipo,  $valid_tipos))  { $where[]='tipo_material=?';    $params[]=$filtro_tipo; }
if (in_array($filtro_cand,  $valid_cands))  { $where[]='tipo_candidatura=?'; $params[]=$filtro_cand; }
if ($filtro_estado === 'activo')            { $where[]='activo=1'; }
if ($filtro_estado === 'oculto')            { $where[]='activo=0'; }
if ($filtro_buscar !== '')                  { $where[]='nombre LIKE ?'; $params[]='%'.$filtro_buscar.'%'; }
$sql_where = $where ? 'WHERE '.implode(' AND ',$where) : '';

$materiales = [];
try {
    $sq = $pdo->prepare("SELECT * FROM material_publicitario $sql_where ORDER BY fecha_publicacion DESC");
    $sq->execute($params);
    $materiales = $sq->fetchAll();
} catch (Exception $e) {}

$total = count($materiales);

// ── Estado página pública ────────────────────────────────────
$page_activa = true;
try {
    $row_pg = $pdo->query("SELECT valor FROM configuracion WHERE clave='material_page_active'")->fetch();
    $page_activa = $row_pg ? ($row_pg['valor'] !== '0') : true;
} catch (Exception $e) {}

$page_title = 'Material Publicitario';
include __DIR__ . '/layout.php';
?>

<style>
  [x-cloak] { display: none !important; }
  .mat-card { transition: transform .18s ease, box-shadow .18s ease; }
  .mat-card:hover { transform: translateY(-3px); box-shadow: 0 12px 32px rgba(0,0,0,.10); }
  .thumb-wrap { position:relative; height:180px; overflow:hidden; border-radius:12px 12px 0 0; }
  .thumb-wrap img { width:100%; height:100%; object-fit:cover; }
  .fmt-icon { width:100%; height:100%; display:flex; flex-direction:column; align-items:center; justify-content:center; gap:6px; }
  .badge-tipo { font-size:10px; font-weight:800; padding:2px 8px; border-radius:99px; text-transform:uppercase; letter-spacing:.04em; }
</style>

<?php $msg = $_GET['msg'] ?? ''; ?>
<?php if ($msg): ?>
<div x-data="{show:true}" x-show="show" x-init="setTimeout(()=>show=false,3500)"
     class="mb-4 px-4 py-3 rounded-xl text-sm font-semibold flex items-center gap-2
            <?= in_array($msg,['created','updated','toggled','page_on','page_off'])
                ? 'bg-green-100 text-green-800' : ($msg==='deleted'?'bg-amber-100 text-amber-800':'bg-red-100 text-red-700') ?>">
  <?= $msg==='created'   ? 'Diseño subido correctamente.'
    :($msg==='updated'   ? 'Diseño actualizado correctamente.'
    :($msg==='deleted'   ? 'Diseño eliminado.'
    :($msg==='toggled'   ? 'Visibilidad actualizada.'
    :($msg==='page_on'   ? 'Página pública activada. Los visitantes ya pueden verla.'
    :($msg==='page_off'  ? 'Página pública desactivada. Los visitantes ven pantalla de "Próximamente".'
    :'Ocurrió un error. Verifica el archivo e inténtalo de nuevo.'))))) ?>
</div>
<?php endif; ?>

<div x-data="materialAdmin()" x-cloak>

  <!-- ── Cabecera ─────────────────────────────────────────── -->
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
    <div>
      <h2 class="text-xl font-black text-gray-800">Material Publicitario</h2>
      <p class="text-sm text-gray-500 mt-0.5"><?= $total ?> diseño<?= $total!==1?'s':'' ?> registrados</p>
    </div>
    <div class="flex items-center gap-3 flex-wrap">

      <!-- Toggle página pública -->
      <form method="POST" class="flex items-center gap-3 bg-white border border-gray-200 rounded-xl px-4 py-2 shadow-sm">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="toggle_page">
        <input type="hidden" name="estado" value="<?= $page_activa ? '0' : '1' ?>">
        <div class="flex items-center gap-2.5">
          <div class="relative flex-shrink-0">
            <div class="w-10 h-5.5 rounded-full flex items-center transition-colors <?= $page_activa ? 'bg-green-500' : 'bg-gray-300' ?>"
                 style="height:22px;width:40px">
              <div class="w-4 h-4 bg-white rounded-full shadow transition-transform mx-0.5
                          <?= $page_activa ? 'translate-x-5' : 'translate-x-0' ?>"
                   style="width:16px;height:16px;transform:translateX(<?= $page_activa ? '20px' : '2px' ?>)"></div>
            </div>
          </div>
          <div class="leading-tight">
            <p class="text-xs font-black text-gray-700">Página pública</p>
            <p class="text-[10px] <?= $page_activa ? 'text-green-600' : 'text-gray-400' ?> font-semibold">
              <?= $page_activa ? 'Visible para el público' : 'Desactivada (Próximamente)' ?>
            </p>
          </div>
        </div>
        <button type="submit"
                onclick="return confirm('<?= $page_activa ? '¿Desactivar la página pública de Material Publicitario? Los visitantes verán una pantalla de próximamente.' : '¿Activar la página pública? Los visitantes podrán ver y descargar los diseños.' ?>')"
                class="text-xs font-bold px-3 py-1.5 rounded-lg transition-all
                       <?= $page_activa ? 'bg-red-50 text-red-600 hover:bg-red-100' : 'bg-green-50 text-green-600 hover:bg-green-100' ?>">
          <?= $page_activa ? 'Desactivar' : 'Activar' ?>
        </button>
        <a href="<?= BASE_URL ?>/material.php" target="_blank"
           class="text-gray-400 hover:text-[#1E3A8A] transition-colors" title="Ver página pública">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
          </svg>
        </a>
      </form>

      <!-- Botón añadir -->
      <button @click="openAdd()"
              class="inline-flex items-center gap-2 bg-[#1E3A8A] hover:bg-blue-900
                     text-white font-bold text-sm px-5 py-2.5 rounded-xl shadow transition-all
                     hover:scale-[1.02] active:scale-95">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
        </svg>
        Añadir diseño
      </button>
    </div>
  </div>

  <!-- ── Filtros ──────────────────────────────────────────── -->
  <form method="GET" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 mb-6 flex flex-wrap gap-3 items-end">
    <div>
      <label class="block text-[10px] font-black text-gray-400 uppercase mb-1">Buscar</label>
      <input name="q" value="<?= htmlspecialchars($filtro_buscar) ?>" placeholder="Nombre del diseño..."
             class="border border-gray-200 rounded-xl px-3 py-2 text-sm w-52 focus:outline-none focus:ring-2 focus:ring-blue-200">
    </div>
    <div>
      <label class="block text-[10px] font-black text-gray-400 uppercase mb-1">Tipo</label>
      <select name="tipo" class="border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none">
        <option value="">Todos</option>
        <?php foreach (['banner'=>'Banner','flyer'=>'Flyer','folleto'=>'Folleto','libro_electronico'=>'Libro Electrónico'] as $v=>$l): ?>
        <option value="<?= $v ?>" <?= $filtro_tipo===$v?'selected':'' ?>><?= $l ?></option>
        <?php endforeach; ?>
      </select>
    </div>
    <div>
      <label class="block text-[10px] font-black text-gray-400 uppercase mb-1">Candidatura</label>
      <select name="candidat" class="border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none">
        <option value="">Todas</option>
        <option value="provincial" <?= $filtro_cand==='provincial'?'selected':'' ?>>Provincial</option>
        <option value="distrital"  <?= $filtro_cand==='distrital'?'selected':'' ?>>Distrital</option>
      </select>
    </div>
    <div>
      <label class="block text-[10px] font-black text-gray-400 uppercase mb-1">Estado</label>
      <select name="estado" class="border border-gray-200 rounded-xl px-3 py-2 text-sm focus:outline-none">
        <option value="">Todos</option>
        <option value="activo" <?= $filtro_estado==='activo'?'selected':'' ?>>Activos</option>
        <option value="oculto" <?= $filtro_estado==='oculto'?'selected':'' ?>>Ocultos</option>
      </select>
    </div>
    <button type="submit" class="bg-[#1E3A8A] text-white text-sm font-bold px-4 py-2 rounded-xl hover:bg-blue-900 transition-all">
      Filtrar
    </button>
    <?php if ($filtro_tipo||$filtro_cand||$filtro_estado||$filtro_buscar): ?>
    <a href="material-publicitario.php" class="text-sm text-gray-400 hover:text-gray-600 font-medium py-2">Limpiar</a>
    <?php endif; ?>
  </form>

  <!-- ── Grid de cards ────────────────────────────────────── -->
  <?php if (empty($materiales)): ?>
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-16 text-center">
    <div class="w-16 h-16 bg-blue-50 rounded-full flex items-center justify-center mx-auto mb-4">
      <svg class="w-8 h-8 text-[#1E3A8A]/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
              d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
      </svg>
    </div>
    <p class="text-gray-400 text-sm font-medium">No hay materiales registrados.</p>
    <button @click="openAdd()" class="mt-4 text-[#1E3A8A] font-bold text-sm hover:underline">+ Añadir el primero</button>
  </div>
  <?php else: ?>
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4 gap-5">
    <?php foreach ($materiales as $mat):
      $es_imagen  = in_array($mat['formato'], ['jpg','png'], true);
      $archivo_url = BASE_URL . '/uploads/material/' . rawurlencode($mat['archivo']);
      $tipo_labels = ['banner'=>'Banner','flyer'=>'Flyer','folleto'=>'Folleto','libro_electronico'=>'Libro Electrónico'];
      $tipo_label  = $tipo_labels[$mat['tipo_material']] ?? $mat['tipo_material'];
      $cand_label  = $mat['tipo_candidatura'] === 'provincial' ? 'Provincial' : 'Distrital';
      $mat_json    = htmlspecialchars(json_encode([
        'id'              => (int)$mat['id'],
        'nombre'          => $mat['nombre'],
        'tipo_candidatura'=> $mat['tipo_candidatura'],
        'tipo_material'   => $mat['tipo_material'],
        'formato'         => $mat['formato'],
        'archivo'         => $mat['archivo'],
      ], JSON_UNESCAPED_UNICODE), ENT_QUOTES);
    ?>
    <div class="mat-card bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden
                <?= !$mat['activo'] ? 'opacity-60' : '' ?>">

      <!-- Thumbnail -->
      <div class="thumb-wrap bg-gray-50">
        <?php if ($es_imagen): ?>
          <img src="<?= htmlspecialchars($archivo_url) ?>" alt="<?= htmlspecialchars($mat['nombre']) ?>"
               onerror="this.parentElement.innerHTML='<div class=\'fmt-icon bg-gray-100\'><span class=\'text-gray-400 text-xs font-bold\'>Sin preview</span></div>'">
        <?php else: ?>
          <?php
          $fmt_styles = [
            'cdr' => ['bg'=>'linear-gradient(135deg,#007A5E,#00A383)','label'=>'CDR','sub'=>'CorelDRAW'],
            'ai'  => ['bg'=>'linear-gradient(135deg,#FF7C00,#FF9A00)','label'=>'Ai', 'sub'=>'Illustrator'],
            'psd' => ['bg'=>'linear-gradient(135deg,#1473E6,#31A8FF)','label'=>'Ps', 'sub'=>'Photoshop'],
          ];
          $fs = $fmt_styles[$mat['formato']] ?? ['bg'=>'linear-gradient(135deg,#6B7280,#9CA3AF)','label'=>strtoupper($mat['formato']),'sub'=>'Archivo'];
          ?>
          <div class="fmt-icon" style="background:<?= $fs['bg'] ?>">
            <span style="font-size:2.8rem;font-weight:900;color:rgba(255,255,255,.95);line-height:1;font-family:'Inter',sans-serif;letter-spacing:-.04em"><?= $fs['label'] ?></span>
            <span style="font-size:11px;color:rgba(255,255,255,.65);font-weight:700;letter-spacing:.06em;text-transform:uppercase"><?= $fs['sub'] ?></span>
            <svg style="width:28px;height:28px;color:rgba(255,255,255,.25);margin-top:4px" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
            </svg>
          </div>
        <?php endif; ?>
        <?php if (!$mat['activo']): ?>
        <div class="absolute top-2 right-2 bg-black/60 text-white text-[9px] font-bold px-2 py-0.5 rounded-full uppercase tracking-widest">Oculto</div>
        <?php endif; ?>
      </div>

      <!-- Datos -->
      <div class="p-4">
        <h3 class="font-bold text-gray-800 text-sm leading-tight mb-2 truncate" title="<?= htmlspecialchars($mat['nombre']) ?>">
          <?= htmlspecialchars($mat['nombre']) ?>
        </h3>
        <div class="flex flex-wrap gap-1.5 mb-3">
          <span class="badge-tipo bg-blue-100 text-blue-700"><?= $tipo_label ?></span>
          <span class="badge-tipo bg-purple-100 text-purple-700"><?= $cand_label ?></span>
          <span class="badge-tipo <?= $mat['formato']==='cdr'?'bg-green-100 text-green-700':($mat['formato']==='ai'?'bg-orange-100 text-orange-700':($mat['formato']==='psd'?'bg-sky-100 text-sky-700':'bg-gray-100 text-gray-600')) ?>"><?= strtoupper($mat['formato']) ?></span>
        </div>
        <p class="text-[10px] text-gray-400 mb-4">
          <?= date('d M Y', strtotime($mat['fecha_publicacion'])) ?>
        </p>

        <!-- Acciones -->
        <div class="flex items-center gap-2 border-t border-gray-100 pt-3">
          <button @click="openEdit(<?= $mat_json ?>)"
                  class="flex-1 text-xs font-bold text-[#1E3A8A] hover:bg-blue-50 py-1.5 rounded-lg transition-colors flex items-center justify-center gap-1">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg> Editar
          </button>

          <form method="POST" class="flex-1" onsubmit="return true">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="toggle">
            <input type="hidden" name="id" value="<?= $mat['id'] ?>">
            <button type="submit"
                    class="w-full text-xs font-bold <?= $mat['activo']?'text-amber-600 hover:bg-amber-50':'text-green-600 hover:bg-green-50' ?> py-1.5 rounded-lg transition-colors flex items-center justify-center gap-1">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $mat['activo']?'M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21':'M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z' ?>"/>
              </svg>
              <?= $mat['activo']?'Ocultar':'Mostrar' ?>
            </button>
          </form>

          <form method="POST" class="flex-1" onsubmit="return confirm('¿Eliminar este diseño? Esta acción no se puede deshacer.')">
            <?= csrf_field() ?>
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" value="<?= $mat['id'] ?>">
            <button type="submit"
                    class="w-full text-xs font-bold text-red-500 hover:bg-red-50 py-1.5 rounded-lg transition-colors flex items-center justify-center gap-1">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
              </svg>
              Eliminar
            </button>
          </form>
        </div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
  <?php endif; ?>

  <!-- ── MODAL Añadir / Editar ────────────────────────────── -->
  <div x-show="showModal"
       class="fixed inset-0 z-50 flex items-center justify-center p-4"
       @keydown.escape.window="close()">
    <!-- Overlay -->
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="close()"></div>

    <!-- Panel -->
    <div class="relative bg-white rounded-3xl shadow-2xl w-full max-w-lg max-h-[90vh] overflow-y-auto"
         @click.stop>
      <!-- Header modal -->
      <div class="flex items-center justify-between px-6 py-5 border-b border-gray-100">
        <div class="flex items-center gap-3">
          <div class="w-9 h-9 bg-[#1E3A8A] rounded-xl flex items-center justify-center">
            <svg class="w-4.5 h-4.5 text-white w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
          </div>
          <div>
            <h3 class="font-black text-gray-800 text-base" x-text="isEdit ? 'Editar Diseño' : 'Añadir Diseño'"></h3>
            <p class="text-xs text-gray-400">Material Publicitario</p>
          </div>
        </div>
        <button @click="close()" class="p-2 rounded-xl hover:bg-gray-100 transition-colors">
          <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>

      <!-- Form -->
      <form method="POST" enctype="multipart/form-data" class="px-6 py-5 space-y-4">
        <?= csrf_field() ?>
        <input type="hidden" name="action" :value="isEdit ? 'edit' : 'create'">
        <input type="hidden" name="id" :value="form.id">

        <!-- Nombre -->
        <div>
          <label class="block text-xs font-black text-gray-500 uppercase mb-1.5">Nombre del diseño <span class="text-red-500">*</span></label>
          <input type="text" name="nombre" x-model="form.nombre" required maxlength="255"
                 placeholder="Ej: Banner Lanzamiento de Campaña"
                 class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
        </div>

        <div class="grid grid-cols-2 gap-3">
          <!-- Tipo candidatura -->
          <div>
            <label class="block text-xs font-black text-gray-500 uppercase mb-1.5">Candidato</label>
            <select name="tipo_candidatura" x-model="form.tipo_candidatura"
                    class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
              <option value="provincial">Provincial</option>
              <option value="distrital">Distrital</option>
            </select>
          </div>
          <!-- Tipo material -->
          <div>
            <label class="block text-xs font-black text-gray-500 uppercase mb-1.5">Tipo</label>
            <select name="tipo_material" x-model="form.tipo_material"
                    class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-200">
              <option value="banner">Banner</option>
              <option value="flyer">Flyer</option>
              <option value="folleto">Folleto</option>
              <option value="libro_electronico">Libro Electrónico</option>
            </select>
          </div>
        </div>

        <!-- Formato -->
        <div>
          <label class="block text-xs font-black text-gray-500 uppercase mb-1.5">Formato</label>
          <div class="grid grid-cols-5 gap-2">
            <?php foreach (['jpg','png','cdr','ai','psd'] as $fmt):
              $fmt_colors = ['jpg'=>'blue','png'=>'teal','cdr'=>'green','ai'=>'orange','psd'=>'sky'];
              $c = $fmt_colors[$fmt] ?? 'gray';
            ?>
            <label class="cursor-pointer">
              <input type="radio" name="formato" value="<?= $fmt ?>"
                     x-model="form.formato" class="sr-only peer">
              <div class="text-center py-2 rounded-xl border-2 border-gray-200 text-xs font-black uppercase
                          peer-checked:border-[#1E3A8A] peer-checked:bg-[#1E3A8A] peer-checked:text-white
                          text-gray-500 hover:border-gray-300 transition-all select-none">
                <?= strtoupper($fmt) ?>
              </div>
            </label>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Archivo -->
        <div>
          <label class="block text-xs font-black text-gray-500 uppercase mb-1.5">
            Archivo <span x-show="!isEdit" class="text-red-500">*</span>
            <span x-show="isEdit" class="text-gray-400 normal-case font-medium">(dejar vacío para conservar el actual)</span>
          </label>
          <!-- Preview archivo actual en edición -->
          <div x-show="isEdit && form.archivo" class="mb-2 p-3 bg-gray-50 rounded-xl flex items-center gap-3">
            <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
            </svg>
            <span class="text-xs text-gray-500 font-mono truncate" x-text="form.archivo"></span>
          </div>
          <div class="border-2 border-dashed border-gray-200 rounded-xl p-4 text-center hover:border-blue-300 transition-colors"
               x-data="{ fileName: '' }">
            <input type="file" name="archivo" id="mat-file-input" class="hidden"
                   :required="!isEdit"
                   accept=".jpg,.jpeg,.png,.cdr,.ai,.psd"
                   @change="fileName = $event.target.files[0]?.name || ''">
            <label for="mat-file-input" class="cursor-pointer">
              <svg class="w-8 h-8 text-gray-300 mx-auto mb-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
              </svg>
              <p class="text-sm text-gray-400" x-show="!fileName">
                <span class="font-semibold text-[#1E3A8A]">Click para seleccionar</span> o arrastra el archivo
              </p>
              <p class="text-sm font-semibold text-[#1E3A8A] truncate" x-show="fileName" x-text="fileName"></p>
              <p class="text-[10px] text-gray-300 mt-1">JPG, PNG, CDR, AI, PSD · Máx 10MB</p>
            </label>
          </div>
        </div>

        <!-- Footer modal -->
        <div class="flex gap-3 pt-2">
          <button type="button" @click="close()"
                  class="flex-1 py-2.5 rounded-xl border border-gray-200 text-sm font-bold text-gray-600 hover:bg-gray-50 transition-colors">
            Cancelar
          </button>
          <button type="submit"
                  class="flex-1 py-2.5 rounded-xl bg-[#1E3A8A] text-white text-sm font-bold hover:bg-blue-900 transition-all hover:scale-[1.02] active:scale-95 shadow">
            <span x-text="isEdit ? 'Guardar cambios' : 'Subir diseño'"></span>
          </button>
        </div>
      </form>
    </div>
  </div>

</div><!-- /x-data -->

<script>
function materialAdmin() {
  return {
    showModal: false,
    isEdit: false,
    form: {
      id: 0,
      nombre: '',
      tipo_candidatura: 'provincial',
      tipo_material: 'banner',
      formato: 'jpg',
      archivo: '',
    },

    openAdd() {
      this.isEdit = false;
      this.form = { id:0, nombre:'', tipo_candidatura:'provincial', tipo_material:'banner', formato:'jpg', archivo:'' };
      this.showModal = true;
      this.$nextTick(() => {
        const inp = document.getElementById('mat-file-input');
        if (inp) inp.value = '';
      });
    },

    openEdit(data) {
      this.isEdit = true;
      this.form = { ...data };
      this.showModal = true;
      this.$nextTick(() => {
        const inp = document.getElementById('mat-file-input');
        if (inp) inp.value = '';
      });
    },

    close() {
      this.showModal = false;
    },
  };
}
</script>

    </main>
  </div>
</body>
</html>
