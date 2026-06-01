<?php
require_once __DIR__ . '/includes/config/db.php';
require_once __DIR__ . '/includes/helpers/config.php';

$cfg = [];
try {
    $cfg = $pdo->query("SELECT clave, valor FROM configuracion")->fetchAll(PDO::FETCH_KEY_PAIR);
} catch (Exception $e) {}

$title    = cfg_value($cfg, 'maint_title',   'Sitio en Mantenimiento');
$msg      = cfg_value($cfg, 'maint_message', 'Estamos trabajando para mejorar tu experiencia. Volvemos pronto.');
$eta      = cfg_value($cfg, 'maint_eta',     '');
// Logo: usa maint_logo si está configurado, si no cae al logo principal
$maint_logo_raw = cfg_value($cfg, 'maint_logo', '');
$logo = $maint_logo_raw !== '' ? $maint_logo_raw : cfg_value($cfg, 'site_header_logo', '/assets/img/logos/logorp.webp');
$show_soc = cfg_value($cfg, 'maint_show_social', '1') === '1';
// Countdown de lanzamiento
$countdown_active = cfg_value($cfg, 'maint_countdown_active', '0') === '1';
$launch_date      = cfg_value($cfg, 'maint_launch_date', '');
$launch_label     = cfg_value($cfg, 'maint_launch_label', 'Lanzamiento oficial');
$fb       = cfg_value($cfg, 'index_social_facebook_url', '');
$ig       = cfg_value($cfg, 'site_footer_instagram_url', '');
$yt       = cfg_value($cfg, 'site_footer_youtube_url', '');
$tk       = cfg_value($cfg, 'site_footer_tiktok_url', '');
$partido  = cfg_value($cfg, 'partido_nombre', 'ALIANZA PARA EL PROGRESO');
$firma    = cfg_value($cfg, 'site_header_signature', 'Ivan Cisneros');

function maint_url(string $p): string {
    if (preg_match('#^https?://#', $p)) return $p;
    return BASE_URL . '/' . ltrim($p, '/');
}

http_response_code(503);
header('Retry-After: 3600');
$fv = cfg_value($cfg, 'site_favicon', $logo);
$fv_url = maint_url($fv);
?>
<!DOCTYPE html>
<html lang="es">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title><?= htmlspecialchars($title) ?> — <?= htmlspecialchars($firma) ?></title>
  <link rel="icon" href="<?= htmlspecialchars($fv_url) ?>">
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@300;400;500;600;700;800;900&display=swap" rel="stylesheet">
  <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html, body { height: 100%; overflow: hidden; font-family: 'Inter', sans-serif; }

    /* ── BG ANIMADO ──────────────────────────────────────────── */
    body {
      background: #020c1f;
      display: flex; align-items: center; justify-content: center;
    }

    .bg-layer {
      position: fixed; inset: 0; pointer-events: none; overflow: hidden;
    }

    /* Degradado animado */
    .bg-gradient {
      position: absolute; inset: 0;
      background: radial-gradient(ellipse 80% 60% at 50% -10%, #1a3a8a44, transparent),
                  radial-gradient(ellipse 60% 50% at 100% 100%, #0f205744, transparent),
                  radial-gradient(ellipse 50% 40% at 0% 60%, #1e3a8a22, transparent),
                  #020c1f;
      animation: bgPulse 8s ease-in-out infinite alternate;
    }
    @keyframes bgPulse {
      0%   { background-position: 0% 0%; }
      100% { background-position: 100% 100%; }
    }

    /* Grid de puntos */
    .bg-dots {
      position: absolute; inset: 0;
      background-image: radial-gradient(rgba(56,189,248,.15) 1px, transparent 1px);
      background-size: 40px 40px;
      animation: dotsParallax 20s linear infinite;
    }
    @keyframes dotsParallax {
      0%   { transform: translate(0, 0); }
      100% { transform: translate(-40px, -40px); }
    }

    /* Orbes de glow */
    .orb {
      position: absolute; border-radius: 50%;
      filter: blur(80px);
      animation: orbFloat ease-in-out infinite alternate;
      pointer-events: none;
    }
    .orb-1 {
      width: 500px; height: 500px;
      background: radial-gradient(circle, #1e3a8a55, transparent);
      top: -150px; left: -100px;
      animation-duration: 7s;
    }
    .orb-2 {
      width: 400px; height: 400px;
      background: radial-gradient(circle, #0ea5e933, transparent);
      bottom: -100px; right: -80px;
      animation-duration: 9s; animation-delay: -3s;
    }
    .orb-3 {
      width: 300px; height: 300px;
      background: radial-gradient(circle, #facc1522, transparent);
      top: 40%; left: 60%;
      animation-duration: 11s; animation-delay: -5s;
    }
    @keyframes orbFloat {
      0%   { transform: translate(0, 0) scale(1); }
      100% { transform: translate(30px, 20px) scale(1.1); }
    }

    /* Partículas flotantes */
    .particle {
      position: absolute; border-radius: 50%;
      animation: particleRise linear infinite;
      pointer-events: none;
    }
    @keyframes particleRise {
      0%   { transform: translateY(110vh) scale(0); opacity: 0; }
      5%   { opacity: 1; }
      95%  { opacity: .5; }
      100% { transform: translateY(-10vh) scale(1.3); opacity: 0; }
    }

    /* Líneas scan */
    .scan-line {
      position: absolute; left: 0; right: 0; height: 1px;
      background: linear-gradient(90deg, transparent, rgba(56,189,248,.3), transparent);
      animation: scanMove 6s linear infinite;
    }
    @keyframes scanMove {
      0%   { top: -2px; opacity: 0; }
      10%  { opacity: 1; }
      90%  { opacity: .5; }
      100% { top: 100%; opacity: 0; }
    }
    .scan-line:nth-child(2) { animation-delay: -2s; }
    .scan-line:nth-child(3) { animation-delay: -4s; }

    /* ── CARD PRINCIPAL ──────────────────────────────────────── */
    .main-card {
      position: relative; z-index: 10;
      max-width: 560px; width: 90%;
      background: rgba(255,255,255,.04);
      backdrop-filter: blur(20px) saturate(1.5);
      -webkit-backdrop-filter: blur(20px) saturate(1.5);
      border: 1px solid rgba(255,255,255,.08);
      border-radius: 28px;
      padding: 52px 44px;
      box-shadow:
        0 0 0 1px rgba(56,189,248,.08),
        0 32px 80px rgba(0,0,0,.6),
        inset 0 1px 0 rgba(255,255,255,.08);
      animation: cardIn .8s cubic-bezier(.34,1.56,.64,1) both;
      text-align: center;
    }
    @keyframes cardIn {
      from { opacity: 0; transform: translateY(40px) scale(.95); }
      to   { opacity: 1; transform: translateY(0) scale(1); }
    }

    /* Borde glow animado */
    .card-glow {
      position: absolute; inset: -1px; border-radius: 29px;
      background: linear-gradient(135deg, rgba(56,189,248,.3), transparent, rgba(250,204,21,.2), transparent);
      -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
      -webkit-mask-composite: xor; mask-composite: exclude;
      padding: 1px;
      animation: glowRotate 4s linear infinite;
      pointer-events: none;
    }
    @keyframes glowRotate {
      0%   { filter: hue-rotate(0deg); }
      100% { filter: hue-rotate(360deg); }
    }

    /* ── COUNTDOWN ──────────────────────────────────────────── */
    .countdown-wrap {
      margin: 0 auto 28px;
      text-align: center;
    }
    .countdown-label {
      color: rgba(255,255,255,.5);
      font-size: 10px; font-weight: 800;
      text-transform: uppercase; letter-spacing: .12em;
      margin-bottom: 10px;
    }
    .countdown-digits {
      display: inline-flex; align-items: center; gap: 8px;
    }
    .countdown-unit {
      display: flex; flex-direction: column; align-items: center;
    }
    .countdown-box {
      background: rgba(11,30,74,.9);
      border: 1px solid rgba(255,255,255,.12);
      border-radius: 12px;
      min-width: 56px; padding: 8px 10px;
      text-align: center;
      box-shadow: 0 4px 20px rgba(0,0,0,.4), inset 0 1px 0 rgba(255,255,255,.06);
      font-family: 'DS-Digital', 'Courier New', monospace;
    }
    .countdown-num {
      display: block;
      font-size: 28px; font-weight: 900;
      color: #fff; line-height: 1;
      letter-spacing: .05em;
      text-shadow: 0 0 12px rgba(56,189,248,.5);
    }
    .countdown-unit-label {
      color: rgba(255,255,255,.35);
      font-size: 9px; text-transform: uppercase;
      letter-spacing: .1em; margin-top: 4px;
      font-weight: 700;
    }
    .countdown-sep {
      color: rgba(255,255,255,.25);
      font-size: 24px; font-weight: 900;
      margin-bottom: 14px; line-height: 1;
    }
    .countdown-done {
      color: #38bdf8; font-weight: 800;
      font-size: .95rem; letter-spacing: .05em;
    }

    /* ── ENGRANAJE ───────────────────────────────────────────── */
    .gear-wrap {
      position: relative; width: 88px; height: 88px;
      margin: 0 auto 28px;
    }
    .gear-ring {
      position: absolute; inset: 0; border-radius: 50%;
      background: rgba(30,58,138,.3);
      border: 1px solid rgba(56,189,248,.2);
      animation: gearRingPulse 3s ease-in-out infinite;
    }
    @keyframes gearRingPulse {
      0%,100% { transform: scale(1);   box-shadow: 0 0 0 0 rgba(56,189,248,.3); }
      50%      { transform: scale(1.08); box-shadow: 0 0 0 12px rgba(56,189,248,.0); }
    }
    .gear-icon {
      position: absolute; inset: 12px;
      animation: gearSpin 4s linear infinite;
      color: rgba(56,189,248,.9);
      filter: drop-shadow(0 0 8px rgba(56,189,248,.6));
    }
    .gear-inner {
      position: absolute; inset: 24px;
      animation: gearSpin 4s linear infinite reverse;
      color: rgba(250,204,21,.7);
      filter: drop-shadow(0 0 6px rgba(250,204,21,.5));
    }
    @keyframes gearSpin {
      from { transform: rotate(0deg); }
      to   { transform: rotate(360deg); }
    }

    /* ── LOGO ────────────────────────────────────────────────── */
    .logo-img {
      height: 44px; margin: 0 auto 20px; display: block;
      filter: drop-shadow(0 0 12px rgba(56,189,248,.3));
      animation: logoGlow 3s ease-in-out infinite alternate;
    }
    @keyframes logoGlow {
      0%   { filter: drop-shadow(0 0 8px  rgba(56,189,248,.2)); }
      100% { filter: drop-shadow(0 0 20px rgba(56,189,248,.5)); }
    }

    /* ── TEXTOS ──────────────────────────────────────────────── */
    .badge {
      display: inline-flex; align-items: center; gap: 6px;
      background: rgba(56,189,248,.1);
      border: 1px solid rgba(56,189,248,.2);
      border-radius: 999px;
      padding: 4px 14px;
      font-size: 10px; font-weight: 800; letter-spacing: .12em;
      text-transform: uppercase; color: #38bdf8;
      margin-bottom: 20px;
    }
    .badge-dot {
      width: 6px; height: 6px; border-radius: 50%;
      background: #38bdf8;
      animation: badgeBlink 1.2s ease-in-out infinite;
      box-shadow: 0 0 6px #38bdf8;
    }
    @keyframes badgeBlink {
      0%,100% { opacity: 1; transform: scale(1); }
      50%      { opacity: .3; transform: scale(.7); }
    }

    h1 {
      font-size: clamp(1.6rem, 5vw, 2.4rem);
      font-weight: 900; line-height: 1.1;
      color: #fff;
      text-shadow: 0 0 40px rgba(56,189,248,.4);
      margin-bottom: 16px;
      animation: textGlow 4s ease-in-out infinite alternate;
    }
    @keyframes textGlow {
      0%   { text-shadow: 0 0 20px rgba(56,189,248,.3); }
      100% { text-shadow: 0 0 50px rgba(56,189,248,.6), 0 0 80px rgba(30,58,138,.4); }
    }
    h1 span { color: #38bdf8; }

    .sub {
      color: rgba(255,255,255,.55);
      font-size: .95rem; line-height: 1.6;
      margin-bottom: 32px;
    }

    /* ── ETA ─────────────────────────────────────────────────── */
    .eta-box {
      display: inline-flex; align-items: center; gap: 10px;
      background: rgba(250,204,21,.07);
      border: 1px solid rgba(250,204,21,.2);
      border-radius: 14px; padding: 12px 20px;
      margin-bottom: 32px;
      animation: etaGlow 3s ease-in-out infinite alternate;
    }
    @keyframes etaGlow {
      0%   { box-shadow: 0 0 0 rgba(250,204,21,0); }
      100% { box-shadow: 0 0 20px rgba(250,204,21,.15); }
    }
    .eta-icon { color: #facc15; flex-shrink: 0; }
    .eta-text { color: #facc15; font-size: .85rem; font-weight: 700; }

    /* ── BARRA PROGRESO ──────────────────────────────────────── */
    .progress-wrap {
      background: rgba(255,255,255,.06);
      border-radius: 999px; height: 3px;
      overflow: hidden; margin-bottom: 36px;
      position: relative;
    }
    .progress-bar {
      height: 100%; border-radius: 999px;
      background: linear-gradient(90deg, #1e3a8a, #38bdf8, #facc15);
      background-size: 200% 100%;
      animation: progressFlow 2.5s linear infinite;
      width: 60%;
    }
    @keyframes progressFlow {
      0%   { background-position: 0% 0%; transform: translateX(-100%); }
      100% { background-position: 100% 0%; transform: translateX(200%); }
    }

    /* ── DIVISOR ─────────────────────────────────────────────── */
    .divider {
      display: flex; align-items: center; gap: 12px;
      color: rgba(255,255,255,.15); font-size: 10px;
      text-transform: uppercase; letter-spacing: .1em;
      margin-bottom: 24px;
    }
    .divider::before, .divider::after {
      content: ''; flex: 1; height: 1px;
      background: linear-gradient(90deg, transparent, rgba(255,255,255,.1), transparent);
    }

    /* ── REDES SOCIALES ──────────────────────────────────────── */
    .social-row {
      display: flex; align-items: center; justify-content: center; gap: 12px;
      flex-wrap: wrap;
    }
    .social-btn {
      display: inline-flex; align-items: center; gap: 8px;
      padding: 9px 18px; border-radius: 12px;
      font-size: 12px; font-weight: 700;
      text-decoration: none;
      border: 1px solid rgba(255,255,255,.08);
      background: rgba(255,255,255,.04);
      color: rgba(255,255,255,.6);
      transition: all .2s;
      backdrop-filter: blur(4px);
    }
    .social-btn:hover {
      background: rgba(255,255,255,.1);
      color: #fff;
      border-color: rgba(255,255,255,.2);
      transform: translateY(-2px);
      box-shadow: 0 8px 20px rgba(0,0,0,.3);
    }
    .social-btn svg { width: 14px; height: 14px; flex-shrink: 0; }

    /* ── FIRMA INFERIOR ──────────────────────────────────────── */
    .footer-sig {
      margin-top: 36px; padding-top: 20px;
      border-top: 1px solid rgba(255,255,255,.06);
      font-size: 11px; color: rgba(255,255,255,.2);
      letter-spacing: .05em;
    }
    .footer-sig span { color: rgba(56,189,248,.4); }

    /* Mobile */
    @media (max-width: 500px) {
      .main-card { padding: 40px 24px; }
      html, body { overflow: auto; }
    }
  </style>
</head>
<body>

  <!-- Capas de fondo -->
  <div class="bg-layer">
    <div class="bg-gradient"></div>
    <div class="bg-dots"></div>
    <div class="orb orb-1"></div>
    <div class="orb orb-2"></div>
    <div class="orb orb-3"></div>
    <div class="scan-line"></div>
    <div class="scan-line"></div>
    <div class="scan-line"></div>
    <!-- Partículas -->
    <div class="particle" style="width:4px;height:4px;left:8%;background:rgba(56,189,248,.4);animation-duration:18s;animation-delay:0s"></div>
    <div class="particle" style="width:6px;height:6px;left:22%;background:rgba(250,204,21,.3);animation-duration:22s;animation-delay:-4s"></div>
    <div class="particle" style="width:3px;height:3px;left:38%;background:rgba(56,189,248,.5);animation-duration:15s;animation-delay:-8s"></div>
    <div class="particle" style="width:5px;height:5px;left:55%;background:rgba(250,204,21,.2);animation-duration:20s;animation-delay:-2s"></div>
    <div class="particle" style="width:4px;height:4px;left:70%;background:rgba(56,189,248,.3);animation-duration:17s;animation-delay:-6s"></div>
    <div class="particle" style="width:7px;height:7px;left:85%;background:rgba(250,204,21,.25);animation-duration:24s;animation-delay:-10s"></div>
    <div class="particle" style="width:3px;height:3px;left:15%;background:rgba(255,255,255,.2);animation-duration:19s;animation-delay:-13s"></div>
    <div class="particle" style="width:5px;height:5px;left:92%;background:rgba(56,189,248,.35);animation-duration:21s;animation-delay:-1s"></div>
  </div>

  <!-- Card principal -->
  <div class="main-card">
    <div class="card-glow"></div>

    <!-- Logo -->
    <img src="<?= htmlspecialchars(maint_url($logo)) ?>"
         alt="<?= htmlspecialchars($firma) ?>"
         class="logo-img"
         onerror="this.style.display='none'">

    <!-- Badge status -->
    <div class="badge">
      <span class="badge-dot"></span>
      En mantenimiento
    </div>

    <!-- Engranajes animados -->
    <div class="gear-wrap">
      <div class="gear-ring"></div>
      <svg class="gear-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round"
              d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
      </svg>
      <svg class="gear-inner" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round"
              d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/>
        <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
      </svg>
    </div>

    <?php if ($countdown_active && $launch_date): ?>
    <!-- Countdown regresivo -->
    <div class="countdown-wrap" id="countdownWrap">
      <p class="countdown-label"><?= htmlspecialchars($launch_label) ?></p>
      <div class="countdown-digits" id="countdownDigits">
        <div class="countdown-unit">
          <div class="countdown-box"><span class="countdown-num" id="cd-days">--</span></div>
          <span class="countdown-unit-label">Días</span>
        </div>
        <span class="countdown-sep">:</span>
        <div class="countdown-unit">
          <div class="countdown-box"><span class="countdown-num" id="cd-hours">--</span></div>
          <span class="countdown-unit-label">Hrs</span>
        </div>
        <span class="countdown-sep">:</span>
        <div class="countdown-unit">
          <div class="countdown-box"><span class="countdown-num" id="cd-mins">--</span></div>
          <span class="countdown-unit-label">Min</span>
        </div>
        <span class="countdown-sep">:</span>
        <div class="countdown-unit">
          <div class="countdown-box"><span class="countdown-num" id="cd-secs">--</span></div>
          <span class="countdown-unit-label">Seg</span>
        </div>
      </div>
    </div>
    <script>
    (function(){
      // Fecha objetivo en hora de Lima (UTC-5)
      var target = new Date('<?= htmlspecialchars($launch_date) ?>T00:00:00-05:00').getTime();
      function pad(n){ return String(n).padStart(2,'0'); }
      function tick(){
        var now  = Date.now();
        var diff = target - now;
        if (diff <= 0) {
          document.getElementById('countdownDigits').innerHTML =
            '<span class="countdown-done">¡Ha llegado el momento!</span>';
          return;
        }
        var d = Math.floor(diff / 86400000);
        var h = Math.floor((diff % 86400000) / 3600000);
        var m = Math.floor((diff % 3600000)  / 60000);
        var s = Math.floor((diff % 60000)    / 1000);
        document.getElementById('cd-days').textContent  = pad(d);
        document.getElementById('cd-hours').textContent = pad(h);
        document.getElementById('cd-mins').textContent  = pad(m);
        document.getElementById('cd-secs').textContent  = pad(s);
      }
      tick(); setInterval(tick, 1000);
    })();
    </script>
    <?php endif; ?>

    <!-- Título -->
    <h1><?= htmlspecialchars($title) ?></h1>

    <!-- Mensaje -->
    <p class="sub"><?= nl2br(htmlspecialchars($msg)) ?></p>

    <!-- ETA si está configurado -->
    <?php if ($eta): ?>
    <div class="eta-box">
      <svg class="eta-icon" width="16" height="16" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
      </svg>
      <span class="eta-text"><?= htmlspecialchars($eta) ?></span>
    </div>
    <?php endif; ?>

    <!-- Barra de progreso animada -->
    <div class="progress-wrap">
      <div class="progress-bar"></div>
    </div>

    <!-- Redes sociales -->
    <?php if ($show_soc && ($fb || $ig || $yt || $tk)): ?>
    <div class="divider">Síguenos</div>
    <div class="social-row">
      <?php if ($fb && $fb !== '#'): ?>
      <a href="<?= htmlspecialchars($fb) ?>" target="_blank" rel="noopener" class="social-btn">
        <svg fill="currentColor" viewBox="0 0 24 24"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
        Facebook
      </a>
      <?php endif; ?>
      <?php if ($ig && $ig !== '#'): ?>
      <a href="<?= htmlspecialchars($ig) ?>" target="_blank" rel="noopener" class="social-btn">
        <svg fill="currentColor" viewBox="0 0 24 24"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zM12 0C8.741 0 8.333.014 7.053.072 2.695.272.273 2.69.073 7.052.014 8.333 0 8.741 0 12c0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98C8.333 23.986 8.741 24 12 24c3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98C15.668.014 15.259 0 12 0zm0 5.838a6.162 6.162 0 100 12.324 6.162 6.162 0 000-12.324zM12 16a4 4 0 110-8 4 4 0 010 8zm6.406-11.845a1.44 1.44 0 100 2.881 1.44 1.44 0 000-2.881z"/></svg>
        Instagram
      </a>
      <?php endif; ?>
      <?php if ($yt && $yt !== '#'): ?>
      <a href="<?= htmlspecialchars($yt) ?>" target="_blank" rel="noopener" class="social-btn">
        <svg fill="currentColor" viewBox="0 0 24 24"><path d="M23.498 6.186a3.016 3.016 0 00-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 00.502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 002.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 002.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12l-6.273 3.568z"/></svg>
        YouTube
      </a>
      <?php endif; ?>
      <?php if ($tk && $tk !== '#'): ?>
      <a href="<?= htmlspecialchars($tk) ?>" target="_blank" rel="noopener" class="social-btn">
        <svg fill="currentColor" viewBox="0 0 24 24"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg>
        TikTok
      </a>
      <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- Firma -->
    <div class="footer-sig">
      <span><?= htmlspecialchars($partido) ?></span> &nbsp;·&nbsp; <?= htmlspecialchars($firma) ?>
    </div>
  </div>

</body>
</html>
