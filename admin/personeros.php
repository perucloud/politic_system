<?php
if (ob_get_level() === 0) ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../includes/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/../includes/helpers/webp.php';

require_login();
require_rol('editor');

$page_title = 'Personeros';

// ── Helpers ───────────────────────────────────────────────────
function json_resp(array $d, int $s = 200): void {
    while (ob_get_level() > 0) ob_end_clean();
    http_response_code($s);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($d, JSON_UNESCAPED_UNICODE);
    exit;
}

// ── Migración automática ──────────────────────────────────────
function ensure_personeros_table(PDO $pdo): void {
    $pdo->exec("CREATE TABLE IF NOT EXISTS personeros (
        id                  INT AUTO_INCREMENT PRIMARY KEY,
        nombres             VARCHAR(120) NOT NULL,
        apellidos           VARCHAR(120) NOT NULL,
        dni                 CHAR(8)      NULL,
        carnet_extranjeria  VARCHAR(9)   NULL,
        edad                TINYINT UNSIGNED NULL,
        correo              VARCHAR(150) NULL,
        nacionalidad        VARCHAR(80)  NOT NULL DEFAULT 'Peruana',
        cargo               ENUM('titular','alterno') NOT NULL DEFAULT 'titular',
        celular             VARCHAR(20)  NULL,
        whatsapp            VARCHAR(20)  NULL,
        direccion           TEXT         NULL,
        local_votacion      VARCHAR(200) NULL,
        numero_mesa         VARCHAR(30)  NULL,
        foto                VARCHAR(300) NULL,
        origen              ENUM('manual','militante','simpatizante') NOT NULL DEFAULT 'manual',
        origen_id           INT          NULL,
        estado              ENUM('activo','inactivo') NOT NULL DEFAULT 'activo',
        creado_en           DATETIME     DEFAULT CURRENT_TIMESTAMP,
        actualizado_en      DATETIME     DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
}

ensure_personeros_table($pdo);

// ── Endpoint JSON para refresh tras guardar ───────────────────
if (isset($_GET['json'])) {
    while (ob_get_level() > 0) ob_end_clean();
    $list = $pdo->query("SELECT * FROM personeros ORDER BY cargo ASC, apellidos ASC, nombres ASC")->fetchAll();
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['personeros' => array_values($list)], JSON_UNESCAPED_UNICODE);
    exit;
}

// ── CSRF ──────────────────────────────────────────────────────
$allowed_actions = ['save_personero','delete_personero','toggle_estado'];
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';
    if (in_array($action, $allowed_actions, true)) csrf_verify();
}

// ── Upload foto ───────────────────────────────────────────────
function upload_foto_personero(array $file, ?string $old_foto): ?string {
    if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) return null;
    if ($file['error'] !== UPLOAD_ERR_OK || empty($file['tmp_name'])) return null;

    $max = 4 * 1024 * 1024;
    if ($file['size'] > $max) throw new RuntimeException('La foto debe pesar máximo 4 MB.');

    $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
    if (!in_array($ext, ['jpg','jpeg','png','webp'], true)) throw new RuntimeException('Solo JPG, PNG o WEBP.');

    $dir = dirname(__DIR__) . '/uploads/personeros/';
    if (!is_dir($dir)) mkdir($dir, 0755, true);

    $prefix = 'per_' . date('Ymd_His') . '_' . bin2hex(random_bytes(4));

    $webp_bytes = img_to_webp($file['tmp_name'], $ext);
    if ($webp_bytes !== false) {
        $nombre = $prefix . '.webp';
        file_put_contents($dir . $nombre, $webp_bytes);
    } else {
        $nombre = $prefix . '.' . $ext;
        move_uploaded_file($file['tmp_name'], $dir . $nombre);
    }

    if ($old_foto) {
        $old_path = dirname(__DIR__) . '/' . $old_foto;
        if (is_file($old_path)) @unlink($old_path);
    }

    return 'uploads/personeros/' . $nombre;
}

// ── POST handlers ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    // ── Guardar (crear / editar) ──────────────────────────────
    if ($action === 'save_personero') {
        $id        = (int)($_POST['id'] ?? 0);
        $nombres   = trim($_POST['nombres']   ?? '');
        $apellidos = trim($_POST['apellidos'] ?? '');
        $dni       = trim($_POST['dni']       ?? '');
        $carnet    = trim($_POST['carnet_extranjeria'] ?? '');
        $edad      = (($_POST['edad'] ?? '') !== '') ? (int)$_POST['edad'] : null;
        $correo    = trim($_POST['correo']       ?? '') ?: null;
        $nacion    = trim($_POST['nacionalidad'] ?? 'Peruana') ?: 'Peruana';
        $cargo     = in_array($_POST['cargo'] ?? '', ['titular','alterno']) ? $_POST['cargo'] : 'titular';
        $celular   = trim($_POST['celular']   ?? '') ?: null;
        $whatsapp  = trim($_POST['whatsapp']  ?? '') ?: null;
        $direccion = trim($_POST['direccion'] ?? '') ?: null;
        $local     = trim($_POST['local_votacion'] ?? '') ?: null;
        $mesa      = trim($_POST['numero_mesa']    ?? '') ?: null;
        $origen    = in_array($_POST['origen'] ?? '', ['manual','militante','simpatizante']) ? $_POST['origen'] : 'manual';
        $origen_id = (int)($_POST['origen_id'] ?? 0) ?: null;

        if ($nombres === '' || $apellidos === '') json_resp(['ok'=>false,'msg'=>'Nombres y apellidos son obligatorios.']);
        if ($dni !== '' && !preg_match('/^\d{8}$/', $dni)) json_resp(['ok'=>false,'msg'=>'El DNI debe tener exactamente 8 dígitos.']);
        if ($carnet !== '' && !preg_match('/^\d{9}$/', $carnet)) json_resp(['ok'=>false,'msg'=>'El carnet debe tener exactamente 9 dígitos.']);

        $old_foto = null;
        if ($id > 0) {
            $r = $pdo->prepare("SELECT foto FROM personeros WHERE id=?");
            $r->execute([$id]);
            $old_foto = $r->fetchColumn() ?: null;
        }

        try {
            $foto_path = upload_foto_personero($_FILES['foto'] ?? [], $old_foto);
        } catch (RuntimeException $e) {
            json_resp(['ok'=>false,'msg'=>$e->getMessage()]);
        }

        $foto_final = $foto_path ?? ($id > 0 ? $old_foto : null);

        if ($id > 0) {
            $pdo->prepare("UPDATE personeros SET
                nombres=?,apellidos=?,dni=?,carnet_extranjeria=?,edad=?,correo=?,
                nacionalidad=?,cargo=?,celular=?,whatsapp=?,direccion=?,
                local_votacion=?,numero_mesa=?,foto=?,origen=?,origen_id=?,
                actualizado_en=NOW()
                WHERE id=?")->execute([
                $nombres,$apellidos,$dni?:null,$carnet?:null,$edad,$correo,
                $nacion,$cargo,$celular,$whatsapp,$direccion,
                $local,$mesa,$foto_final,$origen,$origen_id,$id
            ]);
            json_resp(['ok'=>true,'msg'=>'Personero actualizado.','id'=>$id]);
        } else {
            $pdo->prepare("INSERT INTO personeros
                (nombres,apellidos,dni,carnet_extranjeria,edad,correo,
                 nacionalidad,cargo,celular,whatsapp,direccion,
                 local_votacion,numero_mesa,foto,origen,origen_id)
                VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)")->execute([
                $nombres,$apellidos,$dni?:null,$carnet?:null,$edad,$correo,
                $nacion,$cargo,$celular,$whatsapp,$direccion,
                $local,$mesa,$foto_final,$origen,$origen_id
            ]);
            $nuevo_id = (int)$pdo->lastInsertId();
            log_activity($pdo, 'Registró personero: '.$nombres.' '.$apellidos, 'personeros');
            json_resp(['ok'=>true,'msg'=>'Personero registrado.','id'=>$nuevo_id]);
        }
    }

    // ── Eliminar ──────────────────────────────────────────────
    if ($action === 'delete_personero') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) json_resp(['ok'=>false,'msg'=>'ID inválido.']);
        $r = $pdo->prepare("SELECT foto,nombres,apellidos FROM personeros WHERE id=?");
        $r->execute([$id]);
        $row = $r->fetch();
        if (!$row) json_resp(['ok'=>false,'msg'=>'No encontrado.']);
        if ($row['foto']) {
            $p = dirname(__DIR__) . '/' . $row['foto'];
            if (is_file($p)) @unlink($p);
        }
        $pdo->prepare("DELETE FROM personeros WHERE id=?")->execute([$id]);
        log_activity($pdo, 'Eliminó personero: '.$row['nombres'].' '.$row['apellidos'], 'personeros');
        json_resp(['ok'=>true,'msg'=>'Personero eliminado.']);
    }

    // ── Toggle estado ─────────────────────────────────────────
    if ($action === 'toggle_estado') {
        $id = (int)($_POST['id'] ?? 0);
        if ($id <= 0) json_resp(['ok'=>false,'msg'=>'ID inválido.']);
        $pdo->prepare("UPDATE personeros SET estado = IF(estado='activo','inactivo','activo') WHERE id=?")->execute([$id]);
        $nuevo = $pdo->prepare("SELECT estado FROM personeros WHERE id=?");
        $nuevo->execute([$id]);
        json_resp(['ok'=>true,'estado'=>$nuevo->fetchColumn()]);
    }
}

// ── Cargar datos ──────────────────────────────────────────────
$personeros = $pdo->query(
    "SELECT * FROM personeros ORDER BY cargo ASC, apellidos ASC, nombres ASC"
)->fetchAll();

$total     = count($personeros);
$titulares = count(array_filter($personeros, fn($p) => $p['cargo'] === 'titular'));
$alternos  = $total - $titulares;
$activos   = count(array_filter($personeros, fn($p) => $p['estado'] === 'activo'));

// ── Pre-llenado desde militante o simpatizante ────────────────
$prefill = [];
if (isset($_GET['from_militante'])) {
    $mid = (int)$_GET['from_militante'];
    $m = $pdo->prepare("SELECT * FROM militantes WHERE id=?");
    $m->execute([$mid]);
    $row = $m->fetch();
    if ($row) $prefill = [
        'nombres'   => $row['nombre'] ?? '',
        'apellidos' => '',
        'dni'       => $row['dni'] ?? '',
        'celular'   => $row['celular'] ?? '',
        'whatsapp'  => $row['whatsapp'] ?? '',
        'correo'    => $row['correo'] ?? '',
        'origen'    => 'militante',
        'origen_id' => $mid,
    ];
}
if (isset($_GET['from_simpatizante'])) {
    $sid = (int)$_GET['from_simpatizante'];
    $s = $pdo->prepare("SELECT * FROM simpatizantes WHERE id=?");
    $s->execute([$sid]);
    $row = $s->fetch();
    if ($row) $prefill = [
        'nombres'   => $row['nombre'] ?? '',
        'apellidos' => '',
        'dni'       => $row['dni'] ?? '',
        'celular'   => $row['celular'] ?? $row['telefono'] ?? '',
        'whatsapp'  => $row['whatsapp'] ?? '',
        'correo'    => $row['correo'] ?? '',
        'origen'    => 'simpatizante',
        'origen_id' => $sid,
    ];
}

// ── URLs para PDF ─────────────────────────────────────────────
$pdf_embed_url    = 'exportar-personeros-pdf.php?embed=1';
$pdf_download_url = 'exportar-personeros-pdf.php?download=1';
$pdf_open_url     = 'exportar-personeros-pdf.php';

include __DIR__ . '/layout.php';
?>

<style>
.cargo-titular { background:#1E3A8A; color:#fff; }
.cargo-alterno { background:#059669; color:#fff; }
.origen-badge-manual      { background:#F3F4F6; color:#374151; }
.origen-badge-militante   { background:#EDE9FE; color:#6D28D9; }
.origen-badge-simpatizante{ background:#FEF3C7; color:#92400E; }
</style>

<div class="space-y-5"
     x-data="personeroApp()"
     x-init="init()"
     @keydown.escape.window="closeModal()">

  <!-- ── Header ────────────────────────────────────────────── -->
  <div class="flex flex-wrap items-center justify-between gap-3">
    <div>
      <h2 class="text-xl font-black text-gray-800">Personeros</h2>
      <p class="text-xs text-gray-400 mt-0.5">Gestión de personeros electorales del partido</p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
      <button @click="openPdf('')"
              class="inline-flex items-center gap-2 bg-emerald-600 hover:bg-emerald-700 text-white
                     text-sm font-bold px-4 py-2.5 rounded-xl shadow transition-all">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
        </svg>
        Vista PDF
      </button>
      <button @click="openModal()"
              class="inline-flex items-center gap-2 bg-[#1E3A8A] hover:bg-blue-900 text-white
                     text-sm font-bold px-4 py-2.5 rounded-xl shadow transition-all">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
        </svg>
        Nuevo Personero
      </button>
    </div>
  </div>

  <!-- ── Stats ─────────────────────────────────────────────── -->
  <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
    <?php foreach([
      ['Total',     $total,     '#1E3A8A','M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
      ['Titulares', $titulares, '#1E3A8A','M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'],
      ['Alternos',  $alternos,  '#059669','M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
      ['Activos',   $activos,   '#0369A1','M5 13l4 4L19 7'],
    ] as [$label,$val,$color,$path]): ?>
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 flex items-center gap-3">
      <div class="w-10 h-10 rounded-xl flex items-center justify-center flex-shrink-0"
           style="background:<?= $color ?>1a">
        <svg class="w-5 h-5" style="color:<?= $color ?>" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $path ?>"/>
        </svg>
      </div>
      <div>
        <div class="text-xl font-black text-gray-800"><?= $val ?></div>
        <div class="text-xs text-gray-400"><?= $label ?></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>

  <!-- ── App Alpine ─────────────────────────────────────────── -->
  <div>

    <!-- Filtros -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 mb-4">
      <div class="flex flex-wrap gap-3 items-center">
        <div class="relative flex-1 min-w-[200px]">
          <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-4.35-4.35M17 11A6 6 0 115 11a6 6 0 0112 0z"/>
          </svg>
          <input x-model="filtro.busqueda" type="text" placeholder="Buscar por nombre, DNI..."
                 class="w-full pl-9 pr-3 py-2 text-sm border border-gray-200 rounded-xl
                        focus:ring-2 focus:ring-[#1E3A8A] focus:border-transparent outline-none">
        </div>
        <select x-model="filtro.cargo"
                class="text-sm border border-gray-200 rounded-xl px-3 py-2 outline-none focus:ring-2 focus:ring-[#1E3A8A]">
          <option value="">Todos los cargos</option>
          <option value="titular">Titular</option>
          <option value="alterno">Alterno</option>
        </select>
        <select x-model="filtro.origen"
                class="text-sm border border-gray-200 rounded-xl px-3 py-2 outline-none focus:ring-2 focus:ring-[#1E3A8A]">
          <option value="">Todos los orígenes</option>
          <option value="manual">Manual</option>
          <option value="militante">Militante</option>
          <option value="simpatizante">Simpatizante</option>
        </select>
        <select x-model="filtro.estado"
                class="text-sm border border-gray-200 rounded-xl px-3 py-2 outline-none focus:ring-2 focus:ring-[#1E3A8A]">
          <option value="">Todos</option>
          <option value="activo">Activos</option>
          <option value="inactivo">Inactivos</option>
        </select>
        <button @click="filtro={busqueda:'',cargo:'',origen:'',estado:''}"
                class="text-xs text-gray-400 hover:text-gray-600 px-2">Limpiar</button>
      </div>
    </div>

    <!-- ── Tabla PRO ─────────────────────────────────────────── -->
    <div class="bg-white rounded-2xl border border-gray-100 shadow-sm overflow-hidden">

      <!-- Counter row -->
      <div class="px-5 py-3 border-b border-gray-50 flex items-center justify-between">
        <span class="text-xs text-gray-400">
          Mostrando <span class="font-bold text-gray-600" x-text="filtrados.length"></span>
          de <span class="font-bold text-gray-600" x-text="personeros.length"></span> personeros
        </span>
      </div>

      <div class="overflow-x-auto">
        <table class="w-full text-sm">
          <thead class="bg-gray-50 border-b border-gray-100">
            <tr>
              <th class="px-4 py-3 text-left text-xs font-bold text-gray-400 uppercase tracking-wide w-10">#</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-gray-400 uppercase tracking-wide">Personero</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-gray-400 uppercase tracking-wide">Documento</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-gray-400 uppercase tracking-wide">Cargo</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-gray-400 uppercase tracking-wide">Contacto</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-gray-400 uppercase tracking-wide">Local / Mesa</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-gray-400 uppercase tracking-wide">Origen</th>
              <th class="px-4 py-3 text-left text-xs font-bold text-gray-400 uppercase tracking-wide">Estado</th>
              <th class="px-4 py-3 text-right text-xs font-bold text-gray-400 uppercase tracking-wide">Acciones</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">

            <!-- Empty state -->
            <template x-if="filtrados.length === 0">
              <tr>
                <td colspan="9" class="px-4 py-14 text-center">
                  <div class="flex flex-col items-center gap-2">
                    <svg class="w-10 h-10 text-gray-200" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                            d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                    </svg>
                    <p class="text-gray-400 font-semibold text-sm">No hay personeros que coincidan</p>
                    <p class="text-gray-300 text-xs">Ajusta los filtros o agrega un nuevo personero</p>
                  </div>
                </td>
              </tr>
            </template>

            <!-- Filas -->
            <template x-for="(p, idx) in filtrados" :key="p.id">
              <tr class="hover:bg-blue-50/30 transition-colors"
                  :class="p.estado === 'inactivo' ? 'opacity-60' : ''">

                <!-- # -->
                <td class="px-4 py-3 text-gray-400 text-xs font-mono" x-text="idx + 1"></td>

                <!-- Personero (foto + nombre) -->
                <td class="px-4 py-3">
                  <div class="flex items-center gap-3">
                    <!-- Avatar -->
                    <div class="w-9 h-9 rounded-xl overflow-hidden flex-shrink-0 bg-[#1E3A8A] flex items-center justify-center">
                      <template x-if="p.foto">
                        <img :src="'<?= BASE_URL ?>/' + p.foto" :alt="p.nombres"
                             class="w-9 h-9 object-cover">
                      </template>
                      <template x-if="!p.foto">
                        <span class="text-white font-black text-sm leading-none"
                              x-text="(p.apellidos[0]||'').toUpperCase()+(p.nombres[0]||'').toUpperCase()"></span>
                      </template>
                    </div>
                    <!-- Nombre -->
                    <div>
                      <div class="font-bold text-gray-800 text-sm leading-tight"
                           x-text="p.apellidos + ', ' + p.nombres"></div>
                      <div x-show="p.correo" class="text-xs text-gray-400 truncate max-w-[160px]"
                           x-text="p.correo"></div>
                    </div>
                  </div>
                </td>

                <!-- Documento -->
                <td class="px-4 py-3">
                  <span x-show="p.dni" class="font-mono text-gray-700 text-xs" x-text="p.dni"></span>
                  <span x-show="!p.dni && p.carnet_extranjeria" class="text-xs text-gray-500">
                    <span class="text-gray-400 text-[10px]">CE</span>
                    <span class="font-mono text-gray-700" x-text="p.carnet_extranjeria"></span>
                  </span>
                  <span x-show="!p.dni && !p.carnet_extranjeria" class="text-gray-300 text-xs">—</span>
                </td>

                <!-- Cargo -->
                <td class="px-4 py-3">
                  <span class="inline-flex items-center text-[11px] font-black px-2.5 py-1 rounded-full uppercase tracking-wide"
                        :class="p.cargo === 'titular'
                          ? 'bg-[#1E3A8A] text-white'
                          : 'bg-emerald-100 text-emerald-800'"
                        x-text="p.cargo"></span>
                </td>

                <!-- Contacto -->
                <td class="px-4 py-3 text-xs text-gray-600 whitespace-nowrap">
                  <div x-show="p.celular" class="flex items-center gap-1">
                    <svg class="w-3 h-3 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                            d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                    </svg>
                    <span x-text="p.celular"></span>
                  </div>
                  <span x-show="!p.celular && !p.whatsapp" class="text-gray-300">—</span>
                </td>

                <!-- Local / Mesa -->
                <td class="px-4 py-3 text-xs text-gray-600 max-w-[160px]">
                  <div x-show="p.local_votacion" class="truncate font-medium" x-text="p.local_votacion"></div>
                  <div x-show="p.numero_mesa" class="text-gray-400 text-[11px]"
                       x-text="'Mesa ' + p.numero_mesa"></div>
                  <span x-show="!p.local_votacion && !p.numero_mesa" class="text-gray-300">—</span>
                </td>

                <!-- Origen -->
                <td class="px-4 py-3">
                  <span class="inline-block text-[11px] font-bold px-2.5 py-1 rounded-full"
                        :class="{
                          'bg-gray-100 text-gray-600':         p.origen === 'manual',
                          'bg-violet-100 text-violet-700':     p.origen === 'militante',
                          'bg-amber-100 text-amber-700':       p.origen === 'simpatizante'
                        }"
                        x-text="p.origen.charAt(0).toUpperCase() + p.origen.slice(1)"></span>
                </td>

                <!-- Estado -->
                <td class="px-4 py-3">
                  <span class="inline-flex items-center gap-1 text-[11px] font-bold px-2.5 py-1 rounded-full"
                        :class="p.estado === 'activo'
                          ? 'bg-green-100 text-green-700'
                          : 'bg-red-100 text-red-600'">
                    <span class="w-1.5 h-1.5 rounded-full"
                          :class="p.estado === 'activo' ? 'bg-green-500' : 'bg-red-400'"></span>
                    <span x-text="p.estado === 'activo' ? 'Activo' : 'Inactivo'"></span>
                  </span>
                </td>

                <!-- Acciones -->
                <td class="px-4 py-3">
                  <div class="flex items-center justify-end gap-1.5">
                    <!-- Editar -->
                    <button @click="openModal(p)" title="Editar"
                            class="w-8 h-8 flex items-center justify-center rounded-xl
                                   bg-blue-50 text-blue-600 hover:bg-blue-100 transition-colors">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                      </svg>
                    </button>
                    <!-- WhatsApp -->
                    <button @click="abrirWa(p)" x-show="p.whatsapp || p.celular" title="WhatsApp"
                            class="w-8 h-8 flex items-center justify-center rounded-xl
                                   bg-green-50 text-green-600 hover:bg-green-100 transition-colors">
                      <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24">
                        <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347m-5.421 7.403h-.004a9.87 9.87 0 01-5.031-1.378l-.361-.214-3.741.982.998-3.648-.235-.374a9.86 9.86 0 01-1.51-5.26c.001-5.45 4.436-9.884 9.888-9.884 2.64 0 5.122 1.03 6.988 2.898a9.825 9.825 0 012.893 6.994c-.003 5.45-4.437 9.884-9.885 9.884m8.413-18.297A11.815 11.815 0 0012.05 0C5.495 0 .16 5.335.157 11.892c0 2.096.547 4.142 1.588 5.945L.057 24l6.305-1.654a11.882 11.882 0 005.683 1.448h.005c6.554 0 11.89-5.335 11.893-11.893a11.821 11.821 0 00-3.48-8.413z"/>
                      </svg>
                    </button>
                    <!-- Toggle estado -->
                    <button @click="toggleEstado(p)"
                            :title="p.estado === 'activo' ? 'Inactivar' : 'Activar'"
                            class="w-8 h-8 flex items-center justify-center rounded-xl transition-colors"
                            :class="p.estado === 'activo'
                              ? 'bg-amber-50 text-amber-600 hover:bg-amber-100'
                              : 'bg-green-50 text-green-600 hover:bg-green-100'">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              :d="p.estado === 'activo'
                                ? 'M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636'
                                : 'M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z'"/>
                      </svg>
                    </button>
                    <!-- Eliminar -->
                    <button @click="confirmarEliminar(p)" title="Eliminar"
                            class="w-8 h-8 flex items-center justify-center rounded-xl
                                   bg-red-50 text-red-500 hover:bg-red-100 transition-colors">
                      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                      </svg>
                    </button>
                  </div>
                </td>

              </tr>
            </template>

          </tbody>
        </table>
      </div>
    </div>

    <!-- ══ MODAL ADD / EDIT ══════════════════════════════════ -->
    <div x-show="modal.open" x-cloak
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50 backdrop-blur-sm">
      <div @click.outside="closeModal()"
           x-transition:enter="transition ease-out duration-200"
           x-transition:enter-start="opacity-0 scale-95"
           x-transition:enter-end="opacity-100 scale-100"
           class="bg-white rounded-2xl shadow-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto">

        <!-- Header modal -->
        <div class="bg-[#1E3A8A] px-6 py-4 flex items-center justify-between sticky top-0 z-10 rounded-t-2xl">
          <div class="flex items-center gap-3">
            <div class="w-8 h-8 bg-white/20 rounded-lg flex items-center justify-center">
              <svg class="w-4 h-4 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
              </svg>
            </div>
            <h3 class="text-white font-black text-sm"
                x-text="modal.id ? 'Editar Personero' : 'Nuevo Personero'"></h3>
          </div>
          <button @click="closeModal()" class="text-white/70 hover:text-white transition-colors">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
            </svg>
          </button>
        </div>

        <!-- Form -->
        <form @submit.prevent="guardar()" class="p-6 space-y-5" enctype="multipart/form-data" id="form-personero">
          <?= csrf_field() ?>
          <input type="hidden" name="action" value="save_personero">
          <input type="hidden" name="id" x-model="modal.id">
          <input type="hidden" name="origen" x-model="modal.origen">
          <input type="hidden" name="origen_id" x-model="modal.origen_id">

          <!-- Cargo -->
          <div>
            <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-2">
              Cargo <span class="text-red-500">*</span>
            </label>
            <div class="grid grid-cols-2 gap-3">
              <label class="relative cursor-pointer">
                <input type="radio" name="cargo" value="titular" x-model="modal.cargo" class="sr-only peer">
                <div class="flex items-center gap-2 px-4 py-3 rounded-xl border-2 transition-all
                            peer-checked:border-[#1E3A8A] peer-checked:bg-blue-50 border-gray-200">
                  <div class="w-3 h-3 rounded-full border-2 border-current peer-checked:bg-[#1E3A8A]
                              flex items-center justify-center" :class="modal.cargo==='titular'?'border-[#1E3A8A]':'border-gray-300'">
                    <div class="w-1.5 h-1.5 rounded-full bg-[#1E3A8A]" x-show="modal.cargo==='titular'"></div>
                  </div>
                  <span class="text-sm font-bold" :class="modal.cargo==='titular'?'text-[#1E3A8A]':'text-gray-600'">
                    🏅 Titular
                  </span>
                </div>
              </label>
              <label class="relative cursor-pointer">
                <input type="radio" name="cargo" value="alterno" x-model="modal.cargo" class="sr-only peer">
                <div class="flex items-center gap-2 px-4 py-3 rounded-xl border-2 transition-all
                            peer-checked:border-emerald-600 peer-checked:bg-emerald-50 border-gray-200">
                  <div class="w-3 h-3 rounded-full border-2 flex items-center justify-center"
                       :class="modal.cargo==='alterno'?'border-emerald-600':'border-gray-300'">
                    <div class="w-1.5 h-1.5 rounded-full bg-emerald-600" x-show="modal.cargo==='alterno'"></div>
                  </div>
                  <span class="text-sm font-bold" :class="modal.cargo==='alterno'?'text-emerald-700':'text-gray-600'">
                    🔄 Alterno
                  </span>
                </div>
              </label>
            </div>
          </div>

          <!-- Nombres y Apellidos -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-1.5">
                Nombres <span class="text-red-500">*</span>
              </label>
              <input type="text" name="nombres" x-model="modal.nombres" required
                     placeholder="Ej: Juan Carlos"
                     class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm
                            focus:ring-2 focus:ring-[#1E3A8A] focus:border-transparent outline-none">
            </div>
            <div>
              <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-1.5">
                Apellidos <span class="text-red-500">*</span>
              </label>
              <input type="text" name="apellidos" x-model="modal.apellidos" required
                     placeholder="Ej: Pérez López"
                     class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm
                            focus:ring-2 focus:ring-[#1E3A8A] focus:border-transparent outline-none">
            </div>
          </div>

          <!-- DNI / Carnet / Edad / Nacionalidad -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-1.5">DNI (8 dígitos)</label>
              <input type="text" name="dni" x-model="modal.dni" maxlength="8" pattern="\d{8}"
                     placeholder="12345678"
                     class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm
                            focus:ring-2 focus:ring-[#1E3A8A] focus:border-transparent outline-none">
            </div>
            <div>
              <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-1.5">Carnet Extranjería (9 dig.)</label>
              <input type="text" name="carnet_extranjeria" x-model="modal.carnet_extranjeria" maxlength="9" pattern="\d{9}"
                     placeholder="123456789"
                     class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm
                            focus:ring-2 focus:ring-[#1E3A8A] focus:border-transparent outline-none">
            </div>
            <div>
              <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-1.5">Edad</label>
              <input type="number" name="edad" x-model="modal.edad" min="18" max="99"
                     placeholder="Ej: 35"
                     class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm
                            focus:ring-2 focus:ring-[#1E3A8A] focus:border-transparent outline-none">
            </div>
            <div>
              <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-1.5">Nacionalidad</label>
              <input type="text" name="nacionalidad" x-model="modal.nacionalidad"
                     placeholder="Peruana"
                     class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm
                            focus:ring-2 focus:ring-[#1E3A8A] focus:border-transparent outline-none">
            </div>
          </div>

          <!-- Contacto -->
          <div class="grid grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-1.5">Celular</label>
              <input type="text" name="celular" x-model="modal.celular" maxlength="20"
                     placeholder="987654321"
                     class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm
                            focus:ring-2 focus:ring-[#1E3A8A] focus:border-transparent outline-none">
            </div>
            <div>
              <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-1.5">WhatsApp</label>
              <input type="text" name="whatsapp" x-model="modal.whatsapp" maxlength="20"
                     placeholder="987654321"
                     class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm
                            focus:ring-2 focus:ring-[#1E3A8A] focus:border-transparent outline-none">
            </div>
          </div>

          <div>
            <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-1.5">Correo electrónico</label>
            <input type="email" name="correo" x-model="modal.correo"
                   placeholder="correo@ejemplo.com"
                   class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm
                          focus:ring-2 focus:ring-[#1E3A8A] focus:border-transparent outline-none">
          </div>

          <div>
            <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-1.5">Dirección de domicilio</label>
            <input type="text" name="direccion" x-model="modal.direccion"
                   placeholder="Av. Principal 123, Satipo"
                   class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm
                          focus:ring-2 focus:ring-[#1E3A8A] focus:border-transparent outline-none">
          </div>

          <!-- Sección electoral (opcional) -->
          <div class="bg-blue-50 rounded-xl p-4 space-y-4 border border-blue-100">
            <p class="text-xs font-black text-blue-700 uppercase tracking-widest">
              📍 Asignación Electoral <span class="font-normal text-blue-400">(se completa después)</span>
            </p>
            <div class="grid grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-1.5">Local de Votación</label>
                <input type="text" name="local_votacion" x-model="modal.local_votacion"
                       placeholder="IE N° 30005 - Satipo"
                       class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm bg-white
                              focus:ring-2 focus:ring-[#1E3A8A] focus:border-transparent outline-none">
              </div>
              <div>
                <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-1.5">N° de Mesa Asignada</label>
                <input type="text" name="numero_mesa" x-model="modal.numero_mesa"
                       placeholder="Ej: 024"
                       class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm bg-white
                              focus:ring-2 focus:ring-[#1E3A8A] focus:border-transparent outline-none">
              </div>
            </div>
          </div>

          <!-- Foto -->
          <div>
            <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-2">Fotografía</label>
            <div class="flex items-center gap-4">
              <div class="w-16 h-16 rounded-full bg-gray-100 border-2 border-dashed border-gray-300
                          flex items-center justify-center overflow-hidden flex-shrink-0" id="foto-preview-wrap">
                <template x-if="modal.foto_preview">
                  <img :src="modal.foto_preview" class="w-full h-full object-cover rounded-full" id="foto-preview-img">
                </template>
                <template x-if="!modal.foto_preview">
                  <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
                  </svg>
                </template>
              </div>
              <div class="flex-1">
                <input type="file" name="foto" accept="image/*" id="foto-input"
                       @change="previewFoto($event)"
                       class="text-sm text-gray-500 file:mr-3 file:py-2 file:px-4
                              file:rounded-xl file:border-0 file:text-xs file:font-bold
                              file:bg-[#1E3A8A] file:text-white hover:file:bg-blue-900
                              cursor-pointer w-full">
                <p class="text-xs text-gray-400 mt-1">JPG, PNG o WEBP · Máx 4MB</p>
              </div>
            </div>
          </div>

          <!-- Error -->
          <div x-show="modal.error" x-cloak
               class="bg-red-50 border border-red-200 rounded-xl px-4 py-3 text-sm text-red-700"
               x-text="modal.error"></div>

          <!-- Botones -->
          <div class="flex gap-3 pt-2">
            <button type="button" @click="closeModal()"
                    class="flex-1 border border-gray-200 text-gray-600 font-semibold py-3
                           rounded-xl text-sm hover:bg-gray-50 transition-colors">
              Cancelar
            </button>
            <button type="submit" :disabled="modal.saving"
                    class="flex-1 bg-[#1E3A8A] hover:bg-blue-900 disabled:opacity-60
                           text-white font-black py-3 rounded-xl text-sm transition-all
                           flex items-center justify-center gap-2">
              <svg x-show="modal.saving" class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
              </svg>
              <span x-text="modal.saving ? 'Guardando...' : (modal.id ? 'Actualizar' : 'Registrar')"></span>
            </button>
          </div>
        </form>
      </div>
    </div>

    <!-- ══ MODAL VISTA PDF ══════════════════════════════════════ -->
    <div x-show="pdfModal" x-cloak
         class="fixed inset-0 z-[95] flex items-center justify-center p-4">
      <div class="absolute inset-0 bg-gray-950/60 backdrop-blur-sm" @click="closePdf()"></div>
      <div class="relative bg-white rounded-2xl shadow-2xl border border-gray-100
                  w-full max-w-6xl h-[90vh] overflow-hidden flex flex-col">

        <!-- Cabecera del modal -->
        <div class="bg-[#1E3A8A] px-5 py-4 flex flex-col sm:flex-row sm:items-center justify-between gap-3 flex-shrink-0">
          <div>
            <h2 class="text-white font-black text-lg leading-tight">Vista previa — Personeros</h2>
            <p class="text-blue-200 text-xs mt-0.5">Previsualización del listado para imprimir o descargar como PDF</p>
          </div>
          <div class="flex items-center gap-2 flex-wrap">
            <!-- Filtro rápido cargo -->
            <select id="pdf-cargo-filter"
                    class="text-xs border border-white/30 bg-white/10 text-white rounded-lg px-2.5 py-1.5
                           focus:outline-none focus:ring-2 focus:ring-white/30"
                    @change="openPdf($event.target.value)"
              <option value="">Todos los cargos</option>
              <option value="titular">Solo Titulares</option>
              <option value="alterno">Solo Alternos</option>
            </select>
            <!-- Botón Imprimir -->
            <button type="button" @click="printPdf()"
                    class="inline-flex items-center gap-1.5 bg-yellow-400 hover:bg-yellow-300
                           text-[#1E3A8A] text-xs font-black px-3.5 py-2 rounded-xl transition-colors">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z"/>
              </svg>
              Imprimir
            </button>
            <!-- Botón Descargar -->
            <a :href="pdfDownloadUrl"
               class="inline-flex items-center gap-1.5 bg-emerald-500 hover:bg-emerald-400
                      text-white text-xs font-black px-3.5 py-2 rounded-xl transition-colors no-underline">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
              </svg>
              Descargar PDF
            </a>
            <!-- Botón Abrir en nueva pestaña -->
            <a :href="pdfOpenUrl" target="_blank"
               class="inline-flex items-center gap-1.5 bg-white/10 hover:bg-white/20
                      text-white text-xs font-bold px-3.5 py-2 rounded-xl transition-colors no-underline">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
              </svg>
              Abrir
            </a>
            <!-- Cerrar -->
            <button type="button" @click="closePdf()"
                    class="inline-flex items-center gap-1.5 bg-white/10 hover:bg-white/20
                           text-white text-xs font-bold px-3.5 py-2 rounded-xl transition-colors">
              <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
              </svg>
              Cerrar
            </button>
          </div>
        </div>

        <!-- Área iframe -->
        <div class="bg-gray-100 p-3 flex-1 min-h-0">
          <iframe x-ref="pdfFrame"
                  :src="pdfEmbedUrl"
                  class="w-full h-full bg-white rounded-xl border border-gray-200"
                  title="Vista previa personeros"></iframe>
        </div>
      </div>
    </div>

    <!-- ══ MODAL CONFIRMAR ELIMINAR ════════════════════════════ -->
    <div x-show="confirmDel.open" x-cloak
         class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/50">
      <div class="bg-white rounded-2xl shadow-2xl w-full max-w-sm p-6 text-center">
        <div class="w-14 h-14 bg-red-100 rounded-full flex items-center justify-center mx-auto mb-4">
          <svg class="w-7 h-7 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
          </svg>
        </div>
        <h3 class="text-lg font-black text-gray-800 mb-1">¿Eliminar personero?</h3>
        <p class="text-sm text-gray-500 mb-5" x-text="'Se eliminará a ' + confirmDel.nombre + ' de forma permanente.'"></p>
        <div class="flex gap-3">
          <button @click="confirmDel.open=false"
                  class="flex-1 border border-gray-200 text-gray-600 font-semibold py-2.5 rounded-xl text-sm hover:bg-gray-50">
            Cancelar
          </button>
          <button @click="eliminar()"
                  class="flex-1 bg-red-500 hover:bg-red-600 text-white font-black py-2.5 rounded-xl text-sm transition-colors">
            Eliminar
          </button>
        </div>
      </div>
    </div>

  </div><!-- /x-data -->
</div>

<script>
const PERSONEROS_DATA = <?= json_encode(array_values($personeros), JSON_UNESCAPED_UNICODE | JSON_HEX_TAG) ?>;
const PREFILL_DATA    = <?= json_encode($prefill, JSON_UNESCAPED_UNICODE) ?>;
const BASE_URL_JS     = '<?= BASE_URL ?>';
const CSRF_TOKEN      = '<?= $_SESSION['csrf_token'] ?? '' ?>';

function personeroApp() {
  return {
    personeros: [],
    filtro: { busqueda: '', cargo: '', origen: '', estado: '' },
    modal: {
      open: false, saving: false, error: '',
      id: 0, nombres: '', apellidos: '', dni: '', carnet_extranjeria: '',
      edad: '', correo: '', nacionalidad: 'Peruana', cargo: 'titular',
      celular: '', whatsapp: '', direccion: '', local_votacion: '',
      numero_mesa: '', foto_preview: '', foto_actual: '',
      origen: 'manual', origen_id: 0,
    },
    confirmDel: { open: false, id: 0, nombre: '' },
    pdfModal:       false,
    pdfEmbedUrl:    '',
    pdfDownloadUrl: '<?= htmlspecialchars($pdf_download_url, ENT_QUOTES) ?>',
    pdfOpenUrl:     '<?= htmlspecialchars($pdf_open_url,     ENT_QUOTES) ?>',
    _pdfBase:       '<?= htmlspecialchars($pdf_embed_url,    ENT_QUOTES) ?>',

    init() {
      this.personeros = PERSONEROS_DATA;
      if (Object.keys(PREFILL_DATA).length > 0) {
        this.$nextTick(() => this.openModal(null, PREFILL_DATA));
      }
    },

    get filtrados() {
      const b = this.filtro.busqueda.toLowerCase();
      return this.personeros.filter(p => {
        const nombre = (p.apellidos + ' ' + p.nombres).toLowerCase();
        const doc    = (p.dni || p.carnet_extranjeria || '').toLowerCase();
        if (b && !nombre.includes(b) && !doc.includes(b)) return false;
        if (this.filtro.cargo   && p.cargo   !== this.filtro.cargo)   return false;
        if (this.filtro.origen  && p.origen  !== this.filtro.origen)  return false;
        if (this.filtro.estado  && p.estado  !== this.filtro.estado)  return false;
        return true;
      });
    },

    openModal(p = null, pre = null) {
      const src = p || pre || {};
      this.modal = {
        open: true, saving: false, error: '',
        id:                p ? p.id : 0,
        nombres:           src.nombres           || '',
        apellidos:         src.apellidos         || '',
        dni:               src.dni               || '',
        carnet_extranjeria:src.carnet_extranjeria || '',
        edad:              src.edad              || '',
        correo:            src.correo            || '',
        nacionalidad:      src.nacionalidad      || 'Peruana',
        cargo:             src.cargo             || 'titular',
        celular:           src.celular           || '',
        whatsapp:          src.whatsapp          || '',
        direccion:         src.direccion         || '',
        local_votacion:    src.local_votacion    || '',
        numero_mesa:       src.numero_mesa       || '',
        foto_preview:      p && p.foto ? BASE_URL_JS + '/' + p.foto : '',
        foto_actual:       p ? (p.foto || '') : '',
        origen:            src.origen   || 'manual',
        origen_id:         src.origen_id || 0,
      };
    },

    closeModal() {
      this.modal.open = false;
    },

    previewFoto(e) {
      const file = e.target.files[0];
      if (!file) return;
      const reader = new FileReader();
      reader.onload = ev => { this.modal.foto_preview = ev.target.result; };
      reader.readAsDataURL(file);
    },

    async guardar() {
      this.modal.saving = true;
      this.modal.error  = '';
      const form = document.getElementById('form-personero');
      const fd   = new FormData(form);

      // ── 1. Guardar registro ───────────────────────────────────
      let data;
      try {
        const res = await fetch('personeros.php', { method: 'POST', body: fd });
        data = await res.json();
      } catch(e) {
        this.modal.error = 'Error de red al guardar. Verifica tu conexión.';
        this.modal.saving = false;
        return;
      }
      if (!data.ok) { this.modal.error = data.msg; this.modal.saving = false; return; }

      // ── 2. Refrescar lista (si falla, recarga la página) ──────
      try {
        const r2 = await fetch('personeros.php?json=1&_=' + Date.now());
        if (r2.ok) {
          const d2 = await r2.json();
          if (d2.personeros) this.personeros = d2.personeros;
        } else {
          location.reload(); return;
        }
      } catch(_) {
        location.reload(); return;
      }

      this.closeModal();
    },

    confirmarEliminar(p) {
      this.confirmDel = { open: true, id: p.id, nombre: p.apellidos + ', ' + p.nombres };
    },

    async eliminar() {
      const fd = new FormData();
      fd.append('action', 'delete_personero');
      fd.append('csrf_token', CSRF_TOKEN);
      fd.append('id', this.confirmDel.id);
      const res = await fetch('personeros.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (data.ok) {
        this.personeros = this.personeros.filter(p => p.id !== this.confirmDel.id);
        this.confirmDel.open = false;
      }
    },

    async toggleEstado(p) {
      const fd = new FormData();
      fd.append('action', 'toggle_estado');
      fd.append('csrf_token', CSRF_TOKEN);
      fd.append('id', p.id);
      const res  = await fetch('personeros.php', { method: 'POST', body: fd });
      const data = await res.json();
      if (data.ok) {
        const idx = this.personeros.findIndex(x => x.id === p.id);
        if (idx !== -1) this.personeros[idx].estado = data.estado;
      }
    },

    abrirWa(p) {
      const num = (p.whatsapp || p.celular || '').replace(/\D/g,'');
      if (!num) return;
      const intl = num.startsWith('51') ? num : '51' + num;
      window.open('https://wa.me/' + intl, '_blank');
    },

    openPdf(cargo = '') {
      // Construir URL fresca con timestamp para evitar caché
      const ts  = Date.now();
      const qs  = cargo ? `?cargo=${cargo}&embed=1&_=${ts}` : `?embed=1&_=${ts}`;
      const dqs = cargo ? `?cargo=${cargo}&download=1`      : `?download=1`;
      const oqs = cargo ? `?cargo=${cargo}`                 : '';
      this.pdfEmbedUrl    = 'exportar-personeros-pdf.php' + qs;
      this.pdfDownloadUrl = 'exportar-personeros-pdf.php' + dqs;
      this.pdfOpenUrl     = 'exportar-personeros-pdf.php' + oqs;
      // Resetear el select de filtro cargo
      this.$nextTick(() => {
        const sel = document.getElementById('pdf-cargo-filter');
        if (sel) sel.value = cargo;
        if (this.$refs.pdfFrame) this.$refs.pdfFrame.src = this.pdfEmbedUrl;
      });
      this.pdfModal = true;
    },
    closePdf() {
      this.pdfModal = false;
      // Detener carga del iframe al cerrar
      this.$nextTick(() => {
        if (this.$refs.pdfFrame) this.$refs.pdfFrame.src = '';
      });
    },
    printPdf() {
      const frame = this.$refs.pdfFrame;
      if (frame && frame.contentWindow) frame.contentWindow.print();
    },
  };
}
</script>

