<?php
require_once __DIR__ . '/includes/config/db.php';
require_once __DIR__ . '/includes/helpers/config.php';

$noticias = [];
try {
    $stmt = $pdo->query("SELECT id, titulo, imagen, categoria, fecha
                         FROM noticias WHERE estado='publicado'
                         ORDER BY fecha DESC LIMIT 12");
    $noticias = $stmt->fetchAll();
} catch (Exception $e) {}

// Candidatos / sub-entidades activas (se usa en el slider y en el titulo)
$candidatos = [];
try {
    $candidatos = $pdo->query(
        "SELECT id, nombre, distrito, slug, foto
         FROM candidatos_distritales
         WHERE activo = 1
         ORDER BY orden ASC, id ASC"
    )->fetchAll();
} catch (Exception $e) {}

// Configuracion de campana
$cfg_camp = [];
try {
    $cfg_camp = $pdo->query("SELECT clave, valor FROM configuracion")
                    ->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {}
$camp_tipo        = $cfg_camp['tipo_candidatura']    ?? 'provincial';
$camp_cargo       = $cfg_camp['nombre_cargo']        ?? 'Alcalde Provincial';
$camp_territorio  = $cfg_camp['nombre_territorio']   ?? 'Satipo';
$camp_label_eq    = $cfg_camp['label_equipo']        ?? 'Candidatos Distritales';
$camp_label_sing  = $cfg_camp['label_sub_entidad']   ?? 'Distrito';
$camp_label_plur  = $cfg_camp['label_sub_entidades'] ?? 'Distritos';

$index_hero_stats = cfg_json($cfg_camp, 'index_hero_stats', [
    ['n'=>'8','l'=>'Distritos'],
    ['n'=>'4','l'=>'Anos de gestion'],
    ['n'=>'8','l'=>'Ejes de trabajo'],
]);
$index_hero_img = cfg_value($cfg_camp, 'index_hero_img', '/assets/img/candidato/joyerd.webp');
$index_hero_profile_img = cfg_value($cfg_camp, 'index_hero_profile_img', '/assets/img/candidato/joyer-bastidas-2.webp');
$index_hero_fallback_img = cfg_value($cfg_camp, 'index_hero_fallback_img', '/assets/img/candidato/joyerd.webp');
$index_hero_party_img = cfg_value($cfg_camp, 'index_hero_party_img', '/assets/img/candidato/r.webp');
$index_hero2_bg_img = cfg_value($cfg_camp, 'index_hero2_bg_img', 'https://images.unsplash.com/photo-1506905925346-21bda4d32df4?w=1600&q=80');
$index_hero2_pillars = cfg_json($cfg_camp, 'index_hero2_pillars', [
    ['icon' => 'obra', 'title' => 'Infraestructura', 'desc' => 'Vias, puentes y obras reales'],
    ['icon' => 'gestion', 'title' => 'Gestion honesta', 'desc' => 'Transparencia total'],
    ['icon' => 'campo', 'title' => 'Desarrollo rural', 'desc' => 'Campo y comunidades primero'],
]);
$index_bio_img = cfg_value($cfg_camp, 'index_bio_img', '/uploads/media/media_6a1634dc12ada8.95137384.webp');
$index_bio_fallback_img = cfg_value($cfg_camp, 'index_bio_fallback_img', '/uploads/media/media_6a1634dc12ada8.95137384.webp');
$index_bio_attrs = cfg_json($cfg_camp, 'index_bio_attrs', [
    ['icon' => 'compromiso', 'title' => 'Compromiso', 'desc' => 'Con la gente y la provincia'],
    ['icon' => 'experiencia', 'title' => 'Experiencia', 'desc' => 'En gestion y proyectos'],
    ['icon' => 'cercania', 'title' => 'Cercania', 'desc' => 'Con todas las comunidades'],
]);
$index_work_axes = array_slice(array_values(array_filter(
    cfg_json($cfg_camp, 'index_work_axes', cfg_default_work_axes()),
    fn($e) => ($e['active'] ?? true) !== false
)), 0, 6);
$news_url = site_url(cfg_value($cfg_camp, 'index_news_button_url', '/noticias/index.php'));
$index_social_enabled = cfg_value($cfg_camp, 'index_social_enabled', '1') === '1';
$index_social_image = cfg_value($cfg_camp, 'index_social_image', '/assets/img/candidato/joyerd.webp');

// Contador regresivo
$countdown_active = cfg_value($cfg_camp, 'countdown_active', '1') === '1';
$countdown_date   = cfg_value($cfg_camp, 'countdown_date',   '2026-10-04');
$countdown_title  = cfg_value($cfg_camp, 'countdown_title',  'Faltan para las Elecciones');
$countdown_label  = cfg_value($cfg_camp, 'countdown_label',  'Elecciones Municipales 2026');

// Contador de visitas — registra una vez por IP por dia
$visit_counter_active = cfg_value($cfg_camp, 'visit_counter_active', '1') === '1';
if ($visit_counter_active) {
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS visitas (
            id        INT AUTO_INCREMENT PRIMARY KEY,
            ip_hash   VARCHAR(64) NOT NULL,
            fecha     DATE NOT NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_ip_fecha (ip_hash, fecha)
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4");
        $ip_hash = hash('sha256', $_SERVER['REMOTE_ADDR'] ?? '127.0.0.1');
        $today   = date('Y-m-d');
        $chk = $pdo->prepare("SELECT 1 FROM visitas WHERE ip_hash=? AND fecha=?");
        $chk->execute([$ip_hash, $today]);
        if (!$chk->fetch()) {
            $pdo->prepare("INSERT INTO visitas (ip_hash, fecha) VALUES (?,?)")->execute([$ip_hash, $today]);
        }
    } catch (Exception $e) {}
}
$index_social_facebook_url = cfg_value($cfg_camp, 'index_social_facebook_url', 'https://www.facebook.com/ivanrogercisnerosquispe');
$index_social_height = max(280, min(900, (int)cfg_value($cfg_camp, 'index_social_plugin_height', '500')));

// Efecto visual sobre el candidato
$hero_effect_raw = cfg_value($cfg_camp, 'hero_candidate_effect', 'none');
$hero_effect = in_array($hero_effect_raw, ['none','halo','sparkles','sweep','arcos','combo','arcos_sparkles'], true)
    ? $hero_effect_raw : 'none';
$hfx_use_rings    = in_array($hero_effect, ['halo','combo'], true);
$hfx_use_sparkles = in_array($hero_effect, ['sparkles','combo','arcos_sparkles'], true);
$hfx_use_arcos    = in_array($hero_effect, ['arcos','arcos_sparkles'], true);
$hfx_use_sweep    = $hero_effect === 'sweep';

function site_url(string $url): string {
    $url = trim($url);
    if ($url === '' || $url[0] === '#' || preg_match('#^(https?:)?//#i', $url) || str_starts_with($url, 'data:')) {
        return $url;
    }
    return BASE_URL . '/' . ltrim($url, '/');
}

function index_icon_path(string $icon): string {
    if (function_exists('cfg_axis_icon_path')) {
        return cfg_axis_icon_path($icon);
    }
    $icons = [
        'obra' => 'M3 21h18M6 21V9l6-4 6 4v12M9 21v-6h6v6M9 10h.01M15 10h.01',
        'gestion' => 'M9 12h6m-6 4h6M9 8h6M5 5a2 2 0 012-2h7l5 5v11a2 2 0 01-2 2H7a2 2 0 01-2-2V5z',
        'campo' => 'M12 21c4.418-4.418 6-8 6-11a6 6 0 10-12 0c0 3 1.582 6.582 6 11zM9 10c2.5 0 4-1.5 4-4 2.5 1 4 3 4 5',
        'compromiso' => 'M17 8h1a4 4 0 010 8h-1m-10 0H6a4 4 0 010-8h1m2 4h6M9 12a3 3 0 116 0',
        'experiencia' => 'M9 12l2 2 4-4M7 4h10a2 2 0 012 2v14l-7-3-7 3V6a2 2 0 012-2z',
        'cercania' => 'M12 21s-7-4.438-7-10a7 7 0 1114 0c0 5.562-7 10-7 10zM12 11a2 2 0 100-4 2 2 0 000 4z',
    ];
    return $icons[$icon] ?? 'M12 6v6l4 2M12 22a10 10 0 110-20 10 10 0 010 20z';
}

function map_src_from_config(string $value): string {
    $value = trim($value);
    if ($value === '') return '';
    if (preg_match('/src=["\']([^"\']+)["\']/i', $value, $m)) {
        return html_entity_decode($m[1], ENT_QUOTES);
    }
    return $value;
}

function facebook_page_plugin_src(string $page_url, int $height): string {
    $page_url = trim($page_url);
    if ($page_url === '') {
        $page_url = 'https://www.facebook.com/ivanrogercisnerosquispe';
    }
    $params = [
        'href' => $page_url,
        'tabs' => 'timeline',
        'width' => '700',
        'height' => (string)$height,
        'small_header' => 'true',
        'adapt_container_width' => 'true',
        'hide_cover' => 'true',
        'show_facepile' => 'false',
        'locale' => 'es_LA',
    ];
    return 'https://www.facebook.com/plugins/page.php?' . http_build_query($params);
}
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Lic. Ivan Cisneros - Candidato Alcalde Provincial de Satipo</title>
  <meta name="description" content="Portal oficial de campana del Lic. Ivan Cisneros, <?= htmlspecialchars($cfg_camp['partido_nombre'] ?? 'ALIANZA PARA EL PROGRESO') ?> - Alcaldia Provincial de Satipo 2026.">

  <?php require_once __DIR__ . '/includes/helpers/colors.php'; echo render_color_vars($cfg_camp); ?>
  <script src="https://cdn.tailwindcss.com"></script>
  <script>
    tailwind.config = {
      theme: {
        extend: {
          colors: {
            rp_blue:    '#0039A6',
            rp_mid:     '#0056D6',
            rp_sky:     '#0EA5E9',
            rp_light:   '#38BDF8',
            rp_yellow:  '#FACC15',
          },
          fontFamily: { sans: ['Inter', 'system-ui', 'sans-serif'] },
          animation: {
            'float':      'float 6s ease-in-out infinite',
            'float-slow': 'float 9s ease-in-out infinite',
            'pulse-slow': 'pulse 4s ease-in-out infinite',
            'spin-slow':  'spin 20s linear infinite',
          },/*  */
          keyframes: {
            float: {
              '0%,100%': { transform: 'translateY(0px)' },
              '50%':     { transform: 'translateY(-20px)' },
            }
          }
        }
      }
    }
  </script>

  <script defer src="https://unpkg.com/alpinejs@3.x.x/dist/cdn.min.js"></script>
  <link rel="stylesheet" href="https://unpkg.com/aos@2.3.4/dist/aos.css">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <style>
    @font-face {
      font-family: 'DS-Digital';
      src: url('<?= BASE_URL ?>/assets/fonts/ds-digib.ttf') format('truetype');
      font-weight: normal;
      font-style: normal;
      font-display: swap;
    }
  </style>
  <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/custom.css">

  <style>
    /* -- Gradientes dinamicos ------------------------------- */
    .hero-gradient {
      background: linear-gradient(135deg, #001f6e 0%, #0039A6 35%, #005FCC 60%, #0EA5E9 100%);
    }
    .glass {
      background: rgba(255,255,255,0.08);
      backdrop-filter: blur(12px);
      -webkit-backdrop-filter: blur(12px);
      border: 1px solid rgba(255,255,255,0.15);
    }
    .glass-white {
      background: rgba(255,255,255,0.92);
      backdrop-filter: blur(16px);
      -webkit-backdrop-filter: blur(16px);
      border: 1px solid rgba(14,165,233,0.2);
    }
    /* -- Burbuja animada ------------------------------------ */
    .bubble {
      position: absolute;
      border-radius: 50%;
      background: radial-gradient(circle, rgba(56,189,248,0.3), transparent);
      animation: float 7s ease-in-out infinite;
    }
    /* -- Card eje con gradiente ----------------------------- */
    .eje-card {
      background: white;
      border-radius: 1.25rem;
      overflow: hidden;
      transition: transform .3s ease, box-shadow .3s ease;
      box-shadow: 0 4px 20px rgba(0,57,166,0.08);
    }
    .eje-card:hover {
      transform: translateY(-8px) scale(1.01);
      box-shadow: 0 20px 50px rgba(0,57,166,0.18);
    }
    .eje-card .top-bar {
      height: 5px;
      background: linear-gradient(90deg, #0039A6, #0EA5E9);
    }
    /* -- Candidato card ------------------------------------- */
    .cand-card {
      background: white;
      border-radius: 1.25rem;
      overflow: hidden;
      box-shadow: 0 8px 30px rgba(0,57,166,0.1);
      transition: all .3s ease;
    }
    .cand-card:hover { transform: translateY(-6px); box-shadow: 0 20px 50px rgba(0,57,166,0.2); }
    .fb-timeline-crop {
      --fb-crop-offset: 72px;
      height: var(--fb-visible-height, 500px);
      overflow: hidden;
    }
    .fb-timeline-frame {
      display: block;
      transform: translateY(calc(var(--fb-crop-offset) * -1));
    }
    /* -- Noticia card --------------------------------------- */
    .noticia-card {
      background: white;
      border-radius: 1.25rem;
      overflow: hidden;
      box-shadow: 0 4px 20px rgba(0,0,0,0.07);
      transition: all .3s ease;
    }
    .noticia-card:hover { transform: translateY(-6px); box-shadow: 0 20px 48px rgba(0,57,166,0.15); }
    /* -- Swiper dots ---------------------------------------- */
    .candidate-scroll-stage {
      position: fixed;
      top: 8px;
      right: max(-18px, calc((100vw - 1280px) / 2 - 40px));
      width: min(50vw, 720px);
      height: calc(100vh - 8px);
      z-index: 30;
      pointer-events: none;
      opacity: 0;
      transform: translate3d(32px, 18px, 0) scale(.96);
      transition: opacity .45s ease, transform .55s ease, filter .45s ease;
    }
    .candidate-scroll-stage.is-active {
      opacity: 1;
      transform: translate3d(0, 0, 0) scale(1);
    }
    .candidate-scroll-stage.is-story {
      transform: translate3d(calc(max(-18px, (100vw - 1280px) / 2 - 40px) - 50vw + 80px), 0px, 0) scale(.95);
      filter: drop-shadow(0 26px 60px rgba(0,31,110,.45));
    }
    .candidate-scroll-stage.is-profile {
      opacity: 0;
      transform: translate3d(-18vw, 18px, 0) scale(.74);
    }
    .candidate-scroll-inner {
      position: relative;
      width: 100%;
      height: 100%;
    }
    .candidate-img-container {
      position: absolute;
      left: -14%;
      top: 0;
      bottom: 0;
      width: min(74%, 580px);
      display: flex;
      align-items: center;
      z-index: 1;
    }
    .candidate-main-img {
      width: 100%;
      max-height: 88%;
      object-fit: contain;
      filter: drop-shadow(0 34px 55px rgba(0,31,110,.48));
      animation: float 6s ease-in-out infinite;
      transition: opacity .3s ease, transform .45s ease;
      -webkit-mask-image: linear-gradient(
        to bottom,
        black           0%,
        black          55%,
        rgba(0,0,0,.92) 65%,
        rgba(0,0,0,.72) 73%,
        rgba(0,0,0,.42) 82%,
        rgba(0,0,0,.14) 90%,
        transparent    97%
      );
      mask-image: linear-gradient(
        to bottom,
        black           0%,
        black          55%,
        rgba(0,0,0,.92) 65%,
        rgba(0,0,0,.72) 73%,
        rgba(0,0,0,.42) 82%,
        rgba(0,0,0,.14) 90%,
        transparent    97%
      );
    }
    .candidate-party-img {
      position: absolute;
      right: 12.5%;
      bottom: calc(5% + 40px);
      width: min(44%, 350px);
      max-height: 65%;
      object-fit: contain;
      z-index: 2;
      filter: drop-shadow(0 30px 42px rgba(0,31,110,.42));
      animation: float 7.5s ease-in-out infinite;
      animation-delay: .8s;
      transition: opacity .35s ease, transform .45s ease;
    }
    .candidate-scroll-stage.is-story .candidate-party-img {
      opacity: .72;
      transform: translateX(28px) scale(.9);
    }
    .candidate-scroll-stage.is-profile .candidate-party-img {
      opacity: 0;
      transform: translateX(80px) scale(.72);
    }
    .candidate-party-badge {
      position: absolute;
      top: 22%;
      right: 4%;
      transform: rotate(-2deg);
      filter: drop-shadow(0 8px 24px rgba(0,31,110,0.25));
    }
    .quien-profile-photo {
      opacity: 0;
      transform: translateY(22px) scale(.96);
      transition: opacity .45s ease, transform .45s ease;
    }
    .quien-profile-photo.is-revealed {
      opacity: 1;
      transform: translateY(0) scale(1);
    }
    @media (max-width: 1023px) {
      .candidate-scroll-stage { display: none; }
      .quien-profile-photo { opacity: 1; transform: none; }

      /* ── Hero mobile: columna de texto como flex para reordenar ── */
      .hero-text-col {
        display: flex;
        flex-direction: column;
      }
      .hero-badge-wrap  { order: 0; }
      .hero-photo-block { order: 1; margin-left: -1rem; margin-right: -1rem; }
      .hero-cta-btns    { order: 4; margin-top: 1rem; }
      .hero-countdown   { order: 3; margin-top: 1.25rem; }

      /* ── Bloque foto: apilado (nombre arriba, foto abajo) ── */
      .hero-photo-block {
        display: flex;
        flex-direction: column;
      }
      .hero-photo-name {
        line-height: 1;
        white-space: nowrap;
        margin-bottom: 0.5rem;
      }
      .hero-photo-img-wrap {
        position: relative;
        overflow: hidden;
      }
      .hero-photo-img-wrap > img {
        display: block;
        width: 100%;
      }
      .hero-photo-cargo {
        position: absolute;
        top: 1rem;
        left: 0;
        z-index: 10;
        padding: 0 0.75rem;
      }
    }
    .swiper-pagination-bullet { background: #0039A6 !important; opacity: .3; }
    .swiper-pagination-bullet-active { background: #0039A6 !important; opacity: 1; }
    /* -- Wave divider --------------------------------------- */
    .wave { overflow: hidden; line-height: 0; }
    .wave svg { display: block; }
    [x-cloak] { display: none !important; }

    /* ── Hero Candidate Effects ──────────────────────────── */
    /* Shared base */
    .hfx-ring, .hfx-arco {
      position: absolute;
      border-radius: 50%;
      pointer-events: none;
      top: 42%; left: 28%;
      transform: translate(-50%, -50%);
      aspect-ratio: 1;
    }
    /* Halo – pulsing concentric rings */
    .hfx-ring-1 { width: 52%; border: 1.5px solid rgba(56,189,248,.6); animation: hfx-pulse 3.6s ease-in-out 0s    infinite; }
    .hfx-ring-2 { width: 68%; border: 1.5px solid rgba(56,189,248,.35); animation: hfx-pulse 3.6s ease-in-out 1.2s infinite; }
    .hfx-ring-3 { width: 84%; border: 1.5px solid rgba(56,189,248,.18); animation: hfx-pulse 3.6s ease-in-out 2.4s infinite; }
    @keyframes hfx-pulse {
      0%,100% { opacity:0; transform:translate(-50%,-50%) scale(.88); }
      50%      { opacity:1; transform:translate(-50%,-50%) scale(1.02); }
    }
    /* Sparkles – floating glowing dots */
    .hfx-sp {
      position: absolute; border-radius: 50%; pointer-events: none; z-index: 3;
      animation: hfx-sparkle var(--dur,3s) ease-in-out var(--del,0s) infinite;
    }
    @keyframes hfx-sparkle {
      0%,100% { opacity:0; transform:scale(0) translate(0,0); }
      30%,70% { opacity:.9; }
      50%      { opacity:1; transform:scale(1) translate(var(--tx,0px),var(--ty,-12px)); }
    }
    /* Sweep – diagonal light pass */
    .hfx-sweep-layer {
      position: absolute; inset: 0; z-index: 4; pointer-events: none; overflow: hidden;
    }
    .hfx-sweep-layer::before {
      content: ''; position: absolute; inset: 0;
      background: linear-gradient(108deg, transparent 25%, rgba(255,255,255,.22) 50%, transparent 75%);
      animation: hfx-sweep 5s ease-in-out infinite;
      transform: translateX(-110%);
    }
    @keyframes hfx-sweep {
      0%      { transform: translateX(-110%); opacity:.7; }
      50%     { opacity: 1; }
      70%,100%{ transform: translateX(110%); opacity:.7; }
    }
    /* Arcos – rotating arcs */
    .hfx-arco-1 {
      width: 62%;
      border: 2.5px solid transparent;
      border-top-color: rgba(56,189,248,.8);
      border-bottom-color: rgba(56,189,248,.8);
      animation: hfx-spin 8s linear infinite;
    }
    .hfx-arco-2 {
      width: 82%;
      border: 1.5px solid transparent;
      border-left-color: rgba(250,204,21,.55);
      border-right-color: rgba(250,204,21,.55);
      animation: hfx-spin 13s linear reverse infinite;
    }
    @keyframes hfx-spin { to { transform: translate(-50%,-50%) rotate(360deg); } }
  </style>
</head>
<body class="font-sans text-gray-800 bg-white overflow-x-hidden">

  <?php include __DIR__ . '/includes/navbar.php'; ?>

  <!-- +------------------------------------------------------+
       HERO - Full screen con burbujas y parallax
       +------------------------------------------------------+ -->
  <section id="inicio" class="hero-gradient relative min-h-[100svh] flex items-center overflow-hidden pt-16">

    <!-- Burbujas decorativas animadas -->
    <div class="bubble w-96 h-96 -top-20 -right-20 opacity-40" style="animation-delay:0s"></div>
    <div class="bubble w-64 h-64 top-1/2 -left-16 opacity-30" style="animation-delay:2s"></div>
    <div class="bubble w-48 h-48 bottom-20 right-1/3 opacity-20" style="animation-delay:4s"></div>

    <!-- Circulos geometricos de fondo -->

    <!-- Grid de puntos sutil -->
    <div class="absolute inset-0 pointer-events-none"
         style="background-image:radial-gradient(rgba(255,255,255,0.07) 1px,transparent 1px);background-size:32px 32px"></div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-10 sm:py-16 w-full">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-10 items-center">

        <!-- Texto -->
        <div data-aos="fade-right" data-aos-duration="1000" class="hero-text-col">

          <!-- Badge partido — oculto en mobile, visible desde sm -->
          <div class="hero-badge-wrap hidden sm:inline-flex items-center gap-2 glass rounded-full px-4 py-2 mb-4 sm:mb-6">
            <div class="w-6 h-6 bg-[#FACC15] rounded-md flex items-center justify-center font-black text-[#0039A6] text-xs"><?= htmlspecialchars(cfg_value($cfg_camp, 'index_hero_badge_letter', 'R')) ?></div>
            <span class="text-white font-semibold text-sm tracking-wide"><?= htmlspecialchars(cfg_value($cfg_camp, 'index_hero_badge_label', 'ALIANZA PARA EL PROGRESO')) ?></span>
          </div>

          <!-- ── Foto + nombre (solo mobile) ─────────────────── -->
          <div class="hero-photo-block lg:hidden">

            <!-- 1. Nombre: una sola línea sobre el fondo azul del hero -->
            <div class="hero-photo-name font-black text-center">
              <span class="text-white" style="font-size:2.5rem"><?= htmlspecialchars(cfg_value($cfg_camp, 'index_hero_title_line2', 'IVAN')) ?> </span><span style="font-size:2.5rem;color:#DC2626"><?= htmlspecialchars(cfg_value($cfg_camp, 'index_hero_title_line3', 'CISNEROS')) ?></span>
            </div>

            <!-- 2. Foto debajo del nombre -->
            <div class="hero-photo-img-wrap">
              <img src="<?= htmlspecialchars(BASE_URL) ?>/uploads/media/ivanmovil.webp"
                   alt="<?= htmlspecialchars(cfg_value($cfg_camp,'index_hero_title_line2','Ivan').' '.cfg_value($cfg_camp,'index_hero_title_line3','Cisneros')) ?>"
                   onerror="this.src='<?= htmlspecialchars(site_url($index_hero_profile_img)) ?>'">
              <!-- Cargo y año sobre la zona azul izquierda de la foto -->
              <div class="hero-photo-cargo">
                <div class="text-white font-bold uppercase tracking-widest" style="font-size:0.82rem;line-height:1.65;letter-spacing:.16em">
                  ALCALDE<br>POR<br><?= htmlspecialchars($camp_territorio) ?>
                </div>
                <div class="font-black mt-1" style="color:#FACC15;font-size:0.82rem;letter-spacing:.12em">
                  2026-2030
                </div>
              </div>
            </div>

          </div><!-- /hero-photo-block -->

          <!-- ── Texto desktop (sin la foto mobile) ─────────── -->
          <div class="hidden lg:block">
            <h1 class="text-4xl sm:text-6xl xl:text-7xl font-black text-white leading-[1.0] mb-3 sm:mb-5">
              <span><?= htmlspecialchars(cfg_value($cfg_camp, 'index_hero_title_line1', 'ING.')) ?><br></span>
              <span class="text-[#38BDF8]"><?= htmlspecialchars(cfg_value($cfg_camp, 'index_hero_title_line2', 'JOYER')) ?></span><br>
              <span class="text-white"><?= htmlspecialchars(cfg_value($cfg_camp, 'index_hero_title_line3', 'BASTIDAS')) ?></span>
            </h1>
            <div class="flex items-center gap-2 sm:gap-3 mb-2 sm:mb-4">
              <div class="h-1 w-8 sm:w-12 bg-[#FACC15] rounded-full flex-shrink-0"></div>
              <p class="text-[#38BDF8] font-bold text-base uppercase tracking-widest leading-tight">
                <?= htmlspecialchars(cfg_value($cfg_camp, 'index_hero_kicker', 'Candidato - Alcalde Provincial')) ?>
              </p>
            </div>
            <p class="text-white/80 font-medium text-lg">
              <?= htmlspecialchars(cfg_value($cfg_camp, 'index_hero_location', 'Satipo, Junin - 2026 - 2030')) ?>
            </p>
          </div><!-- /texto desktop -->

          <!-- Quote: oculta en móvil, visible desde lg -->
          <div class="hidden lg:inline-block glass rounded-2xl px-4 sm:px-5 py-3 sm:py-4 mb-8 mt-5">
            <p class="text-[#FACC15] font-black text-base sm:text-lg italic leading-snug">
              <?= nl2br(htmlspecialchars(cfg_value($cfg_camp, 'index_hero_quote', '"Ha llegado el momento de transformar Satipo"'))) ?>
            </p>
          </div>

          <div class="hero-cta-btns flex flex-col sm:flex-row gap-3 mt-5 sm:mt-0">
            <a href="<?= htmlspecialchars(site_url(cfg_value($cfg_camp, 'index_hero_primary_url', '/plan.php'))) ?>"
               class="btn-dyn group inline-flex items-center justify-center gap-2 font-black px-8 py-4 rounded-full transition-all shadow-xl hover:-translate-y-1 text-base"
               style="background-color:var(--color-btn-hero-primary);color:var(--color-btn-hero-primary-text)">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
              </svg>
              <?= htmlspecialchars(cfg_value($cfg_camp, 'index_hero_primary_text', 'Conoce el Plan')) ?>
            </a>
            <a href="<?= htmlspecialchars(site_url(cfg_value($cfg_camp, 'index_hero_secondary_url', '#unete'))) ?>"
               class="btn-dyn inline-flex items-center justify-center gap-2 font-black px-8 py-4 rounded-full transition-all shadow-xl hover:-translate-y-1 text-base"
               style="background-color:var(--color-btn-hero-secondary);color:var(--color-btn-hero-secondary-text)">
              <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
              </svg>
              <?= htmlspecialchars(cfg_value($cfg_camp, 'index_hero_secondary_text', 'Unete al Equipo')) ?>
            </a>
          </div>

          <!-- Contador regresivo -->
          <?php if ($countdown_active): ?>
          <div class="hero-countdown mt-6 sm:mt-10"
               id="countdown-block"
               data-target="<?= htmlspecialchars($countdown_date) ?>">
            <p class="text-white/50 text-xs uppercase tracking-widest mb-3 font-semibold">
              <?= htmlspecialchars($countdown_title) ?>
            </p>
            <div class="inline-flex items-end gap-1.5 sm:gap-2">
              <?php foreach ([['cd-days','Días'],['cd-hours','Hrs'],['cd-min','Min'],['cd-sec','Seg']] as [$elId, $unit]): ?>
              <div class="flex flex-col items-center">
                <div class="w-[4.2rem] sm:w-[5rem] bg-[#0B1E4A]/80 border border-white/10 rounded-xl py-2.5 text-center shadow-xl backdrop-blur-sm">
                  <span id="<?= $elId ?>"
                        class="block text-3xl sm:text-4xl text-white"
                        style="font-family:'DS-Digital',monospace;font-size:2.6rem;line-height:1;letter-spacing:.06em">00</span>
                </div>
                <span class="text-white/40 text-[9px] uppercase tracking-widest mt-1.5"><?= $unit ?></span>
              </div>
              <?php if ($unit !== 'Seg'): ?>
              <span class="text-white/30 text-2xl font-black pb-5 flex-shrink-0">:</span>
              <?php endif; ?>
              <?php endforeach; ?>
            </div>
            <p id="cd-label" class="text-white/30 text-[11px] mt-3 tracking-wide">
              <?= htmlspecialchars($countdown_label) ?>
            </p>
          </div>
          <?php endif; ?>
        </div>

        <!-- Composicion flotante del candidato y partido -->
        <div class="hidden lg:block min-h-[560px]" aria-hidden="true"></div>

        <div id="candidate-scroll-stage"
             class="candidate-scroll-stage"
             data-hero-src="<?= htmlspecialchars(site_url($index_hero_img)) ?>"
             data-profile-src="<?= htmlspecialchars(site_url($index_bio_img)) ?>"
             data-fallback-src="<?= htmlspecialchars(site_url($index_bio_fallback_img)) ?>">
          <div class="candidate-scroll-inner">

            <?php if ($hfx_use_rings): ?>
            <div class="hfx-ring hfx-ring-1" aria-hidden="true"></div>
            <div class="hfx-ring hfx-ring-2" aria-hidden="true"></div>
            <div class="hfx-ring hfx-ring-3" aria-hidden="true"></div>
            <?php endif; ?>

            <?php if ($hfx_use_arcos): ?>
            <div class="hfx-arco hfx-arco-1" aria-hidden="true"></div>
            <div class="hfx-arco hfx-arco-2" aria-hidden="true"></div>
            <?php endif; ?>

            <?php if ($hfx_use_sweep): ?>
            <div class="hfx-sweep-layer" aria-hidden="true"></div>
            <?php endif; ?>

            <?php if ($hfx_use_sparkles):
              $sparks = [
                ['t'=>'8%',  'l'=>'4%',  's'=>'9px',  'c'=>'#38BDF8', 'd'=>'3.2s', 'dl'=>'0s',   'tx'=>'-6px', 'ty'=>'-16px'],
                ['t'=>'22%', 'l'=>'60%', 's'=>'6px',  'c'=>'#FACC15', 'd'=>'2.9s', 'dl'=>'.7s',  'tx'=>'8px',  'ty'=>'-12px'],
                ['t'=>'45%', 'l'=>'2%',  's'=>'7px',  'c'=>'#7DD3FC', 'd'=>'3.7s', 'dl'=>'1.3s', 'tx'=>'-5px', 'ty'=>'-14px'],
                ['t'=>'62%', 'l'=>'62%', 's'=>'5px',  'c'=>'#38BDF8', 'd'=>'2.7s', 'dl'=>'1.9s', 'tx'=>'7px',  'ty'=>'-10px'],
                ['t'=>'15%', 'l'=>'30%', 's'=>'5px',  'c'=>'#FACC15', 'd'=>'4.1s', 'dl'=>'.4s',  'tx'=>'-4px', 'ty'=>'-18px'],
                ['t'=>'75%', 'l'=>'15%', 's'=>'8px',  'c'=>'#38BDF8', 'd'=>'3.5s', 'dl'=>'2.2s', 'tx'=>'-7px', 'ty'=>'-11px'],
                ['t'=>'35%', 'l'=>'68%', 's'=>'4px',  'c'=>'#7DD3FC', 'd'=>'3.0s', 'dl'=>'2.8s', 'tx'=>'5px',  'ty'=>'-13px'],
                ['t'=>'55%', 'l'=>'38%', 's'=>'6px',  'c'=>'#FACC15', 'd'=>'2.6s', 'dl'=>'1.1s', 'tx'=>'-3px', 'ty'=>'-15px'],
              ];
              foreach ($sparks as $sp): ?>
            <span class="hfx-sp" aria-hidden="true"
                  style="top:<?= $sp['t'] ?>;left:<?= $sp['l'] ?>;
                         width:<?= $sp['s'] ?>;height:<?= $sp['s'] ?>;
                         background:<?= $sp['c'] ?>;
                         box-shadow:0 0 7px 3px <?= $sp['c'] ?>88;
                         --dur:<?= $sp['d'] ?>;--del:<?= $sp['dl'] ?>;
                         --tx:<?= $sp['tx'] ?>;--ty:<?= $sp['ty'] ?>"></span>
            <?php endforeach; endif; ?>

            <div class="candidate-img-container">
              <img id="candidate-main-img"
                   src="<?= htmlspecialchars(site_url($index_hero_img)) ?>"
                   alt="Lic. Ivan Cisneros"
                   class="candidate-main-img">
            </div>
            <img src="<?= htmlspecialchars(site_url($index_hero_party_img)) ?>"
                 alt="<?= htmlspecialchars($cfg_camp['partido_nombre'] ?? 'ALIANZA PARA EL PROGRESO') ?>"
                 class="candidate-party-img">

            <!-- Badge flotante -->
            <div class="absolute glass-white rounded-2xl px-4 py-3 shadow-2xl"
                 style="top:22%;right:21%;animation:float 8s ease-in-out infinite;animation-delay:.5s;transform:rotate(1.5deg)">
              <div class="flex items-center gap-2.5">
                <div class="w-9 h-9 rounded-xl flex items-center justify-center flex-shrink-0"
                     style="background:linear-gradient(135deg,#0039A6,#0EA5E9)">
                  <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16m14 0h2m-2 0h-5m-9 0H3m2 0h5M9 7h1m-1 4h1m4-4h1m-1 4h1m-5 10v-5a1 1 0 011-1h2a1 1 0 011 1v5m-4 0h4"/>
                  </svg>
                </div>
                <div>
                  <p class="text-[#0039A6] font-black text-xs leading-tight tracking-wide"><?= htmlspecialchars(cfg_value($cfg_camp, 'index_hero_float_title', 'ALCALDE POR SATIPO')) ?></p>
                  <p class="text-[#0EA5E9] font-bold text-xs tracking-wide"><?= htmlspecialchars(cfg_value($cfg_camp, 'index_hero_float_subtitle', 'Gestion 2026 - 2030')) ?></p>
                </div>
              </div>
            </div>

          </div>
        </div>

        <div class="hidden">
          <div class="relative">
            <!-- Anillo exterior animado -->
            <div class="absolute -inset-6 rounded-full border-2 border-[#38BDF8]/30 animate-pulse-slow"></div>
            <div class="absolute -inset-12 rounded-full border border-white/10 animate-spin-slow"></div>

            <!-- Foto -->
            <div class="relative w-72 sm:w-80 lg:w-[380px] animate-float">
              <img src="<?= htmlspecialchars(site_url($index_bio_img)) ?>"
                   alt="Lic. Ivan Cisneros"
                   class="w-full rounded-3xl object-cover shadow-2xl"
                   style="filter: drop-shadow(0 30px 60px rgba(0,57,166,0.5))"
                   onerror="this.src='https://placehold.co/400x500/0039A6/white?text=Ivan+Cisneros'">

              <!-- Badge flotante partido -->
              <div class="absolute -top-4 -right-4 glass-white rounded-2xl px-4 py-2.5 shadow-lg">
                <div class="flex items-center gap-2">
                  <div class="w-8 h-8 bg-[#0039A6] rounded-lg flex items-center justify-center font-black text-white text-sm">R</div>
                  <div>
                    <p class="text-[#0039A6] font-black text-xs leading-tight">RENOVACION</p>
                    <p class="text-[#0039A6] font-black text-xs">POPULAR</p>
                  </div>
                </div>
              </div>

              <!-- Badge candidato -->
              <div class="absolute -bottom-4 -left-4 glass-white rounded-2xl px-4 py-2.5 shadow-lg">
                <p class="text-[#0039A6] font-black text-xs">Alcalde Provincial 2026</p>
                <p class="text-gray-500 text-[10px]">Satipo, Junin - Peru</p>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>

    <!-- Wave inferior -->
    <div class="wave absolute bottom-0 left-0 right-0">
      <svg viewBox="0 0 1440 80" preserveAspectRatio="none" style="height:80px;width:100%">
        <path d="M0,40 C360,80 1080,0 1440,40 L1440,80 L0,80 Z" fill="white"/>
      </svg>
    </div>
  </section>

  <!-- +------------------------------------------------------+
       STORYTELLING - Texto derecha / Pinned anim izq.
       +------------------------------------------------------+ -->
  <section class="relative overflow-hidden" style="min-height:600px">
    <!-- Fondo -->
    <div class="absolute inset-0">
      <img src="<?= htmlspecialchars(site_url($index_hero2_bg_img)) ?>"
           alt="Satipo amazonico" class="w-full h-full object-cover"
           onerror="this.style.background='#001f6e'">
      <div class="absolute inset-0" style="background:linear-gradient(135deg,rgba(0,31,110,0.95) 0%,rgba(0,57,166,0.90) 55%,rgba(14,165,233,0.82) 100%)"></div>
    </div>

    <!-- Detalles decorativos -->
    <div class="absolute top-10 right-20 w-32 h-32 border-2 border-[#FACC15]/15 rounded-2xl rotate-12 pointer-events-none"></div>
    <div class="absolute bottom-10 right-40 w-20 h-20 border-2 border-white/10 rounded-full pointer-events-none"></div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 h-full">
      <div class="grid grid-cols-1 lg:grid-cols-2 items-center" style="min-height:600px">

        <!-- Columna izquierda vacia - el Pinned Scroll Animation ocupa este espacio -->
        <div class="hidden lg:block"></div>

        <!-- Columna derecha: todo el texto -->
        <div class="text-white py-20 lg:pl-6" data-aos="fade-left" data-aos-duration="900">
          <div class="inline-flex items-center justify-center w-14 h-14 rounded-2xl bg-[#FACC15] mb-6 shadow-xl">
            <svg class="w-7 h-7 text-[#0039A6]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M3.055 11H5a2 2 0 012 2v1a2 2 0 002 2 2 2 0 012 2v2.945M8 3.935V5.5A2.5 2.5 0 0010.5 8h.5a2 2 0 012 2 2 2 0 104 0 2 2 0 012-2h1.064M15 20.488V18a2 2 0 012-2h3.064"/>
            </svg>
          </div>

          <h2 class="text-4xl sm:text-5xl font-black mb-5 leading-tight">
            <?= htmlspecialchars(cfg_value($cfg_camp, 'index_hero2_title_line1', 'Satipo merece')) ?><br>
            <span class="text-[#FACC15]"><?= htmlspecialchars(cfg_value($cfg_camp, 'index_hero2_title_highlight', 'orden, desarrollo')) ?></span><br>
            <?= htmlspecialchars(cfg_value($cfg_camp, 'index_hero2_title_line3', 'y oportunidades reales')) ?>
          </h2>

          <p class="text-lg text-blue-100 leading-relaxed mb-8">
            <?= nl2br(htmlspecialchars(cfg_value($cfg_camp, 'index_hero2_text', 'Nuestra provincia amazonica tiene todo el potencial para crecer. Con liderazgo honesto, planificacion real y trabajo en equipo, juntos construiremos el Satipo que merecemos.'))) ?>
          </p>

          <!-- 3 pilares -->
          <div class="hidden sm:grid grid-cols-1 sm:grid-cols-3 gap-3 mb-8">
            <?php
            foreach ($index_hero2_pillars as $p): ?>
            <div class="glass rounded-2xl p-4 text-center" data-aos="fade-up" data-aos-delay="100">
              <div class="w-9 h-9 mx-auto mb-2 rounded-xl bg-[#FACC15] flex items-center justify-center text-[#0039A6]">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= htmlspecialchars(index_icon_path((string)($p['icon'] ?? ''))) ?>"/>
                </svg>
              </div>
              <p class="font-bold text-white text-sm"><?= htmlspecialchars($p['title'] ?? '') ?></p>
              <p class="text-blue-200 text-xs"><?= htmlspecialchars($p['desc'] ?? '') ?></p>
            </div>
            <?php endforeach; ?>
          </div>

          <?php
            $onpe_text     = cfg_value($cfg_camp, 'index_hero2_onpe_text',     'Conoce tu local de votacion');
            $onpe_url      = cfg_value($cfg_camp, 'index_hero2_onpe_url',      'https://consultaelectoral.onpe.gob.pe/inicio');
            $onpe_subtitle = cfg_value($cfg_camp, 'index_hero2_onpe_subtitle', 'Ademas verifica si eres miembro de mesa');
          ?>
          <div class="flex flex-col sm:flex-row sm:items-start gap-3 w-full">

            <div class="sm:flex-1">
              <a href="<?= htmlspecialchars(site_url(cfg_value($cfg_camp, 'index_hero2_button_url', '/plan.php'))) ?>"
                 class="btn-dyn flex items-center justify-center gap-2 font-black px-6 rounded-full transition-all shadow-2xl hover:-translate-y-1 text-base w-full min-h-[3.25rem]"
                 style="background-color:var(--color-btn-download);color:var(--color-btn-download-text)">
                <?= htmlspecialchars(cfg_value($cfg_camp, 'index_hero2_button_text', 'Ver el Plan de Gobierno')) ?>
                <svg class="w-4 h-4 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                </svg>
              </a>
            </div>

            <?php if ($onpe_url): ?>
            <div class="sm:flex-1">
              <a href="<?= htmlspecialchars($onpe_url) ?>"
                 target="_blank" rel="noopener noreferrer"
                 class="flex items-center justify-center gap-0 font-black px-5 rounded-full transition-all shadow-xl hover:-translate-y-1 text-base bg-white w-full min-h-[3.25rem]">
                <img src="<?= htmlspecialchars(BASE_URL) ?>/uploads/media/onpe.webp"
                     alt="ONPE"
                     class="w-14 h-10 flex-shrink-0 object-contain">
                <span class="w-px self-stretch bg-gray-300 mx-4 flex-shrink-0"></span>
                <span class="text-[#0039A6] font-black text-sm leading-tight text-left"><?= htmlspecialchars($onpe_text) ?></span>
              </a>
              <?php if ($onpe_subtitle): ?>
              <p class="text-white/60 text-xs mt-2 text-center"><?= htmlspecialchars($onpe_subtitle) ?></p>
              <?php endif; ?>
            </div>
            <?php endif; ?>
          </div>
        </div>

      </div>
    </div>
  </section>

  <!-- +------------------------------------------------------+
       QUIEN ES - Diseno dividido con glassmorphism
       +------------------------------------------------------+ -->
  <section id="quien-es" class="py-24 bg-white relative overflow-hidden">
    <!-- Fondo decorativo sutil -->
    <div class="absolute top-0 right-0 w-1/2 h-full pointer-events-none"
         style="background:linear-gradient(135deg, #EFF6FF 0%, white 100%)"></div>
    <div class="absolute top-20 right-20 w-64 h-64 bg-[#0EA5E9]/5 rounded-full blur-3xl pointer-events-none"></div>

    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="grid grid-cols-1 lg:grid-cols-2 gap-16 items-center">

        <!-- Foto con efecto -->
        <div class="flex justify-center" data-aos="fade-right" data-aos-duration="900">
          <div class="relative w-full max-w-lg">
            <!-- Marco con gradiente -->
            <div class="absolute -inset-4 rounded-[2rem] opacity-30"
                 style="background:linear-gradient(135deg,#0039A6,#0EA5E9)"></div>
            <div class="absolute -inset-2 rounded-[2rem] opacity-10"
                 style="background:linear-gradient(135deg,#0039A6,#0EA5E9)"></div>
            <img id="quien-profile-photo" src="<?= htmlspecialchars(site_url($index_bio_img)) ?>"
                 alt="Ivan Cisneros - perfil"
                 class="quien-profile-photo relative w-full rounded-[1.75rem] shadow-2xl object-cover"
                 onerror="this.src='<?= htmlspecialchars(site_url($index_bio_fallback_img)) ?>'">

            <!-- Floating badge experiencia -->
            <div class="absolute -bottom-5 -right-5 bg-[#0039A6] text-white rounded-2xl px-5 py-3 shadow-xl"
                 data-aos="zoom-in" data-aos-delay="400">
              <p class="text-2xl font-black leading-none"><?= htmlspecialchars(cfg_value($cfg_camp, 'index_bio_badge_number', '+10')) ?></p>
              <p class="text-[#38BDF8] text-xs font-semibold"><?= htmlspecialchars(cfg_value($cfg_camp, 'index_bio_badge_label', 'anos de trayectoria')) ?></p>
            </div>
          </div>
        </div>

        <!-- Texto -->
        <div data-aos="fade-left" data-aos-duration="900">
          <span class="inline-block bg-[#0039A6]/10 text-[#0039A6] font-bold text-xs uppercase tracking-widest px-4 py-1.5 rounded-full mb-4">
            <?= htmlspecialchars(cfg_value($cfg_camp, 'index_bio_eyebrow', 'Conocelo')) ?>
          </span>
          <h2 class="text-4xl sm:text-5xl font-black text-[#0039A6] mb-6 leading-tight">
            <?= nl2br(htmlspecialchars(str_replace('|', "\n", cfg_value($cfg_camp, 'index_bio_title', 'Quien es|Ivan Cisneros?')))) ?>
          </h2>
          <p class="text-gray-600 leading-relaxed mb-4 text-base">
            <?= nl2br(htmlspecialchars(cfg_value($cfg_camp, 'index_bio_p1', 'El Lic. Ivan Cisneros es un profesional satipeno comprometido con el desarrollo de su provincia. Con formacion en ingenieria y amplia trayectoria en gestion publica, conoce de primera mano las necesidades de cada comunidad de Satipo.'))) ?>
          </p>
          <p class="text-gray-600 leading-relaxed mb-8 text-base">
            <?= nl2br(htmlspecialchars(cfg_value($cfg_camp, 'index_bio_p2', 'Nacido y criado en la region, ha dedicado su carrera al servicio de la gente, impulsando proyectos de infraestructura, agricultura y desarrollo comunitario en los ocho distritos de la provincia.'))) ?>
          </p>

          <!-- Atributos con iconos modernos -->
          <div class="hidden sm:grid grid-cols-1 sm:grid-cols-3 gap-4 mb-8">
            <?php
            foreach ($index_bio_attrs as $i => $a): ?>
            <div class="group p-4 rounded-2xl border-2 border-gray-100 hover:border-[#0EA5E9] hover:bg-blue-50 transition-all cursor-default"
                 data-aos="fade-up" data-aos-delay="<?= $i*100 ?>">
              <div class="w-10 h-10 mb-2 rounded-xl bg-blue-50 text-[#0039A6] flex items-center justify-center group-hover:bg-[#0039A6] group-hover:text-white transition-colors">
                <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= htmlspecialchars(index_icon_path((string)($a['icon'] ?? ''))) ?>"/>
                </svg>
              </div>
              <p class="font-bold text-[#0039A6] text-sm"><?= htmlspecialchars($a['title'] ?? '') ?></p>
              <p class="text-gray-400 text-xs mt-0.5"><?= htmlspecialchars($a['desc'] ?? '') ?></p>
            </div>
            <?php endforeach; ?>
          </div>

          <a href="<?= htmlspecialchars(site_url(cfg_value($cfg_camp, 'index_bio_button_url', '#biografia'))) ?>"
             class="inline-flex items-center gap-2 bg-[#0039A6] hover:bg-[#0056D6] text-white font-bold px-8 py-3.5 rounded-full transition-all shadow-lg hover:-translate-y-0.5 text-sm">
            <?= htmlspecialchars(cfg_value($cfg_camp, 'index_bio_button_text', 'Ver biografia completa')) ?>
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
            </svg>
          </a>
        </div>
      </div>
    </div>
  </section>

  <!-- +------------------------------------------------------+
       PLAN DE GOBIERNO - Cards con efecto premium
       +------------------------------------------------------+ -->
  <section id="plan" class="py-16 relative overflow-hidden"
           style="background:linear-gradient(180deg,#F0F7FF 0%,#E8F4FF 50%,white 100%)">

    <div class="absolute inset-0 pointer-events-none"
         style="background-image:radial-gradient(rgba(0,57,166,0.04) 1.5px,transparent 1.5px);background-size:28px 28px"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="text-center mb-16" data-aos="fade-up">
        <span class="inline-block bg-[#0039A6] text-white font-bold text-xs uppercase tracking-widest px-4 py-1.5 rounded-full mb-4">
          <?= htmlspecialchars(cfg_value($cfg_camp, 'index_work_eyebrow', 'Propuestas 2026')) ?>
        </span>
        <h2 class="text-4xl sm:text-5xl font-black text-[#0039A6] mt-2 mb-4"><?= htmlspecialchars(cfg_value($cfg_camp, 'index_work_title', 'Nuestros Ejes de Trabajo')) ?></h2>
        <p class="text-gray-500 max-w-xl mx-auto text-base">
          <?= htmlspecialchars(cfg_value($cfg_camp, 'index_work_text', 'Un plan concreto, realista y comprometido para transformar Satipo en cuatro anos.')) ?>
        </p>
      </div>

      <div class="hidden sm:grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-6">
        <?php foreach ($index_work_axes as $i => $eje): ?>
        <div class="eje-card" data-aos="fade-up" data-aos-delay="<?= $i * 70 ?>">
          <div class="top-bar"></div>
          <div class="p-6">
            <div class="w-14 h-14 rounded-2xl bg-gradient-to-br <?= htmlspecialchars($eje['grad'] ?? 'from-blue-500 to-indigo-600') ?> flex items-center justify-center text-white mb-4 shadow-lg">
              <svg class="w-7 h-7" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="<?= htmlspecialchars(index_icon_path((string)($eje['icon'] ?? 'gestion'))) ?>"/>
              </svg>
            </div>
            <h3 class="font-black text-[#0039A6] text-lg mb-2"><?= htmlspecialchars($eje['title'] ?? '') ?></h3>
            <p class="text-gray-500 text-sm leading-relaxed mb-4"><?= htmlspecialchars($eje['desc'] ?? '') ?></p>
            <a href="<?= BASE_URL ?>/plan.php#<?= htmlspecialchars($eje['id'] ?? '') ?>"
               class="inline-flex items-center gap-1 text-[#0EA5E9] font-semibold text-xs hover:gap-2 transition-all">
              Ver mas <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
            </a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="text-center mt-10" data-aos="fade-up">
        <a href="<?= htmlspecialchars(site_url(cfg_value($cfg_camp, 'index_work_button_url', '/plan.php'))) ?>"
           class="btn-dyn inline-flex items-center gap-3 font-black px-12 py-5 rounded-full transition-all shadow-2xl hover:-translate-y-1 text-base"
           style="background-color:var(--color-btn-download);color:var(--color-btn-download-text)">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
          </svg>
          <?= htmlspecialchars(cfg_value($cfg_camp, 'index_work_button_text', 'Ver Plan Completo 2026-2030')) ?>
        </a>
      </div>
    </div>
  </section>

  <!-- +------------------------------------------------------+
       EQUIPO DISTRITAL - Slider Swiper premium
       +------------------------------------------------------+ -->
  <section id="equipo" class="py-16 bg-white relative overflow-hidden">
    <div class="absolute left-0 top-0 bottom-0 w-1 bg-gradient-to-b from-transparent via-[#0039A6] to-transparent opacity-20"></div>

    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="text-center mb-16" data-aos="fade-up">
        <span class="inline-block bg-[#0039A6]/10 text-[#0039A6] font-bold text-xs uppercase tracking-widest px-4 py-1.5 rounded-full mb-4">
          <?= htmlspecialchars($camp_label_eq) ?>
        </span>
        <h2 class="text-4xl sm:text-5xl font-black text-[#0039A6] mb-4">
          Nuestro Equipo en los <?= count($candidatos) ?> <?= htmlspecialchars($camp_label_plur) ?>
        </h2>
        <p class="text-gray-500 max-w-lg mx-auto">
          <?php if ($camp_tipo === 'provincial'): ?>
            Un candidato comprometido en cada <?= htmlspecialchars($camp_label_sing) ?>, respaldado por Ivan Cisneros.
          <?php else: ?>
            Conoce los <?= htmlspecialchars($camp_label_plur) ?> y comunidades de <?= htmlspecialchars($camp_territorio) ?>.
          <?php endif; ?>
        </p>
      </div>


      <div class="swiper candidatos-swiper" data-aos="fade-up" data-aos-delay="100">
        <div class="swiper-wrapper pb-12">
          <?php foreach ($candidatos as $c):
            $foto_src = !empty($c['foto'])
                ? BASE_URL . '/' . ltrim($c['foto'], '/')
                : BASE_URL . '/assets/img/distritales/' . $c['slug'] . '/foto.webp';
            $nombre_display = !empty(trim($c['nombre'])) ? $c['nombre'] : '[Candidato]';
            $fallback = 'https://placehold.co/180x220/0039A6/white?text=' . urlencode($c['distrito']);
          ?>
          <div class="swiper-slide">
            <div class="cand-card mx-2">
              <!-- Imagen -->
              <div class="relative overflow-hidden" style="height:200px">
                <img src="<?= htmlspecialchars($foto_src) ?>"
                     alt="<?= htmlspecialchars($nombre_display) ?>"
                     class="w-full h-full object-cover object-top transition-transform duration-500 hover:scale-110"
                     onerror="this.src='<?= $fallback ?>'">
                <div class="absolute inset-0 bg-gradient-to-t from-[#0039A6]/60 to-transparent"></div>
                <div class="absolute bottom-3 left-3">
                  <span class="bg-[#FACC15] text-[#0039A6] text-[10px] font-black px-2 py-0.5 rounded-full uppercase">
                    <?= htmlspecialchars($c['distrito']) ?>
                  </span>
                </div>
              </div>
              <!-- Info -->
              <div class="p-4 text-center">
                <h3 class="font-black text-[#0039A6] text-sm"><?= htmlspecialchars($nombre_display) ?></h3>
                <p class="text-gray-400 text-xs mt-0.5 mb-3">
                  <?= $camp_tipo === 'provincial' ? 'Candidato Alcalde Distrital' : htmlspecialchars($camp_label_sing) ?>
                </p>
                <a href="<?= BASE_URL ?>/distritales/<?= htmlspecialchars($c['slug']) ?>.php"
                   class="inline-block w-full text-center bg-[#0039A6] hover:bg-[#0056D6] text-white text-xs font-bold py-2 rounded-xl transition-colors">
                  Ver propuestas
                </a>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
        <div class="swiper-pagination"></div>
      </div>
    </div>
  </section>

  <?php if ($index_social_enabled): ?>
  <!-- +------------------------------------------------------+
       REDES SOCIALES - Facebook timeline oficial
       +------------------------------------------------------+ -->
  <section id="redes-sociales" class="py-20 bg-white">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
      <div class="text-center mb-8" data-aos="fade-up">
        <span class="inline-block bg-[#0039A6]/10 text-[#0039A6] font-black text-xs sm:text-sm uppercase tracking-[0.18em] px-5 py-2 rounded-xl">
          <?= htmlspecialchars(cfg_value($cfg_camp, 'index_social_eyebrow', 'Siguenos en nuestras redes sociales')) ?>
        </span>
        <h2 class="text-3xl sm:text-5xl lg:text-6xl font-black text-[#0039A6] mt-4 mb-3 leading-tight">
          <?= htmlspecialchars(cfg_value($cfg_camp, 'index_social_title', 'Unete y se parte del cambio')) ?>
        </h2>
        <p class="text-gray-400 font-bold max-w-2xl mx-auto leading-snug text-sm sm:text-base">
          <?= htmlspecialchars(cfg_value($cfg_camp, 'index_social_subtitle', 'Siguenos en nuestra cuenta oficial de Facebook y enterate de todas nuestras actividades partidarias, juntos por un Satipo progresista.')) ?>
        </p>
      </div>

      <div class="relative overflow-hidden rounded-[1.7rem] bg-white shadow-[0_24px_70px_rgba(0,57,166,.10)]" data-aos="fade-up" data-aos-delay="100">
        <div class="absolute inset-x-0 top-0 h-2 bg-gradient-to-r from-[#0039A6] via-[#0056D6] to-[#0EA5E9]"></div>
        <div class="grid grid-cols-1 lg:grid-cols-[minmax(0,1fr)_520px] gap-5 lg:gap-8 items-start p-3 sm:p-6 lg:p-8 pt-8 lg:pt-10">
          <div>
            <div class="relative overflow-hidden rounded-2xl border border-gray-100 bg-gray-50 shadow-sm">
              <img src="<?= htmlspecialchars(site_url($index_social_image)) ?>"
                   alt="<?= htmlspecialchars(cfg_value($cfg_camp, 'index_social_image_alt', 'Ivan Cisneros por Satipo')) ?>"
                   class="w-full h-[260px] sm:h-[380px] lg:h-[440px] object-cover object-center"
                   onerror="this.src='<?= htmlspecialchars(site_url('/assets/img/candidato/joyerd.webp')) ?>'">
              <a href="<?= htmlspecialchars(site_url(cfg_value($cfg_camp, 'index_social_button_url', $index_social_facebook_url))) ?>"
                 target="_blank" rel="noopener"
                 class="absolute left-5 bottom-5 inline-flex items-center gap-3 bg-[#0484C7] hover:bg-[#036CA3] text-white font-black px-5 sm:px-6 py-3 rounded-xl shadow-lg transition-all hover:-translate-y-0.5">
                <span class="relative flex h-8 w-8 items-center justify-center">
                  <span class="absolute inset-0 rounded-full border-2 border-white/70"></span>
                  <span class="absolute h-12 w-12 rounded-full border border-white/30"></span>
                  <svg class="w-5 h-5 -rotate-12" fill="currentColor" viewBox="0 0 24 24" aria-hidden="true">
                    <path d="M2.01 21 23 12 2.01 3 2 10l15 2-15 2z"/>
                  </svg>
                </span>
                <?= htmlspecialchars(cfg_value($cfg_camp, 'index_social_button_text', 'Unete a nosotros..!')) ?>
              </a>
            </div>
          </div>

          <div class="min-w-0">
            <div class="w-full rounded-2xl bg-white border border-gray-100 shadow-sm p-2 sm:p-3">
              <div class="fb-timeline-crop ml-auto w-full max-w-[500px]" style="--fb-visible-height: <?= $index_social_height ?>px;">
              <iframe
                title="Publicaciones de Facebook"
                src="<?= htmlspecialchars(facebook_page_plugin_src($index_social_facebook_url, $index_social_height + 72)) ?>"
                width="500"
                height="<?= $index_social_height + 72 ?>"
                class="fb-timeline-frame"
                style="border:none;overflow:hidden;width:100%;max-width:500px;min-height:<?= $index_social_height + 72 ?>px"
                scrolling="no"
                frameborder="0"
                allowfullscreen="true"
                allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share">
              </iframe>
              </div>
            </div>
            <div class="mt-3 text-right">
              <a href="<?= htmlspecialchars($index_social_facebook_url) ?>" target="_blank" rel="noopener"
                 class="inline-flex items-center gap-2 text-[#0039A6] hover:text-[#0056D6] text-sm font-bold">
                Ver pagina oficial en Facebook
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/>
                </svg>
              </a>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- +------------------------------------------------------+
       NOTICIAS - Slider Swiper + boton ver todas
       +------------------------------------------------------+ -->
  <section id="noticias" class="py-24"
           style="background:linear-gradient(180deg,#F8FAFF 0%,white 100%)">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">

      <!-- Encabezado + boton ver todas (top) -->
      <div class="flex flex-col sm:flex-row sm:items-end justify-between gap-4 mb-12" data-aos="fade-up">
        <div>
          <span class="inline-block bg-[#0039A6]/10 text-[#0039A6] font-bold text-xs uppercase tracking-widest px-4 py-1.5 rounded-full mb-3">
            <?= htmlspecialchars(cfg_value($cfg_camp, 'index_news_eyebrow', 'Actualidad')) ?>
          </span>
          <h2 class="text-4xl sm:text-5xl font-black text-[#0039A6] mb-2"><?= htmlspecialchars(cfg_value($cfg_camp, 'index_news_title', 'Ultimas Noticias')) ?></h2>
          <p class="text-gray-500 max-w-md"><?= htmlspecialchars(cfg_value($cfg_camp, 'index_news_text', 'Enterate de todo lo que pasa en la campana.')) ?></p>
        </div>
        <a href="<?= htmlspecialchars($news_url) ?>"
           class="flex-shrink-0 inline-flex items-center gap-2 border-2 border-[#0039A6] text-[#0039A6]
                  hover:bg-[#0039A6] hover:text-white font-bold text-sm px-5 py-2.5 rounded-full
                  transition-all duration-200 group">
          <?= htmlspecialchars(cfg_value($cfg_camp, 'index_news_button_text', 'Ver todas las noticias')) ?>
          <svg class="w-4 h-4 transition-transform group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
          </svg>
        </a>
      </div>

      <?php if (!empty($noticias)): ?>
      <!-- Slider Swiper noticias -->
      <div class="relative" data-aos="fade-up" data-aos-delay="100">

        <!-- Flechas personalizadas -->
        <button class="noticias-prev absolute left-0 top-[90px] -translate-x-5 z-10
                       w-10 h-10 bg-white shadow-lg rounded-full items-center justify-center
                       text-[#0039A6] hover:bg-[#0039A6] hover:text-white transition-all duration-200
                       border border-gray-100 hidden lg:flex">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
          </svg>
        </button>
        <button class="noticias-next absolute right-0 top-[90px] translate-x-5 z-10
                       w-10 h-10 bg-white shadow-lg rounded-full items-center justify-center
                       text-[#0039A6] hover:bg-[#0039A6] hover:text-white transition-all duration-200
                       border border-gray-100 hidden lg:flex">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
          </svg>
        </button>

        <div class="swiper noticias-swiper">
          <div class="swiper-wrapper pb-12">
            <?php foreach ($noticias as $i => $n):
              $img_url = !empty($n['imagen'])
                ? BASE_URL . '/assets/img/noticias/' . htmlspecialchars($n['imagen'])
                : 'https://placehold.co/400x200/EEF2FF/0039A6?text=Noticia';
            ?>
            <div class="swiper-slide h-auto">
              <article class="noticia-card group flex flex-col h-full">
                <div class="overflow-hidden flex-shrink-0" style="height:185px">
                  <img src="<?= $img_url ?>"
                       alt="<?= htmlspecialchars($n['titulo']) ?>"
                       class="w-full h-full object-cover group-hover:scale-110 transition-transform duration-500"
                       onerror="this.src='https://placehold.co/400x185/EEF2FF/0039A6?text=Noticia'">
                </div>
                <div class="p-5 flex flex-col flex-1">
                  <span class="inline-block bg-[#0EA5E9]/15 text-[#0039A6] text-[10px] font-bold uppercase px-2.5 py-1 rounded-full mb-2 tracking-wide self-start">
                    <?= htmlspecialchars($n['categoria'] ?: 'Noticias') ?>
                  </span>
                  <a href="<?= BASE_URL ?>/noticias/ver.php?id=<?= $n['id'] ?>"
                     class="font-bold text-gray-800 hover:text-[#0039A6] text-sm leading-snug mb-3 line-clamp-3 flex-1 transition-colors block">
                    <?= htmlspecialchars($n['titulo']) ?>
                  </a>
                  <div class="flex items-center justify-between mt-auto pt-3 border-t border-gray-100">
                    <span class="text-gray-400 text-xs"><?= date('d/m/Y', strtotime($n['fecha'])) ?></span>
                    <a href="<?= BASE_URL ?>/noticias/ver.php?id=<?= $n['id'] ?>"
                       class="inline-flex items-center gap-1 bg-sky-400 hover:bg-sky-500 text-white text-xs font-bold px-3 py-1.5 rounded-full transition-all shadow-sm">
                      Leer
                      <svg class="w-3 h-3" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
                        <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                      </svg>
                    </a>
                  </div>
                </div>
              </article>
            </div>
            <?php endforeach; ?>
          </div>
          <div class="swiper-pagination noticias-pagination"></div>
        </div>
      </div>

      <!-- Boton ver todas (bottom) -->
      <div class="text-center mt-6" data-aos="fade-up">
        <a href="<?= htmlspecialchars($news_url) ?>"
           class="inline-flex items-center gap-2 bg-[#0039A6] hover:bg-[#0056D6] text-white
                  font-bold text-sm px-8 py-3 rounded-full transition-all shadow-lg
                  hover:shadow-xl hover:-translate-y-0.5 duration-200">
          <?= htmlspecialchars(cfg_value($cfg_camp, 'index_news_button_text', 'Ver todas las noticias')) ?>
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
          </svg>
        </a>
      </div>

      <?php else: ?>
      <p class="text-center text-gray-400 py-12"><?= htmlspecialchars(cfg_value($cfg_camp, 'index_news_empty_text', 'Proximamente publicaremos las ultimas noticias de la campana.')) ?></p>
      <?php endif; ?>

    </div>
  </section>

  <!-- +------------------------------------------------------+
       UNETE - Gradiente intenso + formulario glass
       +------------------------------------------------------+ -->
  <section id="unete" class="py-24 relative overflow-hidden"
           style="background:linear-gradient(135deg,#001f6e 0%,#0039A6 40%,#0056D6 70%,#0EA5E9 100%)">

    <!-- Decoracion -->
    <div class="absolute top-0 left-0 right-0 bottom-0 pointer-events-none overflow-hidden">
      <div class="absolute -top-20 -right-20 w-96 h-96 bg-[#38BDF8]/10 rounded-full blur-3xl"></div>
      <div class="absolute -bottom-20 -left-20 w-80 h-80 bg-[#FACC15]/5 rounded-full blur-3xl"></div>
      <div class="absolute top-1/2 left-1/2 -translate-x-1/2 -translate-y-1/2 w-[85vw] h-[85vw] sm:w-[600px] sm:h-[600px] border border-white/5 rounded-full"></div>
    </div>

    <div class="relative max-w-3xl mx-auto px-4 sm:px-6" data-aos="fade-up">
      <div class="text-center mb-10">
        <div class="inline-flex items-center justify-center w-16 h-16 bg-[#FACC15] rounded-2xl mb-5 shadow-xl">
          <svg class="w-8 h-8 text-[#0039A6]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
          </svg>
        </div>
        <h2 class="text-4xl sm:text-5xl font-black text-white mb-3">
          <?= nl2br(htmlspecialchars(str_replace('|', "\n", cfg_value($cfg_camp, 'index_join_title', 'Se parte del cambio|en Satipo')))) ?>
        </h2>
        <p class="text-blue-100 text-sm"><?= htmlspecialchars(cfg_value($cfg_camp, 'index_join_text', 'Suma tu energia, tus ideas y tu compromiso a este equipo.')) ?></p>
      </div>

      <form id="form-unete" action="<?= BASE_URL ?>/includes/registrar.php" method="POST"
            class="glass rounded-3xl p-8 space-y-4">
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
          <div>
            <label class="block text-white text-sm font-semibold mb-1.5"><?= htmlspecialchars(cfg_value($cfg_camp, 'index_join_name_label', 'Nombre completo *')) ?></label>
            <input type="text" name="nombre" required placeholder="<?= htmlspecialchars(cfg_value($cfg_camp, 'index_join_name_placeholder', 'Tu nombre completo')) ?>"
                   class="w-full bg-white/90 border-0 rounded-xl px-4 py-3 text-gray-800 text-sm placeholder-gray-400 focus:ring-2 focus:ring-[#FACC15] outline-none transition-all">
          </div>
          <div>
            <label class="block text-white text-sm font-semibold mb-1.5"><?= htmlspecialchars(cfg_value($cfg_camp, 'index_join_dni_label', 'DNI *')) ?></label>
            <input type="text" name="dni" required placeholder="12345678" maxlength="8" pattern="\d{8}"
                   class="w-full bg-white/90 border-0 rounded-xl px-4 py-3 text-gray-800 text-sm placeholder-gray-400 focus:ring-2 focus:ring-[#FACC15] outline-none transition-all">
          </div>
          <div>
            <label class="block text-white text-sm font-semibold mb-1.5"><?= htmlspecialchars(cfg_value($cfg_camp, 'index_join_phone_label', 'Telefono *')) ?></label>
            <input type="tel" name="telefono" required placeholder="999 999 999"
                   class="w-full bg-white/90 border-0 rounded-xl px-4 py-3 text-gray-800 text-sm placeholder-gray-400 focus:ring-2 focus:ring-[#FACC15] outline-none transition-all">
          </div>
          <div>
            <label class="block text-white text-sm font-semibold mb-1.5"><?= htmlspecialchars(cfg_value($cfg_camp, 'index_join_district_label', 'Distrito *')) ?></label>
            <select name="distrito" required
                    class="w-full bg-white/90 border-0 rounded-xl px-4 py-3 text-gray-800 text-sm focus:ring-2 focus:ring-[#FACC15] outline-none">
              <option value=""><?= htmlspecialchars(cfg_value($cfg_camp, 'index_join_district_placeholder', 'Selecciona tu distrito')) ?></option>
              <option>Satipo (Capital)</option>
              <option>Rio Negro</option><option>Pangoa</option>
              <option>Rio Tambo</option><option>Coviriali</option>
              <option>Llaylla</option><option>Vizcatan del Ene</option>
              <option>Pampa Hermosa</option><option>Mazamari</option>
            </select>
          </div>
        </div>
        <div id="unete-msg" class="hidden text-sm font-semibold rounded-xl px-4 py-3"></div>
        <button type="submit"
                class="btn-dyn w-full font-black text-base py-4 rounded-full transition-all shadow-2xl hover:-translate-y-0.5 mt-2"
                style="background-color:var(--color-btn-join);color:var(--color-btn-join-text)">
          <?= htmlspecialchars(cfg_value($cfg_camp, 'index_join_button_text', 'Me uno al equipo de Ivan Cisneros')) ?>
        </button>
      </form>
    </div>
  </section>

  <!-- +------------------------------------------------------+
       AGENDA - Cards con colores de distrito
       +------------------------------------------------------+ -->
  <section id="agenda" class="py-24 bg-[#F8FAFF]">
    <div class="max-w-6xl mx-auto px-4 sm:px-6 lg:px-8">

      <div class="text-center mb-14" data-aos="fade-up">
        <span class="inline-block bg-[#0039A6]/10 text-[#0039A6] font-bold text-xs uppercase tracking-widest px-4 py-1.5 rounded-full mb-4">
          <?= htmlspecialchars(cfg_value($cfg_camp, 'index_agenda_eyebrow', 'Calendario')) ?>
        </span>
        <h2 class="text-4xl sm:text-5xl font-black text-[#0039A6] mb-3"><?= htmlspecialchars(cfg_value($cfg_camp, 'index_agenda_title', 'Proximas Actividades')) ?></h2>
        <p class="text-gray-500 text-base"><?= htmlspecialchars(cfg_value($cfg_camp, 'index_agenda_text', 'Conoce el itinerario de campana de Ivan Cisneros')) ?></p>
      </div>

      <?php
      $meses_cortos = ['','Ene','Feb','Mar','Abr','May','Jun','Jul','Ago','Sep','Oct','Nov','Dic'];
      $meses_full   = ['','Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'];
      $colores_dist = [
          'Satipo (Capital)'   => '#6366F1',
          'Rio Negro'          => '#EC4899',
          'Pangoa'             => '#F59E0B',
          'Rio Tambo'          => '#10B981',
          'Coviriali'          => '#3B82F6',
          'Llaylla'            => '#8B5CF6',
          'Vizcatan del Ene'   => '#F43F5E',
          'Pampa Hermosa'      => '#06B6D4',
          'Mazamari'           => '#84CC16',
      ];
      try {
          $eventos = $pdo->query(
              "SELECT nombre, fecha, hora, lugar, distrito FROM actividades
               WHERE estado = 'publicado' AND fecha >= CURDATE()
               ORDER BY fecha ASC, hora ASC LIMIT 12"
          )->fetchAll();
          if (empty($eventos)) {
              $eventos = $pdo->query(
                  "SELECT nombre, fecha, hora, lugar, distrito FROM actividades
                   WHERE estado = 'publicado'
                   ORDER BY fecha DESC, hora ASC LIMIT 12"
              )->fetchAll();
          }
      } catch (Exception $e) { $eventos = []; }
      ?>

      <?php if (empty($eventos)): ?>
      <p class="text-center text-gray-400 py-12 text-base"><?= htmlspecialchars(cfg_value($cfg_camp, 'index_agenda_empty_text', 'Proximamente anunciaremos las actividades de campana.')) ?></p>
      <?php else: ?>

      <!-- Slider agenda -->
      <div class="relative" data-aos="fade-up" data-aos-delay="100">

        <!-- Flechas -->
        <button class="agenda-prev absolute left-0 top-1/2 -translate-y-6 -translate-x-5 z-10
                       w-10 h-10 bg-white shadow-lg rounded-full items-center justify-center
                       text-[#0039A6] hover:bg-[#0039A6] hover:text-white transition-all border border-gray-100
                       hidden lg:flex">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
          </svg>
        </button>
        <button class="agenda-next absolute right-0 top-1/2 -translate-y-6 translate-x-5 z-10
                       w-10 h-10 bg-white shadow-lg rounded-full items-center justify-center
                       text-[#0039A6] hover:bg-[#0039A6] hover:text-white transition-all border border-gray-100
                       hidden lg:flex">
          <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
            <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
          </svg>
        </button>

        <div class="swiper agenda-swiper">
          <div class="swiper-wrapper pb-12">
            <?php foreach ($eventos as $i => $ev):
              $ts       = strtotime($ev['fecha']);
              $dia      = date('d', $ts);
              $mes_c    = $meses_cortos[(int)date('n', $ts)];
              $mes_f    = $meses_full[(int)date('n', $ts)];
              $anio      = date('Y', $ts);
              $hora_fmt = $ev['hora'] ? date('g:i a', strtotime($ev['hora'])) : null;
              $color    = $colores_dist[$ev['distrito']] ?? '#0039A6';
              $es_hoy   = date('Y-m-d', $ts) === date('Y-m-d');
              $es_pasado = $ts < strtotime('today');
            ?>
            <div class="swiper-slide h-auto">
            <div class="group relative bg-white rounded-2xl shadow-sm border border-gray-100
                        hover:shadow-lg hover:-translate-y-1 transition-all duration-250 overflow-hidden flex flex-col h-full">

          <!-- Barra de acento superior con color del distrito -->
          <div class="h-1.5 w-full flex-shrink-0" style="background:<?= $color ?>"></div>

          <div class="p-5 flex gap-4 flex-1">

            <!-- Badge fecha -->
            <div class="flex-shrink-0 flex flex-col items-center justify-center rounded-xl px-3 py-2.5
                        min-w-[58px] text-center"
                 style="background:<?= $color ?>18">
              <span class="block text-2xl font-black leading-none" style="color:<?= $color ?>"><?= $dia ?></span>
              <span class="block text-xs font-black uppercase mt-0.5" style="color:<?= $color ?>99"><?= $mes_c ?></span>
              <span class="block text-[10px] text-gray-400 mt-0.5"><?= $anio ?></span>
            </div>

            <!-- Contenido -->
            <div class="flex-1 min-w-0">

              <?php if ($es_hoy): ?>
              <span class="inline-block text-[10px] font-black text-white px-2 py-0.5 rounded-full mb-1.5"
                    style="background:<?= $color ?>">HOY</span>
              <?php elseif ($es_pasado): ?>
              <span class="inline-block text-[10px] font-semibold text-gray-400 bg-gray-100 px-2 py-0.5 rounded-full mb-1.5">Realizado</span>
              <?php endif; ?>

              <h3 class="font-bold text-gray-900 text-sm leading-snug mb-2 line-clamp-2 group-hover:text-[#0039A6] transition-colors">
                <?= htmlspecialchars($ev['nombre']) ?>
              </h3>

              <div class="space-y-1">
                <?php if ($hora_fmt): ?>
                <div class="flex items-center gap-1.5 text-xs text-gray-500">
                  <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                  </svg>
                  <span class="font-semibold"><?= $hora_fmt ?></span>
                </div>
                <?php endif; ?>
                <?php if (!empty($ev['lugar'])): ?>
                <div class="flex items-center gap-1.5 text-xs text-gray-500">
                  <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                  </svg>
                  <span class="truncate"><?= htmlspecialchars($ev['lugar']) ?></span>
                </div>
                <?php endif; ?>
              </div>
            </div>
          </div>

          <!-- Footer card: distrito + flecha -->
          <div class="px-5 py-3 border-t border-gray-50 flex items-center justify-between">
            <span class="inline-flex items-center gap-1.5 text-xs font-bold rounded-full px-2.5 py-1"
                  style="background:<?= $color ?>18; color:<?= $color ?>">
              <span class="w-1.5 h-1.5 rounded-full flex-shrink-0" style="background:<?= $color ?>"></span>
              <?= htmlspecialchars($ev['distrito']) ?>
            </span>
            <div class="w-7 h-7 rounded-full flex items-center justify-center transition-all duration-200"
                 style="background:<?= $color ?>18"
                 :style="{ background: '<?= $color ?>18' }">
              <svg class="w-3.5 h-3.5 transition-transform group-hover:translate-x-0.5" fill="none" stroke="currentColor"
                   viewBox="0 0 24 24" stroke-width="2.5" style="color:<?= $color ?>">
                <path stroke-linecap="round" stroke-linejoin="round" d="M9 5l7 7-7 7"/>
              </svg>
            </div>
          </div>
            </div>
            </div><!-- /swiper-slide -->
            <?php endforeach; ?>
          </div><!-- /swiper-wrapper -->
          <div class="swiper-pagination agenda-pagination"></div>
        </div><!-- /swiper -->
      </div><!-- /relative -->

      <?php endif; ?>
    </div>
  </section>

  <!-- +------------------------------------------------------+
       LOCAL DE CAMPANA
       +------------------------------------------------------+ -->
  <section id="local" class="bg-[#F0F9FF]">

    <!-- Info + Mapa lado a lado -->
    <div class="grid grid-cols-1 lg:grid-cols-3 min-h-[420px]">

      <!-- Columna izquierda - Informacion -->
      <div class="flex items-center px-8 py-16 lg:px-12 xl:px-16 lg:col-span-1" data-aos="fade-right">
        <div class="max-w-md w-full">
          <span class="text-[#38BDF8] font-semibold uppercase tracking-widest text-sm"><?= htmlspecialchars(cfg_value($cfg_camp, 'index_contact_eyebrow', 'Encuentranos')) ?></span>
          <h2 class="text-3xl sm:text-4xl font-black text-[#1E3A8A] mt-2 mb-8">
            <?= nl2br(htmlspecialchars(str_replace('|', "\n", cfg_value($cfg_camp, 'index_contact_title', 'Nuestro Local|de Campana')))) ?>
          </h2>

          <div class="space-y-5">
            <!-- Direccion -->
            <div class="flex items-start gap-4">
              <div class="flex-shrink-0 w-11 h-11 bg-[#1E3A8A] rounded-xl flex items-center justify-center shadow-md">
                <svg class="w-5 h-5 text-[#FACC15]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
                </svg>
              </div>
              <div>
                <p class="text-xs text-gray-400 font-semibold uppercase tracking-wide mb-0.5"><?= htmlspecialchars(cfg_value($cfg_camp, 'index_contact_address_label', 'Direccion')) ?></p>
                <p class="text-gray-800 font-bold text-base leading-snug"><?= htmlspecialchars(cfg_value($cfg_camp, 'index_contact_address_line1', 'Jr. Micaela Bastidas 636')) ?></p>
                <p class="text-gray-500 text-sm"><?= htmlspecialchars(cfg_value($cfg_camp, 'index_contact_address_line2', 'Satipo, Junin - Peru')) ?></p>
              </div>
            </div>

            <!-- Horario -->
            <div class="flex items-start gap-4">
              <div class="flex-shrink-0 w-11 h-11 bg-[#1E3A8A] rounded-xl flex items-center justify-center shadow-md">
                <svg class="w-5 h-5 text-[#FACC15]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
                </svg>
              </div>
              <div>
                <p class="text-xs text-gray-400 font-semibold uppercase tracking-wide mb-0.5"><?= htmlspecialchars(cfg_value($cfg_camp, 'index_contact_hours_label', 'Horario de atencion')) ?></p>
                <p class="text-gray-800 font-bold text-base"><?= htmlspecialchars(cfg_value($cfg_camp, 'index_contact_hours_line1', 'Lunes a Sabado')) ?></p>
                <p class="text-gray-500 text-sm"><?= htmlspecialchars(cfg_value($cfg_camp, 'index_contact_hours_line2', '8:00 am - 7:00 pm')) ?></p>
              </div>
            </div>

            <!-- Telefono -->
            <div class="flex items-start gap-4">
              <div class="flex-shrink-0 w-11 h-11 bg-[#1E3A8A] rounded-xl flex items-center justify-center shadow-md">
                <svg class="w-5 h-5 text-[#FACC15]" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                </svg>
              </div>
              <div>
                <p class="text-xs text-gray-400 font-semibold uppercase tracking-wide mb-0.5"><?= htmlspecialchars(cfg_value($cfg_camp, 'index_contact_phone_label', 'Telefono / WhatsApp')) ?></p>
                <a href="<?= htmlspecialchars(cfg_value($cfg_camp, 'index_contact_phone_href', 'tel:932512948')) ?>" class="text-gray-800 font-bold text-base hover:text-[#0056D6] transition-colors"><?= htmlspecialchars(cfg_value($cfg_camp, 'index_contact_phone_text', '932 512 948')) ?></a>
              </div>
            </div>
          </div>

          <!-- Boton Como llegar -->
          <a href="<?= htmlspecialchars(cfg_value($cfg_camp, 'index_contact_button_url', 'https://www.google.com/maps/search/Jr.+Micaela+Bastidas+636,+Satipo,+Peru')) ?>"
             target="_blank" rel="noopener noreferrer"
             class="inline-flex items-center gap-2.5 mt-9 bg-[#1E3A8A] hover:bg-[#0056D6] text-white font-bold px-7 py-3.5 rounded-full text-sm transition-all duration-300 shadow-lg hover:shadow-blue-700/30 hover:-translate-y-0.5 group">
            <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/>
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/>
            </svg>
            <?= htmlspecialchars(cfg_value($cfg_camp, 'index_contact_button_text', 'Como llegar')) ?>
            <svg class="w-3.5 h-3.5 group-hover:translate-x-0.5 transition-transform" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
              <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
            </svg>
          </a>
        </div>
      </div>

      <!-- Columna derecha - Mapa -->
      <div class="relative min-h-[380px] lg:min-h-full lg:col-span-2" data-aos="fade-left">
        <iframe
          src="<?= htmlspecialchars(map_src_from_config(cfg_value($cfg_camp, 'index_contact_map_iframe', 'https://www.google.com/maps/embed?pb=!1m14!1m12!1m3!1d978.2554392895146!2d-74.64025500233248!3d-11.259809609805213!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!5e0!3m2!1ses!2spe!4v1779183850860!5m2!1ses!2spe'))) ?>"
          class="absolute inset-0 w-full h-full border-0"
          allowfullscreen=""
          loading="lazy"
          referrerpolicy="no-referrer-when-downgrade"
          title="<?= htmlspecialchars(cfg_value($cfg_camp, 'index_contact_map_title', 'Local de Campana - Jr. Micaela Bastidas 636, Satipo')) ?>">
        </iframe>
      </div>
    </div>

  </section>

  <?php include __DIR__ . '/includes/footer.php'; ?>

  <!-- SCRIPTS -->
  <script src="https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js"></script>
  <script src="https://unpkg.com/aos@2.3.4/dist/aos.js"></script>
  <script src="<?= BASE_URL ?>/assets/js/main.js"></script>
  <script>
    AOS.init({ once: true, offset: 60, duration: 800, easing: 'ease-out-cubic' });

    new Swiper('.candidatos-swiper', {
      loop: true,
      autoplay: { delay: 2800, disableOnInteraction: false },
      slidesPerView: 1, spaceBetween: 16,
      pagination: { el: '.swiper-pagination', clickable: true },
      breakpoints: { 640:{slidesPerView:2}, 768:{slidesPerView:3}, 1024:{slidesPerView:4} }
    });

    new Swiper('.agenda-swiper', {
      loop: false,
      autoplay: false,
      slidesPerView: 1,
      spaceBetween: 16,
      pagination: { el: '.agenda-pagination', clickable: true },
      navigation: { nextEl: '.agenda-next', prevEl: '.agenda-prev' },
      breakpoints: {
        560:  { slidesPerView: 2, spaceBetween: 16 },
        1024: { slidesPerView: 3, spaceBetween: 20 }
      }
    });

    new Swiper('.noticias-swiper', {
      loop: true,
      autoplay: { delay: 4500, disableOnInteraction: false, pauseOnMouseEnter: true },
      slidesPerView: 1,
      spaceBetween: 20,
      pagination: { el: '.noticias-pagination', clickable: true },
      navigation: { nextEl: '.noticias-next', prevEl: '.noticias-prev' },
      breakpoints: {
        560:  { slidesPerView: 2, spaceBetween: 16 },
        1024: { slidesPerView: 3, spaceBetween: 20 },
        1280: { slidesPerView: 4, spaceBetween: 24 }
      }
    });

    (function () {
      const stage = document.getElementById('candidate-scroll-stage');
      const mainImg = document.getElementById('candidate-main-img');
      const profilePhoto = document.getElementById('quien-profile-photo');
      const hero = document.getElementById('inicio');
      const quien = document.getElementById('quien-es');
      if (!stage || !mainImg || !profilePhoto || !hero || !quien) return;

      const heroSrc = stage.dataset.heroSrc;
      const profileSrc = stage.dataset.profileSrc;
      const fallbackSrc = stage.dataset.fallbackSrc;
      let resolvedProfileSrc = fallbackSrc;

      const profileProbe = new Image();
      profileProbe.onload = () => {
        resolvedProfileSrc = profileSrc;
        profilePhoto.src = profileSrc;
      };
      profileProbe.onerror = () => {
        resolvedProfileSrc = fallbackSrc;
        profilePhoto.src = fallbackSrc;
      };
      profileProbe.src = profileSrc;

      let ticking = false;
      const updateCandidateScroll = () => {
        const y = window.scrollY || window.pageYOffset;
        const heroTop = hero.offsetTop;
        const heroBottom = heroTop + hero.offsetHeight;
        const quienTop = quien.offsetTop;
        const switchPoint = quienTop - window.innerHeight * 0.48;
        const storyPoint = heroBottom - window.innerHeight * 0.32;
        const inRange = y >= heroTop - 80 && y < switchPoint;
        const inProfile = y >= switchPoint && y < quienTop + quien.offsetHeight * 0.55;

        stage.classList.toggle('is-active', inRange || inProfile);
        stage.classList.toggle('is-story', inRange && y >= storyPoint);
        stage.classList.toggle('is-profile', inProfile);
        profilePhoto.classList.toggle('is-revealed', inProfile);

        const nextSrc = inProfile ? resolvedProfileSrc : heroSrc;
        if (mainImg.getAttribute('src') !== nextSrc) {
          mainImg.style.opacity = '0';
          window.setTimeout(() => {
            mainImg.src = nextSrc;
            mainImg.style.opacity = '1';
          }, 140);
        }
      };

      const requestUpdate = () => {
        if (ticking) return;
        ticking = true;
        window.requestAnimationFrame(() => {
          updateCandidateScroll();
          ticking = false;
        });
      };

      updateCandidateScroll();
      window.addEventListener('scroll', requestUpdate, { passive: true });
      window.addEventListener('resize', requestUpdate);
    })();

    document.getElementById('form-unete').addEventListener('submit', function(e) {
      e.preventDefault();
      const msg  = document.getElementById('unete-msg');
      const data = new FormData(this);
      if (!/^\d{8}$/.test(data.get('dni'))) {
        msg.className = 'text-sm font-semibold rounded-xl px-4 py-3 bg-red-100 text-red-700';
        msg.textContent = 'El DNI debe tener exactamente 8 digitos.';
        msg.classList.remove('hidden'); return;
      }
      msg.classList.add('hidden');
      window.dispatchEvent(new CustomEvent('abrir-paso2', {
        detail: {
          datos: { nombre: data.get('nombre'), dni: data.get('dni'), telefono: data.get('telefono'), distrito: data.get('distrito') },
          url: this.action
        }
      }));
    });

    function modalPaso2() {
      return {
        abierto: false, tipoDoc: 'DNI', otroCheck: false, enviando: false, datosPaso1: null, urlRegistro: '',
        abrir(detail) {
          this.datosPaso1 = detail.datos;
          this.urlRegistro = detail.url;
          this.abierto = true;
          document.body.style.overflow = 'hidden';
        },
        cerrar() {
          this.abierto = false;
          document.body.style.overflow = '';
        },
        async enviar() {
          this.enviando = true;
          const msg   = document.getElementById('paso2-msg');
          const form2 = document.getElementById('form-paso2');
          msg.classList.add('hidden');
          const data = new FormData();
          for (const [k, v] of Object.entries(this.datosPaso1)) data.append(k, v);
          data.append('tipo_documento', this.tipoDoc);
          const correo   = form2.querySelector('[name="correo"]').value;
          const celular  = form2.querySelector('[name="celular"]').value;
          const whatsapp = form2.querySelector('[name="whatsapp"]').value;
          if (correo)   data.append('correo',   correo);
          if (celular)  data.append('celular',  celular);
          if (whatsapp) data.append('whatsapp', whatsapp);
          const checks = form2.querySelectorAll('[name="formas_apoyo[]"]:checked');
          let formas = Array.from(checks).map(c => c.value);
          if (this.otroCheck) {
            const otro = form2.querySelector('[name="otro_apoyo"]')?.value.trim();
            if (otro) formas.push('Otro: ' + otro);
          }
          if (formas.length) data.append('formas_apoyo', formas.join(', '));
          try {
            const r   = await fetch(this.urlRegistro, { method: 'POST', body: data });
            const res = await r.json();
            msg.classList.remove('hidden');
            if (res.ok) {
              msg.className   = 'text-sm font-semibold rounded-xl px-4 py-3 bg-green-100 text-green-700';
              msg.textContent = 'Gracias! Te registramos correctamente. Bienvenido al equipo!';
              setTimeout(() => { this.cerrar(); document.getElementById('form-unete').reset(); }, 2500);
            } else {
              msg.className   = 'text-sm font-semibold rounded-xl px-4 py-3 bg-red-100 text-red-700';
              msg.textContent = res.error || 'Ocurrio un error. Intentalo nuevamente.';
            }
          } catch {
            msg.classList.remove('hidden');
            msg.className   = 'text-sm font-semibold rounded-xl px-4 py-3 bg-red-100 text-red-700';
            msg.textContent = 'No se pudo conectar. Intentalo mas tarde.';
          }
          this.enviando = false;
        }
      };
    }
  </script>

  <!-- -- MODAL PASO 2 - INFORMACION SIMPATIZANTE ---------- -->
  <div x-data="modalPaso2()" @abrir-paso2.window="abrir($event.detail)" x-cloak>
    <div x-show="abierto"
         x-transition:enter="transition ease-out duration-300"
         x-transition:enter-start="opacity-0"
         x-transition:enter-end="opacity-100"
         x-transition:leave="transition ease-in duration-200"
         x-transition:leave-start="opacity-100"
         x-transition:leave-end="opacity-0"
         class="fixed inset-0 z-[100] flex items-start justify-center p-4 sm:p-6 overflow-y-auto">
      <div class="absolute inset-0 bg-black/70 backdrop-blur-sm" @click="cerrar()"></div>

      <div x-transition:enter="transition ease-out duration-300"
           x-transition:enter-start="opacity-0 scale-95 translate-y-4"
           x-transition:enter-end="opacity-100 scale-100 translate-y-0"
           x-transition:leave="transition ease-in duration-200"
           x-transition:leave-start="opacity-100 scale-100 translate-y-0"
           x-transition:leave-end="opacity-0 scale-95 translate-y-4"
           class="relative w-full max-w-lg bg-white rounded-2xl shadow-2xl z-10 my-8 overflow-hidden">

        <!-- Header -->
        <div class="bg-gradient-to-r from-[#1E3A8A] via-[#1a4aad] to-[#0056D6] px-6 py-5 text-white">
          <div class="flex items-start justify-between gap-4">
            <div>
              <span class="inline-block text-[10px] bg-[#FACC15] text-[#1E3A8A] font-extrabold px-2.5 py-0.5 rounded-full uppercase tracking-wider mb-2">Paso 2 de 2</span>
              <h3 class="font-black text-xl leading-tight">Completa tu informacion<br>de simpatizante</h3>
            </div>
            <button @click="cerrar()"
                    class="flex-shrink-0 w-9 h-9 flex items-center justify-center text-white/60 hover:text-white hover:bg-white/15 rounded-xl transition-all duration-200 mt-1">
              <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
              </svg>
            </button>
          </div>
          <!-- Barra de progreso paso 2 completo -->
          <div class="mt-4 bg-white/20 rounded-full h-1.5 overflow-hidden">
            <div class="bg-[#FACC15] h-1.5 rounded-full w-full"></div>
          </div>
        </div>

        <!-- Cuerpo del formulario -->
        <form id="form-paso2" class="px-6 py-6 space-y-5 max-h-[70vh] overflow-y-auto">

          <!-- Tipo de documento -->
          <div>
            <label class="block text-[#1E3A8A] text-sm font-bold mb-2">Tipo de documento</label>
            <div class="flex gap-3">
              <label class="flex-1 flex items-center gap-2.5 border-2 rounded-xl px-4 py-3 cursor-pointer transition-all duration-200"
                     :class="tipoDoc === 'DNI' ? 'border-[#0056D6] bg-blue-50' : 'border-gray-200 hover:border-blue-300'">
                <input type="radio" name="tipo_documento" value="DNI" x-model="tipoDoc" class="accent-[#0056D6]">
                <span class="text-sm font-semibold text-gray-700">DNI</span>
              </label>
              <label class="flex-1 flex items-center gap-2.5 border-2 rounded-xl px-4 py-3 cursor-pointer transition-all duration-200"
                     :class="tipoDoc === 'Carnet de Extranjeria' ? 'border-[#0056D6] bg-blue-50' : 'border-gray-200 hover:border-blue-300'">
                <input type="radio" name="tipo_documento" value="Carnet de Extranjeria" x-model="tipoDoc" class="accent-[#0056D6]">
                <span class="text-sm font-semibold text-gray-700">Carnet de Extranjeria</span>
              </label>
            </div>
          </div>

          <!-- Correo -->
          <div>
            <label class="block text-[#1E3A8A] text-sm font-bold mb-1.5">
              Correo electronico
              <span class="text-gray-400 font-normal text-xs ml-1">- opcional</span>
            </label>
            <input type="email" name="correo" placeholder="tucorreo@gmail.com"
                   class="w-full border-2 border-gray-200 hover:border-gray-300 focus:border-[#0056D6] rounded-xl px-4 py-3 text-sm text-gray-800 placeholder-gray-400 outline-none transition-all">
          </div>

          <!-- Celular y WhatsApp -->
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-[#1E3A8A] text-sm font-bold mb-1.5">
                Nro. Celular
                <span class="text-gray-400 font-normal text-xs ml-1">- opcional</span>
              </label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400">
                  <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z"/>
                  </svg>
                </span>
                <input type="tel" name="celular" placeholder="999 111 222"
                       class="w-full border-2 border-gray-200 hover:border-gray-300 focus:border-[#0056D6] rounded-xl pl-9 pr-4 py-3 text-sm text-gray-800 placeholder-gray-400 outline-none transition-all">
              </div>
            </div>
            <div>
              <label class="block text-[#1E3A8A] text-sm font-bold mb-1.5">
                Nro. WhatsApp
                <span class="text-gray-400 font-normal text-xs ml-1">- opcional</span>
              </label>
              <div class="relative">
                <span class="absolute left-3 top-1/2 -translate-y-1/2 text-[#25D366]">
                  <svg class="w-4 h-4" fill="currentColor" viewBox="0 0 24 24">
                    <path d="M17.472 14.382c-.297-.149-1.758-.867-2.03-.967-.273-.099-.471-.148-.67.15-.197.297-.767.966-.94 1.164-.173.199-.347.223-.644.075-.297-.15-1.255-.463-2.39-1.475-.883-.788-1.48-1.761-1.653-2.059-.173-.297-.018-.458.13-.606.134-.133.298-.347.446-.52.149-.174.198-.298.298-.497.099-.198.05-.371-.025-.52-.075-.149-.669-1.612-.916-2.207-.242-.579-.487-.5-.669-.51-.173-.008-.371-.01-.57-.01-.198 0-.52.074-.792.372-.272.297-1.04 1.016-1.04 2.479 0 1.462 1.065 2.875 1.213 3.074.149.198 2.096 3.2 5.077 4.487.709.306 1.262.489 1.694.625.712.227 1.36.195 1.871.118.571-.085 1.758-.719 2.006-1.413.248-.694.248-1.289.173-1.413-.074-.124-.272-.198-.57-.347z"/>
                    <path d="M12 0C5.373 0 0 5.373 0 12c0 2.123.554 4.118 1.528 5.848L0 24l6.335-1.652A11.954 11.954 0 0012 24c6.627 0 12-5.373 12-12S18.627 0 12 0zm0 21.818a9.818 9.818 0 01-5.017-1.38l-.36-.214-3.732.977.995-3.636-.235-.374A9.818 9.818 0 1112 21.818z"/>
                  </svg>
                </span>
                <input type="tel" name="whatsapp" placeholder="999 333 444"
                       class="w-full border-2 border-gray-200 hover:border-[#25D366] focus:border-[#25D366] rounded-xl pl-9 pr-4 py-3 text-sm text-gray-800 placeholder-gray-400 outline-none transition-all">
              </div>
            </div>
          </div>

          <!-- Formas de apoyo -->
          <div>
            <label class="block text-[#1E3A8A] text-sm font-bold mb-1">Como te gustaria apoyar?</label>
            <p class="text-gray-400 text-xs mb-3">Puedes elegir mas de una opcion</p>
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2">
              <?php
              $opciones_apoyo = ['Volanteo','Redes sociales','Movilizaciones','Pintado','Coordinacion en mi Zona'];
              foreach ($opciones_apoyo as $op): ?>
              <label class="flex items-center gap-3 border-2 border-gray-100 rounded-xl px-4 py-3 cursor-pointer hover:border-[#0056D6] hover:bg-blue-50 transition-all duration-200 group">
                <input type="checkbox" name="formas_apoyo[]" value="<?= htmlspecialchars($op) ?>"
                       class="w-4 h-4 accent-[#0056D6] flex-shrink-0 rounded">
                <span class="text-sm font-medium text-gray-700 group-hover:text-[#1E3A8A]"><?= htmlspecialchars($op) ?></span>
              </label>
              <?php endforeach; ?>
              <label class="flex items-center gap-3 border-2 rounded-xl px-4 py-3 cursor-pointer transition-all duration-200 group"
                     :class="otroCheck ? 'border-[#0056D6] bg-blue-50' : 'border-gray-100 hover:border-[#0056D6] hover:bg-blue-50'">
                <input type="checkbox" x-model="otroCheck" class="w-4 h-4 accent-[#0056D6] flex-shrink-0 rounded">
                <span class="text-sm font-medium text-gray-700 group-hover:text-[#1E3A8A]">Otro</span>
              </label>
            </div>
            <div x-show="otroCheck" x-transition class="mt-3">
              <input type="text" name="otro_apoyo" placeholder="Especifica como te gustaria apoyar..."
                     class="w-full border-2 border-[#0056D6] rounded-xl px-4 py-3 text-sm text-gray-800 placeholder-gray-400 outline-none focus:ring-2 focus:ring-blue-100 transition-all">
            </div>
          </div>

          <!-- Mensaje de error/exito paso 2 -->
          <div id="paso2-msg" class="hidden text-sm font-semibold rounded-xl px-4 py-3"></div>
        </form>

        <!-- Footer con botones -->
        <div class="px-6 pb-6 pt-3 border-t border-gray-100 bg-gray-50/50 flex items-center gap-3">
          <button @click="cerrar()"
                  class="flex items-center gap-1 text-gray-400 hover:text-gray-600 text-sm font-semibold transition-colors px-3 py-3">
            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M15 19l-7-7 7-7"/>
            </svg>
            Volver
          </button>
          <button @click="enviar()" :disabled="enviando"
                  class="flex-1 flex items-center justify-center gap-2 bg-gradient-to-r from-[#0039A6] to-[#0056D6] hover:from-[#0056D6] hover:to-[#0EA5E9] text-white font-extrabold py-3.5 rounded-full text-sm transition-all duration-300 shadow-lg hover:shadow-blue-500/30 hover:-translate-y-0.5 disabled:opacity-60 disabled:cursor-not-allowed disabled:translate-y-0 disabled:shadow-none">
            <span x-show="!enviando">Finalizar registro ?</span>
            <span x-show="enviando" class="flex items-center gap-2">
              <svg class="animate-spin w-4 h-4" fill="none" viewBox="0 0 24 24">
                <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
                <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"/>
              </svg>
              Registrando...
            </span>
          </button>
        </div>
      </div>
    </div>
  </div>
</body>
</html>

