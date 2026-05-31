<?php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_login();
if (is_candidato_distrital()) {
    header('Location: mi-distrito.php');
    exit;
}
$page_title = 'Dashboard';
include __DIR__ . '/layout.php';

// ══════════════════════════════════════════════════════════════════
// QUERIES
// ══════════════════════════════════════════════════════════════════
try {
    $total_simpatizantes  = (int)$pdo->query("SELECT COUNT(*) FROM simpatizantes")->fetchColumn();
    $total_militantes     = (int)$pdo->query("SELECT COUNT(*) FROM militantes WHERE estado='activo'")->fetchColumn();
    $total_noticias       = (int)$pdo->query("SELECT COUNT(*) FROM noticias WHERE estado='publicado'")->fetchColumn();
    $total_actividades    = (int)$pdo->query("SELECT COUNT(*) FROM actividades WHERE estado='publicado'")->fetchColumn();
    $total_candidatos     = (int)$pdo->query("SELECT COUNT(*) FROM candidatos_distritales WHERE activo=1")->fetchColumn();
    $total_contactos_new  = (int)$pdo->query("SELECT COUNT(*) FROM contactos WHERE leido=0")->fetchColumn();

    // Simpatizantes últimos 6 meses
    $simp_meses_raw = $pdo->query(
        "SELECT DATE_FORMAT(fecha_registro,'%Y-%m') as mes, COUNT(*) as total
         FROM simpatizantes
         WHERE fecha_registro >= DATE_SUB(CURDATE(), INTERVAL 6 MONTH)
         GROUP BY mes ORDER BY mes ASC"
    )->fetchAll(PDO::FETCH_KEY_PAIR);

    // Rellenar meses sin datos con 0
    $simp_labels = [];
    $simp_data   = [];
    $meses_es    = ['Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
    for ($i = 5; $i >= 0; $i--) {
        $key = date('Y-m', strtotime("-$i months"));
        $mes_num = (int)date('n', strtotime("-$i months"));
        $simp_labels[] = $meses_es[$mes_num - 1] . ' ' . date('Y', strtotime("-$i months"));
        $simp_data[]   = (int)($simp_meses_raw[$key] ?? 0);
    }

    // Simpatizantes por distrito (top 8)
    $dist_raw = $pdo->query(
        "SELECT distrito, COUNT(*) as total FROM simpatizantes
         GROUP BY distrito ORDER BY total DESC LIMIT 8"
    )->fetchAll(PDO::FETCH_ASSOC);
    $dist_labels = array_column($dist_raw, 'distrito');
    $dist_data   = array_map('intval', array_column($dist_raw, 'total'));

    // Noticias por categoría
    $noticia_cats = $pdo->query(
        "SELECT COALESCE(categoria,'Sin categoría') as cat, COUNT(*) as total
         FROM noticias WHERE estado='publicado' GROUP BY cat ORDER BY total DESC"
    )->fetchAll(PDO::FETCH_ASSOC);
    $ncat_labels = array_column($noticia_cats, 'cat');
    $ncat_data   = array_map('intval', array_column($noticia_cats, 'total'));

    // Actividades por estado
    $act_estados = $pdo->query(
        "SELECT estado, COUNT(*) as total FROM actividades GROUP BY estado"
    )->fetchAll(PDO::FETCH_ASSOC);
    $act_labels = array_column($act_estados, 'estado');
    $act_data   = array_map('intval', array_column($act_estados, 'total'));

    // Feed actividad reciente
    $activity_feed = $pdo->query(
        "SELECT usuario_nombre, accion, modulo, creado_en
         FROM activity_logs ORDER BY id DESC LIMIT 8"
    )->fetchAll(PDO::FETCH_ASSOC);

    // Últimos 5 simpatizantes
    $ultimos5 = $pdo->query(
        "SELECT nombre, dni, distrito, fecha_registro FROM simpatizantes ORDER BY id DESC LIMIT 5"
    )->fetchAll(PDO::FETCH_ASSOC);

} catch (Exception $e) {
    $total_simpatizantes = $total_militantes = $total_noticias = 0;
    $total_actividades   = $total_candidatos = $total_contactos_new = 0;
    $simp_labels = $simp_data = $dist_labels = $dist_data = [];
    $ncat_labels = $ncat_data = $act_labels = $act_data = [];
    $activity_feed = $ultimos5 = [];
}

// Icono por módulo para el feed
function dash_modulo_icon(string $m): string {
    return match($m) {
        'noticias'             => 'M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 12h6m-6-4h.01',
        'simpatizantes'        => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0',
        'militantes'           => 'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z',
        'configuracion_global' => 'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z',
        'candidatos'           => 'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z',
        'actividades'          => 'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z',
        'usuarios'             => 'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',
        default                => 'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2',
    };
}
function dash_time_ago(string $dt): string {
    $diff = time() - strtotime($dt);
    if ($diff < 60)     return 'hace ' . $diff . 's';
    if ($diff < 3600)   return 'hace ' . floor($diff/60) . 'min';
    if ($diff < 86400)  return 'hace ' . floor($diff/3600) . 'h';
    return 'hace ' . floor($diff/86400) . 'd';
}
?>

<style>
  .kpi-card { transition: transform .2s ease, box-shadow .2s ease; }
  .kpi-card:hover { transform: translateY(-3px); box-shadow: 0 12px 32px rgba(30,58,138,.10); }
  .chart-card { background:#fff; border-radius:1.25rem; border:1px solid #f1f5f9; padding:1.5rem; }
  .feed-dot { width:8px; height:8px; border-radius:50%; flex-shrink:0; margin-top:6px; }
</style>

<!-- ══ KPI CARDS ════════════════════════════════════════════════ -->
<div class="grid grid-cols-2 sm:grid-cols-3 xl:grid-cols-6 gap-4 mb-6">

  <?php
  $kpis = [
    ['val'=>$total_simpatizantes, 'label'=>'Simpatizantes',   'color'=>'from-[#1E3A8A] to-[#2563EB]', 'icon'=>'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0'],
    ['val'=>$total_militantes,    'label'=>'Militantes',       'color'=>'from-[#0F766E] to-[#14B8A6]', 'icon'=>'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z'],
    ['val'=>$total_noticias,      'label'=>'Noticias',         'color'=>'from-[#7C3AED] to-[#A78BFA]', 'icon'=>'M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 12h6'],
    ['val'=>$total_actividades,   'label'=>'Actividades',      'color'=>'from-[#D97706] to-[#FBBF24]', 'icon'=>'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z'],
    ['val'=>$total_candidatos,    'label'=>'Candidatos',       'color'=>'from-[#BE185D] to-[#F472B6]', 'icon'=>'M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z'],
    ['val'=>$total_contactos_new, 'label'=>'Mensajes nuevos',  'color'=>'from-[#0369A1] to-[#38BDF8]', 'icon'=>'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z'],
  ];
  foreach($kpis as $k): ?>
  <div class="kpi-card bg-gradient-to-br <?= $k['color'] ?> rounded-2xl p-5 text-white shadow-sm">
    <div class="flex items-center justify-between mb-3">
      <svg class="w-6 h-6 opacity-80" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="<?= $k['icon'] ?>"/>
      </svg>
      <span class="text-white/40 text-xs font-semibold uppercase tracking-wider">HOY</span>
    </div>
    <p class="text-3xl font-black counter" data-target="<?= $k['val'] ?>">0</p>
    <p class="text-white/70 text-xs mt-1 font-medium"><?= $k['label'] ?></p>
  </div>
  <?php endforeach; ?>
</div>

<!-- ══ FILA 2: GRÁFICAS PRINCIPALES ════════════════════════════ -->
<div class="grid grid-cols-1 xl:grid-cols-5 gap-5 mb-5">

  <!-- Área: Simpatizantes por mes -->
  <div class="chart-card xl:col-span-3">
    <div class="flex items-center justify-between mb-1">
      <div>
        <h3 class="font-black text-[#1E3A8A] text-sm">Nuevos Simpatizantes</h3>
        <p class="text-xs text-gray-400 mt-0.5">Últimos 6 meses</p>
      </div>
      <span class="text-2xl font-black text-[#1E3A8A]"><?= $total_simpatizantes ?></span>
    </div>
    <div id="chart-simp-mes"></div>
  </div>

  <!-- Barras: por Distrito -->
  <div class="chart-card xl:col-span-2">
    <div class="mb-1">
      <h3 class="font-black text-[#1E3A8A] text-sm">Por Distrito</h3>
      <p class="text-xs text-gray-400 mt-0.5">Ranking de simpatizantes</p>
    </div>
    <div id="chart-distritos"></div>
  </div>

</div>

<!-- ══ FILA 3: GRÁFICAS PEQUEÑAS + FEED ════════════════════════ -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-5 mb-5">

  <!-- Donut: Noticias por categoría -->
  <div class="chart-card">
    <h3 class="font-black text-[#1E3A8A] text-sm mb-1">Noticias por Categoría</h3>
    <p class="text-xs text-gray-400 mb-1">Solo publicadas</p>
    <div id="chart-noticias-cat"></div>
  </div>

  <!-- Donut: Actividades -->
  <div class="chart-card">
    <h3 class="font-black text-[#1E3A8A] text-sm mb-1">Actividades</h3>
    <p class="text-xs text-gray-400 mb-1">Por estado</p>
    <div id="chart-actividades"></div>
  </div>

  <!-- Feed actividad -->
  <div class="chart-card flex flex-col">
    <h3 class="font-black text-[#1E3A8A] text-sm mb-3">Actividad Reciente</h3>
    <?php if (empty($activity_feed)): ?>
    <p class="text-gray-400 text-sm text-center py-8">Sin actividad registrada.</p>
    <?php else: ?>
    <ul class="space-y-3 flex-1 overflow-hidden">
      <?php foreach ($activity_feed as $log): ?>
      <li class="flex items-start gap-3">
        <div class="feed-dot bg-[#1E3A8A] mt-1.5"></div>
        <div class="flex-1 min-w-0">
          <p class="text-xs font-semibold text-gray-800 truncate"><?= htmlspecialchars($log['accion']) ?></p>
          <p class="text-[10px] text-gray-400 mt-0.5">
            <span class="font-medium text-gray-500"><?= htmlspecialchars($log['usuario_nombre']) ?></span>
            · <?= dash_time_ago($log['creado_en']) ?>
          </p>
        </div>
      </li>
      <?php endforeach; ?>
    </ul>
    <?php endif; ?>
  </div>

</div>

<!-- ══ FILA 4: TABLA ÚLTIMOS SIMPATIZANTES ═════════════════════ -->
<div class="chart-card">
  <div class="flex items-center justify-between mb-4">
    <div>
      <h3 class="font-black text-[#1E3A8A] text-sm">Últimos Registros</h3>
      <p class="text-xs text-gray-400 mt-0.5">Simpatizantes más recientes</p>
    </div>
    <a href="simpatizantes.php"
       class="text-xs font-bold text-[#1E3A8A] bg-blue-50 hover:bg-blue-100 px-3 py-1.5 rounded-full transition-colors">
      Ver todos →
    </a>
  </div>
  <?php if (empty($ultimos5)): ?>
  <p class="text-center text-gray-400 py-8 text-sm">Sin registros aún.</p>
  <?php else: ?>
  <div class="overflow-x-auto -mx-6 px-6">
    <table class="w-full text-sm">
      <thead>
        <tr class="text-[10px] font-black text-gray-400 uppercase tracking-widest border-b border-gray-100">
          <th class="pb-2 text-left">Nombre</th>
          <th class="pb-2 text-left">DNI</th>
          <th class="pb-2 text-left">Distrito</th>
          <th class="pb-2 text-right">Fecha</th>
        </tr>
      </thead>
      <tbody>
        <?php foreach ($ultimos5 as $s): ?>
        <tr class="border-b border-gray-50 hover:bg-gray-50/60 transition-colors">
          <td class="py-2.5 font-semibold text-gray-800"><?= htmlspecialchars($s['nombre']) ?></td>
          <td class="py-2.5 text-gray-400 font-mono text-xs"><?= htmlspecialchars($s['dni']) ?></td>
          <td class="py-2.5">
            <span class="bg-blue-50 text-blue-700 text-[11px] font-semibold px-2 py-0.5 rounded-full">
              <?= htmlspecialchars($s['distrito']) ?>
            </span>
          </td>
          <td class="py-2.5 text-gray-400 text-xs text-right"><?= date('d/m/Y', strtotime($s['fecha_registro'])) ?></td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- ══ APEXCHARTS ════════════════════════════════════════════════ -->
<script src="https://cdn.jsdelivr.net/npm/apexcharts@3.49.0/dist/apexcharts.min.js"></script>
<script>
(function () {
  // ── Datos PHP → JS ──────────────────────────────────────────
  const simpLabels = <?= json_encode($simp_labels) ?>;
  const simpData   = <?= json_encode($simp_data) ?>;
  const distLabels = <?= json_encode(array_reverse($dist_labels)) ?>;
  const distData   = <?= json_encode(array_reverse($dist_data)) ?>;
  const ncatLabels = <?= json_encode($ncat_labels) ?>;
  const ncatData   = <?= json_encode($ncat_data) ?>;
  const actLabels  = <?= json_encode($act_labels) ?>;
  const actData    = <?= json_encode($act_data) ?>;

  const colorPrimary   = '#1E3A8A';
  const colorSecondary = '#38BDF8';
  const colorAccent    = '#FACC15';
  const palette        = ['#1E3A8A','#38BDF8','#FACC15','#34D399','#F472B6','#A78BFA','#FB923C','#60A5FA'];

  // ── Contadores animados ────────────────────────────────────
  document.querySelectorAll('.counter').forEach(el => {
    const target = parseInt(el.dataset.target, 10);
    if (!target) { el.textContent = '0'; return; }
    const duration = 900;
    const step     = Math.ceil(duration / target);
    let current    = 0;
    const timer = setInterval(() => {
      current += Math.max(1, Math.ceil(target / 40));
      if (current >= target) { el.textContent = target.toLocaleString(); clearInterval(timer); }
      else { el.textContent = current.toLocaleString(); }
    }, step);
  });

  // ── Chart 1: Simpatizantes por mes (área) ─────────────────
  new ApexCharts(document.getElementById('chart-simp-mes'), {
    chart: { type: 'area', height: 220, toolbar: { show: false }, sparkline: { enabled: false }, animations: { easing: 'easeinout', speed: 700 } },
    series: [{ name: 'Simpatizantes', data: simpData }],
    xaxis: { categories: simpLabels, labels: { style: { fontSize: '11px', colors: '#9CA3AF' } }, axisBorder: { show: false }, axisTicks: { show: false } },
    yaxis: { labels: { style: { fontSize: '11px', colors: '#9CA3AF' } }, min: 0 },
    fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.45, opacityTo: 0.02, stops: [0, 100] } },
    colors: [colorPrimary],
    stroke: { curve: 'smooth', width: 2.5 },
    dataLabels: { enabled: false },
    grid: { borderColor: '#F1F5F9', strokeDashArray: 4 },
    tooltip: { theme: 'light', style: { fontSize: '12px' } },
    markers: { size: 4, colors: ['#fff'], strokeColors: colorPrimary, strokeWidth: 2 }
  }).render();

  // ── Chart 2: Distritos (barras horizontales) ───────────────
  new ApexCharts(document.getElementById('chart-distritos'), {
    chart: { type: 'bar', height: 220, toolbar: { show: false }, animations: { easing: 'easeinout', speed: 700 } },
    plotOptions: { bar: { horizontal: true, borderRadius: 6, barHeight: '55%' } },
    series: [{ name: 'Simpatizantes', data: distData }],
    xaxis: { categories: distLabels, labels: { style: { fontSize: '11px', colors: '#9CA3AF' } } },
    yaxis: { labels: { style: { fontSize: '11px', colors: '#374151' }, fontWeight: 600 } },
    colors: [colorPrimary],
    dataLabels: { enabled: true, style: { fontSize: '11px', colors: ['#fff'] } },
    grid: { borderColor: '#F1F5F9', strokeDashArray: 4, xaxis: { lines: { show: true } }, yaxis: { lines: { show: false } } },
    tooltip: { theme: 'light' }
  }).render();

  // ── Chart 3: Noticias por categoría (donut) ────────────────
  new ApexCharts(document.getElementById('chart-noticias-cat'), {
    chart: { type: 'donut', height: 220, animations: { easing: 'easeinout', speed: 700 } },
    series: ncatData.length ? ncatData : [1],
    labels: ncatData.length ? ncatLabels : ['Sin datos'],
    colors: palette,
    plotOptions: { pie: { donut: { size: '62%', labels: { show: true, total: { show: true, label: 'Total', fontSize: '12px', color: '#374151', fontWeight: 700 } } } } },
    dataLabels: { enabled: false },
    legend: { position: 'bottom', fontSize: '11px', fontWeight: 600 },
    tooltip: { theme: 'light' }
  }).render();

  // ── Chart 4: Actividades por estado (donut) ────────────────
  const actColors = { publicado: '#1E3A8A', borrador: '#9CA3AF', cancelado: '#F87171', pendiente: '#FBBF24' };
  new ApexCharts(document.getElementById('chart-actividades'), {
    chart: { type: 'donut', height: 220, animations: { easing: 'easeinout', speed: 700 } },
    series: actData.length ? actData : [1],
    labels: actData.length ? actLabels.map(l => l.charAt(0).toUpperCase() + l.slice(1)) : ['Sin datos'],
    colors: actData.length ? actLabels.map(l => actColors[l] ?? '#60A5FA') : ['#E5E7EB'],
    plotOptions: { pie: { donut: { size: '62%', labels: { show: true, total: { show: true, label: 'Total', fontSize: '12px', color: '#374151', fontWeight: 700 } } } } },
    dataLabels: { enabled: false },
    legend: { position: 'bottom', fontSize: '11px', fontWeight: 600 },
    tooltip: { theme: 'light' }
  }).render();

})();
</script>

    </main>
  </div>
</body>
</html>
