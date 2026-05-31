<?php
// ============================================================
// NAVBAR - Portal Ivan Cisneros
// Menu dinamico desde DB (menu_items) con fallback hardcodeado
// ============================================================
require_once __DIR__ . '/helpers/config.php';
require_once __DIR__ . '/helpers/colors.php';

if (!isset($cfg_camp)) {
    $cfg_camp = [];
    try {
        $cfg_camp = $pdo->query("SELECT clave, valor FROM configuracion")
                        ->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Exception $e) {}
}

// Cargar distritos activos (para dropdown candidatos)
$nav_distritos = [];
try {
    $nav_distritos = $pdo->query(
        "SELECT slug, distrito FROM candidatos_distritales
         WHERE activo = 1 ORDER BY orden ASC, id ASC"
    )->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {}

// Cargar items del menu desde DB (con fallback)
$menu_items_db = null;
$nav_child_map = [];
try {
    $all_nav = $pdo->query(
        "SELECT * FROM menu_items WHERE visible=1 ORDER BY orden ASC"
    )->fetchAll();
    $menu_items_db = array_filter($all_nav, fn($i) => $i['parent_id'] === null);
    $menu_items_db = array_values($menu_items_db);
    foreach ($all_nav as $ni) {
        if ($ni['parent_id'] !== null) {
            $nav_child_map[(int)$ni['parent_id']][] = $ni;
        }
    }
} catch (Exception $e) {
    $menu_items_db = null;
    $nav_child_map = [];
}

// Fallback hardcodeado (igual al menu original)
$menu_fallback = [
    ['label'=>'Inicio',                'url'=>'/index.php',          'tipo'=>'interno',         'target'=>'_self'],
    ['label'=>'Quien es?',             'url'=>'/index.php#quien-es', 'tipo'=>'interno',         'target'=>'_self'],
    ['label'=>'Plan de Gobierno',      'url'=>'/plan.php',           'tipo'=>'interno',         'target'=>'_self'],
    ['label'=>($cfg_camp['label_equipo'] ?? 'Candidatos Distritales'), 'url'=>'#', 'tipo'=>'auto-candidatos', 'target'=>'_self'],
    ['label'=>'Noticias',              'url'=>'/noticias/index.php', 'tipo'=>'interno',         'target'=>'_self'],
    ['label'=>'Unete',                 'url'=>'/index.php#unete',    'tipo'=>'interno',         'target'=>'_self'],
];

$nav_items = ($menu_items_db !== null && count($menu_items_db) > 0) ? $menu_items_db : $menu_fallback;

// Helper: construir URL completa para items internos
function nav_url(string $url, string $tipo): string {
    if ($tipo === 'externo' || $tipo === 'auto-candidatos') return $url;
    return BASE_URL . $url;
}
?>
<?php if (!function_exists('_nav_colors_done')) { function _nav_colors_done(){} echo render_color_vars($cfg_camp); } ?>
<style>
  @font-face {
    font-family: 'Signature';
    src: url('<?= BASE_URL ?>/assets/fonts/signature.ttf') format('truetype');
    font-weight: normal;
    font-style: normal;
    font-display: swap;
  }
</style>

<nav x-data="{ open: false }"
     class="fixed top-0 left-0 right-0 z-50 bg-white shadow-md">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
    <div class="flex items-center justify-between h-16">

      <!-- Logo -->
      <a href="<?= BASE_URL ?>/index.php" class="flex items-center gap-3 flex-shrink-0">
        <img src="<?= htmlspecialchars(cfg_site_url(cfg_value($cfg_camp, 'site_header_logo', '/assets/img/logos/logorp.webp'))) ?>"
             alt="<?= htmlspecialchars(cfg_value($cfg_camp, 'site_header_logo_alt', 'ALIANZA PARA EL PROGRESO')) ?>" class="h-9 w-auto object-contain"
             onerror="this.style.display='none'">
        <span class="w-px h-7 bg-gray-200 flex-shrink-0"></span>
        <span class="hidden sm:block text-[#1E3A8A]"
              style="font-family:'Signature',cursive; font-size:1.6rem; line-height:1;">
          <?= htmlspecialchars(cfg_value($cfg_camp, 'site_header_signature', 'Ivan Cisneros')) ?>
        </span>
      </a>

      <!-- Menu desktop -->
      <div class="hidden lg:flex items-center gap-6 text-sm font-medium text-gray-700">
        <?php foreach ($nav_items as $item):
          $item_id      = (int)($item['id'] ?? 0);
          $has_children = !empty($nav_child_map[$item_id]);
          $is_dropdown  = $item['tipo'] === 'auto-candidatos' || $has_children;
        ?>
          <?php if ($item['tipo'] === 'auto-candidatos'): ?>
          <!-- Dropdown AUTO: candidatos distritales -->
          <div class="relative" x-data="{ open: false }"
               @mouseenter="open = true" @mouseleave="open = false">
            <button class="flex items-center gap-1.5 hover:text-[#1E3A8A] transition-colors group">
              <?= htmlspecialchars($item['label']) ?>
              <svg class="w-3.5 h-3.5 transition-transform duration-300 group-hover:rotate-180"
                   fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
              </svg>
            </button>
            <div x-show="open" x-cloak
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95 -translate-y-2"
                 x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                 x-transition:leave-end="opacity-0 scale-95 -translate-y-2"
                 class="absolute top-full left-0 mt-2 w-56 bg-white rounded-2xl shadow-xl border border-gray-100 py-2 z-50 origin-top-left">
              <?php foreach ($nav_distritos as $nd): ?>
                <a href="<?= BASE_URL ?>/distritales/<?= htmlspecialchars($nd['slug']) ?>.php"
                   class="flex items-center gap-2.5 mx-2 px-3 py-2 text-sm text-gray-600 font-medium
                          rounded-xl hover:bg-[#1E3A8A] hover:text-white transition-all duration-150 group">
                  <span class="w-1.5 h-1.5 rounded-full bg-[#38BDF8] flex-shrink-0
                               group-hover:bg-[#FACC15] transition-colors"></span>
                  <?= htmlspecialchars($nd['distrito']) ?>
                </a>
              <?php endforeach; ?>
              <?php if (empty($nav_distritos)): ?>
                <p class="px-5 py-3 text-xs text-gray-400 italic">Sin candidatos activos</p>
              <?php endif; ?>
            </div>
          </div>

          <?php elseif ($has_children): ?>
          <!-- Dropdown dinamico: item con subitems -->
          <div class="relative" x-data="{ open: false }"
               @mouseenter="open = true" @mouseleave="open = false">
            <button class="flex items-center gap-1.5 hover:text-[#1E3A8A] transition-colors group">
              <?= htmlspecialchars($item['label']) ?>
              <svg class="w-3.5 h-3.5 transition-transform duration-300 group-hover:rotate-180"
                   fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/>
              </svg>
            </button>
            <div x-show="open" x-cloak
                 x-transition:enter="transition ease-out duration-200"
                 x-transition:enter-start="opacity-0 scale-95 -translate-y-2"
                 x-transition:enter-end="opacity-100 scale-100 translate-y-0"
                 x-transition:leave="transition ease-in duration-150"
                 x-transition:leave-start="opacity-100 scale-100 translate-y-0"
                 x-transition:leave-end="opacity-0 scale-95 -translate-y-2"
                 class="absolute top-full left-0 mt-2 w-56 bg-white rounded-2xl shadow-xl border border-gray-100 py-2 z-50 origin-top-left">
              <?php foreach ($nav_child_map[$item_id] as $child): ?>
              <a href="<?= htmlspecialchars(nav_url($child['url'], $child['tipo'])) ?>"
                 target="<?= $child['target'] === '_blank' ? '_blank' : '_self' ?>"
                 class="flex items-center gap-2.5 mx-2 px-3 py-2 text-sm text-gray-600 font-medium
                        rounded-xl hover:bg-[#1E3A8A] hover:text-white transition-all duration-150 group">
                <span class="w-1.5 h-1.5 rounded-full bg-[#38BDF8] flex-shrink-0
                             group-hover:bg-[#FACC15] transition-colors"></span>
                <?= htmlspecialchars($child['label']) ?>
              </a>
              <?php endforeach; ?>
            </div>
          </div>

          <?php else: ?>
          <!-- Item simple -->
          <a href="<?= htmlspecialchars(nav_url($item['url'], $item['tipo'])) ?>"
             target="<?= $item['target'] === '_blank' ? '_blank' : '_self' ?>"
             class="hover:text-[#1E3A8A] transition-colors">
            <?= htmlspecialchars($item['label']) ?>
          </a>
          <?php endif; ?>
        <?php endforeach; ?>
      </div>

      <!-- CTA + hamburger -->
      <div class="flex items-center gap-3">
        <a href="<?= htmlspecialchars(cfg_site_url(cfg_value($cfg_camp, 'site_header_cta_url', '/index.php#unete'))) ?>"
           class="btn-dyn hidden sm:inline-flex items-center font-bold text-sm px-4 py-2 rounded-full transition-colors shadow"
           style="background-color:var(--color-btn-cta-navbar);color:var(--color-btn-cta-navbar-text)">
          <?= htmlspecialchars(cfg_value($cfg_camp, 'site_header_cta_text', 'Unete al equipo')) ?>
        </a>
        <button @click="open = !open" class="lg:hidden p-2 rounded-lg text-gray-600 hover:bg-gray-100">
          <svg x-show="!open" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 6h16M4 12h16M4 18h16"/>
          </svg>
          <svg x-show="open" class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
          </svg>
        </button>
      </div>
    </div>
  </div>

  <!-- Menu movil -->
  <div x-show="open" x-transition class="lg:hidden bg-white border-t border-gray-100 px-4 py-4 space-y-2 max-h-[calc(100vh-4rem)] overflow-y-auto">
    <?php foreach ($nav_items as $item):
      $item_id      = (int)($item['id'] ?? 0);
      $has_children = !empty($nav_child_map[$item_id]);
    ?>
      <?php if ($item['tipo'] === 'auto-candidatos'): ?>
      <div x-data="{ sub: false }">
        <button @click="sub=!sub" class="flex items-center justify-between w-full py-2 text-gray-700 font-medium hover:text-[#1E3A8A]">
          <?= htmlspecialchars($item['label']) ?>
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
          </svg>
        </button>
        <div x-show="sub" class="pl-4 space-y-1">
          <?php foreach ($nav_distritos as $nd): ?>
          <a href="<?= BASE_URL ?>/distritales/<?= htmlspecialchars($nd['slug']) ?>.php"
             @click="open = false"
             class="block py-1.5 text-sm text-gray-600 hover:text-[#1E3A8A]">
            <?= htmlspecialchars($nd['distrito']) ?>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php elseif ($has_children): ?>
      <div x-data="{ sub: false }">
        <button @click="sub=!sub" class="flex items-center justify-between w-full py-2 text-gray-700 font-medium hover:text-[#1E3A8A]">
          <?= htmlspecialchars($item['label']) ?>
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
          </svg>
        </button>
        <div x-show="sub" class="pl-4 space-y-1">
          <?php foreach ($nav_child_map[$item_id] as $child): ?>
          <a href="<?= htmlspecialchars(nav_url($child['url'], $child['tipo'])) ?>"
             @click="open = false"
             target="<?= $child['target'] === '_blank' ? '_blank' : '_self' ?>"
             class="block py-1.5 text-sm text-gray-600 hover:text-[#1E3A8A]">
            <?= htmlspecialchars($child['label']) ?>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php else: ?>
      <a href="<?= htmlspecialchars(nav_url($item['url'], $item['tipo'])) ?>"
         @click="open = false"
         target="<?= $item['target'] === '_blank' ? '_blank' : '_self' ?>"
         class="block py-2 text-gray-700 font-medium hover:text-[#1E3A8A]">
        <?= htmlspecialchars($item['label']) ?>
      </a>
      <?php endif; ?>
    <?php endforeach; ?>

    <a href="<?= htmlspecialchars(cfg_site_url(cfg_value($cfg_camp, 'site_header_cta_url', '/index.php#unete'))) ?>"
       @click="open = false"
       class="btn-dyn block mt-2 text-center font-bold py-2 rounded-full"
       style="background-color:var(--color-btn-cta-navbar);color:var(--color-btn-cta-navbar-text)">
      <?= htmlspecialchars(cfg_value($cfg_camp, 'site_header_cta_text', 'Unete al equipo')) ?>
    </a>
  </div>
</nav>

