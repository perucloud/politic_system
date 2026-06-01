<?php
// ============================================================
// FOOTER - Portal Ivan Cisneros
// 4 columnas: Marca | Navegacion | Distritales | Contacto
// ============================================================
require_once __DIR__ . '/helpers/config.php';

if (!isset($cfg_camp)) {
    $cfg_camp = [];
    try {
        $cfg_camp = $pdo->query("SELECT clave, valor FROM configuracion")->fetchAll(PDO::FETCH_KEY_PAIR);
    } catch (Exception $e) {}
}

$footer_distritos = [];
try {
    $footer_distritos = $pdo->query(
        "SELECT slug, distrito FROM candidatos_distritales
         WHERE activo = 1 ORDER BY orden ASC, id ASC"
    )->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {}
if (!function_exists('footer_whatsapp_url')) {
    function footer_whatsapp_url(array $cfg_camp): string {
        $number = preg_replace('/\D+/', '', cfg_value($cfg_camp, 'site_footer_whatsapp_number', ''));
        if ($number !== '') {
            $message = trim(cfg_value($cfg_camp, 'site_footer_whatsapp_message', ''));
            return 'https://wa.me/' . $number . ($message !== '' ? '?text=' . rawurlencode($message) : '');
        }

        $url = trim(cfg_value($cfg_camp, 'site_footer_whatsapp_url', 'https://wa.me/51999999999'));
        if ($url === '' || $url === '#') return '';
        return $url;
    }
}

$floating_whatsapp_url  = footer_whatsapp_url($cfg_camp);
$floating_whatsapp_text = cfg_value($cfg_camp, 'site_footer_whatsapp_text', 'WhatsApp');
$footer_whatsapp_url = footer_whatsapp_url($cfg_camp);

// Contador de visitas para el footer
$footer_visit_active = cfg_value($cfg_camp, 'visit_counter_active', '1') === '1';
$footer_total_visitas = 0;
if ($footer_visit_active) {
    try {
        $footer_total_visitas = (int)$pdo->query("SELECT COUNT(*) FROM visitas")->fetchColumn();
    } catch (Exception $e) {}
}
?>
<footer class="bg-[#1E3A8A] text-white">
  <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-14">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-10">

      <!-- Col 1: Marca -->
      <div>
        <div class="flex items-center gap-2 mb-4">
          <img src="<?= htmlspecialchars(cfg_site_url(cfg_value($cfg_camp, 'site_footer_logo', '/assets/img/logos/logorp.webp'))) ?>"
               alt="Logo" class="h-12 w-auto"
               onerror="this.style.display='none'">
        </div>
        <p class="font-bold text-lg leading-tight"><?= htmlspecialchars(cfg_value($cfg_camp, 'site_footer_name', 'Lic. Ivan Cisneros')) ?></p>
        <p class="text-[#38BDF8] text-sm mt-1"><?= htmlspecialchars(cfg_value($cfg_camp, 'site_footer_party', 'ALIANZA PARA EL PROGRESO')) ?></p>
        <p class="text-gray-300 text-sm mt-2 italic"><?= htmlspecialchars(cfg_value($cfg_camp, 'site_footer_slogan', '"Ha llegado el momento de transformar Satipo"')) ?></p>
        <p class="text-gray-400 text-xs mt-6">&copy; <?= date('Y') ?> <?= htmlspecialchars(cfg_value($cfg_camp, 'site_footer_copyright', 'Todos los derechos reservados.')) ?></p>
      </div>

      <!-- Col 2: Navegacion rapida -->
      <div>
        <h4 class="font-bold text-[#38BDF8] uppercase text-xs tracking-widest mb-4"><?= htmlspecialchars(cfg_value($cfg_camp, 'site_footer_nav_title', 'Navegacion')) ?></h4>
        <ul class="space-y-2 text-sm text-gray-300">
          <li><a href="<?= BASE_URL ?>/index.php" class="hover:text-white transition-colors">Inicio</a></li>
          <li><a href="<?= BASE_URL ?>/index.php#quien-es" class="hover:text-white transition-colors">Quien es?</a></li>
          <li><a href="<?= BASE_URL ?>/plan.php" class="hover:text-white transition-colors">Plan de Gobierno</a></li>
          <li><a href="<?= BASE_URL ?>/index.php#noticias" class="hover:text-white transition-colors">Noticias</a></li>
          <li><a href="<?= BASE_URL ?>/index.php#agenda" class="hover:text-white transition-colors">Agenda</a></li>
          <li><a href="<?= BASE_URL ?>/index.php#unete" class="hover:text-white transition-colors">Unete al equipo</a></li>
        </ul>

        <!-- Intranet — acceso discreto -->
        <div class="mt-5 pt-4 border-t border-blue-800/60">
          <a href="<?= BASE_URL ?>/admin/login.php"
             class="inline-flex items-center gap-2 group
                    text-gray-500 hover:text-gray-300 transition-all duration-200">
            <span class="flex items-center justify-center w-6 h-6 rounded-lg
                         bg-blue-900/60 border border-blue-700/40
                         group-hover:bg-blue-800 group-hover:border-blue-600
                         transition-all duration-200">
              <svg class="w-3 h-3 text-gray-500 group-hover:text-[#38BDF8] transition-colors"
                   fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                      d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
              </svg>
            </span>
            <span class="text-xs font-semibold tracking-wide">Intranet</span>
          </a>
          <p class="text-[10px] text-gray-600 mt-1 ml-8">Acceso al panel administrativo</p>
        </div>
      </div>

      <!-- Col 3: Candidatos distritales -->
      <div>
        <h4 class="font-bold text-[#38BDF8] uppercase text-xs tracking-widest mb-4"><?= htmlspecialchars(cfg_value($cfg_camp, 'site_footer_team_title', 'Candidatos Distritales')) ?></h4>
        <ul class="space-y-2 text-sm text-gray-300">
          <?php
          $distritos = $footer_distritos ?: [
            'rio-negro'     => 'Rio Negro',
            'pangoa'        => 'Pangoa',
            'rio-tambo'     => 'Rio Tambo',
            'coviriali'     => 'Coviriali',
            'llaylla'       => 'Llaylla',
            'vizcatan'      => 'Vizcatan del Ene',
            'pampa-hermosa' => 'Pampa Hermosa',
            'mazamari'      => 'Mazamari',
          ];
          foreach ($distritos as $slug => $nombre): ?>
            <li>
              <a href="<?= BASE_URL ?>/distritales/<?= $slug ?>.php"
                 class="hover:text-white transition-colors"><?= $nombre ?></a>
            </li>
          <?php endforeach; ?>
        </ul>
      </div>

      <!-- Col 4: Contacto y redes -->
      <div>
        <h4 class="font-bold text-[#38BDF8] uppercase text-xs tracking-widest mb-4"><?= htmlspecialchars(cfg_value($cfg_camp, 'site_footer_contact_title', 'Contacto')) ?></h4>
        <ul class="space-y-3 text-sm text-gray-300">
          <li class="flex items-start gap-2">
            <svg class="w-4 h-4 mt-0.5 text-[#38BDF8] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <?= htmlspecialchars(cfg_value($cfg_camp, 'site_footer_address', 'Satipo, Junin, Peru')) ?>
          </li>
          <li class="flex items-start gap-2">
            <svg class="w-4 h-4 mt-0.5 text-[#38BDF8] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
            </svg>
            <?= htmlspecialchars(cfg_value($cfg_camp, 'site_footer_email', 'contacto@ivancisneros.pe')) ?>
          </li>
          <li>
            <a href="<?= htmlspecialchars($footer_whatsapp_url) ?>" target="_blank"
               class="flex items-center gap-2 bg-green-600 hover:bg-green-500 text-white text-xs font-bold px-3 py-2 rounded-full transition-colors w-fit">
              <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                <path d="M12.004 2C6.477 2 2 6.477 2 12.004c0 1.773.463 3.486 1.345 4.999L2 22l5.13-1.345A9.953 9.953 0 0012.004 22C17.53 22 22 17.523 22 11.996 22 6.477 17.53 2 12.004 2zm0 18.18a8.144 8.144 0 01-4.158-1.137l-.298-.177-3.046.799.815-2.979-.194-.307A8.154 8.154 0 013.82 12.004c0-4.514 3.672-8.184 8.184-8.184 4.514 0 8.184 3.67 8.184 8.184 0 4.512-3.67 8.176-8.184 8.176z"/>
              </svg>
              <?= htmlspecialchars(cfg_value($cfg_camp, 'site_footer_whatsapp_text', 'WhatsApp')) ?>
            </a>
          </li>
        </ul>

        <!-- Redes sociales -->
        <div class="flex items-center gap-3 mt-5">
          <a href="<?= htmlspecialchars(cfg_value($cfg_camp, 'site_footer_facebook_url', '#')) ?>" class="text-gray-400 hover:text-[#38BDF8] transition-colors" aria-label="Facebook">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
              <path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>
            </svg>
          </a>
          <a href="<?= htmlspecialchars(cfg_value($cfg_camp, 'site_footer_instagram_url', '#')) ?>" class="text-gray-400 hover:text-[#38BDF8] transition-colors" aria-label="Instagram">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
              <path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/>
            </svg>
          </a>
          <a href="<?= htmlspecialchars(cfg_value($cfg_camp, 'site_footer_tiktok_url', '#')) ?>" class="text-gray-400 hover:text-[#38BDF8] transition-colors" aria-label="TikTok">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
              <path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/>
            </svg>
          </a>
          <a href="<?= htmlspecialchars(cfg_value($cfg_camp, 'site_footer_youtube_url', '#')) ?>" class="text-gray-400 hover:text-[#38BDF8] transition-colors" aria-label="YouTube">
            <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
              <path d="M23.498 6.186a3.016 3.016 0 00-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 00.502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 002.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 002.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/>
            </svg>
          </a>
        </div>
      </div>
    </div>
  </div>

  <!-- Barra inferior -->
  <div class="border-t border-blue-800">
    <div class="max-w-7xl mx-auto px-4 py-4 flex flex-col sm:flex-row items-center justify-between gap-2 text-xs text-gray-400">
      <span>&copy; <?= date('Y') ?> <?= htmlspecialchars(cfg_value($cfg_camp, 'site_footer_bottom_left', 'Lic. Ivan Cisneros - Satipo, Junin, Peru')) ?></span>
      <?php if ($footer_visit_active && $footer_total_visitas > 0): ?>
      <span class="flex items-center gap-1.5 text-gray-500">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
        </svg>
        <?= number_format($footer_total_visitas) ?> visitas
      </span>
      <?php endif; ?>
      <span class="mt-1 sm:mt-0"><?= htmlspecialchars(cfg_value($cfg_camp, 'site_footer_bottom_right', 'Diseno web politico - Todos los derechos reservados')) ?></span>
    </div>
  </div>
</footer>

<?php if ($floating_whatsapp_url !== ''): ?>
<a href="<?= htmlspecialchars($floating_whatsapp_url) ?>"
   target="_blank"
   rel="noopener"
   aria-label="<?= htmlspecialchars($floating_whatsapp_text) ?>"
   class="fixed right-4 bottom-4 sm:right-6 sm:bottom-6 z-50 group inline-flex items-center gap-3 rounded-full bg-[#25D366] px-4 py-3 text-white shadow-[0_16px_40px_rgba(37,211,102,.35)] transition-all duration-200 hover:-translate-y-1 hover:bg-[#1DB954] hover:shadow-[0_20px_46px_rgba(37,211,102,.45)] focus:outline-none focus:ring-4 focus:ring-green-200">
  <span class="relative flex h-11 w-11 items-center justify-center rounded-full bg-white text-[#25D366] shadow-inner">
    <svg class="h-6 w-6" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
      <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
      <path d="M12.004 2C6.477 2 2 6.477 2 12.004c0 1.773.463 3.486 1.345 4.999L2 22l5.13-1.345A9.953 9.953 0 0012.004 22C17.53 22 22 17.523 22 11.996 22 6.477 17.53 2 12.004 2zm0 18.18a8.144 8.144 0 01-4.158-1.137l-.298-.177-3.046.799.815-2.979-.194-.307A8.154 8.154 0 013.82 12.004c0-4.514 3.672-8.184 8.184-8.184 4.514 0 8.184 3.67 8.184 8.184 0 4.512-3.67 8.176-8.184 8.176z"/>
    </svg>
    <span class="absolute inset-0 rounded-full border border-white/60 opacity-0 transition-all group-hover:scale-125 group-hover:opacity-100"></span>
  </span>
  <span class="hidden pr-1 text-sm font-black sm:inline"><?= htmlspecialchars($floating_whatsapp_text) ?></span>
</a>
<?php endif; ?>

