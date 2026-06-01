<?php
// ============================================================
// config-pagina.php — Configuración global del sitio
// Tabs: Favicon | Nombre del Partido | Colores | Contador
// ============================================================
if (ob_get_level() === 0) ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();

require_once __DIR__ . '/../includes/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/../includes/helpers/config.php';

require_login();
require_rol('admin');
require_modulo('configuracion_global', $pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
}

$config = [];
try {
    $config = $pdo->query("SELECT clave, valor FROM configuracion")->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {}

// Estadísticas de visitas para tab Contador
$visit_stats = ['total' => 0, 'hoy' => 0, 'semana' => 0, 'mes' => 0];
try {
    $pdo->exec("CREATE TABLE IF NOT EXISTS visitas (
        id         INT AUTO_INCREMENT PRIMARY KEY,
        ip_hash    VARCHAR(64) NOT NULL,
        fecha      DATE NOT NULL,
        created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
        INDEX idx_ip_fecha (ip_hash, fecha)
    ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
    $row = $pdo->query("SELECT
        COUNT(*) AS total,
        SUM(fecha = CURDATE()) AS hoy,
        SUM(fecha >= DATE_SUB(CURDATE(), INTERVAL 7 DAY)) AS semana,
        SUM(fecha >= DATE_FORMAT(CURDATE(),'%Y-%m-01')) AS mes
    FROM visitas")->fetch(PDO::FETCH_ASSOC);
    $visit_stats = [
        'total'  => (int)($row['total']  ?? 0),
        'hoy'    => (int)($row['hoy']    ?? 0),
        'semana' => (int)($row['semana'] ?? 0),
        'mes'    => (int)($row['mes']    ?? 0),
    ];
} catch (Exception $e) {}

// ── Helper local ─────────────────────────────────────────────
function cpag_val(array $config, string $key, string $default = ''): string {
    return htmlspecialchars(cfg_value($config, $key, $default), ENT_QUOTES);
}

// ── POST handlers ─────────────────────────────────────────────
$flash      = '';
$flash_type = 'ok';
$active_tab = $_GET['tab'] ?? 'favicon';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $active_tab = $_POST['tab'] ?? 'favicon';

    // ── Tab: Favicon ──────────────────────────────────────────
    if ($active_tab === 'favicon') {
        $favicon_url = trim($_POST['site_favicon'] ?? '');
        try {
            cfg_save_values($pdo, ['site_favicon' => $favicon_url]);
            $config['site_favicon'] = $favicon_url;
            log_activity($pdo, 'Actualizo favicon del sitio', 'configuracion_global');
            $flash = 'Favicon actualizado correctamente.';
        } catch (Exception $e) {
            $flash      = 'Error al guardar: ' . $e->getMessage();
            $flash_type = 'error';
        }
    }

    // ── Tab: Nombre del Partido ───────────────────────────────
    if ($active_tab === 'partido') {
        $nombre = trim($_POST['partido_nombre'] ?? 'ALIANZA PARA EL PROGRESO');
        try {
            cfg_save_values($pdo, ['partido_nombre' => $nombre]);
            $config['partido_nombre'] = $nombre;
            log_activity($pdo, 'Actualizo nombre del partido/agrupacion', 'configuracion_global');
            $flash = 'Nombre del partido actualizado correctamente.';
        } catch (Exception $e) {
            $flash      = 'Error al guardar: ' . $e->getMessage();
            $flash_type = 'error';
        }
    }

    // ── Tab: Colores ──────────────────────────────────────────
    if ($active_tab === 'colores') {
        $color_keys = [
            'color_primary', 'color_accent',
            'color_btn_hero_primary', 'color_btn_hero_primary_text',
            'color_btn_hero_secondary', 'color_btn_hero_secondary_text',
            'color_btn_download', 'color_btn_download_text',
            'color_btn_cta_navbar', 'color_btn_cta_navbar_text',
            'color_btn_join', 'color_btn_join_text',
        ];
        $values = [];
        foreach ($color_keys as $key) {
            $val = trim($_POST[$key] ?? '');
            if (preg_match('/^#[0-9A-Fa-f]{3,8}$/', $val)) {
                $values[$key] = $val;
            }
        }
        try {
            cfg_save_values($pdo, $values);
            $config = array_merge($config, $values);
            log_activity($pdo, 'Actualizo paleta de colores del sitio', 'configuracion_colores');
            $flash = 'Colores actualizados correctamente.';
        } catch (Exception $e) {
            $flash      = 'Error al guardar: ' . $e->getMessage();
            $flash_type = 'error';
        }
    }

    // ── Tab: Contador ─────────────────────────────────────────
    if ($active_tab === 'contador') {
        if (!empty($_POST['reset_visits'])) {
            try {
                $pdo->exec("DELETE FROM visitas");
                log_activity($pdo, 'Reinicio el contador de visitas', 'contador_visitas');
                $flash = 'Contador de visitas reiniciado a cero.';
                $visit_stats = ['total' => 0, 'hoy' => 0, 'semana' => 0, 'mes' => 0];
            } catch (Exception $e) {
                $flash      = 'Error al reiniciar: ' . $e->getMessage();
                $flash_type = 'error';
            }
        } else {
            $cd_active    = isset($_POST['countdown_active'])     ? '1' : '0';
            $visit_active = isset($_POST['visit_counter_active']) ? '1' : '0';
            $cd_date      = trim($_POST['countdown_date']  ?? '2026-10-04');
            $cd_title     = trim($_POST['countdown_title'] ?? 'Faltan para las Elecciones');
            $cd_label     = trim($_POST['countdown_label'] ?? 'Elecciones Municipales 2026');
            if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $cd_date)) $cd_date = '2026-10-04';
            try {
                cfg_save_values($pdo, [
                    'countdown_active'     => $cd_active,
                    'countdown_date'       => $cd_date,
                    'countdown_title'      => $cd_title,
                    'countdown_label'      => $cd_label,
                    'visit_counter_active' => $visit_active,
                ]);
                $config = array_merge($config, [
                    'countdown_active'     => $cd_active,
                    'countdown_date'       => $cd_date,
                    'countdown_title'      => $cd_title,
                    'countdown_label'      => $cd_label,
                    'visit_counter_active' => $visit_active,
                ]);
                log_activity($pdo, 'Actualizo configuracion del contador', 'configuracion_contador');
                $flash = 'Configuracion guardada correctamente.';
            } catch (Exception $e) {
                $flash      = 'Error al guardar: ' . $e->getMessage();
                $flash_type = 'error';
            }
        }
    }

    // ── Tab: Login ────────────────────────────────────────────
    if ($active_tab === 'login') {
        $login_logo     = trim($_POST['login_logo']     ?? '/assets/img/logos/logorp.webp');
        $login_subtitle = trim($_POST['login_subtitle'] ?? 'Portal Ivan Cisneros');
        $login_hero_img = trim($_POST['login_hero_img'] ?? '/assets/img/candidato/ivancisneros-login.webp');
        try {
            cfg_save_values($pdo, [
                'login_logo'     => $login_logo,
                'login_subtitle' => $login_subtitle,
                'login_hero_img' => $login_hero_img,
            ]);
            $config['login_logo']     = $login_logo;
            $config['login_subtitle'] = $login_subtitle;
            $config['login_hero_img'] = $login_hero_img;
            log_activity($pdo, 'Actualizo configuracion de la pantalla de login', 'configuracion_global');
            $flash = 'Configuracion del login actualizada correctamente.';
        } catch (Exception $e) {
            $flash      = 'Error al guardar: ' . $e->getMessage();
            $flash_type = 'error';
        }
    }

    if ($flash && $flash_type === 'ok') {
        header('Location: config-pagina.php?tab=' . urlencode($active_tab) . '&ok=1');
        exit;
    }
}

if (isset($_GET['ok'])) $flash = 'Cambios guardados correctamente.';
$active_tab = $_GET['tab'] ?? $active_tab;

$page_title = 'Configurar Página';
require __DIR__ . '/layout.php';
?>

<style>
  .cpag-tab {
    padding: 0.5rem 1.1rem;
    font-size: 0.8rem;
    font-weight: 700;
    border-radius: 0.6rem;
    border: 2px solid transparent;
    color: #64748b;
    background: transparent;
    cursor: pointer;
    transition: all 0.15s;
    white-space: nowrap;
  }
  .cpag-tab:hover { background: #e2e8f0; color: #1e293b; }
  .cpag-tab.is-active {
    background: #1E3A8A;
    color: #fff;
    border-color: #1E3A8A;
  }
</style>

<div class="max-w-4xl mx-auto space-y-6"
     x-data="{ activeTab: '<?= htmlspecialchars($active_tab, ENT_QUOTES) ?>' }">

  <!-- Cabecera -->
  <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-3">
    <div>
      <h1 class="text-xl font-black text-gray-900">Configurar Página</h1>
      <p class="text-sm text-gray-400 mt-0.5">Favicon, identidad del partido, colores y contador del sitio.</p>
    </div>
    <a href="<?= BASE_URL ?>/index.php" target="_blank"
       class="inline-flex items-center gap-2 text-xs font-bold text-gray-500 hover:text-[#1E3A8A] border border-gray-200 rounded-xl px-4 py-2 bg-white transition-colors">
      <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
      </svg>
      Ver sitio
    </a>
  </div>

  <?php if ($flash): ?>
  <div class="px-4 py-3 rounded-xl text-sm font-semibold <?= $flash_type === 'error' ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-green-50 text-green-700 border border-green-200' ?>">
    <?= htmlspecialchars($flash) ?>
  </div>
  <?php endif; ?>

  <!-- Tabs -->
  <div class="bg-white rounded-2xl border border-gray-100 shadow-sm p-1.5 flex flex-wrap gap-1">
    <?php foreach ([
      'favicon'  => 'Favicon',
      'login'    => 'Login',
      'partido'  => 'Nombre del Partido',
      'colores'  => 'Colores',
      'contador' => 'Contador',
    ] as $tid => $tlabel): ?>
    <button type="button"
            @click="activeTab = '<?= $tid ?>'"
            :class="activeTab === '<?= $tid ?>' ? 'is-active' : ''"
            class="cpag-tab">
      <?= $tlabel ?>
    </button>
    <?php endforeach; ?>
  </div>

  <!-- ══════════════════════════════════════════════════════════ -->
  <!-- TAB: FAVICON                                              -->
  <!-- ══════════════════════════════════════════════════════════ -->
  <form method="POST" x-show="activeTab === 'favicon'" class="space-y-6">
    <input type="hidden" name="tab" value="favicon">
    <?= csrf_field() ?>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
      <div class="bg-gradient-to-r from-[#1E3A8A] to-[#0056D6] px-6 py-4">
        <h2 class="text-white font-black text-base">Favicon del sitio</h2>
        <p class="text-blue-200 text-xs mt-0.5">
          El favicon es el ícono pequeño que aparece en la pestaña del navegador, en el admin y en el sitio público.
        </p>
      </div>
      <div class="p-6 space-y-6">

        <!-- Campo URL + media picker -->
        <div x-data="{ url: '<?= cpag_val($config, 'site_favicon') ?>' }">
          <label class="block text-xs font-black text-gray-500 uppercase mb-1">URL del favicon</label>
          <p class="text-xs text-gray-400 mb-3">
            Sube una imagen cuadrada (ICO, PNG o WEBP, mínimo 32×32 px). Se recomienda 64×64 o 192×192 px.
          </p>
          <div class="flex gap-2 items-center">
            <input name="site_favicon"
                   x-model="url"
                   placeholder="/assets/img/logos/logorp.webp"
                   class="min-w-0 flex-1 border border-gray-200 rounded-xl px-3 py-2.5 text-sm font-mono focus:outline-none focus:ring-2 focus:ring-blue-300">
            <button type="button"
                    @click="openMediaPicker((picked) => { url = picked }, 'image')"
                    class="px-4 py-2.5 rounded-xl bg-indigo-50 text-indigo-600 text-xs font-bold border border-indigo-100 hover:bg-indigo-100 transition-colors flex-shrink-0">
              Media
            </button>
          </div>

          <!-- Preview en vivo -->
          <div class="mt-4 flex items-center gap-4 p-4 bg-gray-50 rounded-xl border border-gray-100">
            <template x-if="url && url !== ''">
              <img :src="url.startsWith('/') ? '<?= BASE_URL ?>' + url : url"
                   class="w-10 h-10 rounded-lg object-contain border border-gray-200 bg-white shadow-sm"
                   alt="Preview favicon">
            </template>
            <template x-if="!url || url === ''">
              <div class="w-10 h-10 rounded-lg bg-gray-200 border border-gray-200 flex items-center justify-center">
                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
              </div>
            </template>
            <div>
              <p class="text-sm font-black text-gray-700">Vista previa del favicon</p>
              <p class="text-xs text-gray-400 mt-0.5">Así se verá en la pestaña del navegador.</p>
            </div>
          </div>
        </div>

        <!-- Info de uso -->
        <div class="grid sm:grid-cols-2 gap-3">
          <div class="flex items-start gap-3 p-4 rounded-xl bg-blue-50 border border-blue-100">
            <svg class="w-5 h-5 text-blue-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
            <div>
              <p class="text-xs font-black text-blue-700">Admin panel</p>
              <p class="text-xs text-blue-500 mt-0.5">Se aplica en todas las páginas del panel de administración.</p>
            </div>
          </div>
          <div class="flex items-start gap-3 p-4 rounded-xl bg-green-50 border border-green-100">
            <svg class="w-5 h-5 text-green-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M21 12a9 9 0 01-9 9m9-9a9 9 0 00-9-9m9 9H3m9 9a9 9 0 01-9-9m9 9c1.657 0 3-4.03 3-9s-1.343-9-3-9m0 18c-1.657 0-3-4.03-3-9s1.343-9 3-9"/>
            </svg>
            <div>
              <p class="text-xs font-black text-green-700">Sitio público</p>
              <p class="text-xs text-green-500 mt-0.5">Se aplica en el landing page y todas las subpáginas públicas.</p>
            </div>
          </div>
        </div>

      </div>
    </div>

    <div class="sticky bottom-0 bg-[#F1F5F9]/95 backdrop-blur py-4 flex justify-end gap-3">
      <a href="<?= BASE_URL ?>/index.php" target="_blank"
         class="bg-white border border-gray-200 text-gray-600 font-bold px-5 py-3 rounded-xl text-sm">Ver sitio</a>
      <button type="submit"
              class="bg-[#FACC15] hover:bg-yellow-400 text-[#1E3A8A] font-black px-8 py-3 rounded-xl text-sm shadow">
        Guardar Favicon
      </button>
    </div>
  </form>

  <!-- ══════════════════════════════════════════════════════════ -->
  <!-- TAB: LOGIN                                                -->
  <!-- ══════════════════════════════════════════════════════════ -->
  <form method="POST" x-show="activeTab === 'login'" class="space-y-6">
    <input type="hidden" name="tab" value="login">
    <?= csrf_field() ?>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
      <div class="bg-gradient-to-r from-[#1E3A8A] to-[#0056D6] px-6 py-4">
        <h2 class="text-white font-black text-base">Pantalla de inicio de sesión</h2>
        <p class="text-blue-200 text-xs mt-0.5">
          Personaliza el logo y el subtítulo que aparecen en la página de login del panel admin.
        </p>
      </div>
      <div class="p-6 space-y-6">

        <!-- Logo del login -->
        <div x-data="{ url: '<?= cpag_val($config, 'login_logo', '/assets/img/logos/logorp.webp') ?>' }">
          <label class="block text-xs font-black text-gray-500 uppercase mb-1">Logo del panel de acceso (Login)</label>
          <p class="text-xs text-gray-400 mb-3">Logo que aparece en la página de inicio de sesión del panel.</p>
          <div class="flex gap-2">
            <input name="login_logo"
                   x-model="url"
                   class="min-w-0 flex-1 border border-gray-200 rounded-xl px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-blue-300">
            <button type="button"
                    @click="openMediaPicker((picked) => { url = picked }, 'image')"
                    class="px-4 py-2 rounded-xl bg-indigo-50 text-indigo-600 text-xs font-bold border border-indigo-100 hover:bg-indigo-100 transition-colors flex-shrink-0">
              Media
            </button>
          </div>
          <!-- Preview -->
          <div class="mt-3 flex items-center gap-4 p-4 bg-gray-50 rounded-xl border border-gray-100">
            <template x-if="url && url !== ''">
              <img :src="url.match(/^https?:\/\//) ? url : '<?= BASE_URL ?>/' + url.replace(/^\/+/,'')"
                   class="h-16 object-contain rounded-lg border border-gray-200 bg-white p-1 shadow-sm"
                   alt="Preview logo login">
            </template>
            <template x-if="!url || url === ''">
              <div class="h-16 w-16 rounded-lg bg-gray-200 border border-gray-200 flex items-center justify-center">
                <svg class="w-6 h-6 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
                </svg>
              </div>
            </template>
            <div>
              <p class="text-sm font-black text-gray-700">Vista previa del logo</p>
              <p class="text-xs text-gray-400 mt-0.5">Así se verá en la pantalla de login del admin.</p>
            </div>
          </div>
        </div>

        <!-- Foto lateral (desktop) -->
        <div x-data="{ url: '<?= cpag_val($config, 'login_hero_img', '/assets/img/candidato/ivancisneros-login.webp') ?>' }">
          <label class="block text-xs font-black text-gray-500 uppercase mb-1">Foto lateral (desktop)</label>
          <p class="text-xs text-gray-400 mb-3">Imagen que aparece en la columna izquierda del login en pantallas grandes.</p>
          <div class="flex gap-2">
            <input name="login_hero_img"
                   x-model="url"
                   class="min-w-0 flex-1 border border-gray-200 rounded-xl px-3 py-2 text-xs font-mono focus:outline-none focus:ring-2 focus:ring-blue-300">
            <button type="button"
                    @click="openMediaPicker((picked) => { url = picked }, 'image')"
                    class="px-4 py-2 rounded-xl bg-indigo-50 text-indigo-600 text-xs font-bold border border-indigo-100 hover:bg-indigo-100 transition-colors flex-shrink-0">
              Media
            </button>
          </div>
          <div class="mt-3">
            <img :src="url.match(/^https?:\/\//) ? url : '<?= BASE_URL ?>/' + url.replace(/^\/+/,'')"
                 class="h-32 w-full object-cover object-top rounded-xl border border-gray-200"
                 alt="Preview foto lateral" onerror="this.style.opacity='0.2'">
          </div>
        </div>

        <!-- Subtítulo del login -->
        <div>
          <label class="block text-xs font-black text-gray-500 uppercase mb-1">Subtítulo del login</label>
          <input name="login_subtitle"
                 value="<?= cpag_val($config, 'login_subtitle', 'Portal Ivan Cisneros') ?>"
                 maxlength="120"
                 placeholder="Portal Ivan Cisneros"
                 class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:outline-none focus:ring-2 focus:ring-blue-300">
          <p class="text-xs text-gray-400 mt-1">Texto que aparece debajo del logo en la pantalla de login.</p>
        </div>

        <!-- Preview de la pantalla de login -->
        <div class="rounded-2xl border border-gray-100 overflow-hidden">
          <p class="text-xs font-black text-gray-400 uppercase tracking-widest px-4 py-2 bg-gray-50 border-b border-gray-100">
            Preview pantalla login
          </p>
          <div class="bg-[#0F2057] p-8 flex flex-col items-center gap-4">
            <div x-data="{ url: '<?= cpag_val($config, 'login_logo', '/assets/img/logos/logorp.webp') ?>' }"
                 x-init="$watch('$el.closest(\'form\').querySelector(\'input[name=login_logo]\').value', v => url = v)">
              <img :src="url.match(/^https?:\/\//) ? url : '<?= BASE_URL ?>/' + url.replace(/^\/+/,'')"
                   class="h-16 object-contain" alt="Logo login preview"
                   onerror="this.style.opacity='0.3'">
            </div>
            <div class="text-center">
              <p class="text-white font-black text-sm">Panel Admin</p>
              <p class="text-blue-300 text-xs mt-0.5"
                 x-text="$el.closest('form').querySelector('input[name=login_subtitle]')?.value || 'Portal Ivan Cisneros'">
                <?= cpag_val($config, 'login_subtitle', 'Portal Ivan Cisneros') ?>
              </p>
            </div>
            <div class="w-full max-w-xs bg-white/10 rounded-xl p-4 space-y-2">
              <div class="h-8 bg-white/20 rounded-lg"></div>
              <div class="h-8 bg-white/20 rounded-lg"></div>
              <div class="h-9 bg-[#FACC15] rounded-lg"></div>
            </div>
          </div>
        </div>

      </div>
    </div>

    <div class="sticky bottom-0 bg-[#F1F5F9]/95 backdrop-blur py-4 flex justify-end gap-3">
      <a href="<?= BASE_URL ?>/admin/login.php" target="_blank"
         class="bg-white border border-gray-200 text-gray-600 font-bold px-5 py-3 rounded-xl text-sm">Ver login</a>
      <button type="submit"
              class="bg-[#FACC15] hover:bg-yellow-400 text-[#1E3A8A] font-black px-8 py-3 rounded-xl text-sm shadow">
        Guardar Login
      </button>
    </div>
  </form>

  <!-- ══════════════════════════════════════════════════════════ -->
  <!-- TAB: NOMBRE DEL PARTIDO                                   -->
  <!-- ══════════════════════════════════════════════════════════ -->
  <form method="POST" x-show="activeTab === 'partido'" class="space-y-6">
    <input type="hidden" name="tab" value="partido">
    <?= csrf_field() ?>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
      <div class="bg-gradient-to-r from-[#1E3A8A] to-[#0056D6] px-6 py-4">
        <h2 class="text-white font-black text-base">Nombre del Partido / Agrupación Política</h2>
        <p class="text-blue-200 text-xs mt-0.5">
          Este valor se aplica globalmente en PDFs, candidatos distritales, footer, hero y subpáginas.
        </p>
      </div>
      <div class="p-6 space-y-6">

        <div class="bg-yellow-50 border border-yellow-200 rounded-xl p-5">
          <label class="block text-xs font-black text-yellow-700 uppercase mb-1">
            Nombre del Partido / Agrupación Política
          </label>
          <p class="text-xs text-yellow-600 mb-3">
            Escríbelo en mayúsculas tal como debe aparecer en todos los documentos.
          </p>
          <input name="partido_nombre"
                 value="<?= cpag_val($config, 'partido_nombre', 'ALIANZA PARA EL PROGRESO') ?>"
                 maxlength="120"
                 class="w-full border border-yellow-300 rounded-xl px-3 py-2.5 text-sm font-bold bg-white focus:outline-none focus:ring-2 focus:ring-yellow-400"
                 placeholder="ALIANZA PARA EL PROGRESO">
        </div>

        <!-- Donde se usa -->
        <div>
          <p class="text-xs font-black text-gray-500 uppercase tracking-widest mb-3">Dónde se aplica este valor</p>
          <div class="grid sm:grid-cols-2 gap-3">
            <?php foreach ([
              ['PDFs de militantes y simpatizantes',   'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z'],
              ['Exportación PDF de personeros',         'M17 17h2a2 2 0 002-2v-4a2 2 0 00-2-2H5a2 2 0 00-2 2v4a2 2 0 002 2h2m2 4h6a2 2 0 002-2v-4a2 2 0 00-2-2H9a2 2 0 00-2 2v4a2 2 0 002 2zm8-12V5a2 2 0 00-2-2H9a2 2 0 00-2 2v4h10z'],
              ['Footer del sitio público',              'M4 6h16M4 10h16M4 14h16M4 18h7'],
              ['Hero y secciones del landing page',     'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6'],
              ['Páginas de candidatos distritales',     'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M15 7a3 3 0 11-6 0 3 3 0 016 0z'],
              ['Badge del hero principal',              'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z'],
            ] as [$lbl, $icon]): ?>
            <div class="flex items-center gap-3 p-3 rounded-xl bg-gray-50 border border-gray-100">
              <svg class="w-4 h-4 text-[#1E3A8A] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= $icon ?>"/>
              </svg>
              <span class="text-xs font-semibold text-gray-600"><?= $lbl ?></span>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

      </div>
    </div>

    <div class="sticky bottom-0 bg-[#F1F5F9]/95 backdrop-blur py-4 flex justify-end gap-3">
      <a href="<?= BASE_URL ?>/index.php" target="_blank"
         class="bg-white border border-gray-200 text-gray-600 font-bold px-5 py-3 rounded-xl text-sm">Ver sitio</a>
      <button type="submit"
              class="bg-[#FACC15] hover:bg-yellow-400 text-[#1E3A8A] font-black px-8 py-3 rounded-xl text-sm shadow">
        Guardar Nombre
      </button>
    </div>
  </form>

  <!-- ══════════════════════════════════════════════════════════ -->
  <!-- TAB: COLORES                                              -->
  <!-- ══════════════════════════════════════════════════════════ -->
  <form method="POST" x-show="activeTab === 'colores'" class="space-y-6">
    <input type="hidden" name="tab" value="colores">
    <?= csrf_field() ?>

    <?php
    $color_groups = [
        ['label' => 'Colores globales', 'items' => [
            'color_primary' => ['Azul principal (bg)',  'Navbar, fondos de secciones, textos destacados.', '#1E3A8A'],
            'color_accent'  => ['Amarillo acento (bg)', 'Detalles, etiquetas, bordes y highlights.',       '#FACC15'],
        ]],
        ['label' => 'Botón hero principal — "Conoce el Plan"', 'preview' => 'prev-hero-primary', 'items' => [
            'color_btn_hero_primary'      => ['Fondo', 'Color de fondo del botón principal del hero.', '#CC1F2D'],
            'color_btn_hero_primary_text' => ['Texto', 'Color del texto e ícono.',                     '#FFFFFF'],
        ]],
        ['label' => 'Botón hero secundario — "Únete al Equipo"', 'preview' => 'prev-hero-secondary', 'items' => [
            'color_btn_hero_secondary'      => ['Fondo', 'Color de fondo del botón secundario del hero.', '#FACC15'],
            'color_btn_hero_secondary_text' => ['Texto', 'Color del texto e ícono.',                      '#0039A6'],
        ]],
        ['label' => 'Botón descarga / plan — "Ver Plan Completo"', 'preview' => 'prev-download', 'items' => [
            'color_btn_download'      => ['Fondo', '"Ver Plan Completo" y PDF en plan.php.', '#CC1F2D'],
            'color_btn_download_text' => ['Texto', 'Color del texto e ícono.',               '#FFFFFF'],
        ]],
        ['label' => 'Botón CTA navbar — "Únete al equipo"', 'preview' => 'prev-navbar', 'items' => [
            'color_btn_cta_navbar'      => ['Fondo', 'Esquina superior derecha del menú.', '#FACC15'],
            'color_btn_cta_navbar_text' => ['Texto', 'Color del texto.',                   '#1E3A8A'],
        ]],
        ['label' => 'Botón formulario — "Me uno al equipo"', 'preview' => 'prev-join', 'items' => [
            'color_btn_join'      => ['Fondo', 'Enviar del formulario "Únete al equipo".', '#FACC15'],
            'color_btn_join_text' => ['Texto', 'Color del texto.',                         '#0039A6'],
        ]],
    ];
    ?>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
      <div class="bg-gradient-to-r from-[#1E3A8A] to-[#0056D6] px-6 py-4">
        <h2 class="text-white font-black text-base">Paleta de colores del sitio</h2>
        <p class="text-blue-200 text-xs mt-0.5">Cada botón tiene su propio color de fondo y de texto.</p>
      </div>
      <div class="p-6 space-y-6">
        <?php foreach ($color_groups as $group): ?>
        <div>
          <p class="text-xs font-black text-gray-500 uppercase tracking-widest mb-3"><?= htmlspecialchars($group['label']) ?></p>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <?php foreach ($group['items'] as $key => [$label, $desc, $default]): ?>
            <div class="flex items-start gap-4 p-4 rounded-xl border border-gray-100 bg-gray-50 hover:border-blue-200 transition-colors">
              <div class="flex-shrink-0 mt-0.5">
                <input type="color"
                       name="<?= $key ?>"
                       value="<?= htmlspecialchars(cfg_value($config, $key, $default)) ?>"
                       class="w-12 h-12 rounded-xl border border-gray-200 cursor-pointer p-0.5 bg-white shadow-sm"
                       title="<?= htmlspecialchars($label) ?>">
              </div>
              <div class="min-w-0">
                <p class="text-sm font-black text-gray-800 leading-tight"><?= htmlspecialchars($label) ?></p>
                <p class="text-xs text-gray-400 mt-0.5"><?= htmlspecialchars($desc) ?></p>
                <code class="text-[10px] text-gray-400 font-mono"><?= htmlspecialchars($key) ?></code>
              </div>
            </div>
            <?php endforeach; ?>
          </div>
          <?php if (!empty($group['preview'])): ?>
          <div class="mt-3 flex items-center gap-3">
            <span class="text-xs text-gray-400">Preview:</span>
            <span id="<?= $group['preview'] ?>"
                  class="inline-flex items-center gap-2 text-sm font-black px-6 py-2.5 rounded-full shadow cursor-default transition-all">
              <?php
                $previewLabels = [
                  'prev-hero-primary'   => 'Conoce el Plan',
                  'prev-hero-secondary' => 'Únete al Equipo',
                  'prev-download'       => 'Ver Plan 2026-2030',
                  'prev-navbar'         => 'Únete al equipo',
                  'prev-join'           => 'Me uno al equipo',
                ];
                echo htmlspecialchars($previewLabels[$group['preview']] ?? 'Botón');
              ?>
            </span>
          </div>
          <?php endif; ?>
        </div>
        <?php endforeach; ?>
      </div>
    </div>

    <div class="sticky bottom-0 bg-[#F1F5F9]/95 backdrop-blur py-4 flex justify-end gap-3">
      <a href="<?= BASE_URL ?>/index.php" target="_blank"
         class="bg-white border border-gray-200 text-gray-600 font-bold px-5 py-3 rounded-xl text-sm">Ver sitio</a>
      <button type="submit"
              class="bg-[#FACC15] hover:bg-yellow-400 text-[#1E3A8A] font-black px-8 py-3 rounded-xl text-sm shadow">
        Guardar Colores
      </button>
    </div>
  </form>

  <!-- ══════════════════════════════════════════════════════════ -->
  <!-- TAB: CONTADOR                                             -->
  <!-- ══════════════════════════════════════════════════════════ -->
  <form method="POST" x-show="activeTab === 'contador'" class="space-y-6">
    <input type="hidden" name="tab" value="contador">
    <?= csrf_field() ?>

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
      <div class="bg-gradient-to-r from-[#1E3A8A] to-[#0056D6] px-6 py-4">
        <h2 class="text-white font-black text-base">Contador regresivo</h2>
        <p class="text-blue-200 text-xs mt-0.5">Se muestra en el hero del landing page debajo de los botones CTA.</p>
      </div>
      <div class="p-6 space-y-6">

        <div class="flex items-center justify-between p-4 rounded-xl border border-gray-100 bg-gray-50">
          <div>
            <p class="text-sm font-black text-gray-800">Mostrar contador en el sitio</p>
            <p class="text-xs text-gray-400 mt-0.5">Si está desactivado el bloque no aparece en la web.</p>
          </div>
          <label class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" name="countdown_active" value="1" class="sr-only peer"
                   <?= cfg_value($config, 'countdown_active', '1') === '1' ? 'checked' : '' ?>>
            <div class="w-11 h-6 bg-gray-200 peer-focus:ring-2 peer-focus:ring-blue-300 rounded-full peer
                        peer-checked:bg-[#1E3A8A] transition-colors"></div>
            <div class="absolute left-0.5 top-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform
                        peer-checked:translate-x-5"></div>
          </label>
        </div>

        <div class="p-4 rounded-xl border border-gray-100 bg-gray-50">
          <label class="block text-sm font-black text-gray-800 mb-1">Fecha de las elecciones</label>
          <p class="text-xs text-gray-400 mb-3">El contador llega a cero a la medianoche de este día (hora de Lima, UTC-5).</p>
          <input type="date" name="countdown_date"
                 value="<?= cpag_val($config, 'countdown_date', '2026-10-04') ?>"
                 class="border border-gray-200 rounded-xl px-4 py-2.5 text-sm font-mono text-gray-800 focus:outline-none focus:ring-2 focus:ring-blue-300">
        </div>

        <div class="p-4 rounded-xl border border-gray-100 bg-gray-50">
          <label class="block text-sm font-black text-gray-800 mb-1">Título (encima de los dígitos)</label>
          <input type="text" name="countdown_title"
                 value="<?= cpag_val($config, 'countdown_title', 'Faltan para las Elecciones') ?>"
                 maxlength="80"
                 class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-blue-300"
                 placeholder="Faltan para las Elecciones">
        </div>

        <div class="p-4 rounded-xl border border-gray-100 bg-gray-50">
          <label class="block text-sm font-black text-gray-800 mb-1">Etiqueta inferior (debajo de los dígitos)</label>
          <input type="text" name="countdown_label"
                 value="<?= cpag_val($config, 'countdown_label', 'Elecciones Municipales 2026') ?>"
                 maxlength="80"
                 class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm text-gray-800 focus:outline-none focus:ring-2 focus:ring-blue-300"
                 placeholder="Elecciones Municipales 2026">
        </div>

        <!-- Preview estático -->
        <div class="p-4 rounded-xl border border-blue-100 bg-[#0B1E4A]">
          <p class="text-white/40 text-xs uppercase tracking-widest mb-2 font-semibold">Preview (estático)</p>
          <div class="inline-flex items-center gap-2">
            <?php foreach ([['365','Días'],['08','Hrs'],['22','Min'],['10','Seg']] as [$n, $u]): ?>
            <div class="flex flex-col items-center">
              <div class="bg-[#0B1E4A]/80 border border-white/10 rounded-xl px-3 py-2 min-w-[3.2rem] text-center shadow-lg">
                <span class="block text-2xl font-black text-white tabular-nums"><?= $n ?></span>
              </div>
              <span class="text-white/40 text-[10px] uppercase tracking-widest mt-1"><?= $u ?></span>
            </div>
            <?php if ($u !== 'Seg'): ?><span class="text-white/30 text-2xl font-black mb-4">:</span><?php endif; ?>
            <?php endforeach; ?>
          </div>
        </div>

      </div>
    </div>

    <!-- Contador de visitas -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
      <div class="bg-gradient-to-r from-emerald-600 to-teal-600 px-6 py-4 flex items-center justify-between">
        <div>
          <h2 class="text-white font-black text-base">Contador de visitas</h2>
          <p class="text-emerald-100 text-xs mt-0.5">Visitantes únicos por IP, una vez por día.</p>
        </div>
        <svg class="w-8 h-8 text-white/40" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
        </svg>
      </div>
      <div class="p-6 space-y-6">

        <div class="flex items-center justify-between p-4 rounded-xl border border-gray-100 bg-gray-50">
          <div>
            <p class="text-sm font-black text-gray-800">Activar registro de visitas</p>
            <p class="text-xs text-gray-400 mt-0.5">Muestra el total en el footer del sitio.</p>
          </div>
          <label class="relative inline-flex items-center cursor-pointer">
            <input type="checkbox" name="visit_counter_active" value="1" class="sr-only peer"
                   <?= cfg_value($config, 'visit_counter_active', '1') === '1' ? 'checked' : '' ?>>
            <div class="w-11 h-6 bg-gray-200 peer-focus:ring-2 peer-focus:ring-emerald-300 rounded-full peer
                        peer-checked:bg-emerald-600 transition-colors"></div>
            <div class="absolute left-0.5 top-0.5 w-5 h-5 bg-white rounded-full shadow transition-transform
                        peer-checked:translate-x-5"></div>
          </label>
        </div>

        <div class="grid grid-cols-2 sm:grid-cols-4 gap-4">
          <?php foreach ([
            ['Total histórico', $visit_stats['total'],  'text-emerald-600', 'bg-emerald-50'],
            ['Hoy',            $visit_stats['hoy'],    'text-blue-600',    'bg-blue-50'],
            ['Esta semana',    $visit_stats['semana'], 'text-violet-600',  'bg-violet-50'],
            ['Este mes',       $visit_stats['mes'],    'text-orange-500',  'bg-orange-50'],
          ] as [$lbl, $val, $color, $bg]): ?>
          <div class="<?= $bg ?> rounded-2xl p-4 text-center border border-white">
            <p class="<?= $color ?> text-3xl font-black"><?= number_format($val) ?></p>
            <p class="text-gray-500 text-xs uppercase tracking-widest mt-1"><?= $lbl ?></p>
          </div>
          <?php endforeach; ?>
        </div>

        <div class="p-4 rounded-xl border border-red-100 bg-red-50 flex items-center justify-between gap-4">
          <div>
            <p class="text-sm font-black text-red-700">Reiniciar contador</p>
            <p class="text-xs text-red-400 mt-0.5">Elimina todos los registros de visitas. Esta acción no se puede deshacer.</p>
          </div>
          <button type="submit" name="reset_visits" value="1"
                  onclick="return confirm('¿Seguro que deseas reiniciar el contador a cero? Esta acción no se puede deshacer.')"
                  class="flex-shrink-0 bg-red-600 hover:bg-red-700 text-white font-black text-xs px-4 py-2.5 rounded-xl shadow transition-colors">
            Reiniciar a 0
          </button>
        </div>

      </div>
    </div>

    <div class="sticky bottom-0 bg-[#F1F5F9]/95 backdrop-blur py-4 flex justify-end gap-3">
      <a href="<?= BASE_URL ?>/index.php" target="_blank"
         class="bg-white border border-gray-200 text-gray-600 font-bold px-5 py-3 rounded-xl text-sm">Ver sitio</a>
      <button type="submit"
              class="bg-[#FACC15] hover:bg-yellow-400 text-[#1E3A8A] font-black px-8 py-3 rounded-xl text-sm shadow">
        Guardar Contador
      </button>
    </div>
  </form>

</div>

<script>
// Preview en vivo de colores (igual que config-index.php)
(function () {
  const pairs = [
    ['color_btn_hero_primary',      'color_btn_hero_primary_text',      'prev-hero-primary'],
    ['color_btn_hero_secondary',    'color_btn_hero_secondary_text',    'prev-hero-secondary'],
    ['color_btn_download',          'color_btn_download_text',          'prev-download'],
    ['color_btn_cta_navbar',        'color_btn_cta_navbar_text',        'prev-navbar'],
    ['color_btn_join',              'color_btn_join_text',              'prev-join'],
  ];
  function applyPreview() {
    pairs.forEach(([bgKey, textKey, previewId]) => {
      const bgInput   = document.querySelector(`input[name="${bgKey}"]`);
      const textInput = document.querySelector(`input[name="${textKey}"]`);
      const preview   = document.getElementById(previewId);
      if (!bgInput || !textInput || !preview) return;
      preview.style.background = bgInput.value;
      preview.style.color      = textInput.value;
    });
  }
  document.addEventListener('input', e => {
    if (e.target.type === 'color') applyPreview();
  });
  applyPreview();
})();
</script>

<?php include __DIR__ . '/_media-picker.php'; ?>

<?php
$content = ob_get_clean();
echo $content;
?>
