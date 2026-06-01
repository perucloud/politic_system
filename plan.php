<?php
require_once __DIR__ . '/includes/config/db.php';
require_once __DIR__ . '/includes/helpers/config.php';

$cfg_camp = [];
try {
    $cfg_camp = $pdo->query("SELECT clave, valor FROM configuracion")->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {}

$work_axes = array_values(array_filter(
    cfg_json($cfg_camp, 'index_work_axes', cfg_default_work_axes()),
    fn($e) => ($e['active'] ?? true) !== false
));

// ── Configuración editable del plan (admin → Plan de Gobierno) ─
$plan_hero_logo   = cfg_value($cfg_camp, 'plan_hero_logo',  '/assets/img/logos/logo_renovacion_popular.webp');
$plan_hero_img    = cfg_value($cfg_camp, 'plan_hero_img',   '/assets/img/candidato/agricultura.webp');
$plan_hero_anios  = cfg_value($cfg_camp, 'plan_hero_anios', '2026 - 2030');
$plan_hero_slogan = cfg_value($cfg_camp, 'plan_hero_slogan', '"Satipo que crece, Satipo que avanza"');
$plan_pdf_titulo  = cfg_value($cfg_camp, 'plan_pdf_titulo', 'Descarga el Plan de Gobierno Completo');
$plan_pdf_desc    = cfg_value($cfg_camp, 'plan_pdf_desc',   'Version extendida en PDF con todos los detalles y proyecciones 2026-2030.');
$plan_cta_titulo  = cfg_value($cfg_camp, 'plan_cta_titulo', 'Compartes esta vision para Satipo?');
$plan_cta_texto   = cfg_value($cfg_camp, 'plan_cta_texto',  'Unete al equipo y se parte del cambio.');

$plan_pdf_rel    = '/assets/docs/plan.pdf';
$plan_pdf_exists = file_exists(__DIR__ . $plan_pdf_rel);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Plan de Gobierno 2026-2030 - Ivan Cisneros - Satipo</title>
  <meta name="description" content="Plan de gobierno del Lic. Ivan Cisneros para la Alcaldia Provincial de Satipo 2026-2030.">
  <?php $fv=cfg_value($cfg_camp,'site_favicon','/assets/img/logos/logorp.webp'); $fv_url=(str_starts_with($fv,'/')?BASE_URL:'').$fv; ?>
  <link rel="icon" href="<?= htmlspecialchars($fv_url) ?>">
  <link rel="apple-touch-icon" href="<?= htmlspecialchars($fv_url) ?>">

  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: { primary: '#1E3A8A', secondary: '#38BDF8', accent: '#FACC15' },
          fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] }
        }
      }
    }
  </script>
  <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <link rel="stylesheet" href="https://unpkg.com/aos@2.3.4/dist/aos.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/custom.css">
  <?php require_once __DIR__ . '/includes/helpers/colors.php'; echo render_color_vars($cfg_camp); ?>
</head>
<body class="font-sans text-gray-800 bg-white">

  <?php include __DIR__ . '/includes/navbar.php'; ?>

  <!-- HERO PLAN -->
  <section class="relative pt-16 min-h-[40vh] flex items-end overflow-hidden">
    <div class="absolute inset-0">
      <img src="<?= htmlspecialchars(cfg_site_url($plan_hero_img)) ?>"
           alt="Plan de Gobierno" class="w-full h-full object-cover"
           onerror="this.style.background='#1E3A8A'">
      <div class="absolute inset-0 bg-gradient-to-r from-[#1E3A8A] to-[#1E3A8A]/70"></div>
    </div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-16 text-white" data-aos="fade-up">
      <!-- Breadcrumb -->
      <nav class="text-sm text-blue-300 mb-6">
        <a href="<?= BASE_URL ?>/index.php" class="hover:text-white">Inicio</a>
        <span class="mx-2">&rsaquo;</span>
        <span class="text-white">Plan de Gobierno</span>
      </nav>

      <div class="flex flex-col sm:flex-row items-start sm:items-center gap-8">
        <!-- Logo configurable -->
        <div class="flex-shrink-0">
          <img src="<?= htmlspecialchars(cfg_site_url($plan_hero_logo)) ?>"
               alt="Logo"
               class="h-28 sm:h-36 w-auto object-contain drop-shadow-xl"
               onerror="this.style.display='none'">
        </div>

        <!-- Texto -->
        <div>
          <h1 class="text-4xl sm:text-5xl font-black mb-3 leading-tight">
            Plan de Gobierno<br>
            <span class="text-[#FACC15]"><?= htmlspecialchars($plan_hero_anios) ?></span>
          </h1>
          <p class="text-xl text-blue-100 font-medium">
            <?= htmlspecialchars($plan_hero_slogan) ?>
          </p>
        </div>
      </div>
    </div>
  </section>

  <!-- INDICE / NAV INTERNA -->
  <?php $ejes = $work_axes; ?>
  <div class="sticky top-16 z-40 bg-white border-b border-gray-200 shadow-sm overflow-x-auto">
    <div class="flex items-center gap-2 px-4 py-3 max-w-7xl mx-auto w-max sm:w-auto sm:flex-wrap sm:justify-center">
      <?php foreach ($ejes as $eje): ?>
      <a href="#<?= htmlspecialchars($eje['id'] ?? '') ?>"
         class="flex-shrink-0 inline-flex items-center gap-1.5 text-xs font-semibold px-3 py-1.5 rounded-full border <?= htmlspecialchars($eje['nav_color'] ?? 'bg-blue-100 text-blue-700') ?> <?= htmlspecialchars($eje['nav_border'] ?? 'border-blue-300') ?> hover:opacity-80 transition-opacity whitespace-nowrap">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= htmlspecialchars(cfg_axis_icon_path((string)($eje['icon'] ?? 'gestion'))) ?>"/>
        </svg>
        <?= htmlspecialchars($eje['label'] ?? $eje['title'] ?? '') ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>

  <!-- EJES TEMATICOS -->

  <?php foreach ($ejes as $i => $eje):
    $desc = array_values(array_filter(array_map('trim', preg_split('/\R/', (string)($eje['plan_desc'] ?? '')))));
    if (empty($desc)) $desc = [(string)($eje['desc'] ?? '')];
    $propuestas = array_values(array_filter(array_map('trim', preg_split('/\R/', (string)($eje['proposals'] ?? '')))));
    $alt  = ($i % 2 !== 0);
  ?>
  <section id="<?= htmlspecialchars($eje['id'] ?? '') ?>" class="py-20" style="background-color: <?= htmlspecialchars($eje['section_bg'] ?? '#EFF6FF') ?>">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center <?= $alt ? 'lg:flex-row-reverse' : '' ?>">

        <!-- Contenido -->
        <div class="<?= $alt ? 'lg:order-2' : '' ?>" data-aos="<?= $alt ? 'fade-left' : 'fade-right' ?>">
          <div class="flex items-center gap-3 mb-6">
            <div class="w-14 h-14 rounded-2xl bg-white shadow flex items-center justify-center text-[#1E3A8A]">
              <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= htmlspecialchars(cfg_axis_icon_path((string)($eje['icon'] ?? 'gestion'))) ?>"/>
              </svg>
            </div>
            <div>
              <span class="block text-xs font-semibold uppercase tracking-widest text-gray-400">
                Eje <?= $i + 1 ?>
              </span>
              <h2 class="text-2xl sm:text-3xl font-black text-[#1E3A8A]">
                <?= htmlspecialchars($eje['title'] ?? '') ?>
              </h2>
            </div>
          </div>

          <?php foreach ($desc as $p): ?>
          <p class="text-gray-600 leading-relaxed mb-4"><?= htmlspecialchars($p) ?></p>
          <?php endforeach; ?>

          <h3 class="font-bold text-[#1E3A8A] mt-6 mb-3">Propuestas concretas:</h3>
          <ul class="space-y-2">
            <?php foreach ($propuestas as $prop): ?>
            <li class="flex items-start gap-2 text-sm text-gray-700">
              <span class="mt-1 flex-shrink-0 w-4 h-4 rounded-full bg-[#FACC15] flex items-center justify-center">
                <svg class="w-2.5 h-2.5 text-[#1E3A8A]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/>
                </svg>
              </span>
              <?= htmlspecialchars($prop) ?>
            </li>
            <?php endforeach; ?>
          </ul>
        </div>

        <!-- Imagen -->
        <div class="flex justify-center <?= $alt ? 'lg:order-1' : '' ?>"
             data-aos="<?= $alt ? 'fade-right' : 'fade-left' ?>" data-aos-delay="100">
          <div class="relative w-full max-w-md">
            <div class="absolute -inset-3 rounded-3xl blur-xl opacity-40"
                 style="background-color: <?= htmlspecialchars($eje['section_border'] ?? '#BFDBFE') ?>"></div>
            <img src="<?= htmlspecialchars(cfg_site_url((string)($eje['img'] ?? ''))) ?>"
                 alt="<?= htmlspecialchars($eje['title'] ?? '') ?>"
                 class="relative w-full rounded-2xl shadow-xl object-cover h-72"
                 onerror="this.src='https://placehold.co/480x288/1E3A8A/white?text=<?= urlencode((string)($eje['title'] ?? 'Eje')) ?>'">
          </div>
        </div>
      </div>
    </div>
  </section>
  <?php endforeach; ?>

  <!-- DESCARGA PDF -->
  <section class="py-16 bg-[#1E3A8A]">
    <div class="max-w-3xl mx-auto px-4 text-center text-white" data-aos="fade-up">
      <div class="text-sm font-black tracking-widest text-[#FACC15] mb-4">PDF</div>
      <h2 class="text-2xl sm:text-3xl font-black mb-3"><?= htmlspecialchars($plan_pdf_titulo) ?></h2>
      <p class="text-blue-200 mb-8"><?= htmlspecialchars($plan_pdf_desc) ?></p>
      <?php if ($plan_pdf_exists): ?>
      <a href="<?= BASE_URL . $plan_pdf_rel ?>"
         download
         class="btn-dyn inline-flex items-center gap-2 font-black px-10 py-4 rounded-full transition-all shadow-lg text-base"
         style="background-color:var(--color-btn-download);color:var(--color-btn-download-text)">
        <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
        Descargar PDF
      </a>
      <?php else: ?>
      <span class="inline-flex items-center gap-2 bg-white/10 text-white/70 font-black px-10 py-4 rounded-full text-base border border-white/15">
        PDF en preparacion
      </span>
      <?php endif; ?>
    </div>
  </section>

  <!-- CTA FINAL -->
  <section class="py-16 bg-white text-center" data-aos="fade-up">
    <div class="max-w-xl mx-auto px-4">
      <h2 class="text-2xl sm:text-3xl font-black text-[#1E3A8A] mb-3">
        <?= htmlspecialchars($plan_cta_titulo) ?>
      </h2>
      <p class="text-gray-500 mb-8"><?= htmlspecialchars($plan_cta_texto) ?></p>
      <a href="<?= BASE_URL ?>/index.php#unete"
         class="btn-dyn inline-flex items-center gap-2 font-black px-10 py-4 rounded-full transition-all shadow-lg text-base"
         style="background-color:var(--color-btn-join);color:var(--color-btn-join-text)">
        Unete al equipo ->
      </a>
    </div>
  </section>

  <?php include __DIR__ . '/includes/footer.php'; ?>

  <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
  <script src="<?= BASE_URL ?>/assets/js/main.js"></script>
  <script>AOS.init({ once: true, offset: 80 });</script>
</body>
</html>


