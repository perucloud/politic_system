<?php
// ============================================================
// _ver.php — Página dinámica universal de candidato distrital
// Uso: thin wrapper establece $candidato_slug, luego require
//   $candidato_slug = 'rio-negro'; require '_ver.php';
// ============================================================
require_once __DIR__ . '/../includes/config/db.php';
require_once __DIR__ . '/../includes/helpers/config.php';

// ── Config del banner CTA provincial ─────────────────────────
$_dist_cfg = [];
try {
    $_dist_cfg = $pdo->query(
        "SELECT clave, valor FROM configuracion
         WHERE clave IN ('dist_cta_label','dist_cta_name','dist_cta_text','dist_cta_button','dist_cta_url','dist_cta_photo','partido_nombre')"
    )->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {}

$dist_cta_label  = cfg_value($_dist_cfg, 'dist_cta_label',  'es parte del equipo de');
$dist_cta_name   = cfg_value($_dist_cfg, 'dist_cta_name',   'Ivan Cisneros');
$dist_cta_text   = cfg_value($_dist_cfg, 'dist_cta_text',   'Juntos trabajamos por el desarrollo integral de toda la provincia de Satipo. Un equipo unido, un solo objetivo.');
$dist_cta_button = cfg_value($_dist_cfg, 'dist_cta_button', 'Plan Provincial →');
$dist_cta_url    = cfg_value($_dist_cfg, 'dist_cta_url',    '/plan.php');
$dist_cta_photo  = cfg_value($_dist_cfg, 'dist_cta_photo',  '/assets/img/candidato/ivancisneros-perfil.webp');

// ━━ CONSULTA PRINCIPAL ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
$db = null;
try {
    $stmt = $pdo->prepare(
        "SELECT * FROM candidatos_distritales WHERE slug = ? AND activo = 1 LIMIT 1"
    );
    $stmt->execute([$candidato_slug]);
    $db = $stmt->fetch(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    // Si la BD falla, $db queda null → 404
}

// ━━ 404 SI NO SE ENCONTRÓ ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
if (!$db) {
    http_response_code(404);

    // Cargar candidatos activos para el slider
    $activos_404 = [];
    try {
        $s404 = $pdo->query(
            "SELECT nombre, distrito, slug, foto FROM candidatos_distritales
             WHERE activo = 1 ORDER BY orden ASC, id ASC"
        );
        $activos_404 = $s404->fetchAll(PDO::FETCH_ASSOC);
    } catch (Exception $e) {}
    ?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Candidato no disponible · Ivan Cisneros</title>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: { extend: {
        colors: { primary: '#1E3A8A', secondary: '#38BDF8', accent: '#FACC15' },
        fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] }
      }}
    }
  </script>
  <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/custom.css">
</head>
<body class="font-sans text-gray-800 bg-white">
  <?php include __DIR__ . '/../includes/navbar.php'; ?>

  <!-- ── Bloque 404 distrital ──────────────────────────────── -->
  <div class="pt-24 pb-12 px-4 text-center bg-gradient-to-b from-[#F0F9FF] to-white">
    <div class="text-[120px] font-black text-[#1E3A8A]/8 leading-none select-none">404</div>
    <div class="text-5xl mb-4 -mt-4">🏛️</div>
    <h1 class="text-2xl sm:text-3xl font-black text-[#1E3A8A] mb-3">
      Candidato no disponible
    </h1>
    <p class="text-gray-500 max-w-sm mx-auto mb-8 leading-relaxed text-sm">
      Este candidato no existe o está temporalmente desactivado.<br>
      Conoce a los demás candidatos de nuestro equipo.
    </p>
    <a href="<?= BASE_URL ?>/index.php#equipo"
       class="inline-flex items-center gap-2 bg-[#1E3A8A] hover:bg-[#0056D6] text-white font-bold px-6 py-3 rounded-full text-sm transition-all duration-200 shadow-lg hover:shadow-blue-700/30 hover:-translate-y-0.5">
      <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
      </svg>
      Ver todos los candidatos
    </a>
  </div>

  <!-- ── Slider de candidatos activos ──────────────────────── -->
  <?php if (!empty($activos_404)): ?>
  <section class="py-12 bg-white">
    <div class="max-w-5xl mx-auto px-4 sm:px-6">
      <h2 class="text-center text-lg font-black text-[#1E3A8A] mb-8 uppercase tracking-wide">
        Nuestro equipo distrital
      </h2>
      <div class="swiper candidatos404-swiper">
        <div class="swiper-wrapper pb-10">
          <?php foreach ($activos_404 as $ca):
            $foto_ca = !empty($ca['foto'])
                ? BASE_URL . '/' . ltrim($ca['foto'], '/')
                : BASE_URL . '/assets/img/distritales/' . $ca['slug'] . '/foto.webp';
            $nombre_ca  = !empty(trim($ca['nombre'])) ? $ca['nombre'] : '[Candidato]';
            $fallback_ca = 'https://placehold.co/180x220/1E3A8A/white?text=' . urlencode($ca['distrito']);
          ?>
          <div class="swiper-slide">
            <a href="<?= BASE_URL ?>/distritales/<?= htmlspecialchars($ca['slug']) ?>.php"
               class="block bg-white rounded-2xl overflow-hidden shadow-md hover:shadow-xl
                      hover:-translate-y-1 transition-all duration-300 mx-2 group">
              <!-- Foto -->
              <div class="relative overflow-hidden" style="height:190px">
                <img src="<?= htmlspecialchars($foto_ca) ?>"
                     alt="<?= htmlspecialchars($nombre_ca) ?>"
                     class="w-full h-full object-cover object-top group-hover:scale-105 transition-transform duration-500"
                     onerror="this.src='<?= $fallback_ca ?>'">
                <div class="absolute inset-0 bg-gradient-to-t from-[#1E3A8A]/70 to-transparent"></div>
                <div class="absolute bottom-3 left-3">
                  <span class="bg-[#FACC15] text-[#1E3A8A] text-[10px] font-black px-2 py-0.5 rounded-full uppercase">
                    <?= htmlspecialchars($ca['distrito']) ?>
                  </span>
                </div>
              </div>
              <!-- Info -->
              <div class="p-4 text-center">
                <p class="font-black text-[#1E3A8A] text-sm truncate">
                  <?= htmlspecialchars($nombre_ca) ?>
                </p>
                <p class="text-gray-400 text-xs mt-0.5 mb-3">Candidato Alcalde Distrital</p>
                <span class="inline-block w-full text-center bg-[#1E3A8A] text-white text-xs font-bold py-2 rounded-xl
                             group-hover:bg-[#0056D6] transition-colors">
                  Ver candidato →
                </span>
              </div>
            </a>
          </div>
          <?php endforeach; ?>
        </div>
        <div class="swiper-pagination"></div>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <?php include __DIR__ . '/../includes/footer.php'; ?>

  <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
  <script>
    new Swiper('.candidatos404-swiper', {
      slidesPerView: 1.2,
      spaceBetween: 12,
      centeredSlides: true,
      pagination: { el: '.swiper-pagination', clickable: true },
      breakpoints: {
        480:  { slidesPerView: 2.2, centeredSlides: false },
        768:  { slidesPerView: 3,   centeredSlides: false },
        1024: { slidesPerView: 4,   centeredSlides: false },
      }
    });
  </script>
</body>
</html>
    <?php
    exit;
}

// ━━ VARIABLES DE PRESENTACIÓN CON FALLBACKS ━━━━━━━━━━━━━━━
$candidato_nombre = !empty($db['nombre'])
    ? $db['nombre']
    : '[Candidato]';

$candidato_distrito = $db['distrito'] ?? '';

// foto: URL principal (usada en el hero circular)
if (!empty($db['foto'])) {
    $candidato_foto = BASE_URL . '/' . ltrim($db['foto'], '/');
} else {
    $candidato_foto = BASE_URL . "/assets/img/distritales/{$candidato_slug}/foto.webp";
}

// foto_perfil: usada en la sección "Quién es" (columna derecha)
if (!empty($db['foto_perfil'])) {
    $candidato_foto_perfil = BASE_URL . '/' . ltrim($db['foto_perfil'], '/');
} else {
    $candidato_foto_perfil = $candidato_foto;
}

// bio
if (!empty($db['bio'])) {
    $candidato_bio = $db['bio'];
} else {
    $candidato_bio = "Natural de {$db['distrito']}, con amplia trayectoria en la gestión comunal y el desarrollo local.";
}

// propuestas — JSON desde BD o array por defecto
$propuestas_default = [
    ['icon' => '🛣️', 'titulo' => 'Vías de acceso comunal',
     'desc'  => 'Mejoramiento y apertura de caminos rurales que conecten las comunidades más alejadas.'],
    ['icon' => '💧', 'titulo' => 'Agua potable para todos',
     'desc'  => 'Instalación de sistemas de agua potable en los centros poblados sin cobertura.'],
    ['icon' => '📚', 'titulo' => 'Educación de calidad',
     'desc'  => 'Infraestructura educativa renovada y apoyo a docentes locales.'],
    ['icon' => '🌾', 'titulo' => 'Apoyo al agricultor',
     'desc'  => 'Asistencia técnica y acceso a mercados para productores de café y cacao.'],
    ['icon' => '🏥', 'titulo' => 'Salud preventiva',
     'desc'  => 'Brigadas de salud itinerantes y mejoramiento del puesto de salud distrital.'],
];

$propuestas = $propuestas_default;
if (!empty($db['propuestas'])) {
    $db_props = json_decode($db['propuestas'], true);
    if (is_array($db_props) && !empty($db_props)) {
        $propuestas = $db_props;
    }
}

// galería — vacía por ahora, se salta la sección
$galeria = [];

// PDF del plan de gobierno
$plan_pdf_server = __DIR__ . '/../assets/docs/planes/' . $candidato_slug . '.pdf';
$plan_pdf_url    = BASE_URL . '/assets/docs/planes/' . $candidato_slug . '.pdf';
$plan_pdf_existe = file_exists($plan_pdf_server);

$noticias_distritales = [];
$noticias_total        = 0;
$noticias_per_page     = 6;
$noticias_page         = max(1, (int)($_GET['npage'] ?? 1));
$noticias_pages        = 1;
try {
    $cnt = $pdo->prepare("SELECT COUNT(*) FROM candidato_noticias WHERE candidato_id = ? AND estado = 'publicado'");
    $cnt->execute([(int)$db['id']]);
    $noticias_total  = (int)$cnt->fetchColumn();
    $noticias_pages  = max(1, (int)ceil($noticias_total / $noticias_per_page));
    $noticias_page   = min($noticias_page, $noticias_pages);
    $noticias_offset = ($noticias_page - 1) * $noticias_per_page;

    $stmt_news = $pdo->prepare(
        "SELECT id, titulo, contenido, imagen, creado_en
         FROM candidato_noticias
         WHERE candidato_id = ? AND estado = 'publicado'
         ORDER BY creado_en DESC, id DESC
         LIMIT ? OFFSET ?"
    );
    $stmt_news->execute([(int)$db['id'], $noticias_per_page, $noticias_offset]);
    $noticias_distritales = $stmt_news->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $noticias_distritales = [];
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($candidato_nombre) ?> — Candidato Alcalde de <?= htmlspecialchars($candidato_distrito) ?> · Ivan Cisneros</title>

  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: { extend: {
        colors: { primary: '#1E3A8A', secondary: '#38BDF8', accent: '#FACC15' },
        fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] }
      }}
    }
  </script>
  <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <link rel="stylesheet" href="https://unpkg.com/aos@2.3.4/dist/aos.css">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/custom.css">
</head>
<body class="font-sans text-gray-800 bg-white">

  <?php include __DIR__ . '/../includes/navbar.php'; ?>

  <!-- ══ HERO DISTRITAL ════════════════════════════════════ -->
  <section class="relative pt-16 min-h-[50vh] flex items-end overflow-hidden">
    <div class="absolute inset-0">
      <img src="<?= BASE_URL ?>/assets/img/distritales/<?= htmlspecialchars($candidato_slug) ?>/fondo.jpg"
           alt="<?= htmlspecialchars($candidato_distrito) ?>"
           class="w-full h-full object-cover"
           onerror="this.style.background='#1E3A8A'">
      <div class="absolute inset-0 bg-gradient-to-t from-[#1E3A8A] via-[#1E3A8A]/60 to-transparent"></div>
    </div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 w-full">
      <div class="flex flex-col sm:flex-row items-center sm:items-end gap-6 text-white">

        <!-- Foto circular del candidato -->
        <div class="flex-shrink-0">
          <div class="w-52 h-52 rounded-full border-4 border-[#FACC15] shadow-xl overflow-hidden bg-[#1E3A8A]">
            <img src="<?= htmlspecialchars($candidato_foto) ?>"
                 alt="<?= htmlspecialchars($candidato_nombre) ?>"
                 class="w-full h-full object-cover object-top"
                 onerror="this.src='https://placehold.co/208x208/1E3A8A/white?text=<?= urlencode($candidato_nombre) ?>'">
          </div>
        </div>

        <!-- Texto del hero -->
        <div data-aos="fade-right">
          <nav class="text-blue-300 text-sm mb-2">
            <a href="<?= BASE_URL ?>/index.php" class="hover:text-white">Inicio</a>
            <span class="mx-2">›</span>
            <a href="<?= BASE_URL ?>/index.php#equipo" class="hover:text-white">Candidatos Distritales</a>
            <span class="mx-2">›</span>
            <span class="text-white"><?= htmlspecialchars($candidato_distrito) ?></span>
          </nav>
          <h1 class="text-3xl sm:text-4xl font-black mb-1">
            <?= htmlspecialchars($candidato_nombre) ?>
          </h1>
          <p class="text-[#38BDF8] font-semibold text-lg">
            Candidato a la Alcaldía Distrital de <?= htmlspecialchars($candidato_distrito) ?>
          </p>
          <p class="text-blue-200 text-sm mt-1"><?= htmlspecialchars(cfg_value($_dist_cfg, 'partido_nombre', 'ALIANZA PARA EL PROGRESO')) ?> · Con el respaldo de Ivan Cisneros</p>

          <!-- Botón Plan de Gobierno (hero) -->
          <div class="mt-5">
            <?php if ($plan_pdf_existe): ?>
            <button onclick="window.dispatchEvent(new CustomEvent('abrir-plan-pdf'))"
              class="group inline-flex items-center gap-2.5 bg-gradient-to-r from-[#FACC15] to-amber-400 hover:from-amber-400 hover:to-[#FACC15] text-[#1E3A8A] font-extrabold px-6 py-3 rounded-full text-sm shadow-lg shadow-amber-500/30 hover:shadow-amber-500/50 transition-all duration-300 hover:-translate-y-0.5 active:scale-95">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
              Ver Plan de Gobierno
              <span class="bg-[#1E3A8A]/10 text-[#1E3A8A] text-[10px] font-bold px-2 py-0.5 rounded-full border border-[#1E3A8A]/20">PDF</span>
            </button>
            <?php else: ?>
            <button disabled
              class="inline-flex items-center gap-2.5 bg-white/10 border border-white/20 text-white/40 font-bold px-6 py-3 rounded-full text-sm cursor-not-allowed">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
              </svg>
              Plan de Gobierno
              <span class="bg-white/5 text-white/30 text-[10px] font-bold px-2 py-0.5 rounded-full border border-white/10">Próximamente</span>
            </button>
            <?php endif; ?>
          </div>
        </div>

      </div>
    </div>
  </section>

  <!-- ══ QUIÉN ES ═══════════════════════════════════════════ -->
  <section class="py-20 bg-white">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-12 items-center">

        <!-- Columna izquierda: texto y atributos -->
        <div data-aos="fade-right">
          <span class="text-[#38BDF8] font-semibold uppercase tracking-widest text-sm">Conócelo</span>
          <h2 class="text-2xl sm:text-3xl font-black text-[#1E3A8A] mt-2 mb-5">
            ¿Quién es <?= htmlspecialchars(explode(' ', $candidato_nombre)[0]) ?>?
          </h2>
          <p class="text-gray-600 leading-relaxed mb-6"><?= htmlspecialchars($candidato_bio) ?></p>
          <div class="grid grid-cols-3 gap-3">
            <?php
            $atributos = [
                ['ico' => '🤝', 'lab' => 'Compromiso'],
                ['ico' => '🏠', 'lab' => 'Identidad local'],
                ['ico' => '💪', 'lab' => 'Trabajo'],
            ];
            foreach ($atributos as $a): ?>
            <div class="text-center p-3 bg-blue-50 rounded-xl">
              <div class="text-2xl mb-1"><?= $a['ico'] ?></div>
              <p class="text-xs font-semibold text-[#1E3A8A]"><?= htmlspecialchars($a['lab']) ?></p>
            </div>
            <?php endforeach; ?>
          </div>
        </div>

        <!-- Columna derecha: foto de perfil -->
        <div class="flex justify-center" data-aos="fade-left">
          <div class="w-full max-w-[600px] mx-auto rounded-2xl shadow-xl overflow-hidden bg-[#EFF6FF]" style="aspect-ratio:600/350">
            <img src="<?= htmlspecialchars($candidato_foto_perfil) ?>"
                 alt="<?= htmlspecialchars($candidato_nombre) ?>"
                 class="w-full h-full object-cover object-center"
                 onerror="this.src='https://placehold.co/600x350/EFF6FF/1E3A8A?text=<?= urlencode($candidato_nombre) ?>'">
          </div>
        </div>

      </div>
    </div>
  </section>

  <!-- ══ PROPUESTAS DISTRITALES ════════════════════════════ -->
  <section class="py-20 bg-[#F0F9FF]">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="text-center mb-12" data-aos="fade-up">
        <span class="text-[#38BDF8] font-semibold uppercase tracking-widest text-sm">Propuestas</span>
        <h2 class="text-2xl sm:text-3xl font-black text-[#1E3A8A] mt-2">
          Nuestras Propuestas para <?= htmlspecialchars($candidato_distrito) ?>
        </h2>
      </div>

      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($propuestas as $i => $p): ?>
        <div class="bg-white rounded-2xl p-6 shadow hover:shadow-md transition-all border border-gray-100 hover:-translate-y-1"
             data-aos="fade-up" data-aos-delay="<?= (int)$i * 80 ?>">
          <div class="text-4xl mb-3"><?= $p['icon'] ?></div>
          <h3 class="font-bold text-[#1E3A8A] text-base mb-2"><?= htmlspecialchars($p['titulo']) ?></h3>
          <p class="text-gray-500 text-sm leading-relaxed"><?= htmlspecialchars($p['desc']) ?></p>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- CTA Plan de Gobierno bajo propuestas -->
      <div class="text-center mt-14" data-aos="fade-up" data-aos-delay="200">
        <?php if ($plan_pdf_existe): ?>
        <button onclick="window.dispatchEvent(new CustomEvent('abrir-plan-pdf'))"
          class="group inline-flex items-center gap-3 bg-gradient-to-r from-[#1E3A8A] to-[#0056D6] hover:from-[#0056D6] hover:to-[#0EA5E9] text-white font-extrabold px-9 py-4 rounded-full text-base shadow-xl shadow-blue-900/25 hover:shadow-blue-600/40 transition-all duration-300 hover:-translate-y-1 active:scale-95">
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
          </svg>
          Ver Plan de Gobierno Completo
          <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 group-hover:translate-x-1 transition-transform duration-300" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3" />
          </svg>
        </button>
        <p class="text-[#0056D6] text-xs font-medium mt-4">
          Documento PDF oficial · <?= htmlspecialchars($candidato_nombre) ?> para <?= htmlspecialchars($candidato_distrito) ?>
        </p>
        <?php else: ?>
        <div class="inline-flex flex-col items-center gap-2">
          <button disabled
            class="inline-flex items-center gap-3 bg-gray-200 text-gray-400 font-bold px-9 py-4 rounded-full text-base cursor-not-allowed">
            <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z" />
            </svg>
            Plan de Gobierno
          </button>
          <p class="text-gray-400 text-xs">El plan de gobierno estará disponible próximamente</p>
        </div>
        <?php endif; ?>
      </div>
    </div>
  </section>

  <?php if (!empty($noticias_distritales)): ?>
  <section id="noticias-distrito" class="py-20 bg-white">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="text-center mb-12" data-aos="fade-up">
        <span class="text-[#38BDF8] font-semibold uppercase tracking-widest text-sm">Noticias del distrito</span>
        <h2 class="text-2xl sm:text-3xl font-black text-[#1E3A8A] mt-2">
          Ultimas actividades de <?= htmlspecialchars($candidato_distrito) ?>
        </h2>
        <p class="text-gray-500 text-sm mt-3 max-w-2xl mx-auto">
          Comunicados y avances publicados por el equipo distrital.
        </p>
      </div>

      <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
        <?php foreach ($noticias_distritales as $i => $n):
          $img_news    = !empty($n['imagen']) ? BASE_URL . '/' . ltrim((string)$n['imagen'], '/') : '';
          $noticia_url = BASE_URL . '/distritales/noticia.php?id=' . (int)$n['id'];
        ?>
        <a href="<?= htmlspecialchars($noticia_url) ?>"
           class="rounded-2xl border border-gray-100 bg-white shadow hover:shadow-xl transition-all overflow-hidden group block"
           data-aos="fade-up" data-aos-delay="<?= (int)$i * 80 ?>">
          <?php if ($img_news): ?>
          <div class="aspect-[16/10] bg-[#EFF6FF] overflow-hidden relative">
            <img src="<?= htmlspecialchars($img_news) ?>" alt="<?= htmlspecialchars((string)$n['titulo']) ?>"
                 class="w-full h-full object-cover group-hover:scale-105 transition-transform duration-500">
            <div class="absolute inset-0 bg-[#1E3A8A]/0 group-hover:bg-[#1E3A8A]/10 transition-colors duration-300 flex items-center justify-center">
              <span class="opacity-0 group-hover:opacity-100 transition-opacity bg-white text-[#1E3A8A] text-xs font-black px-3 py-1.5 rounded-full shadow-lg">
                Leer más →
              </span>
            </div>
          </div>
          <?php else: ?>
          <div class="aspect-[16/10] bg-gradient-to-br from-[#EFF6FF] to-blue-100 flex items-center justify-center">
            <svg class="w-12 h-12 text-[#1E3A8A]/20" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                    d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/>
            </svg>
          </div>
          <?php endif; ?>
          <div class="p-6">
            <p class="text-[11px] font-black uppercase tracking-widest text-[#38BDF8] mb-2">
              <?= htmlspecialchars(date('d/m/Y', strtotime((string)$n['creado_en']))) ?>
            </p>
            <h3 class="font-black text-[#1E3A8A] text-base leading-snug mb-3 group-hover:text-[#2563EB] transition-colors">
              <?= htmlspecialchars((string)$n['titulo']) ?>
            </h3>
            <p class="text-gray-500 text-sm leading-relaxed line-clamp-3">
              <?= htmlspecialchars(mb_strimwidth(strip_tags((string)$n['contenido']), 0, 170, '...')) ?>
            </p>
            <p class="mt-4 text-xs font-black text-[#1E3A8A] group-hover:text-[#2563EB] transition-colors">
              Leer artículo completo →
            </p>
          </div>
        </a>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- ══ Paginación pública ════════════════════════════════════ -->
    <?php if ($noticias_pages > 1): ?>
    <div class="flex items-center justify-center gap-2 mt-12 flex-wrap">
      <?php if ($noticias_page > 1): ?>
      <a href="?npage=<?= $noticias_page - 1 ?>#noticias-distrito"
         class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-white border border-gray-200
                text-sm font-bold text-gray-600 hover:border-[#1E3A8A] hover:text-[#1E3A8A] transition-all shadow-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
        </svg>
        Anterior
      </a>
      <?php endif; ?>

      <div class="flex gap-1">
        <?php for ($p = 1; $p <= $noticias_pages; $p++): ?>
        <a href="?npage=<?= $p ?>#noticias-distrito"
           class="w-9 h-9 rounded-xl flex items-center justify-center text-sm font-black transition-all
                  <?= $p === $noticias_page
                    ? 'bg-[#1E3A8A] text-white shadow-md'
                    : 'bg-white border border-gray-200 text-gray-600 hover:border-[#1E3A8A] hover:text-[#1E3A8A]' ?>">
          <?= $p ?>
        </a>
        <?php endfor; ?>
      </div>

      <?php if ($noticias_page < $noticias_pages): ?>
      <a href="?npage=<?= $noticias_page + 1 ?>#noticias-distrito"
         class="inline-flex items-center gap-1.5 px-4 py-2 rounded-xl bg-white border border-gray-200
                text-sm font-bold text-gray-600 hover:border-[#1E3A8A] hover:text-[#1E3A8A] transition-all shadow-sm">
        Siguiente
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
        </svg>
      </a>
      <?php endif; ?>
    </div>
    <?php endif; ?>

  </section>
  <?php endif; ?>

  <!-- ══ CONEXIÓN PROVINCIAL ═══════════════════════════════ -->
  <section class="py-16 bg-[#1E3A8A]">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 text-center" data-aos="fade-up">
      <div class="flex flex-col sm:flex-row items-center gap-6 bg-white/10 backdrop-blur-sm rounded-3xl p-8">
        <img src="<?= htmlspecialchars(cfg_site_url($dist_cta_photo)) ?>"
             alt="<?= htmlspecialchars($dist_cta_name) ?>"
             class="w-20 h-20 rounded-full border-4 border-[#FACC15] object-cover flex-shrink-0"
             onerror="this.src='https://placehold.co/80x80/FACC15/1E3A8A?text=JB'">
        <div class="text-left text-white">
          <p class="font-bold text-lg mb-1">
            <?= htmlspecialchars($candidato_nombre) ?> <?= htmlspecialchars($dist_cta_label) ?>
            <span class="text-[#FACC15]"><?= htmlspecialchars($dist_cta_name) ?></span>
          </p>
          <p class="text-blue-200 text-sm">
            <?= htmlspecialchars($dist_cta_text) ?>
          </p>
        </div>
        <a href="<?= htmlspecialchars(cfg_site_url($dist_cta_url)) ?>"
           class="flex-shrink-0 bg-[#FACC15] hover:bg-yellow-400 text-[#1E3A8A] font-bold px-6 py-3 rounded-full text-sm transition-all whitespace-nowrap">
          <?= htmlspecialchars($dist_cta_button) ?>
        </a>
      </div>
    </div>
  </section>

  <!-- ══ GALERÍA LOCAL (solo si hay imágenes) ══════════════ -->
  <?php if (!empty($galeria)): ?>
  <section class="py-20 bg-white">
    <div class="max-w-5xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="text-center mb-10" data-aos="fade-up">
        <h2 class="text-2xl font-black text-[#1E3A8A]">
          Galería — <?= htmlspecialchars($candidato_distrito) ?>
        </h2>
      </div>
      <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-4 gap-3">
        <?php foreach ($galeria as $i => $img): ?>
        <div class="aspect-square overflow-hidden rounded-xl"
             data-aos="fade-up" data-aos-delay="<?= (int)$i * 60 ?>">
          <img src="<?= htmlspecialchars($img) ?>" alt="Galería"
               class="w-full h-full object-cover hover:scale-105 transition-transform duration-500">
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- ══ MODAL — PLAN DE GOBIERNO PDF ════════════════════ -->
  <?php if ($plan_pdf_existe): ?>
  <div x-data="{ abierto: false }" @abrir-plan-pdf.window="abierto = true">
    <div x-show="abierto"
         x-cloak
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-[100] flex items-center justify-center p-3 sm:p-6">
      <div class="absolute inset-0 bg-black/75 backdrop-blur-sm" @click="abierto = false"></div>
      <div class="relative flex flex-col w-full max-w-4xl h-[90vh] bg-white rounded-2xl shadow-2xl overflow-hidden z-10">

        <!-- Header del modal -->
        <div class="flex items-center justify-between px-5 py-4 bg-gradient-to-r from-[#1E3A8A] via-[#1a4aad] to-[#0056D6] text-white flex-shrink-0 border-b border-white/10">
          <div class="flex items-center gap-3 min-w-0">
            <div class="flex-shrink-0 w-9 h-9 bg-white/15 rounded-xl flex items-center justify-center">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5 text-[#FACC15]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
              </svg>
            </div>
            <div class="min-w-0">
              <p class="text-[#38BDF8] text-[10px] uppercase tracking-widest font-semibold">Plan de Gobierno Distrital</p>
              <p class="font-black text-sm sm:text-base truncate leading-tight">
                <?= htmlspecialchars($candidato_nombre) ?>
                <span class="text-blue-200 font-normal">— <?= htmlspecialchars($candidato_distrito) ?></span>
              </p>
            </div>
          </div>
          <div class="flex items-center gap-2 flex-shrink-0 ml-3">
            <a href="<?= htmlspecialchars($plan_pdf_url) ?>" download
               class="hidden sm:flex items-center gap-1.5 bg-[#FACC15] hover:bg-yellow-300 text-[#1E3A8A] font-extrabold px-4 py-2 rounded-full text-xs transition-all duration-200 shadow-lg shadow-yellow-500/20 hover:shadow-yellow-500/40 hover:-translate-y-0.5 active:scale-95">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-3.5 h-3.5 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4" />
              </svg>
              Descargar PDF
            </a>
            <button @click="abierto = false"
                    class="w-9 h-9 flex items-center justify-center text-white/60 hover:text-white hover:bg-white/15 rounded-xl transition-all duration-200">
              <svg xmlns="http://www.w3.org/2000/svg" class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12" />
              </svg>
            </button>
          </div>
        </div>

        <!-- Visor PDF -->
        <div class="flex-1 bg-gray-100 overflow-hidden relative">
          <iframe
            :src="abierto ? '<?= addslashes(htmlspecialchars($plan_pdf_url)) ?>#toolbar=1&navpanes=0&scrollbar=1&view=FitH' : ''"
            class="w-full h-full border-0 absolute inset-0"
            title="Plan de Gobierno — <?= htmlspecialchars($candidato_nombre) ?>">
          </iframe>
        </div>

        <!-- Footer del modal -->
        <div class="flex items-center justify-between px-5 py-3 bg-gray-50 border-t border-gray-200 flex-shrink-0">
          <div class="flex items-center gap-2">
            <div class="w-1.5 h-1.5 rounded-full bg-[#FACC15]"></div>
            <p class="text-gray-400 text-xs">Renovación Popular · Satipo 2026–2030</p>
          </div>
          <div class="flex items-center gap-4">
            <a href="<?= htmlspecialchars($plan_pdf_url) ?>" download
               class="sm:hidden text-[#1E3A8A] font-bold text-xs hover:underline">
              Descargar
            </a>
            <button @click="abierto = false"
                    class="text-gray-400 hover:text-gray-700 text-xs font-semibold transition-colors duration-200">
              Cerrar ✕
            </button>
          </div>
        </div>

      </div>
    </div>
  </div>
  <?php endif; ?>

  <?php include __DIR__ . '/../includes/footer.php'; ?>

  <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
  <script src="<?= BASE_URL ?>/assets/js/main.js"></script>
  <script>AOS.init({ once: true, offset: 80 });</script>
</body>
</html>
