<?php
// ============================================================
// PLANTILLA DISTRITAL — Portal Ivan Cisneros
// Copiar y renombrar: rio-negro.php, pangoa.php, etc.
// Editar solo las variables de configuración de abajo.
// ============================================================
require_once __DIR__ . '/../includes/config/db.php';
require_once __DIR__ . '/../includes/helpers/config.php';
$cfg_camp = [];
try { $cfg_camp = $pdo->query("SELECT clave, valor FROM configuracion")->fetchAll(PDO::FETCH_KEY_PAIR); } catch(Exception $e){}
require_once __DIR__ . '/../includes/maintenance_check.php';

$_partido_nombre = 'ALIANZA PARA EL PROGRESO';
try {
    $v = $pdo->query("SELECT valor FROM configuracion WHERE clave='partido_nombre' LIMIT 1")->fetchColumn();
    if ($v) $_partido_nombre = $v;
} catch (Exception $e) {}

// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
// CONFIGURAR PARA CADA DISTRITO
// ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
$candidato_nombre   = "Mauro Mezona";
$candidato_distrito = "Río Negro";
$candidato_slug     = "rio-negro";
$candidato_foto     = BASE_URL . "/assets/img/distritales/rio-negro/foto.png";
$candidato_bio      = "Natural de Río Negro, con amplia trayectoria en la gestión comunal y el desarrollo local. Conoce cada rincón del distrito y trabaja incansablemente por el bienestar de su gente.";

$propuestas = [
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
  ['icon' => '🏘️', 'titulo' => 'Comunidades nativas',
   'desc'  => 'Apoyo a comunidades nativas en titulación, servicios básicos e identidad cultural.'],
];

$galeria = [
  // Agregar rutas de imágenes del distrito
  // BASE_URL . '/assets/img/distritales/rio-negro/foto1.jpg',
];

// ━━ PLAN DE GOBIERNO PDF ━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━━
$plan_pdf_server = __DIR__ . '/../assets/docs/planes/' . $candidato_slug . '.pdf';
$plan_pdf_url    = BASE_URL . '/assets/docs/planes/' . $candidato_slug . '.pdf';
$plan_pdf_existe = file_exists($plan_pdf_server);

// ━━ SOBREESCRIBIR CON DATOS DE BASE DE DATOS ━━━━━━━━━━━━━━
try {
    $db_cand = $pdo->prepare("SELECT * FROM candidatos_distritales WHERE slug = ? LIMIT 1");
    $db_cand->execute([$candidato_slug]);
    $db_cand = $db_cand->fetch();
    if ($db_cand) {
        if (!empty($db_cand['nombre']))     $candidato_nombre = $db_cand['nombre'];
        if (!empty($db_cand['bio']))        $candidato_bio    = $db_cand['bio'];
        if (!empty($db_cand['foto']))       $candidato_foto   = BASE_URL . ltrim($db_cand['foto'], '/');
        if (!empty($db_cand['propuestas'])) {
            $db_props = json_decode($db_cand['propuestas'], true);
            if (is_array($db_props) && !empty($db_props)) $propuestas = $db_props;
        }
    }
} catch (Exception $e) {}
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
      <img src="<?= BASE_URL ?>/assets/img/distritales/<?= $candidato_slug ?>/fondo.jpg"
           alt="<?= htmlspecialchars($candidato_distrito) ?>"
           class="w-full h-full object-cover"
           onerror="this.style.background='#1E3A8A'">
      <div class="absolute inset-0 bg-gradient-to-t from-[#1E3A8A] via-[#1E3A8A]/60 to-transparent"></div>
    </div>
    <div class="relative max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-12 w-full">
      <div class="flex flex-col sm:flex-row items-center sm:items-end gap-6 text-white">
        <!-- Foto candidato circular -->
        <div class="flex-shrink-0">
          <div class="w-52 h-52 rounded-full border-4 border-[#FACC15] shadow-xl overflow-hidden">
            <img src="<?= htmlspecialchars($candidato_foto) ?>"
                 alt="<?= htmlspecialchars($candidato_nombre) ?>"
                 class="w-full h-full object-cover"
                 onerror="this.src='https://placehold.co/208x208/1E3A8A/white?text=<?= urlencode($candidato_nombre) ?>'">
          </div>
        </div>
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
          <p class="text-blue-200 text-sm mt-1"><?= htmlspecialchars($_partido_nombre) ?> · Con el respaldo de Ivan Cisneros</p>
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
        <div data-aos="fade-right">
          <span class="text-[#38BDF8] font-semibold uppercase tracking-widest text-sm">Conócelo</span>
          <h2 class="text-2xl sm:text-3xl font-black text-[#1E3A8A] mt-2 mb-5">
            ¿Quién es <?= htmlspecialchars(explode(' ', $candidato_nombre)[0]) ?>?
          </h2>
          <p class="text-gray-600 leading-relaxed mb-6"><?= htmlspecialchars($candidato_bio) ?></p>
          <div class="grid grid-cols-3 gap-3">
            <?php
            $atributos = ['🤝 Compromiso', '🏠 Identidad local', '💪 Trabajo'];
            foreach ($atributos as $a): [$ico, $lab] = explode(' ', $a, 2); ?>
            <div class="text-center p-3 bg-blue-50 rounded-xl">
              <div class="text-2xl mb-1"><?= $ico ?></div>
              <p class="text-xs font-semibold text-[#1E3A8A]"><?= $lab ?></p>
            </div>
            <?php endforeach; ?>
          </div>
        </div>
        <div class="flex justify-center" data-aos="fade-left">
          <img src="<?= htmlspecialchars($candidato_foto) ?>"
               alt="<?= htmlspecialchars($candidato_nombre) ?>"
               class="w-72 rounded-2xl shadow-xl object-cover"
               onerror="this.src='https://placehold.co/300x380/EFF6FF/1E3A8A?text=<?= urlencode($candidato_nombre) ?>'">
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
             data-aos="fade-up" data-aos-delay="<?= $i * 80 ?>">
          <div class="text-4xl mb-3"><?= $p['icon'] ?></div>
          <h3 class="font-bold text-[#1E3A8A] text-base mb-2"><?= htmlspecialchars($p['titulo']) ?></h3>
          <p class="text-gray-500 text-sm leading-relaxed"><?= htmlspecialchars($p['desc']) ?></p>
        </div>
        <?php endforeach; ?>
      </div>

      <!-- Botón Plan de Gobierno — CTA bajo propuestas -->
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

  <!-- ══ CONEXIÓN PROVINCIAL ═══════════════════════════════ -->
  <section class="py-16 bg-[#1E3A8A]">
    <div class="max-w-4xl mx-auto px-4 sm:px-6 text-center" data-aos="fade-up">
      <div class="flex flex-col sm:flex-row items-center gap-6 bg-white/10 backdrop-blur-sm rounded-3xl p-8">
        <img src="<?= BASE_URL ?>/assets/img/candidato/ivancisneros-perfil.webp"
             alt="Ivan Cisneros"
             class="w-20 h-20 rounded-full border-4 border-[#FACC15] object-cover flex-shrink-0"
             onerror="this.src='https://placehold.co/80x80/FACC15/1E3A8A?text=JB'">
        <div class="text-left text-white">
          <p class="font-bold text-lg mb-1">
            <?= htmlspecialchars($candidato_nombre) ?> es parte del equipo de
            <span class="text-[#FACC15]">Ivan Cisneros</span>
          </p>
          <p class="text-blue-200 text-sm">
            Juntos trabajamos por el desarrollo integral de toda la provincia de Satipo.
            Un equipo unido, un solo objetivo.
          </p>
        </div>
        <a href="<?= BASE_URL ?>/plan.php"
           class="flex-shrink-0 bg-[#FACC15] hover:bg-yellow-400 text-[#1E3A8A] font-bold px-6 py-3 rounded-full text-sm transition-all whitespace-nowrap">
          Plan Provincial →
        </a>
      </div>
    </div>
  </section>

  <!-- ══ GALERÍA LOCAL ══════════════════════════════════════ -->
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
             data-aos="fade-up" data-aos-delay="<?= $i * 60 ?>">
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
            :src="abierto ? '<?= addslashes($plan_pdf_url) ?>#toolbar=1&navpanes=0&scrollbar=1&view=FitH' : ''"
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
