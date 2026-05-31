<?php
// ============================================================
// layout.php - Base del panel admin con flyout submenus
// ob_start() permite usar header() incluso despues de output
// ============================================================
if (ob_get_level() === 0) ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/config/db.php';
require_once __DIR__ . '/config/auth.php';

require_login();

$admin_nombre = htmlspecialchars($_SESSION['admin_nombre'] ?? 'Admin');
$admin_rol    = get_rol();
$page_title   = $page_title ?? 'Panel Admin';
$current      = basename($_SERVER['PHP_SELF']);

// -- Iconos reutilizables -----------------------------------
$ic_list  = 'M4 6h16M4 10h16M4 14h16M4 18h7';
$ic_plus  = 'M12 4v16m8-8H4';
$ic_pdf   = 'M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z';
$ic_excel = 'M3 10h18M3 14h18m-9-4v8m-7 0h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v8a2 2 0 002 2z';
$ic_eye   = 'M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z';
$ic_tag   = 'M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z';

// -- Menu por secciones con submenus -----------------------
$secciones = [
  [
    'titulo' => 'PRINCIPAL',
    'items'  => [
      ['id'=>'dashboard','href'=>'dashboard.php','icon'=>'M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6','label'=>'Dashboard','rol'=>'editor','modulo'=>null,'solo_rol'=>null,'submenu'=>null],
      ['id'=>'mi_distrito','href'=>'mi-distrito.php','icon'=>'M3 21h18M6 21V8l6-5 6 5v13M9 21v-6h6v6','label'=>'Mi Distrito','rol'=>'candidato_distrital','modulo'=>null,'solo_rol'=>'candidato_distrital','submenu'=>null],
    ],
  ],
  [
    'titulo' => 'CONTENIDO',
    'items'  => [
      ['id'=>'candidatos','href'=>'candidatos-distritales.php','icon'=>'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z','label'=>'Candidatos','rol'=>'editor','modulo'=>'candidatos_distritales','solo_rol'=>null,
        'submenu'=>[
          ['href'=>'candidatos-distritales.php','label'=>'Ver todos','icon'=>$ic_list],
          ['href'=>'candidato-nuevo.php',       'label'=>'Nuevo Distrito','icon'=>$ic_plus],
        ]
      ],
      ['id'=>'noticias','href'=>'noticias.php','icon'=>'M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 12h6m-6 4h6','label'=>'Noticias','rol'=>'editor','modulo'=>'noticias','solo_rol'=>null,
        'submenu'=>[
          ['href'=>'noticias.php',            'label'=>'Todas las noticias','icon'=>$ic_list],
          ['href'=>'noticia-form.php',         'label'=>'Nueva Noticia',     'icon'=>$ic_plus],
          ['href'=>'categorias-noticias.php',  'label'=>'Categorias',        'icon'=>$ic_tag],
        ]
      ],
      ['id'=>'actividades','href'=>'actividades.php','icon'=>'M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z','label'=>'Calendario','rol'=>'editor','modulo'=>'actividades','solo_rol'=>null,
        'submenu'=>[
          ['href'=>'actividades.php',     'label'=>'Todas las actividades','icon'=>$ic_list],
          ['href'=>'actividad-form.php',  'label'=>'Nueva Actividad',      'icon'=>$ic_plus],
        ]
      ],
      ['id'=>'paginas','href'=>'paginas.php','icon'=>'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z','label'=>'Paginas','rol'=>'editor','modulo'=>'paginas','solo_rol'=>null,
        'submenu'=>[
          ['href'=>'paginas.php',    'label'=>'Todas las paginas','icon'=>$ic_list],
          ['href'=>'pagina-form.php','label'=>'Nueva Pagina',     'icon'=>$ic_plus],
        ]
      ],
      ['id'=>'media','href'=>'media.php','icon'=>'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z','label'=>'Archivos y Media','rol'=>'editor','modulo'=>'media','solo_rol'=>null,'submenu'=>null],
    ],
  ],
  [
    'titulo' => 'PARTIDO POLITICO',
    'items'  => [
      ['id'=>'simpatizantes','href'=>'simpatizantes.php','icon'=>'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z','label'=>'Simpatizantes','rol'=>'editor','modulo'=>'simpatizantes','solo_rol'=>null,
        'submenu'=>[
          ['href'=>'simpatizantes.php',          'label'=>'Ver registros',  'icon'=>$ic_list],
          ['href'=>'exportar-pdf.php',           'label'=>'Exportar PDF',   'icon'=>$ic_pdf, 'target'=>'_blank'],
          ['href'=>'simpatizantes.php?export=excel','label'=>'Exportar Excel','icon'=>$ic_excel],
        ]
      ],
      ['id'=>'militantes','href'=>'militantes.php','icon'=>'M9 12l2 2 4-4m5.618-4.016A11.955 11.955 0 0112 2.944a11.955 11.955 0 01-8.618 3.04A12.02 12.02 0 003 9c0 5.591 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.042-.133-2.052-.382-3.016z','label'=>'Militantes','rol'=>'editor','modulo'=>'militantes','solo_rol'=>null,'submenu'=>null],
      ['id'=>'personeros','href'=>'personeros.php','icon'=>'M10 6H5a2 2 0 00-2 2v9a2 2 0 002 2h14a2 2 0 002-2V8a2 2 0 00-2-2h-5m-4 0V5a2 2 0 114 0v1m-4 0a2 2 0 104 0m-5 8a2 2 0 100-4 2 2 0 000 4zm0 0c1.306 0 2.417.835 2.83 2M9 14a3.001 3.001 0 00-2.83 2','label'=>'Personeros','rol'=>'editor','modulo'=>null,'solo_rol'=>null,'submenu'=>null],
      ['id'=>'config_plan','href'=>'config-plan.php','icon'=>'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z','label'=>'Plan de Gobierno','rol'=>'admin','modulo'=>'configuracion_global','solo_rol'=>null,'submenu'=>null],
      ['id'=>'material_pub','href'=>'material-publicitario.php','icon'=>'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z','label'=>'Material Publicitario','rol'=>'editor','modulo'=>'material_publicitario','solo_rol'=>null,'submenu'=>null],
    ],
  ],
  [
    'titulo' => 'CONFIGURACION',
    'items'  => [
      ['id'=>'configurar','href'=>'#','icon'=>'M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z M15 12a3 3 0 11-6 0 3 3 0 016 0z','label'=>'Configurar','rol'=>'admin','modulo'=>'configuracion_global','solo_rol'=>null,
        'submenu'=>[
          ['href'=>'menu-manager.php',       'label'=>'Menu Web',          'icon'=>'M4 6h16M4 12h16M4 18h16',                                                                                                                                                                                                                                                                                               'rol'=>'admin',      'modulo'=>'menu_web',              'solo_rol'=>null],
          ['href'=>'config-index.php',        'label'=>'Configurar Index',  'icon'=>'M3 4a1 1 0 011-1h16a1 1 0 011 1v5H3V4zm0 7h8v10H4a1 1 0 01-1-1v-9zm10 10V11h8v9a1 1 0 01-1 1h-7z',                                                                                                                                                                                                                    'rol'=>'admin',      'modulo'=>'configuracion_global',  'solo_rol'=>null],
          ['href'=>'seo.php',                 'label'=>'SEO por Pagina',    'icon'=>'M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z',                                                                                                                                                                                                                                                                           'rol'=>'admin',      'modulo'=>'seo',                   'solo_rol'=>null],
          ['href'=>'tools/migrate-webp.php',  'label'=>'Migrar a WebP',     'icon'=>'M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z',                                                                                                                                                          'rol'=>'admin',      'modulo'=>'configuracion_global',  'solo_rol'=>null],
          ['href'=>'auditoria.php',           'label'=>'Auditoria',         'icon'=>'M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-3 7h3m-3 4h3m-6-4h.01M9 16h.01',                                                                                                                                                    'rol'=>'admin',      'modulo'=>'auditoria',             'solo_rol'=>null],
          ['href'=>'usuarios.php',            'label'=>'Usuarios',          'icon'=>'M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z',                                                                                                                                                                                                      'rol'=>'superadmin', 'modulo'=>'usuarios',             'solo_rol'=>null],
          ['href'=>'editores.php',            'label'=>'Gestionar Editores','icon'=>'M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z',                                                                                                                                                                                                                               'rol'=>'admin',      'modulo'=>'usuarios',              'solo_rol'=>'admin'],
          ['href'=>'backup.php',              'label'=>'Backup',            'icon'=>'M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M9 19l3 3m0 0l3-3m-3 3V10',                                                                                                                                                                                                                                            'rol'=>'admin',      'modulo'=>'configuracion_global',  'solo_rol'=>null],
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
  <link rel="apple-touch-icon" href="<?= BASE_URL ?>/assets/img/logos/logorp.webp">
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
                <svg class="w-4 h-4 flex-shrink-0 opacity-80 group-hover:opacity-100"
                     fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="<?= $item['icon'] ?>"/>
                </svg>
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
          <svg class="w-4 h-4 text-[#FACC15] flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" :d="currentSub?.icon ?? ''"/>
          </svg>
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
            <svg class="w-3.5 h-3.5 flex-shrink-0 text-gray-400 group-hover:text-[#FACC15] transition-colors"
                 fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" :d="sub.icon"/>
            </svg>
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

