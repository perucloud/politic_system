<?php
// ── Bootstrap ANTES del layout ───────────────────────────────
if (ob_get_level() === 0) ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_login();
require_modulo('actividades', $pdo);
require_rol('editor');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
}

// ── Paleta de colores por distrito ───────────────────────────
$colores = [
    'Satipo (Capital)'   => '#6366F1',
    'Río Negro'          => '#EC4899',
    'Pangoa'             => '#F59E0B',
    'Río Tambo'          => '#10B981',
    'Coviriali'          => '#3B82F6',
    'Llaylla'            => '#8B5CF6',
    'Vizcatán del Ene'   => '#F43F5E',
    'Pampa Hermosa'      => '#06B6D4',
    'Mazamari'           => '#84CC16',
];

$distritos = array_keys($colores);

$meses_es = ['Enero','Febrero','Marzo','Abril','Mayo','Junio',
             'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
$dias_es  = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];

function fecha_es_act(string $fecha): string {
    global $meses_es;
    if (!$fecha || $fecha === '0000-00-00') return '—';
    $ts = strtotime($fecha);
    return date('d', $ts) . ' ' . $meses_es[(int)date('n', $ts) - 1] . ' ' . date('Y', $ts);
}

// ── Acción: crear rápido desde modal ─────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'quick_create') {
    $nombre      = trim($_POST['nombre']      ?? '');
    $fecha       = trim($_POST['fecha']       ?? '');
    $hora        = trim($_POST['hora']        ?? '') ?: null;
    $lugar       = trim($_POST['lugar']       ?? '') ?: null;
    $distrito    = trim($_POST['distrito']    ?? 'Satipo (Capital)');
    $descripcion = trim($_POST['descripcion'] ?? '') ?: null;
    $estado      = in_array($_POST['estado'] ?? '', ['publicado','borrador'])
                   ? $_POST['estado'] : 'publicado';
    if (!in_array($distrito, $distritos)) $distrito = 'Satipo (Capital)';

    if ($nombre && $fecha) {
        try {
            $pdo->prepare(
                "INSERT INTO actividades (nombre,fecha,hora,lugar,distrito,descripcion,estado,creado_en,actualizado)
                 VALUES (?,?,?,?,?,?,?,NOW(),NOW())"
            )->execute([$nombre,$fecha,$hora,$lugar,$distrito,$descripcion,$estado]);
            log_activity($pdo, 'Creó actividad: ' . $nombre, 'actividades');
            header('Location: actividades.php?msg=created&cal=1');
        } catch (Exception $e) {
            header('Location: actividades.php?msg=error&cal=1');
        }
    } else {
        header('Location: actividades.php?msg=error&cal=1');
    }
    exit;
}

// ── Acción: eliminar ─────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        try {
            $r = $pdo->prepare("SELECT nombre FROM actividades WHERE id=?");
            $r->execute([$id]);
            $act = $r->fetch();
            $pdo->prepare("DELETE FROM actividades WHERE id=?")->execute([$id]);
            if ($act) log_activity($pdo, 'Eliminó actividad: ' . $act['nombre'], 'actividades');
            header('Location: actividades.php?msg=deleted');
        } catch (Exception $e) { header('Location: actividades.php?msg=error'); }
    }
    exit;
}

// ── Acción: toggle estado ─────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle') {
    $id = (int)($_POST['id'] ?? 0);
    if ($id) {
        try {
            $r = $pdo->prepare("SELECT nombre, estado FROM actividades WHERE id=?");
            $r->execute([$id]);
            $act = $r->fetch();
            if ($act) {
                $nuevo = $act['estado'] === 'publicado' ? 'borrador' : 'publicado';
                $pdo->prepare("UPDATE actividades SET estado=?, actualizado=NOW() WHERE id=?")
                    ->execute([$nuevo, $id]);
                log_activity($pdo, 'Cambió estado de actividad: ' . $act['nombre'] . ' → ' . $nuevo, 'actividades');
            }
            header('Location: actividades.php?msg=toggled');
        } catch (Exception $e) { header('Location: actividades.php?msg=error'); }
    }
    exit;
}

// ── Cargar TODAS las actividades para el calendario ──────────
$todas_cal = [];
try {
    $stmt_cal = $pdo->query(
        "SELECT id, nombre, fecha, hora, lugar, distrito, estado
         FROM actividades ORDER BY fecha ASC, hora ASC"
    );
    foreach ($stmt_cal->fetchAll() as $a) {
        $color = $colores[$a['distrito']] ?? '#6B7280';
        $todas_cal[] = [
            'id'       => (int)$a['id'],
            'nombre'   => $a['nombre'],
            'fecha'    => substr($a['fecha'], 0, 10),
            'hora'     => $a['hora'] ? substr($a['hora'], 0, 5) : null,
            'lugar'    => $a['lugar'],
            'distrito' => $a['distrito'],
            'estado'   => $a['estado'],
            'color'    => $color,
        ];
    }
} catch (Exception $e) {}

$actividades_json = json_encode($todas_cal, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE);

// ── Cargar actividades para la vista lista (con filtros) ──────
$filtro_estado   = $_GET['estado']   ?? '';
$filtro_distrito = $_GET['distrito'] ?? '';
$filtro_buscar   = trim($_GET['buscar'] ?? '');
$where = []; $params = [];
if (in_array($filtro_estado, ['publicado','borrador'])) { $where[] = "estado=?"; $params[] = $filtro_estado; }
if ($filtro_distrito && in_array($filtro_distrito, $distritos)) { $where[] = "distrito=?"; $params[] = $filtro_distrito; }
if ($filtro_buscar !== '') { $where[] = "nombre LIKE ?"; $params[] = '%' . $filtro_buscar . '%'; }
$sql_where = $where ? 'WHERE ' . implode(' AND ', $where) : '';

$total = 0; $actividades = [];
try {
    $tq = $pdo->prepare("SELECT COUNT(*) FROM actividades $sql_where");
    $tq->execute($params);
    $total = (int)$tq->fetchColumn();
    $sq = $pdo->prepare("SELECT * FROM actividades $sql_where ORDER BY fecha DESC");
    $sq->execute($params);
    $actividades = $sq->fetchAll();
} catch (Exception $e) {}

$init_vista = isset($_GET['cal']) ? 'calendario' : 'lista';

$page_title = 'Actividades';
include __DIR__ . '/layout.php';
?>

<style>
  .cal-day { min-height: 90px; transition: background 0.12s; }
  .cal-day:hover { background: #F0F4FF; }
  .cal-day.is-today > .day-num { background: #6366F1; color: white; border-radius: 50%; }
  .evt-chip { display: block; font-size: 11px; padding: 2px 6px; border-radius: 6px;
              color: white; white-space: nowrap; overflow: hidden; text-overflow: ellipsis;
              margin-bottom: 2px; cursor: pointer; font-weight: 600; }
  .week-col { min-height: 180px; }
  [x-cloak] { display: none !important; }
</style>

<?php
$msg = $_GET['msg'] ?? '';
$msg_map = [
    'created' => ['Actividad creada correctamente.', 'green'],
    'updated' => ['Actividad actualizada.', 'green'],
    'deleted' => ['Actividad eliminada.', 'green'],
    'toggled' => ['Estado actualizado.', 'green'],
    'error'   => ['Ocurrió un error. Inténtalo de nuevo.', 'red'],
];
if ($msg && isset($msg_map[$msg])):
    [$mt, $mc] = $msg_map[$msg];
?>
<div class="mb-4 px-4 py-3 rounded-xl text-sm font-semibold border
            <?= $mc === 'green' ? 'bg-green-50 border-green-200 text-green-700' : 'bg-red-50 border-red-200 text-red-700' ?>">
  <?= htmlspecialchars($mt) ?>
</div>
<?php endif; ?>

<!-- ══════════════════════════════════════════════════════════ -->
<!--  COMPONENTE ALPINE                                        -->
<!-- ══════════════════════════════════════════════════════════ -->
<div x-data="actividadesApp()" x-cloak>

  <!-- ── Encabezado ──────────────────────────────────────── -->
  <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-5">
    <div>
      <h2 class="text-xl font-black text-gray-800">Actividades</h2>
      <p class="text-sm text-gray-500"><?= $total ?> actividad<?= $total!==1?'es':'' ?> registrada<?= $total!==1?'s':'' ?></p>
    </div>
    <div class="flex items-center gap-2 flex-wrap">
      <!-- Toggle vista -->
      <div class="flex bg-gray-100 rounded-xl p-1 gap-1">
        <button @click="vista='lista'"
                :class="vista==='lista' ? 'bg-white shadow text-[#1E3A8A] font-bold' : 'text-gray-500 hover:text-gray-700'"
                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all flex items-center gap-1.5">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M4 6h16M4 10h16M4 14h16M4 18h7"/>
          </svg>
          Lista
        </button>
        <button @click="vista='calendario'"
                :class="vista==='calendario' ? 'bg-white shadow text-[#6366F1] font-bold' : 'text-gray-500 hover:text-gray-700'"
                class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all flex items-center gap-1.5">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
          </svg>
          Calendario
        </button>
      </div>
      <!-- Nueva Actividad -->
      <button @click="openModal()"
              class="inline-flex items-center gap-2 bg-[#6366F1] hover:bg-indigo-700 text-white
                     font-bold text-sm px-5 py-2.5 rounded-xl transition-all shadow-sm hover:shadow">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
        </svg>
        Nueva Actividad
      </button>
    </div>
  </div>

  <!-- ══ VISTA LISTA ══════════════════════════════════════ -->
  <div x-show="vista==='lista'" x-transition>

    <!-- Filtros -->
    <form method="GET" class="bg-white rounded-2xl border border-gray-100 shadow-sm p-4 mb-5">
      <div class="grid grid-cols-1 sm:grid-cols-3 gap-3">
        <div>
          <label class="block text-xs font-semibold text-gray-500 mb-1 uppercase tracking-wide">Estado</label>
          <select name="estado" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-[#1E3A8A] outline-none">
            <option value="" <?= $filtro_estado==='' ? 'selected' : '' ?>>Todos los estados</option>
            <option value="publicado" <?= $filtro_estado==='publicado' ? 'selected' : '' ?>>Publicado</option>
            <option value="borrador"  <?= $filtro_estado==='borrador'  ? 'selected' : '' ?>>Borrador</option>
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-500 mb-1 uppercase tracking-wide">Distrito</label>
          <select name="distrito" class="w-full border border-gray-200 rounded-xl px-3 py-2 text-sm focus:ring-2 focus:ring-[#1E3A8A] outline-none">
            <option value="">Todos los distritos</option>
            <?php foreach ($distritos as $d): ?>
            <option value="<?= htmlspecialchars($d) ?>" <?= $filtro_distrito===$d ? 'selected' : '' ?>><?= htmlspecialchars($d) ?></option>
            <?php endforeach; ?>
          </select>
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-500 mb-1 uppercase tracking-wide">Buscar</label>
          <div class="relative">
            <input type="text" name="buscar" value="<?= htmlspecialchars($filtro_buscar) ?>"
                   placeholder="Nombre de la actividad..."
                   class="w-full border border-gray-200 rounded-xl pl-9 pr-3 py-2 text-sm focus:ring-2 focus:ring-[#1E3A8A] outline-none">
            <svg class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0"/>
            </svg>
          </div>
        </div>
      </div>
      <div class="flex items-center gap-2 mt-3">
        <button type="submit" class="bg-[#1E3A8A] hover:bg-blue-900 text-white text-sm font-bold px-5 py-2 rounded-xl transition-all">Filtrar</button>
        <?php if ($filtro_estado || $filtro_distrito || $filtro_buscar): ?>
        <a href="actividades.php" class="text-sm text-gray-400 hover:text-gray-600 font-medium px-3 py-2">Limpiar filtros</a>
        <?php endif; ?>
      </div>
    </form>

    <!-- Tabla -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
      <?php if (empty($actividades)): ?>
      <div class="py-20 flex flex-col items-center text-center">
        <div class="w-16 h-16 bg-gray-100 rounded-2xl flex items-center justify-center mb-4">
          <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
          </svg>
        </div>
        <p class="text-gray-500 font-semibold text-sm">No hay actividades registradas</p>
      </div>
      <?php else: ?>
      <div class="overflow-x-auto" x-data="{ delId:null, delNombre:'' }">

        <!-- Modal eliminar -->
        <div x-show="delId" x-cloak
             class="fixed inset-0 z-[60] flex items-center justify-center px-4"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0"
             x-transition:enter-end="opacity-100">
          <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="delId=null"></div>
          <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden"
               x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95"
               x-transition:enter-end="opacity-100 scale-100">
            <div class="flex flex-col items-center pt-8 pb-4 px-6 text-center">
              <div class="w-14 h-14 rounded-full bg-red-100 flex items-center justify-center mb-3">
                <svg class="w-7 h-7 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
                </svg>
              </div>
              <h3 class="font-black text-gray-900 text-lg mb-2">¿Eliminar actividad?</h3>
              <div class="bg-gray-50 rounded-xl px-4 py-2.5 w-full text-sm font-semibold text-gray-700 mb-4 line-clamp-2" x-text="'«' + delNombre + '»'"></div>
              <div class="flex gap-3 w-full">
                <button @click="delId=null" class="flex-1 border-2 border-gray-200 text-gray-600 font-bold py-2.5 rounded-xl text-sm hover:bg-gray-50 transition-all">No, cancelar</button>
                <form method="POST" class="flex-1">
                  <input type="hidden" name="action" value="delete">
                  <input type="hidden" name="id" :value="delId">
                  <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-2.5 rounded-xl text-sm transition-all">Sí, eliminar</button>
                </form>
              </div>
            </div>
          </div>
        </div>

        <table class="w-full text-sm">
          <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wide">
            <tr>
              <th class="px-4 py-3 text-left">Fecha</th>
              <th class="px-4 py-3 text-left">Hora</th>
              <th class="px-4 py-3 text-left">Nombre</th>
              <th class="px-4 py-3 text-left">Lugar</th>
              <th class="px-4 py-3 text-left">Distrito</th>
              <th class="px-4 py-3 text-left">Estado</th>
              <th class="px-4 py-3 text-center">Acciones</th>
            </tr>
          </thead>
          <tbody class="divide-y divide-gray-50">
            <?php foreach ($actividades as $act):
              $color = $colores[$act['distrito']] ?? '#6B7280';
            ?>
            <tr class="hover:bg-gray-50 transition-colors">
              <td class="px-4 py-3 whitespace-nowrap">
                <span class="font-bold text-xs" style="color:<?= $color ?>"><?= fecha_es_act($act['fecha']) ?></span>
              </td>
              <td class="px-4 py-3 text-gray-500 whitespace-nowrap text-xs">
                <?= $act['hora'] ? substr($act['hora'],0,5) : '—' ?>
              </td>
              <td class="px-4 py-3 font-semibold text-gray-800 max-w-[180px] truncate">
                <?= htmlspecialchars($act['nombre']) ?>
              </td>
              <td class="px-4 py-3 text-gray-500 max-w-[130px] truncate text-xs">
                <?= $act['lugar'] ? htmlspecialchars($act['lugar']) : '—' ?>
              </td>
              <td class="px-4 py-3">
                <span class="text-xs px-2.5 py-1 rounded-full font-bold text-white"
                      style="background:<?= $color ?>">
                  <?= htmlspecialchars($act['distrito']) ?>
                </span>
              </td>
              <td class="px-4 py-3">
                <span class="text-xs px-2 py-0.5 rounded-full font-semibold
                             <?= $act['estado']==='publicado' ? 'bg-green-100 text-green-700' : 'bg-gray-100 text-gray-500' ?>">
                  <?= ucfirst($act['estado']) ?>
                </span>
              </td>
              <td class="px-4 py-3">
                <div class="flex items-center justify-center gap-1.5">
                  <a href="actividad-form.php?id=<?= $act['id'] ?>"
                     class="inline-flex items-center gap-1 bg-[#1E3A8A] hover:bg-blue-800 text-white text-xs font-bold px-3 py-1.5 rounded-lg transition-all">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                    </svg>
                    Editar
                  </a>
                  <form method="POST" class="inline">
                    <input type="hidden" name="action" value="toggle">
                    <input type="hidden" name="id" value="<?= $act['id'] ?>">
                    <button type="submit"
                            class="text-xs font-bold px-3 py-1.5 rounded-lg transition-all
                                   <?= $act['estado']==='publicado'
                                       ? 'bg-amber-50 text-amber-600 hover:bg-amber-100'
                                       : 'bg-green-50 text-green-600 hover:bg-green-100' ?>">
                      <?= $act['estado']==='publicado' ? 'Ocultar' : 'Publicar' ?>
                    </button>
                  </form>
                  <button @click="delId=<?= $act['id'] ?>; delNombre=<?= htmlspecialchars(json_encode($act['nombre']), ENT_QUOTES) ?>"
                          class="inline-flex items-center gap-1 bg-red-50 hover:bg-red-500 hover:text-white text-red-500 text-xs font-bold px-3 py-1.5 rounded-lg transition-all">
                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                      <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                    </svg>
                    Eliminar
                  </button>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
      </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ══ VISTA CALENDARIO ═════════════════════════════════ -->
  <div x-show="vista==='calendario'" x-transition>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">

      <!-- Header calendario -->
      <div class="flex items-center justify-between px-5 py-4 border-b border-gray-100">
        <div class="flex items-center gap-2">
          <button @click="prevPeriod()"
                  class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 transition-colors">
            <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
          </button>
          <h3 class="font-black text-gray-800 text-base min-w-[160px] text-center" x-text="periodLabel"></h3>
          <button @click="nextPeriod()"
                  class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 transition-colors">
            <svg class="w-4 h-4 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
            </svg>
          </button>
          <button @click="goToday()"
                  class="ml-1 text-xs font-bold text-indigo-600 hover:text-indigo-800 px-3 py-1.5 border border-indigo-200 rounded-lg hover:bg-indigo-50 transition-all">
            Hoy
          </button>
        </div>
        <!-- Toggle mes / semana -->
        <div class="flex bg-gray-100 rounded-xl p-1 gap-1">
          <button @click="viewMode='month'"
                  :class="viewMode==='month' ? 'bg-white shadow text-indigo-600 font-bold' : 'text-gray-500'"
                  class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all">Mes</button>
          <button @click="viewMode='week'"
                  :class="viewMode==='week' ? 'bg-white shadow text-indigo-600 font-bold' : 'text-gray-500'"
                  class="px-3 py-1.5 rounded-lg text-xs font-semibold transition-all">Semana</button>
        </div>
      </div>

      <!-- ── Vista MES ── -->
      <div x-show="viewMode==='month'" x-transition>
        <!-- Cabecera días semana -->
        <div class="grid grid-cols-7 border-b border-gray-100">
          <template x-for="h in ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom']" :key="h">
            <div class="text-center text-xs font-bold text-gray-400 py-2.5" x-text="h"></div>
          </template>
        </div>
        <!-- Días del mes -->
        <div class="grid grid-cols-7 border-l border-gray-100">
          <template x-for="cell in calendarDays" :key="cell.dateStr">
            <div :class="{
                   'opacity-40': !cell.isCurrentMonth,
                   'is-today': cell.isToday,
                   'bg-indigo-50/60': cell.isToday
                 }"
                 class="cal-day border-r border-b border-gray-100 p-1.5 cursor-pointer"
                 @click="clickCalDay(cell.dateStr)">
              <!-- Número del día -->
              <div class="flex justify-center mb-1">
                <span :class="cell.isToday ? 'bg-indigo-600 text-white w-6 h-6 flex items-center justify-center rounded-full text-xs font-black' : 'text-xs font-semibold text-gray-600'"
                      x-text="cell.day"></span>
              </div>
              <!-- Eventos -->
              <template x-for="(evt, idx) in getEventsForDate(cell.dateStr).slice(0,3)" :key="evt.id">
                <span class="evt-chip" :style="'background:' + evt.color"
                      :title="(evt.hora ? evt.hora + ' — ' : '') + evt.nombre"
                      @click.stop="openDetail(evt)"
                      x-text="(evt.hora ? evt.hora + ' ' : '') + evt.nombre"></span>
              </template>
              <template x-if="getEventsForDate(cell.dateStr).length > 3">
                <span class="text-[10px] text-gray-400 font-semibold px-1"
                      x-text="'+' + (getEventsForDate(cell.dateStr).length - 3) + ' más'"></span>
              </template>
            </div>
          </template>
        </div>
      </div>

      <!-- ── Vista SEMANA ── -->
      <div x-show="viewMode==='week'" x-transition>
        <div class="grid grid-cols-7 border-b border-gray-100">
          <template x-for="day in weekDays" :key="day.dateStr">
            <div class="text-center py-3 border-r border-gray-100 last:border-r-0 cursor-pointer hover:bg-indigo-50 transition-colors"
                 :class="day.isToday ? 'bg-indigo-50' : ''"
                 @click="clickCalDay(day.dateStr)">
              <div class="text-xs font-bold text-gray-400 mb-0.5" x-text="day.dayName"></div>
              <div :class="day.isToday ? 'bg-indigo-600 text-white w-8 h-8 rounded-full flex items-center justify-center mx-auto font-black text-sm' : 'text-xl font-black text-gray-700'"
                   x-text="day.day"></div>
            </div>
          </template>
        </div>
        <div class="grid grid-cols-7 border-l border-gray-100" style="min-height:300px">
          <template x-for="day in weekDays" :key="day.dateStr">
            <div class="week-col border-r border-gray-100 last:border-r-0 p-2 space-y-1.5">
              <template x-for="evt in getEventsForDate(day.dateStr)" :key="evt.id">
                <div class="rounded-xl p-2.5 text-white cursor-pointer hover:opacity-90 transition-opacity"
                     :style="'background:' + evt.color"
                     @click.stop="openDetail(evt)">
                  <div class="text-[11px] font-black" x-text="evt.hora || '·'"></div>
                  <div class="text-xs font-semibold leading-snug" x-text="evt.nombre"></div>
                  <div x-show="evt.lugar" class="text-[10px] opacity-80 truncate" x-text="evt.lugar"></div>
                </div>
              </template>
              <div x-show="getEventsForDate(day.dateStr).length === 0"
                   class="h-full flex items-center justify-center pt-8">
                <span class="text-xs text-gray-200 font-medium">sin eventos</span>
              </div>
              <button @click="clickCalDay(day.dateStr)"
                      x-show="getEventsForDate(day.dateStr).length === 0"
                      class="w-full text-[10px] text-indigo-400 hover:text-indigo-600 font-semibold text-center py-1 hover:bg-indigo-50 rounded-lg transition-colors">
                + añadir
              </button>
            </div>
          </template>
        </div>
      </div>

      <!-- Leyenda colores -->
      <div class="px-5 py-3 border-t border-gray-100 flex flex-wrap gap-2">
        <?php foreach ($colores as $dist => $color): ?>
        <span class="inline-flex items-center gap-1.5 text-[11px] font-semibold text-gray-600">
          <span class="w-2.5 h-2.5 rounded-full flex-shrink-0" style="background:<?= $color ?>"></span>
          <?= htmlspecialchars($dist) ?>
        </span>
        <?php endforeach; ?>
      </div>
    </div>
  </div>

  <!-- ══════════════════════════════════════════════════════
       MODAL — NUEVA ACTIVIDAD
       ══════════════════════════════════════════════════════ -->
  <div x-show="modal" x-cloak
       class="fixed inset-0 z-50 flex items-center justify-center px-4 py-6"
       x-transition:enter="transition ease-out duration-200"
       x-transition:enter-start="opacity-0"
       x-transition:enter-end="opacity-100"
       x-transition:leave="transition ease-in duration-150"
       x-transition:leave-start="opacity-100"
       x-transition:leave-end="opacity-0">

    <!-- Fondo -->
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="closeModal()"></div>

    <!-- Panel -->
    <div class="relative bg-white rounded-2xl shadow-2xl w-full overflow-hidden flex flex-col"
         style="max-width:520px; max-height:92vh;"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95 translate-y-2"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0">

      <!-- Header modal -->
      <div class="flex items-center justify-between px-6 py-4 border-b border-gray-100 flex-shrink-0">
        <div class="flex items-center gap-3">
          <div class="w-8 h-8 bg-indigo-100 rounded-xl flex items-center justify-center">
            <svg class="w-4 h-4 text-indigo-600" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
          </div>
          <div>
            <h3 class="font-black text-gray-900 text-base leading-tight">Nueva Actividad</h3>
            <p class="text-xs text-gray-400" x-text="step==='calendar' ? 'Selecciona una fecha' : selectedDateDisplay"></p>
          </div>
        </div>
        <button @click="closeModal()" class="w-8 h-8 flex items-center justify-center rounded-xl hover:bg-gray-100 transition-colors text-gray-400 hover:text-gray-600">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>

      <!-- Cuerpo scrollable -->
      <div class="flex-1 overflow-y-auto">

        <!-- ── STEP: CALENDAIO ── -->
        <div x-show="step==='calendar'" x-transition class="p-6">

          <!-- Nav mes del modal -->
          <div class="flex items-center justify-between mb-4">
            <button @click="prevModalMonth()"
                    class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 transition-colors">
              <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
              </svg>
            </button>
            <span class="font-black text-gray-800" x-text="modalMonthLabel"></span>
            <button @click="nextModalMonth()"
                    class="w-8 h-8 flex items-center justify-center rounded-lg hover:bg-gray-100 transition-colors">
              <svg class="w-4 h-4 text-gray-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
              </svg>
            </button>
          </div>

          <!-- Cabecera días -->
          <div class="grid grid-cols-7 mb-1">
            <template x-for="h in ['L','M','X','J','V','S','D']" :key="h">
              <div class="text-center text-xs font-bold text-gray-400 py-1" x-text="h"></div>
            </template>
          </div>

          <!-- Grid días modal -->
          <div class="grid grid-cols-7 gap-y-1">
            <template x-for="cell in modalCalendarDays" :key="cell.dateStr + '_m'">
              <div class="flex flex-col items-center">
                <button :class="{
                    'opacity-30 pointer-events-none': !cell.isCurrentMonth,
                    'bg-indigo-600 !text-white font-black shadow': cell.isToday,
                    'bg-indigo-100 text-indigo-700': cell.hasEvents && !cell.isToday,
                  }"
                  class="w-9 h-9 flex items-center justify-center rounded-full text-sm
                         text-gray-700 hover:bg-indigo-100 hover:text-indigo-700 transition-all font-medium"
                  @click="pickDate(cell.dateStr)"
                  x-text="cell.day">
                </button>
                <!-- Dots de eventos -->
                <div class="flex gap-0.5 mt-0.5 h-1.5">
                  <template x-for="(evt, idx) in getEventsForDate(cell.dateStr).slice(0,3)" :key="evt.id">
                    <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" :style="'background:' + evt.color"></span>
                  </template>
                </div>
              </div>
            </template>
          </div>

          <!-- Hint -->
          <p class="text-center text-gray-400 text-xs mt-5">
            Haz clic en una fecha para registrar la actividad
          </p>
          <div class="text-center mt-2">
            <button @click="pickDate(todayStr)"
                    class="text-indigo-600 hover:text-indigo-800 text-sm font-bold hover:underline transition-colors">
              Seleccionar hoy
            </button>
          </div>
        </div>

        <!-- ── STEP: FORMULARIO ── -->
        <div x-show="step==='form'" x-transition class="p-6">

          <!-- Volver -->
          <button @click="step='calendar'"
                  class="flex items-center gap-1.5 text-sm text-gray-400 hover:text-indigo-600 font-semibold mb-4 transition-colors">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
            Volver al calendario
          </button>

          <!-- Fecha seleccionada -->
          <div class="flex items-center gap-3 bg-indigo-50 rounded-xl px-4 py-3 mb-5 border border-indigo-100">
            <div class="w-12 h-12 bg-indigo-600 rounded-xl flex flex-col items-center justify-center text-white flex-shrink-0">
              <span class="text-xs font-bold opacity-80" x-text="selectedMonthShort"></span>
              <span class="text-xl font-black leading-tight" x-text="selectedDayNum"></span>
            </div>
            <div>
              <p class="font-bold text-gray-800 text-sm" x-text="selectedDateDisplay"></p>
              <p class="text-xs text-indigo-500 font-medium">Fecha de la actividad</p>
            </div>
          </div>

          <!-- Formulario -->
          <form id="quick-form" method="POST" action="actividades.php" class="space-y-4">
            <input type="hidden" name="action" value="quick_create">
            <input type="hidden" name="fecha" :value="selectedDate">

            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Nombre <span class="text-red-500">*</span></label>
              <input type="text" name="nombre" x-model="form.nombre" required
                     placeholder="Nombre de la actividad"
                     class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none transition-shadow">
            </div>

            <div class="grid grid-cols-2 gap-3">
              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Hora</label>
                <input type="time" name="hora" x-model="form.hora"
                       class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
              </div>
              <div>
                <label class="block text-xs font-semibold text-gray-600 mb-1">Lugar</label>
                <input type="text" name="lugar" x-model="form.lugar" placeholder="Lugar o dirección"
                       class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
              </div>
            </div>

            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Distrito</label>
              <select name="distrito" x-model="form.distrito"
                      class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none">
                <?php foreach ($distritos as $d): ?>
                <option value="<?= htmlspecialchars($d) ?>"><?= htmlspecialchars($d) ?></option>
                <?php endforeach; ?>
              </select>
              <!-- Badge color del distrito seleccionado -->
              <div class="flex items-center gap-2 mt-1.5">
                <span class="w-3 h-3 rounded-full" :style="'background:' + districtColor(form.distrito)"></span>
                <span class="text-xs text-gray-400" x-text="'Color asignado: ' + form.distrito"></span>
              </div>
            </div>

            <div>
              <label class="block text-xs font-semibold text-gray-600 mb-1">Descripción <span class="text-gray-400 font-normal">(opcional)</span></label>
              <textarea name="descripcion" x-model="form.descripcion" rows="3"
                        placeholder="Detalles, objetivos, público..."
                        class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-indigo-400 outline-none resize-none"></textarea>
            </div>

            <div class="flex items-center gap-5">
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="radio" name="estado" value="publicado" x-model="form.estado" class="accent-indigo-600 w-4 h-4">
                <span class="text-sm font-semibold text-gray-700">Publicado</span>
              </label>
              <label class="flex items-center gap-2 cursor-pointer">
                <input type="radio" name="estado" value="borrador" x-model="form.estado" class="accent-indigo-600 w-4 h-4">
                <span class="text-sm font-semibold text-gray-700">Borrador</span>
              </label>
            </div>

            <div class="flex gap-3 pt-2">
              <button type="submit"
                      :disabled="!form.nombre.trim()"
                      class="flex-1 bg-indigo-600 hover:bg-indigo-700 disabled:opacity-40 disabled:cursor-not-allowed
                             text-white font-black py-3 rounded-xl text-sm transition-all shadow-sm hover:shadow flex items-center justify-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                </svg>
                Guardar actividad
              </button>
              <button type="button" @click="closeModal()"
                      class="px-5 border-2 border-gray-200 text-gray-500 font-bold py-3 rounded-xl text-sm hover:bg-gray-50 transition-all">
                Cancelar
              </button>
            </div>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- ══════════════════════════════════════════════════════
       MODAL — DETALLE DE ACTIVIDAD
       ══════════════════════════════════════════════════════ -->
  <div x-show="detailModal" x-cloak
       class="fixed inset-0 z-50 flex items-center justify-center px-4"
       x-transition:enter="transition ease-out duration-200"
       x-transition:enter-start="opacity-0"
       x-transition:enter-end="opacity-100"
       x-transition:leave="transition ease-in duration-150"
       x-transition:leave-start="opacity-100"
       x-transition:leave-end="opacity-0">

    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="closeDetail()"></div>

    <div class="relative bg-white rounded-2xl shadow-2xl w-full overflow-hidden"
         style="max-width:460px"
         x-transition:enter="transition ease-out duration-200"
         x-transition:enter-start="opacity-0 scale-95 translate-y-2"
         x-transition:enter-end="opacity-100 scale-100 translate-y-0">

      <!-- Header coloreado con el color del distrito -->
      <div class="px-6 py-5 flex items-start justify-between gap-3"
           :style="'background:' + (activeActivity?.color ?? '#6366F1')">
        <div class="flex items-start gap-3 min-w-0">
          <!-- Icono calendario -->
          <div class="w-10 h-10 bg-white/20 rounded-xl flex items-center justify-center flex-shrink-0 mt-0.5">
            <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
          </div>
          <div class="min-w-0">
            <p class="text-white/70 text-xs font-semibold uppercase tracking-widest mb-1"
               x-text="activeActivity?.distrito ?? ''"></p>
            <h3 class="text-white font-black text-lg leading-snug"
                x-text="activeActivity?.nombre ?? ''"></h3>
          </div>
        </div>
        <!-- Cerrar -->
        <button @click="closeDetail()"
                class="w-8 h-8 flex items-center justify-center rounded-xl bg-white/20 hover:bg-white/30 text-white transition-colors flex-shrink-0">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>

      <!-- Cuerpo -->
      <div class="p-6 space-y-4">

        <!-- Fecha + Hora -->
        <div class="flex items-center gap-4 flex-wrap">
          <div class="flex items-center gap-2 text-gray-700">
            <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <span class="text-sm font-semibold" x-text="formatDetailDate(activeActivity?.fecha)"></span>
          </div>
          <div x-show="activeActivity?.hora" class="flex items-center gap-2 text-gray-700">
            <svg class="w-4 h-4 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
            </svg>
            <span class="text-sm font-semibold" x-text="activeActivity?.hora ?? ''"></span>
          </div>
        </div>

        <!-- Lugar -->
        <div x-show="activeActivity?.lugar" class="flex items-start gap-2 text-gray-600">
          <svg class="w-4 h-4 text-gray-400 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
          </svg>
          <span class="text-sm" x-text="activeActivity?.lugar ?? ''"></span>
        </div>

        <!-- Descripción -->
        <div x-show="activeActivity?.descripcion" class="bg-gray-50 rounded-xl px-4 py-3">
          <p class="text-xs font-bold text-gray-400 uppercase tracking-wide mb-1">Descripción</p>
          <p class="text-sm text-gray-700 leading-relaxed" x-text="activeActivity?.descripcion ?? ''"></p>
        </div>

        <!-- Estado -->
        <div class="flex items-center gap-2">
          <span class="text-xs font-bold text-gray-400 uppercase tracking-wide">Estado:</span>
          <span :class="activeActivity?.estado === 'publicado'
                        ? 'bg-green-100 text-green-700'
                        : 'bg-gray-100 text-gray-500'"
                class="text-xs font-bold px-2.5 py-1 rounded-full capitalize"
                x-text="activeActivity?.estado ?? ''"></span>
        </div>

        <!-- Botones -->
        <div class="flex gap-3 pt-2">
          <a :href="'actividad-form.php?id=' + (activeActivity?.id ?? '')"
             class="flex-1 inline-flex items-center justify-center gap-2
                    bg-[#1E3A8A] hover:bg-blue-800 text-white font-bold py-2.5 rounded-xl text-sm
                    transition-all shadow-sm hover:shadow">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
            </svg>
            Editar actividad
          </a>
          <button @click="closeDetail()"
                  class="px-5 border-2 border-gray-200 text-gray-500 font-bold py-2.5 rounded-xl text-sm hover:bg-gray-50 transition-all">
            Cerrar
          </button>
        </div>
      </div>
    </div>
  </div>

</div><!-- /x-data -->

<script>
function actividadesApp() {
  const MESES = ['Enero','Febrero','Marzo','Abril','Mayo','Junio',
                 'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
  const MESES_CORTOS = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
  const DIAS  = ['Lun','Mar','Mié','Jue','Vie','Sáb','Dom'];
  const DIAS_FULL = ['Lunes','Martes','Miércoles','Jueves','Viernes','Sábado','Domingo'];

  const COLORS = {
    'Satipo (Capital)' : '#6366F1',
    'Río Negro'        : '#EC4899',
    'Pangoa'           : '#F59E0B',
    'Río Tambo'        : '#10B981',
    'Coviriali'        : '#3B82F6',
    'Llaylla'          : '#8B5CF6',
    'Vizcatán del Ene' : '#F43F5E',
    'Pampa Hermosa'    : '#06B6D4',
    'Mazamari'         : '#84CC16',
  };

  function dateStr(d) {
    return d.getFullYear() + '-'
      + String(d.getMonth() + 1).padStart(2, '0') + '-'
      + String(d.getDate()).padStart(2, '0');
  }

  function buildCalGrid(year, month, todayStr, activities) {
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    let firstDay = new Date(year, month, 1).getDay();
    firstDay = firstDay === 0 ? 6 : firstDay - 1; // Monday-first

    const days = [];

    // Días del mes anterior
    const prevDays = new Date(year, month, 0).getDate();
    const prevMonth = month === 0 ? 11 : month - 1;
    const prevYear  = month === 0 ? year - 1 : year;
    for (let i = firstDay - 1; i >= 0; i--) {
      const d = prevDays - i;
      const ds = prevYear + '-' + String(prevMonth+1).padStart(2,'0') + '-' + String(d).padStart(2,'0');
      days.push({ day: d, isCurrentMonth: false, dateStr: ds, isToday: false,
                  hasEvents: activities.some(a => a.fecha === ds) });
    }

    // Días del mes actual
    for (let d = 1; d <= daysInMonth; d++) {
      const ds = year + '-' + String(month+1).padStart(2,'0') + '-' + String(d).padStart(2,'0');
      days.push({ day: d, isCurrentMonth: true, dateStr: ds, isToday: ds === todayStr,
                  hasEvents: activities.some(a => a.fecha === ds) });
    }

    // Días del mes siguiente
    const totalCells = Math.ceil(days.length / 7) * 7;
    const nextMonth = month === 11 ? 0 : month + 1;
    const nextYear  = month === 11 ? year + 1 : year;
    let nd = 1;
    while (days.length < totalCells) {
      const ds = nextYear + '-' + String(nextMonth+1).padStart(2,'0') + '-' + String(nd).padStart(2,'0');
      days.push({ day: nd++, isCurrentMonth: false, dateStr: ds, isToday: false,
                  hasEvents: activities.some(a => a.fecha === ds) });
    }
    return days;
  }

  const now = new Date();
  const td  = dateStr(now);

  return {
    // Vista
    vista:    '<?= $init_vista ?>',
    viewMode: 'month',

    // Calendario principal
    currentDate: new Date(now.getFullYear(), now.getMonth(), 1),

    // Calendario modal
    modalDate: new Date(now.getFullYear(), now.getMonth(), 1),

    // Activities data
    activities: <?= $actividades_json ?>,

    // Today
    todayStr: td,
    today: now,

    // Modal nueva actividad
    modal:   false,
    step:    'calendar',
    selectedDate: null,

    // Modal detalle
    detailModal:    false,
    activeActivity: null,

    // Form
    form: {
      nombre: '', hora: '', lugar: '',
      distrito: '<?= $distritos[0] ?>',
      descripcion: '', estado: 'publicado'
    },

    // ── Computed: etiqueta del período ──────────────────────
    get periodLabel() {
      const y = this.currentDate.getFullYear();
      const m = this.currentDate.getMonth();
      if (this.viewMode === 'month') {
        return MESES[m] + ' ' + y;
      } else {
        const days = this.weekDays;
        if (!days.length) return '';
        const first = days[0];
        const last  = days[6];
        const fd = new Date(first.dateStr + 'T00:00:00');
        const ld = new Date(last.dateStr  + 'T00:00:00');
        if (fd.getMonth() === ld.getMonth()) {
          return fd.getDate() + ' – ' + ld.getDate() + ' ' + MESES[ld.getMonth()] + ' ' + ld.getFullYear();
        }
        return fd.getDate() + ' ' + MESES_CORTOS[fd.getMonth()] + ' – '
             + ld.getDate() + ' ' + MESES_CORTOS[ld.getMonth()] + ' ' + ld.getFullYear();
      }
    },

    get modalMonthLabel() {
      return MESES[this.modalDate.getMonth()] + ' ' + this.modalDate.getFullYear();
    },

    // ── Grid del calendario principal ───────────────────────
    get calendarDays() {
      return buildCalGrid(
        this.currentDate.getFullYear(),
        this.currentDate.getMonth(),
        this.todayStr,
        this.activities
      );
    },

    // ── Grid del modal ──────────────────────────────────────
    get modalCalendarDays() {
      return buildCalGrid(
        this.modalDate.getFullYear(),
        this.modalDate.getMonth(),
        this.todayStr,
        this.activities
      );
    },

    // ── Días de la semana ────────────────────────────────────
    get weekDays() {
      const base = new Date(this.currentDate);
      const dow  = base.getDay();
      const diff = dow === 0 ? -6 : 1 - dow;
      base.setDate(base.getDate() + diff);
      const days = [];
      for (let i = 0; i < 7; i++) {
        const d = new Date(base);
        d.setDate(d.getDate() + i);
        const ds = dateStr(d);
        const jsDay = d.getDay(); // 0=Sun
        const idx   = jsDay === 0 ? 6 : jsDay - 1;
        days.push({ day: d.getDate(), dateStr: ds, isToday: ds === this.todayStr, dayName: DIAS[idx] });
      }
      return days;
    },

    // ── Fecha seleccionada (formato display) ────────────────
    get selectedDateDisplay() {
      if (!this.selectedDate) return '';
      const d = new Date(this.selectedDate + 'T00:00:00');
      const dow = d.getDay();
      const idx = dow === 0 ? 6 : dow - 1;
      return DIAS_FULL[idx] + ', ' + d.getDate() + ' de ' + MESES[d.getMonth()] + ' ' + d.getFullYear();
    },

    get selectedDayNum() {
      if (!this.selectedDate) return '';
      return new Date(this.selectedDate + 'T00:00:00').getDate();
    },

    get selectedMonthShort() {
      if (!this.selectedDate) return '';
      return MESES_CORTOS[new Date(this.selectedDate + 'T00:00:00').getMonth()].toUpperCase();
    },

    // ── Navegación ───────────────────────────────────────────
    prevPeriod() {
      const d = new Date(this.currentDate);
      if (this.viewMode === 'month') {
        d.setMonth(d.getMonth() - 1);
      } else {
        d.setDate(d.getDate() - 7);
      }
      this.currentDate = d;
    },
    nextPeriod() {
      const d = new Date(this.currentDate);
      if (this.viewMode === 'month') {
        d.setMonth(d.getMonth() + 1);
      } else {
        d.setDate(d.getDate() + 7);
      }
      this.currentDate = d;
    },
    goToday() {
      this.currentDate = new Date(now.getFullYear(), now.getMonth(), 1);
    },
    prevModalMonth() {
      const d = new Date(this.modalDate);
      d.setMonth(d.getMonth() - 1);
      this.modalDate = d;
    },
    nextModalMonth() {
      const d = new Date(this.modalDate);
      d.setMonth(d.getMonth() + 1);
      this.modalDate = d;
    },

    // ── Eventos por fecha ────────────────────────────────────
    getEventsForDate(ds) {
      return this.activities.filter(a => a.fecha === ds);
    },

    // ── Color por distrito ───────────────────────────────────
    districtColor(distrito) {
      return COLORS[distrito] || '#6B7280';
    },

    // ── Modal ────────────────────────────────────────────────
    openModal() {
      this.step = 'calendar';
      this.selectedDate = null;
      this.resetForm();
      this.modal = true;
    },

    clickCalDay(ds) {
      this.pickDate(ds);
      this.modal = true;
    },

    pickDate(ds) {
      this.selectedDate = ds;
      this.step = 'form';
      this.resetForm();
    },

    closeModal() {
      this.modal = false;
      this.step  = 'calendar';
      this.selectedDate = null;
    },

    // ── Detalle de actividad ─────────────────────────────────
    openDetail(evt) {
      this.activeActivity = evt;
      this.detailModal    = true;
    },

    closeDetail() {
      this.detailModal    = false;
      this.activeActivity = null;
    },

    formatDetailDate(dateStr) {
      if (!dateStr) return '';
      const MESES_F = ['Enero','Febrero','Marzo','Abril','Mayo','Junio',
                       'Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
      const DIAS_F  = ['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'];
      const d = new Date(dateStr + 'T00:00:00');
      return DIAS_F[d.getDay()] + ', ' + d.getDate() + ' de ' + MESES_F[d.getMonth()] + ' ' + d.getFullYear();
    },

    resetForm() {
      this.form = {
        nombre: '', hora: '', lugar: '',
        distrito: '<?= $distritos[0] ?>',
        descripcion: '', estado: 'publicado'
      };
    },
  };
}
</script>

    </main>
  </div>
</body>
</html>
