<?php
require_once __DIR__ . '/includes/config/db.php';
require_once __DIR__ . '/includes/helpers/config.php';

$cfg = [];
try { $cfg = $pdo->query("SELECT clave, valor FROM configuracion")->fetchAll(PDO::FETCH_KEY_PAIR); } catch(Exception $e){}

function maint_url(string $p): string {
    if ($p === '') return '';
    if (preg_match('#^https?://#', $p)) return $p;
    return BASE_URL . '/' . ltrim($p, '/');
}

$title          = cfg_value($cfg, 'maint_title',   'Sitio en Mantenimiento');
$msg            = cfg_value($cfg, 'maint_message', 'Estamos trabajando para mejorar tu experiencia. Volvemos pronto.');
$eta            = cfg_value($cfg, 'maint_eta', '');
$maint_logo_raw = cfg_value($cfg, 'maint_logo', '');
$logo           = $maint_logo_raw !== '' ? $maint_logo_raw : cfg_value($cfg, 'site_header_logo', '/assets/img/logos/logorp.webp');
$cand_photo_raw = cfg_value($cfg, 'maint_candidate_photo', '');
$cand_photo     = $cand_photo_raw !== '' ? $cand_photo_raw : cfg_value($cfg, 'login_hero_img', '/assets/img/candidato/ivancisneros.webp');
$show_soc       = cfg_value($cfg, 'maint_show_social', '1') === '1';
$fb  = cfg_value($cfg, 'index_social_facebook_url', '');
$ig  = cfg_value($cfg, 'site_footer_instagram_url', '');
$yt  = cfg_value($cfg, 'site_footer_youtube_url', '');
$tk  = cfg_value($cfg, 'site_footer_tiktok_url', '');
$partido          = cfg_value($cfg, 'partido_nombre', 'ALIANZA PARA EL PROGRESO');
$firma            = cfg_value($cfg, 'site_header_signature', 'Ivan Cisneros');
$countdown_active = cfg_value($cfg, 'maint_countdown_active', '0') === '1';
$launch_date      = cfg_value($cfg, 'maint_launch_date', '');
$launch_label     = cfg_value($cfg, 'maint_launch_label', 'Lanzamiento oficial');

http_response_code(503);
header('Retry-After: 3600');
$fv_url = maint_url(cfg_value($cfg, 'site_favicon', $logo) ?: $logo);
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
    @font-face {
      font-family: 'DS-Digital';
      src: url('<?= BASE_URL ?>/assets/fonts/ds-digib.ttf') format('truetype');
      font-weight: normal; font-style: normal; font-display: swap;
    }

    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
    html { height: 100%; }
    body {
      min-height: 100%; font-family: 'Inter', sans-serif;
      display: flex; align-items: center; justify-content: center;
      padding: 24px 16px;
      background: #020c1f;
      position: relative; overflow-x: hidden;
    }

    /* ── BG ANIMADO ──────────────────────────────────────────── */
    .bg-layer { position: fixed; inset: 0; pointer-events: none; z-index: 0; }
    .bg-grad {
      position: absolute; inset: 0;
      background:
        radial-gradient(ellipse 70% 55% at 20% 10%, rgba(30,58,138,.35), transparent),
        radial-gradient(ellipse 60% 50% at 85% 90%, rgba(15,32,87,.4), transparent),
        radial-gradient(ellipse 50% 40% at 60% 50%, rgba(14,165,233,.08), transparent),
        #020c1f;
      animation: bgShift 12s ease-in-out infinite alternate;
    }
    @keyframes bgShift { 0%{opacity:.7} 100%{opacity:1} }

    .bg-dots {
      position: absolute; inset: 0;
      background-image: radial-gradient(rgba(56,189,248,.1) 1px, transparent 1px);
      background-size: 36px 36px;
      animation: dotsMove 25s linear infinite;
    }
    @keyframes dotsMove { 0%{transform:translate(0,0)} 100%{transform:translate(-36px,-36px)} }

    .orb {
      position: absolute; border-radius: 50%;
      filter: blur(90px); pointer-events: none;
      animation: orbFloat ease-in-out infinite alternate;
    }
    @keyframes orbFloat {
      0%  { transform: translate(0,0) scale(1); }
      100%{ transform: translate(20px,15px) scale(1.08); }
    }

    .scan-line {
      position: absolute; left: 0; right: 0; height: 1px;
      background: linear-gradient(90deg, transparent, rgba(56,189,248,.2), transparent);
      animation: scanMove 8s linear infinite; pointer-events: none;
    }
    @keyframes scanMove { 0%{top:-1px;opacity:0} 8%{opacity:1} 92%{opacity:.4} 100%{top:100%;opacity:0} }

    .particle { position: absolute; border-radius: 50%; animation: rise linear infinite; pointer-events: none; }
    @keyframes rise { 0%{transform:translateY(110vh);opacity:0} 6%{opacity:.9} 94%{opacity:.4} 100%{transform:translateY(-5vh);opacity:0} }

    /* ── CARD BOXED ──────────────────────────────────────────── */
    .card {
      position: relative; z-index: 10;
      width: 100%; max-width: 920px;
      border-radius: 28px; overflow: hidden;
      display: flex; flex-direction: column;
      box-shadow:
        0 0 0 1px rgba(255,255,255,.06),
        0 40px 100px rgba(0,0,0,.6),
        0 0 80px rgba(30,58,138,.15);
      animation: cardIn .75s cubic-bezier(.34,1.56,.64,1) both;
    }
    @keyframes cardIn {
      from { opacity:0; transform:translateY(36px) scale(.96); }
      to   { opacity:1; transform:translateY(0) scale(1); }
    }

    /* Borde glow animado */
    .card::before {
      content: '';
      position: absolute; inset: 0; z-index: 0; border-radius: 28px;
      padding: 1px;
      background: linear-gradient(135deg, rgba(56,189,248,.35), transparent 40%, rgba(250,204,21,.2), transparent);
      -webkit-mask: linear-gradient(#fff 0 0) content-box, linear-gradient(#fff 0 0);
      -webkit-mask-composite: xor; mask-composite: exclude;
      pointer-events: none;
      animation: borderGlow 5s linear infinite;
    }
    @keyframes borderGlow { 0%{filter:hue-rotate(0deg)} 100%{filter:hue-rotate(360deg)} }

    @media (min-width: 700px) {
      .card { flex-direction: row; min-height: 560px; }
    }

    /* ── PANEL FOTO ──────────────────────────────────────────── */
    .photo-panel {
      position: relative; overflow: hidden;
      height: 220px; flex-shrink: 0;
      background: #0f2057;
    }
    @media (min-width: 700px) {
      .photo-panel { width: 42%; height: auto; }
    }

    .photo-img {
      position: absolute; inset: 0; width: 100%; height: 100%;
      object-fit: cover; object-position: center top;
      transition: transform 8s ease;
    }
    .card:hover .photo-img { transform: scale(1.04); }

    .photo-ov-r {
      position: absolute; inset: 0;
      background: linear-gradient(to right, transparent 30%, rgba(2,12,31,.5) 100%);
    }
    @media (min-width: 700px) {
      .photo-ov-r { background: linear-gradient(to right, transparent 45%, rgba(2,12,31,.75) 100%); }
    }
    .photo-ov-b {
      position: absolute; inset: 0;
      background: linear-gradient(to top, rgba(2,12,31,.95) 0%, transparent 50%);
    }
    @media (min-width: 700px) {
      .photo-ov-b { background: linear-gradient(to top, rgba(2,12,31,.85) 0%, transparent 40%); }
    }
    .photo-dots-inner {
      position: absolute; inset: 0; pointer-events: none;
      background-image: radial-gradient(rgba(250,204,21,.1) 1px, transparent 1px);
      background-size: 28px 28px;
    }

    /* Logo y texto en foto */
    .photo-logo-wrap {
      position: absolute; top: 20px; left: 20px;
      display: flex; align-items: center; gap: 10px;
    }
    .photo-logo {
      height: 38px; object-fit: contain;
      filter: drop-shadow(0 0 12px rgba(56,189,248,.4));
    }

    .photo-bottom {
      position: absolute; bottom: 0; left: 0; right: 0; padding: 20px;
    }
    .photo-badge-pill {
      display: inline-flex; align-items: center; gap: 6px;
      background: rgba(250,204,21,.12); border: 1px solid rgba(250,204,21,.25);
      border-radius: 999px; padding: 3px 12px;
      font-size: 9px; font-weight: 800; letter-spacing: .14em;
      text-transform: uppercase; color: #facc15; margin-bottom: 10px;
    }
    .bdot {
      width: 5px; height: 5px; border-radius: 50%;
      background: #facc15; box-shadow: 0 0 7px #facc15;
      animation: bdot 1.4s ease-in-out infinite;
    }
    @keyframes bdot { 0%,100%{opacity:1;transform:scale(1)} 50%{opacity:.3;transform:scale(.7)} }
    .photo-name {
      font-size: clamp(1.1rem, 3vw, 1.6rem); font-weight: 900;
      color: #fff; line-height: 1.1;
      text-shadow: 0 2px 16px rgba(0,0,0,.5);
    }
    .photo-sub { font-size: .75rem; color: rgba(56,189,248,.75); font-weight: 500; margin-top: 3px; }
    .photo-lines { display:flex; align-items:center; gap:6px; margin-top:12px; }
    .photo-lines span { display:block; height:2px; border-radius:999px; background:#facc15; }

    /* ── PANEL CONTENIDO ─────────────────────────────────────── */
    .content-panel {
      flex: 1; min-width: 0;
      background: rgba(8,20,60,.92);
      backdrop-filter: blur(16px);
      display: flex; flex-direction: column;
      align-items: center; justify-content: center;
      padding: 36px 32px; text-align: center;
      position: relative; overflow: hidden;
    }

    /* Inner glow del panel */
    .content-panel::before {
      content: '';
      position: absolute; top: -60px; right: -60px;
      width: 200px; height: 200px; border-radius: 50%;
      background: radial-gradient(circle, rgba(56,189,248,.07), transparent);
      pointer-events: none;
    }
    .content-panel::after {
      content: '';
      position: absolute; bottom: -50px; left: -50px;
      width: 160px; height: 160px; border-radius: 50%;
      background: radial-gradient(circle, rgba(250,204,21,.05), transparent);
      pointer-events: none;
    }

    /* Logo (solo móvil — en desktop va en la foto) */
    .logo-mobile {
      height: 40px; margin: 0 auto 16px; display: block;
      filter: drop-shadow(0 0 10px rgba(56,189,248,.3));
    }
    @media (min-width: 700px) { .logo-mobile { display: none; } }

    /* Badge */
    .badge {
      display: inline-flex; align-items: center; gap: 6px;
      background: rgba(56,189,248,.1); border: 1px solid rgba(56,189,248,.2);
      border-radius: 999px; padding: 4px 14px;
      font-size: 10px; font-weight: 800; letter-spacing: .12em;
      text-transform: uppercase; color: #38bdf8; margin-bottom: 20px;
    }
    .badge-dot {
      width: 6px; height: 6px; border-radius: 50%;
      background: #38bdf8; box-shadow: 0 0 7px #38bdf8;
      animation: bdot 1.2s ease-in-out infinite;
    }

    /* Engranaje */
    .gear-wrap { position:relative; width:68px; height:68px; margin:0 auto 18px; }
    .gear-ring {
      position:absolute; inset:0; border-radius:50%;
      background:rgba(30,58,138,.3); border:1px solid rgba(56,189,248,.2);
      animation:gRing 3s ease-in-out infinite;
    }
    @keyframes gRing { 0%,100%{transform:scale(1)} 50%{transform:scale(1.09)} }
    .gs1 { position:absolute; inset:9px; animation:spin 4s linear infinite; color:rgba(56,189,248,.9); filter:drop-shadow(0 0 7px rgba(56,189,248,.5)); }
    .gs2 { position:absolute; inset:19px; animation:spin 4s linear infinite reverse; color:rgba(250,204,21,.75); filter:drop-shadow(0 0 5px rgba(250,204,21,.4)); }
    @keyframes spin { from{transform:rotate(0deg)} to{transform:rotate(360deg)} }

    /* Countdown */
    .cd-label { color:rgba(255,255,255,.4); font-size:9px; font-weight:800; text-transform:uppercase; letter-spacing:.14em; margin-bottom:10px; }
    .cd-row { display:inline-flex; align-items:center; gap:6px; margin-bottom:20px; }
    .cd-unit { display:flex; flex-direction:column; align-items:center; }
    .cd-box {
      background:rgba(5,15,50,.95); border:1px solid rgba(56,189,248,.2);
      border-radius:10px; min-width:50px; padding:7px 8px;
      box-shadow:0 4px 20px rgba(0,0,0,.5), inset 0 1px 0 rgba(255,255,255,.05), 0 0 12px rgba(56,189,248,.08);
    }
    .cd-num {
      display:block; font-size:28px; font-weight:normal; color:#fff; line-height:1;
      text-align:center; letter-spacing:.06em;
      font-family:'DS-Digital','Courier New',monospace;
      text-shadow:0 0 14px rgba(56,189,248,.7), 0 0 30px rgba(56,189,248,.3);
    }
    .cd-ulabel { color:rgba(255,255,255,.28); font-size:8px; text-transform:uppercase; letter-spacing:.1em; margin-top:4px; font-weight:700; }
    .cd-sep { color:rgba(56,189,248,.35); font-size:20px; font-weight:900; margin-bottom:12px; }
    .cd-done { color:#38bdf8; font-weight:800; font-size:.9rem; }

    /* Título */
    h1 {
      font-size:clamp(1.4rem,3.5vw,1.9rem); font-weight:900; color:#fff;
      line-height:1.15; margin-bottom:12px;
      animation:tGlow 4s ease-in-out infinite alternate;
    }
    @keyframes tGlow { 0%{text-shadow:0 0 20px rgba(56,189,248,.2)} 100%{text-shadow:0 0 40px rgba(56,189,248,.55)} }

    .sub { color:rgba(255,255,255,.48); font-size:.875rem; line-height:1.65; margin-bottom:22px; }

    /* ETA */
    .eta-box {
      display:inline-flex; align-items:center; gap:8px;
      background:rgba(250,204,21,.07); border:1px solid rgba(250,204,21,.2);
      border-radius:12px; padding:9px 18px; margin-bottom:22px;
    }
    .eta-text { color:#facc15; font-size:.8rem; font-weight:700; }

    /* Progreso shimmer */
    .prog { background:rgba(255,255,255,.06); border-radius:999px; height:2px; overflow:hidden; margin-bottom:24px; position:relative; }
    .prog-bar {
      height:100%; border-radius:999px;
      background:linear-gradient(90deg,#1e3a8a,#38bdf8,#facc15);
      background-size:200% 100%;
      animation:pFlow 2.5s linear infinite; width:55%;
    }
    @keyframes pFlow { 0%{background-position:0%;transform:translateX(-100%)} 100%{background-position:100%;transform:translateX(200%)} }

    /* Divisor */
    .divider { display:flex; align-items:center; gap:10px; color:rgba(255,255,255,.1); font-size:9px; text-transform:uppercase; letter-spacing:.1em; margin-bottom:16px; }
    .divider::before,.divider::after { content:''; flex:1; height:1px; background:linear-gradient(90deg,transparent,rgba(255,255,255,.07),transparent); }

    /* Redes */
    .social-row { display:flex; flex-wrap:wrap; align-items:center; justify-content:center; gap:8px; }
    .social-btn {
      display:inline-flex; align-items:center; gap:7px;
      padding:7px 14px; border-radius:9px; font-size:11px; font-weight:700;
      text-decoration:none; border:1px solid rgba(255,255,255,.07);
      background:rgba(255,255,255,.04); color:rgba(255,255,255,.5);
      transition:all .2s; backdrop-filter:blur(4px);
    }
    .social-btn:hover { background:rgba(255,255,255,.1); color:#fff; border-color:rgba(255,255,255,.18); transform:translateY(-2px); box-shadow:0 8px 18px rgba(0,0,0,.3); }
    .social-btn svg { width:12px; height:12px; flex-shrink:0; }

    /* Firma */
    .footer-sig { margin-top:24px; padding-top:14px; border-top:1px solid rgba(255,255,255,.05); font-size:10px; color:rgba(255,255,255,.16); letter-spacing:.05em; }
    .footer-sig span { color:rgba(56,189,248,.3); }
  </style>
</head>
<body>

<!-- ── Fondo animado ── -->
<div class="bg-layer">
  <div class="bg-grad"></div>
  <div class="bg-dots"></div>
  <div class="orb" style="width:500px;height:500px;top:-150px;left:-120px;background:radial-gradient(circle,rgba(30,58,138,.3),transparent);animation-duration:9s"></div>
  <div class="orb" style="width:350px;height:350px;bottom:-80px;right:-80px;background:radial-gradient(circle,rgba(14,165,233,.2),transparent);animation-duration:11s;animation-delay:-4s"></div>
  <div class="orb" style="width:200px;height:200px;top:40%;left:40%;background:radial-gradient(circle,rgba(250,204,21,.06),transparent);animation-duration:7s;animation-delay:-2s"></div>
  <div class="scan-line" style="animation-delay:0s"></div>
  <div class="scan-line" style="animation-delay:-3s"></div>
  <div class="particle" style="width:4px;height:4px;left:8%;background:rgba(56,189,248,.4);animation-duration:18s;animation-delay:0s"></div>
  <div class="particle" style="width:5px;height:5px;left:25%;background:rgba(250,204,21,.3);animation-duration:22s;animation-delay:-4s"></div>
  <div class="particle" style="width:3px;height:3px;left:55%;background:rgba(56,189,248,.5);animation-duration:16s;animation-delay:-8s"></div>
  <div class="particle" style="width:6px;height:6px;left:75%;background:rgba(250,204,21,.2);animation-duration:20s;animation-delay:-2s"></div>
  <div class="particle" style="width:4px;height:4px;left:90%;background:rgba(56,189,248,.35);animation-duration:19s;animation-delay:-11s"></div>
</div>

<!-- ── Card boxed centrada ── -->
<div class="card">

  <!-- Panel foto -->
  <div class="photo-panel">
    <?php if ($cand_photo): ?>
    <img src="<?= htmlspecialchars(maint_url($cand_photo)) ?>"
         alt="<?= htmlspecialchars($firma) ?>" class="photo-img"
         onerror="this.style.display='none'">
    <?php endif; ?>
    <div class="photo-ov-r"></div>
    <div class="photo-ov-b"></div>
    <div class="photo-dots-inner"></div>

    <!-- Logo en foto -->
    <div class="photo-logo-wrap">
      <img src="<?= htmlspecialchars(maint_url($logo)) ?>"
           alt="<?= htmlspecialchars($partido) ?>" class="photo-logo"
           onerror="this.style.display='none'">
    </div>

    <!-- Texto inferior en foto -->
    <div class="photo-bottom">
      <div class="photo-badge-pill"><span class="bdot"></span>En mantenimiento</div>
      <p class="photo-name"><?= htmlspecialchars($firma) ?></p>
      <p class="photo-sub"><?= htmlspecialchars($partido) ?></p>
      <div class="photo-lines">
        <span style="width:36px"></span>
        <span style="width:18px;opacity:.4"></span>
        <span style="width:7px;opacity:.2"></span>
      </div>
    </div>
  </div>

  <!-- Panel contenido -->
  <div class="content-panel">

    <!-- Logo solo móvil -->
    <img src="<?= htmlspecialchars(maint_url($logo)) ?>"
         alt="<?= htmlspecialchars($partido) ?>"
         class="logo-mobile" onerror="this.style.display='none'">

    <!-- Badge -->
    <div class="badge"><span class="badge-dot"></span>En mantenimiento</div>

    <!-- Engranaje -->
    <div class="gear-wrap">
      <div class="gear-ring"></div>
      <svg class="gs1" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
      </svg>
      <svg class="gs2" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2">
        <path stroke-linecap="round" stroke-linejoin="round" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
      </svg>
    </div>

    <?php if ($countdown_active && $launch_date): ?>
    <!-- Countdown -->
    <p class="cd-label"><?= htmlspecialchars($launch_label) ?></p>
    <div class="cd-row" id="cdRow">
      <div class="cd-unit"><div class="cd-box"><span class="cd-num" id="cd-d">--</span></div><span class="cd-ulabel">Días</span></div>
      <span class="cd-sep">:</span>
      <div class="cd-unit"><div class="cd-box"><span class="cd-num" id="cd-h">--</span></div><span class="cd-ulabel">Hrs</span></div>
      <span class="cd-sep">:</span>
      <div class="cd-unit"><div class="cd-box"><span class="cd-num" id="cd-m">--</span></div><span class="cd-ulabel">Min</span></div>
      <span class="cd-sep">:</span>
      <div class="cd-unit"><div class="cd-box"><span class="cd-num" id="cd-s">--</span></div><span class="cd-ulabel">Seg</span></div>
    </div>
    <script>
    (function(){
      var t=new Date('<?= htmlspecialchars($launch_date) ?>T00:00:00-05:00').getTime();
      function p(n){return String(n).padStart(2,'0');}
      function tick(){
        var diff=t-Date.now();
        if(diff<=0){document.getElementById('cdRow').innerHTML='<span class="cd-done">¡Ha llegado el momento!</span>';return;}
        var d=Math.floor(diff/86400000),h=Math.floor((diff%86400000)/3600000),m=Math.floor((diff%3600000)/60000),s=Math.floor((diff%60000)/1000);
        document.getElementById('cd-d').textContent=p(d);
        document.getElementById('cd-h').textContent=p(h);
        document.getElementById('cd-m').textContent=p(m);
        document.getElementById('cd-s').textContent=p(s);
      }
      tick();setInterval(tick,1000);
    })();
    </script>
    <?php endif; ?>

    <h1><?= htmlspecialchars($title) ?></h1>
    <p class="sub"><?= nl2br(htmlspecialchars($msg)) ?></p>

    <?php if ($eta): ?>
    <div class="eta-box">
      <svg class="eta-icon" width="14" height="14" fill="none" stroke="#facc15" stroke-width="2" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/>
      </svg>
      <span class="eta-text"><?= htmlspecialchars($eta) ?></span>
    </div>
    <?php endif; ?>

    <div class="prog"><div class="prog-bar"></div></div>

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

    <div class="footer-sig">
      <span><?= htmlspecialchars($partido) ?></span> &nbsp;·&nbsp; <?= htmlspecialchars($firma) ?>
    </div>
  </div>

</div><!-- /card -->

</body>
</html>
