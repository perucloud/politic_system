<?php
// ============================================================
// layout.php - Base del panel admin con flyout submenus
// ob_start() permite usar header() incluso despues de output
// ============================================================
if (ob_get_level() === 0) ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();

// Prevenir que LiteSpeed u otros proxies cacheen páginas del admin
header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: Thu, 01 Jan 1970 00:00:00 GMT');

require_once __DIR__ . '/../includes/config/db.php';
require_once __DIR__ . '/config/auth.php';

require_login();

$admin_nombre = htmlspecialchars($_SESSION['admin_nombre'] ?? 'Admin');
$admin_rol    = get_rol();
$page_title   = $page_title ?? 'Panel Admin';
$current      = basename($_SERVER['PHP_SELF']);

// -- Iconos reutilizables (Tabler Icons) --------------------
$ic_list  = 'ti-list';
$ic_plus  = 'ti-plus';
$ic_pdf   = 'ti-file-type-pdf';
$ic_excel = 'ti-file-type-xls';
$ic_eye   = 'ti-eye';
$ic_tag   = 'ti-tag';

// -- Menu por secciones con submenus -----------------------
$secciones = [
  [
    'titulo' => 'PRINCIPAL',
    'items'  => [
      ['id'=>'dashboard','href'=>'dashboard.php','icon'=>'ti-layout-dashboard','label'=>'Dashboard','rol'=>'editor','modulo'=>null,'solo_rol'=>null,'submenu'=>null],
      ['id'=>'mi_distrito','href'=>'mi-distrito.php','icon'=>'ti-map-pin','label'=>'Mi Distrito','rol'=>'candidato_distrital','modulo'=>null,'solo_rol'=>'candidato_distrital','submenu'=>null],
    ],
  ],
  [
    'titulo' => 'CONTENIDO',
    'items'  => [
      ['id'=>'candidatos','href'=>'candidatos-distritales.php','icon'=>'ti-user-star','label'=>'Candidatos','rol'=>'editor','modulo'=>'candidatos_distritales','solo_rol'=>null,
        'submenu'=>[
          ['href'=>'candidatos-distritales.php','label'=>'Ver todos','icon'=>$ic_list],
          ['href'=>'candidato-nuevo.php',       'label'=>'Nuevo Distrito','icon'=>$ic_plus],
        ]
      ],
      ['id'=>'noticias','href'=>'noticias.php','icon'=>'ti-news','label'=>'Noticias','rol'=>'editor','modulo'=>'noticias','solo_rol'=>null,
        'submenu'=>[
          ['href'=>'noticias.php',            'label'=>'Todas las noticias','icon'=>$ic_list],
          ['href'=>'noticia-form.php',         'label'=>'Nueva Noticia',     'icon'=>$ic_plus],
          ['href'=>'categorias-noticias.php',  'label'=>'Categorias',        'icon'=>$ic_tag],
        ]
      ],
      ['id'=>'actividades','href'=>'actividades.php','icon'=>'ti-calendar-event','label'=>'Calendario','rol'=>'editor','modulo'=>'actividades','solo_rol'=>null,
        'submenu'=>[
          ['href'=>'actividades.php',     'label'=>'Todas las actividades','icon'=>$ic_list],
          ['href'=>'actividad-form.php',  'label'=>'Nueva Actividad',      'icon'=>$ic_plus],
        ]
      ],
      ['id'=>'paginas','href'=>'paginas.php','icon'=>'ti-file-text','label'=>'Paginas','rol'=>'editor','modulo'=>'paginas','solo_rol'=>null,
        'submenu'=>[
          ['href'=>'paginas.php',    'label'=>'Todas las paginas','icon'=>$ic_list],
          ['href'=>'pagina-form.php','label'=>'Nueva Pagina',     'icon'=>$ic_plus],
        ]
      ],
      ['id'=>'media','href'=>'media.php','icon'=>'ti-photo','label'=>'Archivos y Media','rol'=>'editor','modulo'=>'media','solo_rol'=>null,'submenu'=>null],
    ],
  ],
  [
    'titulo' => 'PARTIDO POLITICO',
    'items'  => [
      ['id'=>'simpatizantes','href'=>'simpatizantes.php','icon'=>'ti-users-group','label'=>'Simpatizantes','rol'=>'editor','modulo'=>'simpatizantes','solo_rol'=>null,
        'submenu'=>[
          ['href'=>'simpatizantes.php',          'label'=>'Ver registros',  'icon'=>$ic_list],
          ['href'=>'exportar-pdf.php',           'label'=>'Exportar PDF',   'icon'=>$ic_pdf, 'target'=>'_blank'],
          ['href'=>'simpatizantes.php?export=excel','label'=>'Exportar Excel','icon'=>$ic_excel],
        ]
      ],
      ['id'=>'militantes','href'=>'militantes.php','icon'=>'ti-shield-check','label'=>'Militantes','rol'=>'editor','modulo'=>'militantes','solo_rol'=>null,'submenu'=>null],
      ['id'=>'personeros','href'=>'personeros.php','icon'=>'ti-id-badge-2','label'=>'Personeros','rol'=>'editor','modulo'=>null,'solo_rol'=>null,'submenu'=>null],
      ['id'=>'config_plan','href'=>'config-plan.php','icon'=>'ti-file-certificate','label'=>'Plan de Gobierno','rol'=>'admin','modulo'=>'configuracion_global','solo_rol'=>null,'submenu'=>null],
      ['id'=>'material_pub','href'=>'material-publicitario.php','icon'=>'ti-photo-scan','label'=>'Material Publicitario','rol'=>'editor','modulo'=>'material_publicitario','solo_rol'=>null,'submenu'=>null],
    ],
  ],
  [
    'titulo' => 'CONFIGURACION',
    'items'  => [
      ['id'=>'configurar','href'=>'#','icon'=>'ti-adjustments-horizontal','label'=>'Configurar','rol'=>'admin','modulo'=>'configuracion_global','solo_rol'=>null,
        'submenu'=>[
          ['href'=>'menu-manager.php',       'label'=>'Menu Web',          'icon'=>'ti-sitemap',                  'rol'=>'admin',      'modulo'=>'menu_web',             'solo_rol'=>null],
          ['href'=>'config-pagina.php',      'label'=>'Configurar Pagina', 'icon'=>'ti-settings-2',               'rol'=>'admin',      'modulo'=>'configuracion_global', 'solo_rol'=>null],
          ['href'=>'config-index.php',       'label'=>'Configurar Index',  'icon'=>'ti-layout',                   'rol'=>'admin',      'modulo'=>'configuracion_global', 'solo_rol'=>null],
          ['href'=>'seo.php',                'label'=>'SEO por Pagina',    'icon'=>'ti-search',                   'rol'=>'admin',      'modulo'=>'seo',                  'solo_rol'=>null],
          ['href'=>'tools/migrate-webp.php', 'label'=>'Migrar a WebP',     'icon'=>'ti-photo-bolt',               'rol'=>'admin',      'modulo'=>'configuracion_global', 'solo_rol'=>null],
          ['href'=>'auditoria.php',          'label'=>'Auditoria',         'icon'=>'ti-activity',                 'rol'=>'admin',      'modulo'=>'auditoria',            'solo_rol'=>null],
          ['href'=>'usuarios.php',           'label'=>'Usuarios',          'icon'=>'ti-users',                    'rol'=>'superadmin', 'modulo'=>'usuarios',             'solo_rol'=>null],
          ['href'=>'editores.php',           'label'=>'Gestionar Editores','icon'=>'ti-user-plus',                'rol'=>'admin',      'modulo'=>'usuarios',             'solo_rol'=>'admin'],
          ['href'=>'backup.php',             'label'=>'Backup',            'icon'=>'ti-cloud-upload',             'rol'=>'admin',      'modulo'=>'configuracion_global', 'solo_rol'=>null],
        ]
      ],
    ],
  ],
];


$jerarquia     = ['candidato_distrital' => 0, 'editor' => 1, 'admin' => 2, 'superadmin' => 3];
$nivel_usuario = $jerarquia[$admin_rol] ?? 0;
$_modulos_usuario_sidebar = is_superadmin() ? null : get_modulos_usuario((int)($_SESSION['admin_id'] ?? 0), $pdo);

// -- Construir datos de flyout para Alpine.js -------------
$flyout_data = [];
foreach ($secciones as $sec) {
    foreach ($sec['items'] as $item) {
        if (!empty($item['submenu'])) {
            // Filtrar sub-items por rol y módulo igual que el sidebar
            $sub_filtrado = array_values(array_filter($item['submenu'], function($sub) use ($jerarquia, $nivel_usuario, $_modulos_usuario_sidebar, $admin_rol) {
                if (!empty($sub['solo_rol']) && $admin_rol !== $sub['solo_rol']) return false;
                if (isset($sub['rol']) && ($jerarquia[$sub['rol']] ?? 99) > $nivel_usuario) return false;
                if (!isset($sub['modulo']) || $sub['modulo'] === null) return true;
                if ($_modulos_usuario_sidebar === null) return true;
                return in_array($sub['modulo'], $_modulos_usuario_sidebar);
            }));
            if (empty($sub_filtrado)) continue;
            $flyout_data[$item['id']] = [
                'title' => $item['label'],
                'icon'  => $item['icon'],
                'href'  => $item['href'],
                'items' => $sub_filtrado,
            ];
        }
    }
}
$flyout_json = json_encode($flyout_data, JSON_HEX_TAG | JSON_UNESCAPED_UNICODE);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($page_title) ?> - Admin Ivan Cisneros</title>
  <link rel="manifest" href="<?= BASE_URL ?>/admin/manifest.json">
  <meta name="theme-color" content="#1E3A8A">
  <meta name="mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-capable" content="yes">
  <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
  <meta name="apple-mobile-web-app-title" content="JB Admin">
  <?php
  $favicon_admin = '';
  try {
      $fv = $pdo->query("SELECT valor FROM configuracion WHERE clave='site_favicon' LIMIT 1")->fetchColumn();
      if ($fv) $favicon_admin = $fv;
  } catch (Exception $e) {}
  $favicon_href = $favicon_admin ?: '/assets/img/logos/logorp.webp';
  $favicon_href = (str_starts_with($favicon_href, '/') ? BASE_URL : '') . $favicon_href;
  ?>
  <link rel="icon" href="<?= htmlspecialchars($favicon_href) ?>">
  <link rel="apple-touch-icon" href="<?= htmlspecialchars($favicon_href) ?>">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/@tabler/icons-webfont@3.x/dist/tabler-icons.min.css">
  <script src="https://cdn.tailwindcss.com"></script>
  <script>tailwind.config={theme:{extend:{colors:{primary:'#1E3A8A',secondary:'#38BDF8',accent:'#FACC15'}}}}</script>
  <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <style>
    body { font-family: 'Inter', sans-serif; }
    [x-cloak] { display: none !important; }

    /* Nav items - hover fondo completo + scale */
    .nav-item {
      border-radius: 10px;
      transition: background 0.15s ease, transform 0.15s ease, color 0.15s ease;
    }
    .nav-item:hover {
      background: #2563EB;
      transform: scale(1.015);
    }
    .nav-active {
      background: rgba(250,204,21,0.17);
      color: #FACC15 !important;
      font-weight: 700;
    }
    .nav-active:hover {
      background: rgba(250,204,21,0.24);
    }

    /* Flyout panel */
    .flyout-panel {
      position: fixed;
      left: 256px;
      z-index: 9999;
      min-width: 220px;
      max-width: 260px;
    }
    /* Puente invisible entre sidebar y flyout para evitar gap */
    .flyout-bridge {
      position: absolute;
      left: -12px;
      top: 0;
      width: 14px;
      height: 100%;
      background: transparent;
    }
  </style>
</head>
<body class="bg-[#F1F5F9] min-h-screen"
      x-data="adminLayout()"
      @mousemove.window="handleMouseMove($event)">

  <script>
    window.CSRF_TOKEN = '<?= htmlspecialchars(csrf_token(), ENT_QUOTES) ?>';

    function ensureCsrfField(form) {
      if (!form || String(form.method || '').toLowerCase() !== 'post') return;
      if (form.querySelector('input[name="_csrf"]')) return;
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = '_csrf';
      input.value = window.CSRF_TOKEN;
      form.appendChild(input);
    }

    document.addEventListener('DOMContentLoaded', () => {
      document.querySelectorAll('form').forEach(ensureCsrfField);
    });
    document.addEventListener('submit', (event) => {
      ensureCsrfField(event.target);
    }, true);

    function adminLayout() {
  return {
    sidebarOpen: false,
    flyout: null,
    flyoutY: 0,
    flyoutFromBottom: false,
    flyoutTimer: null,
    submenus: <?= $flyout_json ?>,

    get currentSub() {
      return this.flyout ? this.submenus[this.flyout] : null;
    },

    openFlyout(id, el) {
      clearTimeout(this.flyoutTimer);
      const rect   = el.getBoundingClientRect();
      const items  = this.submenus[id]?.items?.length ?? 0;
      const panelH = items * 44 + 88;

      if (rect.top + panelH > window.innerHeight - 12) {
        // Anclar desde abajo: calcular distancia desde el borde inferior
        this.flyoutFromBottom = true;
        this.flyoutY = Math.max(window.innerHeight - rect.bottom, 8);
      } else {
        this.flyoutFromBottom = false;
        this.flyoutY = rect.top;
      }
      this.flyout = id;
    },

    closeFlyout(delay = 300) {
      clearTimeout(this.flyoutTimer);
      this.flyoutTimer = setTimeout(() => { this.flyout = null; }, delay);
    },

    keepFlyout() {
      clearTimeout(this.flyoutTimer);
    },

    handleMouseMove(e) {
      // Solo cerrar si el mouse esta LEJOS a la derecha (fuera del flyout)
      // No tocar si esta en el sidebar (< 256) ni en el flyout (256-520)
      if (!this.flyout) return;
      if (e.clientX > 520) {
        this.closeFlyout(250);
      }
    }
  };
}
</script>

  <!-- Overlay movil -->
  <div x-show="sidebarOpen" @click="sidebarOpen=false"
       class="fixed inset-0 bg-black/50 z-30 lg:hidden" x-cloak></div>

  <!-- -- SIDEBAR ------------------------------------------- -->
  <aside :class="sidebarOpen ? 'translate-x-0' : '-translate-x-full'"
         class="fixed top-0 left-0 h-full w-64 bg-[#0F2057] z-40 flex flex-col
                transition-transform duration-300 lg:translate-x-0 overflow-y-auto overflow-x-visible">

    <!-- Logo / Marca -->
    <div class="px-5 py-5 border-b border-white/10 flex-shrink-0">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 bg-[#FACC15] rounded-xl flex items-center justify-center flex-shrink-0">
          <svg class="w-5 h-5 text-[#0F2057]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M3 21v-4m0 0V5a2 2 0 012-2h6.5l1 1H21l-3 6 3 6h-8.5l-1-1H5a2 2 0 00-2 2zm9-13.5V9"/>
          </svg>
        </div>
        <div>
          <p class="text-white font-black text-sm leading-tight">Ivan Cisneros</p>
          <p class="text-[#38BDF8] text-[10px] uppercase tracking-widest">Panel Admin</p>
        </div>
      </div>
    </div>

    <!-- Info usuario -->
    <div class="px-5 py-4 border-b border-white/10 flex-shrink-0">
      <div class="flex items-center gap-3">
        <div class="w-9 h-9 bg-gradient-to-br from-[#38BDF8] to-[#1E3A8A] rounded-full flex items-center justify-center flex-shrink-0">
          <span class="text-white text-sm font-black"><?= strtoupper(substr($admin_nombre, 0, 1)) ?></span>
        </div>
        <div class="min-w-0">
          <p class="text-white text-sm font-semibold truncate"><?= $admin_nombre ?></p>
          <p class="text-[11px] mt-0.5">
            <?php if ($admin_rol === 'superadmin'): ?>
              <span class="text-purple-300 font-bold">SuperAdmin</span>
            <?php elseif ($admin_rol === 'admin'): ?>
              <span class="text-blue-300 font-bold">Admin</span>
            <?php elseif ($admin_rol === 'candidato_distrital'): ?>
              <span class="text-amber-300 font-bold">Candidato Distrital</span>
            <?php else: ?>
              <span class="text-green-300 font-bold">Editor</span>
            <?php endif; ?>
          </p>
        </div>
      </div>
    </div>

    <!-- Navegacion -->
    <nav class="flex-1 px-3 py-4 space-y-5 overflow-x-visible">
      <?php foreach ($secciones as $seccion): ?>
        <?php
        $items_visibles = array_filter($seccion['items'], function($item) use ($jerarquia, $nivel_usuario, $_modulos_usuario_sidebar, $admin_rol) {
            if (!empty($item['solo_rol']) && $admin_rol !== $item['solo_rol']) return false;
            if (($jerarquia[$item['rol']] ?? 99) > $nivel_usuario) return false;
            if ($item['modulo'] === null) return true;
            if ($_modulos_usuario_sidebar === null) return true;
            return in_array($item['modulo'], $_modulos_usuario_sidebar);
        });
        if (empty($items_visibles)) continue;
        ?>
        <div>
          <p class="text-[9px] font-black text-white/30 uppercase tracking-widest px-4 mb-2">
            <?= $seccion['titulo'] ?>
          </p>
          <div class="space-y-0.5">
            <?php foreach ($items_visibles as $item):
              $has_sub = !empty($item['submenu']);
              $is_active = $current === $item['href'];
            ?>
            <div class="relative"
                 <?php if ($has_sub): ?>
                 @mouseenter="openFlyout('<?= $item['id'] ?>', $el)"
                 @mouseleave="closeFlyout(350)"
                 <?php endif; ?>>

              <!-- Link principal -->
              <a href="<?= $item['href'] === '#' ? '#' : BASE_URL . '/admin/' . $item['href'] ?>"
                 <?= $item['href'] === '#' ? '@click.prevent=""' : '' ?>
                 class="nav-item flex items-center gap-3 px-4 py-2.5 rounded-xl text-sm
                        text-blue-200 hover:text-white group
                        <?= $is_active ? 'nav-active' : '' ?>">
                <i class="ti <?= htmlspecialchars($item['icon']) ?> text-base flex-shrink-0 opacity-80 group-hover:opacity-100 transition-opacity"></i>
                <span class="truncate flex-1"><?= $item['label'] ?></span>
                <?php if ($has_sub): ?>
                <svg class="w-3 h-3 flex-shrink-0 opacity-40 group-hover:opacity-80 transition-all"
                     fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
                </svg>
                <?php endif; ?>
              </a>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
      <?php endforeach; ?>
    </nav>

    <!-- Footer sidebar -->
    <div class="px-3 py-4 border-t border-white/10 flex-shrink-0 space-y-1">

      <!-- ── Botón instalar PWA ───────────────────────────── -->
      <div id="pwa-install-wrap" style="display:none" class="px-1 pb-2">
        <button id="pwa-install-btn"
                class="w-full relative overflow-hidden group
                       flex items-center gap-3 px-4 py-3 rounded-2xl
                       bg-gradient-to-r from-[#FACC15] via-[#FCD34D] to-[#FACC15]
                       bg-[length:200%_100%] bg-left
                       hover:bg-right
                       text-[#0F2057] font-black text-sm
                       shadow-lg shadow-yellow-500/30
                       transition-all duration-700 ease-in-out
                       hover:shadow-xl hover:shadow-yellow-400/40 hover:scale-[1.02]
                       active:scale-95">

          <!-- Shimmer effect -->
          <span class="absolute inset-0 bg-gradient-to-r from-transparent via-white/30 to-transparent
                       translate-x-[-100%] group-hover:translate-x-[100%]
                       transition-transform duration-700 ease-in-out pointer-events-none"></span>

          <!-- Icono descarga animado -->
          <span class="relative flex-shrink-0 w-8 h-8 rounded-xl bg-[#0F2057]/15
                       flex items-center justify-center
                       group-hover:bg-[#0F2057]/25 transition-colors">
            <svg class="w-4 h-4 group-hover:animate-bounce" fill="none" stroke="currentColor"
                 stroke-width="2.5" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round"
                    d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
          </span>

          <!-- Texto -->
          <span class="relative flex-1 text-left leading-tight">
            <span class="block text-sm font-black">Instalar App</span>
            <span class="block text-[10px] font-semibold text-[#0F2057]/60">Acceso rápido desde tu celular</span>
          </span>

          <!-- Flecha -->
          <svg class="relative w-4 h-4 flex-shrink-0 opacity-60 group-hover:opacity-100 group-hover:translate-x-0.5 transition-all"
               fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
          </svg>
        </button>

        <!-- Subtexto -->
        <p class="text-center text-[9px] text-white/25 mt-1.5 font-medium tracking-wide">
          Sin tiendas · Instalación directa
        </p>
      </div>

      <!-- Separador si el botón está visible -->
      <div id="pwa-divider" style="display:none" class="border-t border-white/10 mb-1"></div>

      <a href="<?= BASE_URL ?>/index.php" target="_blank"
         class="nav-item flex items-center gap-3 px-4 py-2.5 rounded-r-xl text-sm text-blue-200 hover:text-white transition-all duration-150">
        <svg class="w-4 h-4 flex-shrink-0 opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
        </svg>
        Ver sitio web
      </a>
      <a href="<?= BASE_URL ?>/admin/logout.php"
         class="nav-item flex items-center gap-3 px-4 py-2.5 rounded-r-xl text-sm text-red-300 hover:bg-red-500/15 hover:text-red-200 transition-all duration-150">
        <svg class="w-4 h-4 flex-shrink-0 opacity-80" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
          <path stroke-linecap="round" stroke-linejoin="round" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/>
        </svg>
        Cerrar sesion
      </a>
    </div>
  </aside>

  <!-- -- FLYOUT PANEL (fixed, fuera del sidebar) ----------- -->
  <div x-cloak
       x-show="flyout !== null && currentSub !== null"
       x-transition:enter="transition ease-out duration-150"
       x-transition:enter-start="opacity-0 translate-x-1 scale-95"
       x-transition:enter-end="opacity-100 translate-x-0 scale-100"
       x-transition:leave="transition ease-in duration-100"
       x-transition:leave-start="opacity-100 translate-x-0 scale-100"
       x-transition:leave-end="opacity-0 translate-x-1 scale-95"
       :style="flyoutFromBottom ? `bottom: ${flyoutY}px; top: auto` : `top: ${flyoutY}px; bottom: auto`"
       @mouseenter="keepFlyout()"
       @mouseleave="closeFlyout()"
       class="flyout-panel hidden lg:block">

    <!-- Puente invisible anti-gap -->
    <div class="flyout-bridge"></div>

    <!-- Panel -->
    <div class="bg-white rounded-2xl shadow-2xl shadow-slate-900/20 border border-gray-100 overflow-hidden">

      <!-- Header del flyout -->
      <div class="px-4 py-3 bg-gradient-to-r from-[#0F2057] to-[#1E3A8A] flex items-center gap-2.5">
        <template x-if="currentSub">
          <i :class="'ti ' + (currentSub?.icon ?? '') + ' text-base text-[#FACC15] flex-shrink-0'"></i>
        </template>
        <span class="text-white font-black text-sm" x-text="currentSub?.title ?? ''"></span>
      </div>

      <!-- Sub-links -->
      <div class="py-1.5">
        <template x-for="sub in (currentSub?.items ?? [])" :key="sub.href">
          <a :href="'<?= BASE_URL ?>/admin/' + sub.href"
             :target="sub.target ?? '_self'"
             class="flex items-center gap-3 mx-2 px-3 py-2.5 text-sm text-gray-600 font-medium rounded-xl
                    hover:bg-[#1E3A8A] hover:text-white hover:scale-[1.02] transition-all duration-150 group">
            <i :class="'ti ' + sub.icon + ' text-sm flex-shrink-0 text-gray-400 group-hover:text-[#FACC15] transition-colors'"></i>
            <span x-text="sub.label"></span>
          </a>
        </template>

        <!-- Divisor + ir a la seccion principal -->
        <div class="border-t border-gray-100 mt-1 pt-1">
          <a :href="'<?= BASE_URL ?>/admin/' + (currentSub?.href ?? '#')"
             class="flex items-center gap-3 mx-2 px-3 py-2 text-xs text-gray-400 font-semibold rounded-xl
                    hover:bg-gray-50 hover:text-[#1E3A8A] hover:scale-[1.02] transition-all duration-150">
            <svg class="w-3 h-3" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
            </svg>
            <span x-text="'Ir a ' + (currentSub?.title ?? '')"></span>
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- -- MAIN AREA ------------------------------------------ -->
  <div class="lg:ml-64 flex flex-col min-h-screen">

    <!-- Topbar -->
    <header class="bg-white border-b border-gray-200 sticky top-0 z-20 px-4 sm:px-6 h-14 flex items-center justify-between shadow-sm">
      <div class="flex items-center gap-3">
        <button @click="sidebarOpen = !sidebarOpen"
                class="lg:hidden p-1.5 rounded-lg hover:bg-gray-100 transition-colors">
          <svg class="w-5 h-5 text-gray-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
          </svg>
        </button>
        <div class="flex items-center gap-2">
          <span class="text-gray-300 hidden sm:block">/</span>
          <h1 class="font-bold text-gray-800 text-sm"><?= htmlspecialchars($page_title) ?></h1>
        </div>
      </div>
      <div class="flex items-center gap-4">
        <a href="<?= BASE_URL ?>/index.php" target="_blank"
           class="hidden sm:flex items-center gap-1.5 text-xs text-gray-400 hover:text-[#1E3A8A] transition-colors font-medium">
          <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
          </svg>
          Ver sitio
        </a>
        <div class="flex items-center gap-2">
          <div class="w-7 h-7 bg-gradient-to-br from-[#38BDF8] to-[#1E3A8A] rounded-full flex items-center justify-center">
            <span class="text-white text-xs font-black"><?= strtoupper(substr($admin_nombre, 0, 1)) ?></span>
          </div>
          <div class="hidden sm:block">
            <p class="text-xs font-semibold text-gray-700 leading-tight"><?= $admin_nombre ?></p>
            <p class="text-[10px] text-gray-400 leading-tight capitalize"><?= $admin_rol ?></p>
          </div>
        </div>
        <a href="<?= BASE_URL ?>/admin/logout.php"
           class="text-xs text-red-400 hover:text-red-600 font-semibold transition-colors">
          Salir
        </a>
      </div>
    </header>

    <!-- ── PWA: registro SW + lógica de instalación ─────────── -->
    <script>
    (function () {
      // 1. Registrar Service Worker
      if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register('<?= BASE_URL ?>/admin/sw.js', { scope: '/admin/' })
          .catch(() => {});
      }

      // 2. Capturar evento de instalación
      let _deferredPrompt = null;
      const wrap    = document.getElementById('pwa-install-wrap');
      const divider = document.getElementById('pwa-divider');
      const btn     = document.getElementById('pwa-install-btn');

      window.addEventListener('beforeinstallprompt', e => {
        e.preventDefault();
        _deferredPrompt = e;
        if (wrap)    wrap.style.display    = 'block';
        if (divider) divider.style.display = 'block';
      });

      // 3. Click en el botón de instalar
      if (btn) {
        btn.addEventListener('click', async () => {
          if (!_deferredPrompt) return;
          _deferredPrompt.prompt();
          const { outcome } = await _deferredPrompt.userChoice;
          _deferredPrompt = null;
          if (outcome === 'accepted') {
            if (wrap)    wrap.style.display    = 'none';
            if (divider) divider.style.display = 'none';
          }
        });
      }

      // 4. Si ya está instalada, ocultar el botón
      window.addEventListener('appinstalled', () => {
        if (wrap)    wrap.style.display    = 'none';
        if (divider) divider.style.display = 'none';
        _deferredPrompt = null;
      });

      // 5. Detectar si ya corre como PWA instalada
      if (window.matchMedia('(display-mode: standalone)').matches ||
          window.navigator.standalone === true) {
        if (wrap)    wrap.style.display    = 'none';
        if (divider) divider.style.display = 'none';
      }
    })();
    </script>

    <!-- Contenido dinamico -->
    <main class="flex-1 p-4 sm:p-6 lg:p-8">

