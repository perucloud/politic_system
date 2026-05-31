<?php
if (ob_get_level() === 0) ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../includes/config/db.php';
require_once __DIR__ . '/config/auth.php';

require_login();
require_modulo('simpatizantes', $pdo);

// ── Exportar CSV ─────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'csv') {
    try {
        $all = $pdo->query("SELECT nombre,dni,COALESCE(NULLIF(celular,''), telefono) AS cel,whatsapp,correo,distrito,formas_apoyo,fecha_registro FROM simpatizantes ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="simpatizantes_' . date('Ymd') . '.csv"');
        $out = fopen('php://output', 'w');
        fputs($out, "\xEF\xBB\xBF");
        fputcsv($out, ['Apellidos y nombres','DNI','Cel','WhatsApp','Correo','Distrito','Cómo apoya','Fecha de registro']);
        foreach ($all as $r) fputcsv($out, array_values($r));
        fclose($out);
    } catch (Exception $e) {}
    exit;
}

// ── Exportar Excel ────────────────────────────────────────────
if (isset($_GET['export']) && $_GET['export'] === 'excel') {
    try {
        $all = $pdo->query("SELECT nombre,dni,COALESCE(NULLIF(celular,''), telefono) AS cel,whatsapp,correo,distrito,formas_apoyo,fecha_registro FROM simpatizantes ORDER BY id DESC")->fetchAll(PDO::FETCH_ASSOC);
        header('Content-Type: application/vnd.ms-excel; charset=utf-8');
        header('Content-Disposition: attachment; filename="simpatizantes_' . date('Ymd') . '.xls"');
        header('Cache-Control: max-age=0');
        echo "\xEF\xBB\xBF";
        echo '<table border="1">';
        echo '<tr style="background:#1E3A8A;color:white;font-weight:bold">
              <th>N°</th><th>Apellidos y nombres</th><th>DNI</th><th>Cel</th><th>WhatsApp</th><th>Correo</th>
              <th>Distrito</th><th>Cómo apoya</th><th>Fecha Registro</th></tr>';
        foreach ($all as $i => $r) {
            echo '<tr>';
            echo '<td>' . ($i+1) . '</td>';
            echo '<td>' . htmlspecialchars($r['nombre']) . '</td>';
            echo '<td style="mso-number-format:\'@\'">' . htmlspecialchars($r['dni']) . '</td>';
            echo '<td style="mso-number-format:\'@\'">' . htmlspecialchars($r['cel'] ?? '') . '</td>';
            echo '<td style="mso-number-format:\'@\'">' . htmlspecialchars($r['whatsapp'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($r['correo'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($r['distrito'] ?? '') . '</td>';
            echo '<td>' . htmlspecialchars($r['formas_apoyo'] ?? '') . '</td>';
            echo '<td>' . date('d/m/Y H:i', strtotime($r['fecha_registro'])) . '</td>';
            echo '</tr>';
        }
        echo '</table>';
    } catch (Exception $e) {}
    exit;
}

$flash = null;
$flash_type = 'success';
$can_manage_militantes = has_modulo('militantes', $pdo);

if (isset($_GET['msg'])) {
    $messages = [
        'militante_creado' => ['success', 'Simpatizante convertido en militante correctamente.'],
        'militante_duplicado' => ['error', 'Este DNI ya existe en la lista de militantes.'],
        'militante_error' => ['error', 'No se pudo convertir el simpatizante. Revisa la base de datos.'],
        'militante_permiso' => ['error', 'No tienes permisos para administrar militantes.'],
    ];
    if (isset($messages[$_GET['msg']])) {
        [$flash_type, $flash] = $messages[$_GET['msg']];
    }
}

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && ($_POST['action'] ?? '') === 'convertir_militante') {
    csrf_verify();

    if (!$can_manage_militantes) {
        header('Location: simpatizantes.php?msg=militante_permiso');
        exit;
    }

    $simpatizante_id = (int)($_POST['simpatizante_id'] ?? 0);
    $cargo_id = (int)($_POST['cargo_id'] ?? 0);
    $fecha_ingreso = trim($_POST['fecha_ingreso'] ?? date('Y-m-d'));
    if ($fecha_ingreso === '') $fecha_ingreso = date('Y-m-d');

    try {
        $stmt = $pdo->prepare("SELECT * FROM simpatizantes WHERE id = ? LIMIT 1");
        $stmt->execute([$simpatizante_id]);
        $simpatizante = $stmt->fetch(PDO::FETCH_ASSOC);

        if (!$simpatizante) {
            header('Location: simpatizantes.php?msg=militante_error');
            exit;
        }

        $dup = $pdo->prepare("SELECT id FROM militantes WHERE dni = ? LIMIT 1");
        $dup->execute([$simpatizante['dni']]);
        if ($dup->fetchColumn()) {
            header('Location: simpatizantes.php?msg=militante_duplicado');
            exit;
        }

        $celular = $simpatizante['celular'] ?: ($simpatizante['telefono'] ?? null);
        $whatsapp = $simpatizante['whatsapp'] ?: null;
        $correo = $simpatizante['correo'] ?: null;
        $cargo_value = $cargo_id > 0 ? $cargo_id : null;

        $insert = $pdo->prepare(
            "INSERT INTO militantes
             (simpatizante_id, nombre, dni, celular, whatsapp, correo, cargo_id, fecha_ingreso, estado)
             VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'activo')"
        );
        $insert->execute([
            $simpatizante_id,
            $simpatizante['nombre'],
            $simpatizante['dni'],
            $celular,
            $whatsapp,
            $correo,
            $cargo_value,
            $fecha_ingreso,
        ]);

        log_activity($pdo, 'Convirtio simpatizante en militante: ' . $simpatizante['nombre'], 'militantes');
        header('Location: simpatizantes.php?msg=militante_creado');
        exit;
    } catch (Exception $e) {
        header('Location: simpatizantes.php?msg=militante_error');
        exit;
    }
}

$buscar  = trim($_GET['q'] ?? '');
$por_pag = 15;
$pag     = max(1, (int)($_GET['pag'] ?? 1));
$offset  = ($pag - 1) * $por_pag;

$stats = [
    'total' => 0,
    'semana' => 0,
    'mes' => 0,
    'distrito_fuerte' => ['distrito' => 'Sin datos', 'total' => 0],
    'distrito_debil' => ['distrito' => 'Sin datos', 'total' => 0],
    'distritos' => [],
];
$cargos_militante = [];

try {
    try {
        $cargos_militante = $pdo->query(
            "SELECT id, nombre FROM militante_cargos WHERE activo=1 ORDER BY orden ASC, nombre ASC"
        )->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {
        $cargos_militante = [];
    }

    $stats['total'] = (int)$pdo->query("SELECT COUNT(*) FROM simpatizantes")->fetchColumn();
    $stats['semana'] = (int)$pdo->query(
        "SELECT COUNT(*) FROM simpatizantes
         WHERE YEARWEEK(fecha_registro, 1) = YEARWEEK(CURDATE(), 1)"
    )->fetchColumn();
    $stats['mes'] = (int)$pdo->query(
        "SELECT COUNT(*) FROM simpatizantes
         WHERE YEAR(fecha_registro) = YEAR(CURDATE())
           AND MONTH(fecha_registro) = MONTH(CURDATE())"
    )->fetchColumn();

    $distritos_stmt = $pdo->query(
        "SELECT COALESCE(NULLIF(TRIM(distrito), ''), 'Sin distrito') AS distrito,
                COUNT(*) AS total
         FROM simpatizantes
         GROUP BY COALESCE(NULLIF(TRIM(distrito), ''), 'Sin distrito')
         ORDER BY total DESC, distrito ASC"
    );
    $stats['distritos'] = $distritos_stmt->fetchAll(PDO::FETCH_ASSOC);
    if (!empty($stats['distritos'])) {
        $stats['distrito_fuerte'] = $stats['distritos'][0];
        $stats['distrito_debil'] = $stats['distritos'][count($stats['distritos']) - 1];
    }

    if ($buscar) {
        $like  = '%' . $buscar . '%';
        $total = $pdo->prepare("SELECT COUNT(*) FROM simpatizantes WHERE nombre LIKE ? OR dni LIKE ? OR distrito LIKE ? OR formas_apoyo LIKE ?");
        $total->execute([$like,$like,$like,$like]);
        $total = $total->fetchColumn();

        $stmt = $pdo->prepare(
            "SELECT s.*, m.id AS militante_id
             FROM simpatizantes s
             LEFT JOIN militantes m ON m.dni = s.dni
             WHERE s.nombre LIKE ? OR s.dni LIKE ? OR s.distrito LIKE ? OR s.formas_apoyo LIKE ?
             ORDER BY s.id DESC LIMIT ? OFFSET ?"
        );
        $stmt->execute([$like,$like,$like,$like,$por_pag,$offset]);
    } else {
        $total = $pdo->query("SELECT COUNT(*) FROM simpatizantes")->fetchColumn();
        $stmt  = $pdo->prepare(
            "SELECT s.*, m.id AS militante_id
             FROM simpatizantes s
             LEFT JOIN militantes m ON m.dni = s.dni
             ORDER BY s.id DESC LIMIT ? OFFSET ?"
        );
        $stmt->execute([$por_pag,$offset]);
    }
    $registros = $stmt->fetchAll();
} catch (Exception $e) { $total = 0; $registros = []; }

$pages = ceil($total / $por_pag);

$page_title = 'Simpatizantes';
include __DIR__ . '/layout.php';
?>

<?php if ($flash): ?>
<div class="mb-5 rounded-2xl px-5 py-4 text-sm font-bold border <?= $flash_type === 'success' ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200' ?>">
  <?= htmlspecialchars($flash) ?>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 sm:grid-cols-2 xl:grid-cols-5 gap-4 mb-6">
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
    <p class="text-xs font-black uppercase tracking-wide text-gray-400">Total registrados</p>
    <p class="text-3xl font-black text-[#1E3A8A] mt-1"><?= (int)$stats['total'] ?></p>
    <p class="text-xs text-gray-400 mt-1">Base completa</p>
  </div>
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
    <p class="text-xs font-black uppercase tracking-wide text-gray-400">Esta semana</p>
    <p class="text-3xl font-black text-green-600 mt-1"><?= (int)$stats['semana'] ?></p>
    <p class="text-xs text-gray-400 mt-1">Semana actual</p>
  </div>
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
    <p class="text-xs font-black uppercase tracking-wide text-gray-400">Este mes</p>
    <p class="text-3xl font-black text-blue-600 mt-1"><?= (int)$stats['mes'] ?></p>
    <p class="text-xs text-gray-400 mt-1">Mes actual</p>
  </div>
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
    <p class="text-xs font-black uppercase tracking-wide text-gray-400">Distrito fuerte</p>
    <p class="text-base font-black text-[#1E3A8A] mt-2 truncate" title="<?= htmlspecialchars($stats['distrito_fuerte']['distrito']) ?>">
      <?= htmlspecialchars($stats['distrito_fuerte']['distrito']) ?>
    </p>
    <p class="text-xs text-gray-400 mt-1"><?= (int)$stats['distrito_fuerte']['total'] ?> simpatizantes</p>
  </div>
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5">
    <p class="text-xs font-black uppercase tracking-wide text-gray-400">Distrito debil</p>
    <p class="text-base font-black text-red-500 mt-2 truncate" title="<?= htmlspecialchars($stats['distrito_debil']['distrito']) ?>">
      <?= htmlspecialchars($stats['distrito_debil']['distrito']) ?>
    </p>
    <p class="text-xs text-gray-400 mt-1"><?= (int)$stats['distrito_debil']['total'] ?> simpatizantes</p>
  </div>
</div>

<?php if (!empty($stats['distritos'])): ?>
<div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-5 mb-6">
  <div class="flex items-center justify-between gap-3 mb-4">
    <div>
      <h2 class="font-black text-[#1E3A8A] text-sm">Ranking por distrito</h2>
      <p class="text-xs text-gray-400 mt-0.5">Lectura rapida de fuerza territorial.</p>
    </div>
    <span class="text-xs font-bold text-gray-400"><?= count($stats['distritos']) ?> distrito(s)</span>
  </div>
  <div class="space-y-3">
    <?php
      $max_distrito_total = max(1, (int)$stats['distrito_fuerte']['total']);
      foreach (array_slice($stats['distritos'], 0, 8) as $dist):
        $dist_total = (int)$dist['total'];
        $pct = max(4, (int)round(($dist_total / $max_distrito_total) * 100));
    ?>
    <div>
      <div class="flex items-center justify-between gap-3 text-xs mb-1">
        <span class="font-bold text-gray-700"><?= htmlspecialchars($dist['distrito']) ?></span>
        <span class="text-gray-400"><?= $dist_total ?> simpatizantes</span>
      </div>
      <div class="h-2.5 bg-gray-100 rounded-full overflow-hidden">
        <div class="h-full bg-[#1E3A8A] rounded-full" style="width: <?= $pct ?>%"></div>
      </div>
    </div>
    <?php endforeach; ?>
  </div>
</div>
<?php endif; ?>

<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 mb-6">
  <!-- Buscador -->
  <form method="GET" class="flex items-center gap-2 w-full sm:w-auto">
    <div class="relative flex-1 sm:w-72">
      <input type="text" name="q" value="<?= htmlspecialchars($buscar) ?>"
             placeholder="Buscar por nombre, DNI o distrito..."
             id="buscador"
             class="w-full border border-gray-200 rounded-xl pl-9 pr-4 py-2.5 text-sm focus:ring-2 focus:ring-[#1E3A8A] outline-none">
      <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"
           fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
      </svg>
    </div>
    <button type="submit"
            class="bg-[#1E3A8A] text-white text-sm font-semibold px-4 py-2.5 rounded-xl hover:bg-blue-900 transition-colors">
      Buscar
    </button>
    <?php if ($buscar): ?>
    <a href="simpatizantes.php" class="text-sm text-gray-400 hover:text-gray-600">✕</a>
    <?php endif; ?>
  </form>
  <!-- Exportar -->
  <div class="flex items-center gap-2 flex-wrap">
    <a href="simpatizantes.php?export=csv"
       class="inline-flex items-center gap-2 border border-green-300 text-green-700 hover:bg-green-50 font-semibold text-sm px-4 py-2.5 rounded-xl transition-colors">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
      CSV
    </a>
    <a href="simpatizantes.php?export=excel"
       class="inline-flex items-center gap-2 border border-emerald-400 text-emerald-700 hover:bg-emerald-50 font-semibold text-sm px-4 py-2.5 rounded-xl transition-colors">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
      Excel
    </a>
    <a href="exportar-pdf.php" target="_blank"
       class="inline-flex items-center gap-2 border border-red-300 text-red-600 hover:bg-red-50 font-semibold text-sm px-4 py-2.5 rounded-xl transition-colors">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2"><path stroke-linecap="round" stroke-linejoin="round" d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>
      PDF
    </a>
  </div>
</div>

<p class="text-sm text-gray-400 mb-4"><?= $total ?> simpatizantes <?= $buscar ? "encontrados para \"$buscar\"" : 'registrados' ?></p>

<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden"
     x-data="{ modal: false, simpatizante: {}, openConvertir(row) { this.simpatizante = row; this.modal = true; } }">
  <?php if (empty($registros)): ?>
  <p class="text-center text-gray-400 py-16 text-sm">Sin registros<?= $buscar ? ' para esa búsqueda' : '' ?>.</p>
  <?php else: ?>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wide">
        <tr>
          <th class="px-4 py-3 text-left">#</th>
          <th class="px-4 py-3 text-left">Apellidos y nombres</th>
          <th class="px-4 py-3 text-left">DNI</th>
          <th class="px-4 py-3 text-left">Cel</th>
          <th class="px-4 py-3 text-left">WhatsApp</th>
          <th class="px-4 py-3 text-left">Correo</th>
          <th class="px-4 py-3 text-left">Distrito</th>
          <th class="px-4 py-3 text-left">Cómo apoya</th>
          <th class="px-4 py-3 text-left">Fecha</th>
          <th class="px-4 py-3 text-left">Accion</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-50">
        <?php foreach ($registros as $i => $r): ?>
        <tr class="hover:bg-gray-50 transition-colors">
          <td class="px-4 py-3 text-gray-400 text-xs"><?= $offset + $i + 1 ?></td>
          <td class="px-4 py-3">
            <p class="font-semibold text-gray-800 text-sm"><?= htmlspecialchars($r['nombre']) ?></p>
          </td>
          <td class="px-4 py-3">
            <p class="font-mono text-gray-600 text-sm"><?= htmlspecialchars($r['dni']) ?></p>
          </td>
          <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">
            <?php $cel = $r['celular'] ?: ($r['telefono'] ?? ''); ?>
            <?= $cel !== '' ? htmlspecialchars($cel) : '<span class="text-gray-300">-</span>' ?>
          </td>
          <td class="px-4 py-3 text-sm text-gray-600 whitespace-nowrap">
            <?= !empty($r['whatsapp']) ? htmlspecialchars($r['whatsapp']) : '<span class="text-gray-300">-</span>' ?>
          </td>
          <td class="px-4 py-3 text-xs text-gray-500 max-w-[180px] break-words">
            <?= !empty($r['correo']) ? htmlspecialchars($r['correo']) : '<span class="text-gray-300">-</span>' ?>
          </td>
          <td class="px-4 py-3">
            <span class="bg-blue-50 text-blue-700 text-xs px-2 py-0.5 rounded-full font-medium">
              <?= htmlspecialchars($r['distrito'] ?? '') ?>
            </span>
          </td>
          <td class="px-4 py-3 text-xs text-gray-500 max-w-[180px]">
            <?= htmlspecialchars($r['formas_apoyo'] ?? '—') ?>
          </td>
          <td class="px-4 py-3 text-xs text-gray-400 whitespace-nowrap"><?= date('d/m/Y', strtotime($r['fecha_registro'])) ?></td>
          <td class="px-4 py-3 whitespace-nowrap">
            <?php if (!empty($r['militante_id'])): ?>
            <span class="inline-flex items-center bg-green-50 text-green-700 text-xs font-bold px-3 py-1.5 rounded-full">
              Militante
            </span>
            <?php elseif (!$can_manage_militantes): ?>
            <span class="inline-flex items-center bg-gray-50 text-gray-400 text-xs font-bold px-3 py-1.5 rounded-full">
              Sin permiso
            </span>
            <?php else: ?>
            <div class="flex items-center gap-1.5">
              <button type="button"
                      @click='openConvertir(<?= json_encode([
                          'id' => (int)$r['id'],
                          'nombre' => $r['nombre'],
                          'dni' => $r['dni'],
                      ], JSON_HEX_APOS | JSON_HEX_TAG | JSON_UNESCAPED_UNICODE) ?>)'
                      class="inline-flex items-center gap-1.5 bg-[#1E3A8A] hover:bg-blue-900 text-white text-xs font-bold px-3 py-1.5 rounded-full transition-colors">
                Militante
              </button>
              <a href="personeros.php?from_simpatizante=<?= $r['id'] ?>"
                 title="Convertir a Personero"
                 class="inline-flex items-center gap-1 bg-purple-600 hover:bg-purple-700 text-white text-xs font-bold px-3 py-1.5 rounded-full transition-colors">
                <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0"/>
                </svg>
                Personero
              </a>
            </div>
            <?php endif; ?>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- Paginación -->
  <div x-show="modal" x-cloak class="fixed inset-0 z-[80] flex items-center justify-center p-4">
    <div class="absolute inset-0 bg-gray-900/50 backdrop-blur-sm" @click="modal = false"></div>
    <form method="POST" class="relative bg-white rounded-2xl shadow-2xl border border-gray-100 w-full max-w-lg overflow-hidden">
      <?= csrf_field() ?>
      <input type="hidden" name="action" value="convertir_militante">
      <input type="hidden" name="simpatizante_id" :value="simpatizante.id || ''">

      <div class="bg-[#1E3A8A] px-6 py-4">
        <h2 class="text-white font-black text-lg">Convertir en Militante</h2>
        <p class="text-blue-100 text-sm mt-1">Asignacion oficial dentro de la estructura del partido.</p>
      </div>

      <div class="p-6 space-y-5">
        <div class="bg-gray-50 rounded-xl p-4">
          <p class="text-xs font-black uppercase tracking-wide text-gray-400 mb-1">Simpatizante</p>
          <p class="font-black text-gray-800" x-text="simpatizante.nombre"></p>
          <p class="text-sm text-gray-500">DNI: <span x-text="simpatizante.dni"></span></p>
        </div>

        <div>
          <label class="block text-xs font-black text-gray-500 uppercase mb-1">Fecha de ingreso</label>
          <input type="date" name="fecha_ingreso" value="<?= date('Y-m-d') ?>"
                 class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-[#1E3A8A] outline-none">
        </div>

        <div>
          <label class="block text-xs font-black text-gray-500 uppercase mb-1">Cargo</label>
          <select name="cargo_id"
                  class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-[#1E3A8A] outline-none">
            <option value="">Sin cargo por ahora</option>
            <?php foreach ($cargos_militante as $cargo): ?>
            <option value="<?= (int)$cargo['id'] ?>"><?= htmlspecialchars($cargo['nombre']) ?></option>
            <?php endforeach; ?>
          </select>
          <?php if (empty($cargos_militante)): ?>
          <p class="text-xs text-amber-600 mt-2">No hay cargos activos. Ejecuta la migracion de militantes.</p>
          <?php endif; ?>
        </div>
      </div>

      <div class="px-6 py-4 bg-gray-50 flex items-center justify-end gap-3">
        <button type="button" @click="modal = false"
                class="px-5 py-2.5 rounded-xl border border-gray-200 bg-white text-gray-600 text-sm font-bold hover:bg-gray-100">
          Cancelar
        </button>
        <button type="submit"
                class="px-5 py-2.5 rounded-xl bg-[#1E3A8A] text-white text-sm font-bold hover:bg-blue-900">
          Crear militante
        </button>
      </div>
    </form>
  </div>

  <?php if ($pages > 1): ?>
  <div class="flex items-center justify-center gap-2 py-5 border-t border-gray-50">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
    <a href="?pag=<?= $i ?><?= $buscar ? '&q='.urlencode($buscar) : '' ?>"
       class="w-8 h-8 flex items-center justify-center rounded-lg text-sm font-semibold transition-colors
              <?= $i===$pag ? 'bg-[#1E3A8A] text-white' : 'text-gray-500 hover:bg-gray-100' ?>">
      <?= $i ?>
    </a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>

    </main>
  </div>
</body>
</html>
