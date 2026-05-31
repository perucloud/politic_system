<?php
// ============================================================
// auditoria.php — Visor del registro de actividad del sistema
// Requiere rol mínimo: admin
// ============================================================
session_start();
require_once __DIR__ . '/../includes/config/db.php';
require_once __DIR__ . '/config/auth.php';

require_rol('admin');
require_modulo('auditoria', $pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
}

// ── Limpiar logs de más de 30 días (POST action=clean) ───────
$flash = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'clean') {
    try {
        $stmt = $pdo->exec("DELETE FROM activity_logs WHERE creado_en < DATE_SUB(NOW(), INTERVAL 30 DAY)");
        $eliminados = is_int($stmt) ? $stmt : 0;
        log_activity($pdo, 'Limpió logs de auditoría con más de 30 días', 'configuracion');
        $flash = ['tipo' => 'success', 'texto' => "Se eliminaron {$eliminados} registro(s) de más de 30 días."];
    } catch (Exception $e) {
        $flash = ['tipo' => 'error', 'texto' => 'Error al limpiar logs: ' . htmlspecialchars($e->getMessage())];
    }
}

// ── Filtros ──────────────────────────────────────────────────
$filtro_usuario = trim($_GET['usuario'] ?? '');
$filtro_modulo  = trim($_GET['modulo']  ?? '');
$filtro_desde   = trim($_GET['desde']  ?? '');
$filtro_hasta   = trim($_GET['hasta']  ?? '');

// ── Módulos disponibles (para el select) ─────────────────────
$modulos_disponibles = [];
try {
    $rows_mod = $pdo->query(
        "SELECT DISTINCT modulo FROM activity_logs WHERE modulo IS NOT NULL AND modulo <> '' ORDER BY modulo ASC"
    )->fetchAll(PDO::FETCH_COLUMN);
    $modulos_disponibles = $rows_mod;
} catch (Exception $e) {
    $modulos_disponibles = [];
}

// ── Construcción dinámica de WHERE ───────────────────────────
$where  = [];
$params = [];

if ($filtro_usuario !== '') {
    $where[]  = 'usuario_nombre LIKE ?';
    $params[] = '%' . $filtro_usuario . '%';
}
if ($filtro_modulo !== '') {
    $where[]  = 'modulo = ?';
    $params[] = $filtro_modulo;
}
if ($filtro_desde !== '') {
    $where[]  = 'DATE(creado_en) >= ?';
    $params[] = $filtro_desde;
}
if ($filtro_hasta !== '') {
    $where[]  = 'DATE(creado_en) <= ?';
    $params[] = $filtro_hasta;
}

$sql_where = $where ? ('WHERE ' . implode(' AND ', $where)) : '';

// ── Paginación ───────────────────────────────────────────────
$por_pag = 25;
$pag     = max(1, (int)($_GET['pag'] ?? 1));
$offset  = ($pag - 1) * $por_pag;

$total = 0;
$logs  = [];

try {
    $total = (int)$pdo->prepare("SELECT COUNT(*) FROM activity_logs {$sql_where}")
                      ->execute($params) ? 0 : 0; // reuse below
    $stmt_cnt = $pdo->prepare("SELECT COUNT(*) FROM activity_logs {$sql_where}");
    $stmt_cnt->execute($params);
    $total = (int)$stmt_cnt->fetchColumn();

    $stmt_data = $pdo->prepare(
        "SELECT id, usuario_id, usuario_nombre, accion, modulo, ip, creado_en
         FROM activity_logs
         {$sql_where}
         ORDER BY creado_en DESC
         LIMIT ? OFFSET ?"
    );
    // PDO bind — integers need explicit type
    $bind = $params;
    $bind[] = $por_pag;
    $bind[] = $offset;
    $stmt_data->execute($bind);
    $logs = $stmt_data->fetchAll();
} catch (Exception $e) {
    $total = 0;
    $logs  = [];
    if (!$flash) {
        $flash = ['tipo' => 'error', 'texto' => 'Error al consultar logs: ' . htmlspecialchars($e->getMessage())];
    }
}

$pages = $total > 0 ? (int)ceil($total / $por_pag) : 0;

// ── Total general (sin filtros, para el badge del header) ─────
$total_general = 0;
try {
    $total_general = (int)$pdo->query("SELECT COUNT(*) FROM activity_logs")->fetchColumn();
} catch (Exception $e) {}

// ── Colores de módulos ────────────────────────────────────────
function modulo_badge(string $modulo): string {
    $m = strtolower(trim($modulo));
    $map = [
        'candidatos_distritales' => ['bg' => 'bg-blue-100',   'text' => 'text-blue-700',   'label' => 'Candidatos'],
        'noticias'               => ['bg' => 'bg-green-100',  'text' => 'text-green-700',  'label' => 'Noticias'],
        'usuarios'               => ['bg' => 'bg-purple-100', 'text' => 'text-purple-700', 'label' => 'Usuarios'],
        'configuracion'          => ['bg' => 'bg-orange-100', 'text' => 'text-orange-700', 'label' => 'Configuración'],
        'media'                  => ['bg' => 'bg-yellow-100', 'text' => 'text-yellow-700', 'label' => 'Media'],
    ];
    if (isset($map[$m])) {
        $b = $map[$m];
        return '<span class="inline-flex items-center text-[11px] font-bold px-2 py-0.5 rounded-full '
             . $b['bg'] . ' ' . $b['text'] . '">' . htmlspecialchars($b['label']) . '</span>';
    }
    $label = $modulo !== '' ? $modulo : 'Otros';
    return '<span class="inline-flex items-center text-[11px] font-bold px-2 py-0.5 rounded-full bg-gray-100 text-gray-600">'
         . htmlspecialchars($label) . '</span>';
}

// ── Avatar color por inicial ──────────────────────────────────
function avatar_color(string $nombre): string {
    $paleta = [
        'from-blue-500 to-blue-700',
        'from-purple-500 to-purple-700',
        'from-emerald-500 to-teal-700',
        'from-rose-500 to-red-700',
        'from-amber-500 to-orange-600',
        'from-cyan-500 to-sky-700',
        'from-fuchsia-500 to-pink-700',
        'from-indigo-500 to-indigo-700',
    ];
    $idx = $nombre !== '' ? (ord(strtoupper($nombre[0])) % count($paleta)) : 0;
    return $paleta[$idx];
}

// ── Helper para query string preservando filtros ──────────────
function audit_qs(array $override = []): string {
    $base = [
        'usuario' => $_GET['usuario'] ?? '',
        'modulo'  => $_GET['modulo']  ?? '',
        'desde'   => $_GET['desde']   ?? '',
        'hasta'   => $_GET['hasta']   ?? '',
        'pag'     => $_GET['pag']     ?? 1,
    ];
    $merged = array_merge($base, $override);
    $parts  = [];
    foreach ($merged as $k => $v) {
        if ($v !== '' && $v !== null) {
            $parts[] = urlencode($k) . '=' . urlencode((string)$v);
        }
    }
    return $parts ? ('?' . implode('&', $parts)) : '?';
}

$page_title = 'Auditoría del Sistema';
include __DIR__ . '/layout.php';
?>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- CABECERA                                                    -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
  <div class="flex items-center gap-3">
    <div>
      <h2 class="text-2xl font-black text-gray-800 flex items-center gap-3">
        Auditoría del Sistema
        <span class="text-sm font-bold bg-[#1E3A8A] text-white px-2.5 py-1 rounded-full align-middle">
          <?= number_format($total_general) ?>
        </span>
      </h2>
      <p class="text-sm text-gray-400 mt-0.5">Historial de actividad de todos los usuarios del panel.</p>
    </div>
  </div>

  <!-- Botón limpiar -->
  <form method="POST" action=""
        onsubmit="return confirm('¿Eliminar todos los logs de más de 30 días? Esta acción no se puede deshacer.');">
    <input type="hidden" name="action" value="clean">
    <button type="submit"
      class="inline-flex items-center gap-2 bg-red-50 hover:bg-red-100 text-red-600 border border-red-200
             font-semibold text-sm px-4 py-2.5 rounded-xl transition-all duration-200 active:scale-95">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
      </svg>
      Limpiar logs de más de 30 días
    </button>
  </form>
</div>

<!-- ── Flash ──────────────────────────────────────────────────── -->
<?php if ($flash): ?>
<div x-data="{ show: true }" x-show="show" x-init="setTimeout(() => show = false, 6000)"
     x-transition:leave="transition ease-in duration-300"
     x-transition:leave-start="opacity-100" x-transition:leave-end="opacity-0"
     class="mb-5 flex items-center gap-3 px-4 py-3 rounded-xl text-sm font-semibold
            <?= $flash['tipo'] === 'success'
                ? 'bg-green-50 text-green-700 border border-green-200'
                : 'bg-red-50 text-red-700 border border-red-200' ?>">
  <?php if ($flash['tipo'] === 'success'): ?>
    <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
    </svg>
  <?php else: ?>
    <svg class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
    </svg>
  <?php endif; ?>
  <?= htmlspecialchars($flash['texto']) ?>
  <button @click="show = false" class="ml-auto text-current opacity-50 hover:opacity-100 transition-opacity">
    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
    </svg>
  </button>
</div>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- FILTROS                                                     -->
<!-- ═══════════════════════════════════════════════════════════ -->
<form method="GET" action="" class="bg-white rounded-2xl shadow-sm border border-gray-100 p-4 mb-5">
  <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-3 items-end">

    <!-- Usuario -->
    <div>
      <label class="block text-xs font-semibold text-gray-600 mb-1.5">Usuario</label>
      <input
        type="text"
        name="usuario"
        value="<?= htmlspecialchars($filtro_usuario) ?>"
        placeholder="Buscar por nombre…"
        class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-800
               focus:outline-none focus:ring-2 focus:ring-[#1E3A8A]/30 focus:border-[#1E3A8A]
               transition placeholder-gray-300"
      >
    </div>

    <!-- Módulo -->
    <div>
      <label class="block text-xs font-semibold text-gray-600 mb-1.5">Módulo</label>
      <select
        name="modulo"
        class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-800
               focus:outline-none focus:ring-2 focus:ring-[#1E3A8A]/30 focus:border-[#1E3A8A]
               transition bg-white appearance-none"
      >
        <option value="">Todos los módulos</option>
        <?php foreach ($modulos_disponibles as $mod): ?>
        <option value="<?= htmlspecialchars($mod) ?>"
                <?= $filtro_modulo === $mod ? 'selected' : '' ?>>
          <?= htmlspecialchars($mod !== '' ? $mod : 'Sin módulo') ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Desde -->
    <div>
      <label class="block text-xs font-semibold text-gray-600 mb-1.5">Desde</label>
      <input
        type="date"
        name="desde"
        value="<?= htmlspecialchars($filtro_desde) ?>"
        class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-800
               focus:outline-none focus:ring-2 focus:ring-[#1E3A8A]/30 focus:border-[#1E3A8A]
               transition"
      >
    </div>

    <!-- Hasta -->
    <div>
      <label class="block text-xs font-semibold text-gray-600 mb-1.5">Hasta</label>
      <input
        type="date"
        name="hasta"
        value="<?= htmlspecialchars($filtro_hasta) ?>"
        class="w-full px-3.5 py-2.5 rounded-xl border border-gray-200 text-sm text-gray-800
               focus:outline-none focus:ring-2 focus:ring-[#1E3A8A]/30 focus:border-[#1E3A8A]
               transition"
      >
    </div>
  </div>

  <div class="flex items-center gap-2 mt-3">
    <button type="submit"
      class="inline-flex items-center gap-2 bg-[#1E3A8A] hover:bg-blue-900 text-white font-bold
             text-sm px-5 py-2.5 rounded-xl transition-all shadow-sm active:scale-95">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
      </svg>
      Filtrar
    </button>
    <?php if ($filtro_usuario !== '' || $filtro_modulo !== '' || $filtro_desde !== '' || $filtro_hasta !== ''): ?>
    <a href="auditoria.php"
       class="inline-flex items-center gap-1.5 text-sm text-gray-400 hover:text-gray-700 font-medium transition-colors px-3 py-2.5">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
      </svg>
      Limpiar filtros
    </a>
    <?php endif; ?>
  </div>
</form>

<!-- ── Info resultados ────────────────────────────────────────── -->
<?php if ($filtro_usuario !== '' || $filtro_modulo !== '' || $filtro_desde !== '' || $filtro_hasta !== ''): ?>
<p class="text-xs text-gray-400 mb-3 pl-1">
  <?= number_format($total) ?> resultado(s) encontrado(s) con los filtros activos.
</p>
<?php endif; ?>

<!-- ═══════════════════════════════════════════════════════════ -->
<!-- TABLA DE LOGS                                               -->
<!-- ═══════════════════════════════════════════════════════════ -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
  <?php if (empty($logs)): ?>
  <!-- Estado vacío -->
  <div class="flex flex-col items-center justify-center py-20 text-center px-6">
    <div class="w-16 h-16 bg-gray-100 rounded-2xl flex items-center justify-center mb-4">
      <svg class="w-8 h-8 text-gray-300" fill="none" viewBox="0 0 24 24" stroke="currentColor">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
      </svg>
    </div>
    <p class="text-gray-500 font-semibold text-sm">No hay registros de auditoría</p>
    <p class="text-gray-400 text-xs mt-1">
      <?php if ($filtro_usuario !== '' || $filtro_modulo !== '' || $filtro_desde !== '' || $filtro_hasta !== ''): ?>
        Ningún log coincide con los filtros aplicados.
      <?php else: ?>
        Las acciones de los usuarios aparecerán aquí automáticamente.
      <?php endif; ?>
    </p>
  </div>
  <?php else: ?>

  <!-- Tabla -->
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-gray-50 border-b border-gray-100">
        <tr class="text-gray-500 uppercase text-[11px] tracking-wide font-bold">
          <th class="px-4 py-3 text-left whitespace-nowrap">Fecha / Hora</th>
          <th class="px-4 py-3 text-left">Usuario</th>
          <th class="px-4 py-3 text-left">Acción</th>
          <th class="px-4 py-3 text-left">Módulo</th>
          <th class="px-4 py-3 text-left whitespace-nowrap">Dirección IP</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-50">
        <?php foreach ($logs as $log):
          $nombre   = $log['usuario_nombre'] ?? 'Sistema';
          $inicial  = strtoupper(substr($nombre, 0, 1)) ?: 'S';
          $grad     = avatar_color($nombre);
        ?>
        <tr class="hover:bg-gray-50/70 transition-colors">

          <!-- Fecha / Hora -->
          <td class="px-4 py-3 whitespace-nowrap">
            <p class="text-gray-800 font-semibold text-xs">
              <?= date('d/m/Y', strtotime($log['creado_en'])) ?>
            </p>
            <p class="text-gray-400 text-[11px]">
              <?= date('H:i:s', strtotime($log['creado_en'])) ?>
            </p>
          </td>

          <!-- Usuario con avatar -->
          <td class="px-4 py-3">
            <div class="flex items-center gap-2.5">
              <div class="w-8 h-8 flex-shrink-0 rounded-full bg-gradient-to-br <?= $grad ?>
                          flex items-center justify-center shadow-sm">
                <span class="text-white text-xs font-black"><?= htmlspecialchars($inicial) ?></span>
              </div>
              <span class="text-gray-700 font-medium text-xs max-w-[120px] truncate" title="<?= htmlspecialchars($nombre) ?>">
                <?= htmlspecialchars($nombre) ?>
              </span>
            </div>
          </td>

          <!-- Acción -->
          <td class="px-4 py-3 max-w-[280px]">
            <p class="text-gray-700 text-xs leading-relaxed truncate" title="<?= htmlspecialchars($log['accion']) ?>">
              <?= htmlspecialchars($log['accion']) ?>
            </p>
          </td>

          <!-- Módulo -->
          <td class="px-4 py-3 whitespace-nowrap">
            <?= modulo_badge($log['modulo'] ?? '') ?>
          </td>

          <!-- IP -->
          <td class="px-4 py-3 whitespace-nowrap">
            <span class="text-gray-400 text-xs font-mono">
              <?= htmlspecialchars($log['ip'] ?? '—') ?>
            </span>
          </td>

        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>

  <!-- ── Paginación ─────────────────────────────────────────── -->
  <?php if ($pages > 1): ?>
  <div class="flex items-center justify-between px-4 py-4 border-t border-gray-100">
    <p class="text-xs text-gray-400">
      Página <?= $pag ?> de <?= $pages ?>
      — mostrando <?= count($logs) ?> de <?= number_format($total) ?> registros
    </p>
    <div class="flex items-center gap-1.5">
      <?php if ($pag > 1): ?>
      <a href="<?= htmlspecialchars(audit_qs(['pag' => $pag - 1])) ?>"
         class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold text-gray-500
                hover:bg-gray-100 rounded-lg transition-colors">
        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        Anterior
      </a>
      <?php endif; ?>

      <?php
      // Mostrar ventana de páginas alrededor de la actual
      $window = 2;
      $p_start = max(1, $pag - $window);
      $p_end   = min($pages, $pag + $window);
      if ($p_start > 1): ?>
        <a href="<?= htmlspecialchars(audit_qs(['pag' => 1])) ?>"
           class="w-8 h-8 flex items-center justify-center rounded-lg text-xs font-semibold text-gray-500 hover:bg-gray-100 transition-colors">1</a>
        <?php if ($p_start > 2): ?>
          <span class="text-gray-400 text-xs px-1">…</span>
        <?php endif; ?>
      <?php endif; ?>

      <?php for ($i = $p_start; $i <= $p_end; $i++): ?>
      <a href="<?= htmlspecialchars(audit_qs(['pag' => $i])) ?>"
         class="w-8 h-8 flex items-center justify-center rounded-lg text-xs font-semibold transition-colors
                <?= $i === $pag
                    ? 'bg-[#1E3A8A] text-white shadow-sm'
                    : 'text-gray-500 hover:bg-gray-100' ?>">
        <?= $i ?>
      </a>
      <?php endfor; ?>

      <?php if ($p_end < $pages): ?>
        <?php if ($p_end < $pages - 1): ?>
          <span class="text-gray-400 text-xs px-1">…</span>
        <?php endif; ?>
        <a href="<?= htmlspecialchars(audit_qs(['pag' => $pages])) ?>"
           class="w-8 h-8 flex items-center justify-center rounded-lg text-xs font-semibold text-gray-500 hover:bg-gray-100 transition-colors">
          <?= $pages ?>
        </a>
      <?php endif; ?>

      <?php if ($pag < $pages): ?>
      <a href="<?= htmlspecialchars(audit_qs(['pag' => $pag + 1])) ?>"
         class="inline-flex items-center gap-1 px-3 py-1.5 text-xs font-semibold text-gray-500
                hover:bg-gray-100 rounded-lg transition-colors">
        Siguiente
        <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
      </a>
      <?php endif; ?>
    </div>
  </div>
  <?php else: ?>
  <div class="px-4 py-3 border-t border-gray-100">
    <p class="text-xs text-gray-400">
      Mostrando <?= count($logs) ?> de <?= number_format($total) ?> registro(s).
    </p>
  </div>
  <?php endif; ?>

  <?php endif; // end empty check ?>
</div>

    </main>
  </div>
</body>
</html>
