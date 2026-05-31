<?php
// ============================================================
// candidato-form.php — Editar candidato distrital
// Requiere rol mínimo: editor
// ============================================================
// Bootstrap manual ANTES del layout para poder usar header()
session_start();
require_once __DIR__ . '/../includes/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_once __DIR__ . '/../includes/helpers/webp.php';
require_login();
require_rol('editor');
require_modulo('candidatos_distritales', $pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
}

// ── Mapa de distritos ────────────────────────────────────────
$distritos_nombres = [
    'rio-negro'     => 'Río Negro',
    'pangoa'        => 'Pangoa',
    'rio-tambo'     => 'Río Tambo',
    'coviriali'     => 'Coviriali',
    'llaylla'       => 'Llaylla',
    'vizcatan'      => 'Vizcatán del Ene',
    'pampa-hermosa' => 'Pampa Hermosa',
    'mazamari'      => 'Mazamari',
];

// ── Validar ?id ───────────────────────────────────────────────
$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
if ($id <= 0) {
    header('Location: candidatos-distritales.php');
    exit;
}

// ── Cargar candidato ──────────────────────────────────────────
$candidato = null;
try {
    $stmt = $pdo->prepare("SELECT * FROM candidatos_distritales WHERE id = ?");
    $stmt->execute([$id]);
    $candidato = $stmt->fetch();
} catch (Exception $e) {
    $candidato = null;
}

if (!$candidato) {
    header('Location: candidatos-distritales.php');
    exit;
}

$slug     = $candidato['slug'];
$distrito = $distritos_nombres[$slug] ?? ucfirst($slug);
$page_title = 'Editar — ' . $distrito;

// ── Paginación de noticias ────────────────────────────────────
$news_per_page = 5;
$news_page     = max(1, (int)($_GET['news_page'] ?? 1));
$news_total    = (int)$pdo->prepare("SELECT COUNT(*) FROM candidato_noticias WHERE candidato_id = ?")->execute([$id]) ? 0 : 0;
$stmt_cnt = $pdo->prepare("SELECT COUNT(*) FROM candidato_noticias WHERE candidato_id = ?");
$stmt_cnt->execute([$id]);
$news_total  = (int)$stmt_cnt->fetchColumn();
$news_pages  = max(1, (int)ceil($news_total / $news_per_page));
$news_page   = min($news_page, $news_pages);
$news_offset = ($news_page - 1) * $news_per_page;

$stmt_news = $pdo->prepare("SELECT * FROM candidato_noticias WHERE candidato_id = ? ORDER BY creado_en DESC, id DESC LIMIT ? OFFSET ?");
$stmt_news->execute([$id, $news_per_page, $news_offset]);
$noticias_candidato = $stmt_news->fetchAll();

// Si hay paginación activa, la pestaña activa debe ser noticias
$default_tab = (isset($_GET['news_page']) || isset($_GET['msg']) && in_array($_GET['msg'], ['noticia','noticia_actualizada','noticia_eliminada'])) ? 'noticias' : 'candidatura';

// ── Decodificar propuestas (fallback a 5 por defecto) ────────
$propuestas_default = [
    ['icon' => '🛣️', 'titulo' => 'Vías de acceso comunal',  'desc' => 'Mejoramiento y apertura de caminos rurales que conecten las comunidades más alejadas.'],
    ['icon' => '💧', 'titulo' => 'Agua potable para todos', 'desc' => 'Instalación de sistemas de agua potable en los centros poblados sin cobertura.'],
    ['icon' => '📚', 'titulo' => 'Educación de calidad',    'desc' => 'Infraestructura educativa renovada y apoyo a docentes locales.'],
    ['icon' => '🌾', 'titulo' => 'Apoyo al agricultor',     'desc' => 'Asistencia técnica y acceso a mercados para productores de café y cacao.'],
    ['icon' => '🏥', 'titulo' => 'Salud preventiva',        'desc' => 'Brigadas de salud itinerantes y mejoramiento del puesto de salud distrital.'],
];

$propuestas_actuales = $propuestas_default;
if (!empty($candidato['propuestas'])) {
    $decoded = json_decode($candidato['propuestas'], true);
    if (is_array($decoded)) {
        $validas = [];
        foreach ($decoded as $p) {
            if (!is_array($p)) continue;
            if (trim((string)($p['titulo'] ?? '')) === '') continue;
            $validas[] = [
                'icon'   => (string)($p['icon']   ?? '📋'),
                'titulo' => (string)($p['titulo']  ?? ''),
                'desc'   => (string)($p['desc']    ?? ''),
            ];
        }
        if (!empty($validas)) {
            $propuestas_actuales = $validas;
        }
    }
}

// ── Ruta del PDF ──────────────────────────────────────────────
$pdf_rel    = 'assets/docs/planes/' . $slug . '.pdf';
$pdf_abs    = dirname(__DIR__) . '/' . $pdf_rel;
$pdf_exists = file_exists($pdf_abs);

// ── Estado de errores / mensaje ───────────────────────────────
$errors   = [];
$msg_ok   = '';

// ── Procesar POST ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'save_profile';

    // ── Helper upload para noticias ───────────────────────────
    $guardar_noticia_imagen = function(array $file) use (&$errors, $slug, $id): ?string {
        if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) return null;
        if ($file['error'] !== UPLOAD_ERR_OK || empty($file['tmp_name'])) {
            $errors[] = 'Error al recibir imagen de la noticia.';
            return null;
        }
        if ($file['size'] > 5 * 1024 * 1024) { $errors[] = 'La imagen no puede superar 5 MB.'; return null; }
        $ext_map = ['jpg'=>'jpg','jpeg'=>'jpg','png'=>'png','webp'=>'webp'];
        $orig_ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if (!isset($ext_map[$orig_ext])) { $errors[] = 'Formato de imagen no permitido.'; return null; }
        $ext = $ext_map[$orig_ext];
        $dir = dirname(__DIR__) . '/assets/img/distritales/' . $slug . '/';
        if (!is_dir($dir)) @mkdir($dir, 0755, true);
        $filename = 'noticia-' . date('YmdHis') . '.' . $ext;
        $content  = @file_get_contents($file['tmp_name']);
        if (!$content || @file_put_contents($dir . $filename, $content) === false) {
            $errors[] = 'No se pudo guardar la imagen de la noticia.';
            return null;
        }
        @unlink($file['tmp_name']);
        return '/assets/img/distritales/' . $slug . '/' . $filename;
    };

    // ── Acciones de noticias ──────────────────────────────────
    if ($action === 'save_news') {
        $n_titulo    = trim($_POST['noticia_titulo']    ?? '');
        $n_contenido = trim($_POST['noticia_contenido'] ?? '');
        $n_estado    = ($_POST['noticia_estado'] ?? 'publicado') === 'borrador' ? 'borrador' : 'publicado';
        if ($n_titulo    === '') $errors[] = 'El título de la noticia es obligatorio.';
        if ($n_contenido === '') $errors[] = 'El contenido de la noticia es obligatorio.';
        $n_imagen = isset($_FILES['noticia_imagen']) ? $guardar_noticia_imagen($_FILES['noticia_imagen']) : null;
        if (empty($errors)) {
            $pdo->prepare("INSERT INTO candidato_noticias (candidato_id, titulo, contenido, imagen, estado, creado_en, actualizado) VALUES (?,?,?,?,?,NOW(),NOW())")
                ->execute([$id, $n_titulo, $n_contenido, $n_imagen, $n_estado]);
            log_activity($pdo, 'Publicó noticia distrital: ' . $n_titulo, 'candidatos_distritales');
            header('Location: candidato-form.php?id=' . $id . '&msg=noticia#noticias');
            exit;
        }
    }

    if ($action === 'update_news') {
        $news_id     = (int)($_POST['news_id']           ?? 0);
        $n_titulo    = trim($_POST['noticia_titulo']      ?? '');
        $n_contenido = trim($_POST['noticia_contenido']   ?? '');
        $n_estado    = ($_POST['noticia_estado'] ?? 'publicado') === 'borrador' ? 'borrador' : 'publicado';
        if ($news_id     <= 0) $errors[] = 'Noticia no válida.';
        if ($n_titulo    === '') $errors[] = 'El título de la noticia es obligatorio.';
        if ($n_contenido === '') $errors[] = 'El contenido de la noticia es obligatorio.';
        $n_imagen = isset($_FILES['noticia_imagen']) ? $guardar_noticia_imagen($_FILES['noticia_imagen']) : null;
        if (empty($errors)) {
            $sql_n = "UPDATE candidato_noticias SET titulo = ?, contenido = ?, estado = ?, actualizado = NOW()";
            $par_n = [$n_titulo, $n_contenido, $n_estado];
            if ($n_imagen) { $sql_n .= ', imagen = ?'; $par_n[] = $n_imagen; }
            $sql_n .= ' WHERE id = ? AND candidato_id = ?';
            $par_n[] = $news_id; $par_n[] = $id;
            $pdo->prepare($sql_n)->execute($par_n);
            log_activity($pdo, 'Actualizó noticia distrital: ' . $n_titulo, 'candidatos_distritales');
            header('Location: candidato-form.php?id=' . $id . '&msg=noticia_actualizada#noticias');
            exit;
        }
    }

    if ($action === 'delete_news') {
        $news_id = (int)($_POST['news_id'] ?? 0);
        if ($news_id > 0) {
            $pdo->prepare("DELETE FROM candidato_noticias WHERE id = ? AND candidato_id = ?")->execute([$news_id, $id]);
            log_activity($pdo, 'Eliminó noticia distrital #' . $news_id, 'candidatos_distritales');
        }
        header('Location: candidato-form.php?id=' . $id . '&msg=noticia_eliminada#noticias');
        exit;
    }

    if ($action === 'save_profile') {

    // --- Campos de texto ---
    $nombre = trim($_POST['nombre'] ?? '');
    $bio    = trim($_POST['bio']    ?? '');

    if ($nombre === '') {
        $errors[] = 'El nombre del candidato es requerido.';
    }

    // ── Helper: guarda imagen usando file_put_contents (compatible Windows) ──
    $project_root  = dirname(__DIR__); // C:\laragon\www\ivancisneros
    $guardar_imagen = function(array $file, string $nombre_base) use (&$errors, $slug, $project_root): ?string {
        // Sin archivo seleccionado → saltar silenciosamente
        if (!isset($file['error']) || $file['error'] === UPLOAD_ERR_NO_FILE) return null;
        if (empty($file['tmp_name']) || $file['tmp_name'] === '') return null;

        if ($file['error'] !== UPLOAD_ERR_OK) {
            $errors[] = "Error al recibir archivo (código {$file['error']}).";
            return null;
        }
        if ($file['size'] > 5 * 1024 * 1024) {
            $errors[] = 'La imagen no puede superar 5 MB.';
            return null;
        }
        if ($file['size'] === 0) {
            $errors[] = 'El archivo está vacío.';
            return null;
        }

        // Validar extensión
        $orig_ext    = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        $ext_validas = ['jpg' => 'jpg', 'jpeg' => 'jpg', 'png' => 'png', 'webp' => 'webp', 'gif' => 'gif'];
        if (!isset($ext_validas[$orig_ext])) {
            $errors[] = "Formato '$orig_ext' no permitido. Usa JPG, PNG o WebP.";
            return null;
        }
        $ext = $ext_validas[$orig_ext];

        // Ruta destino con path explícito (sin .. para evitar problemas Windows)
        $dir_destino = $project_root . DIRECTORY_SEPARATOR . 'assets' . DIRECTORY_SEPARATOR
                     . 'img' . DIRECTORY_SEPARATOR . 'distritales' . DIRECTORY_SEPARATOR
                     . $slug . DIRECTORY_SEPARATOR;

        if (!is_dir($dir_destino)) {
            @mkdir($dir_destino, 0755, true);
        }

        // Convertir a WebP
        $webp_cf = img_to_webp($file['tmp_name'], $orig_ext);
        if ($webp_cf !== false) {
            $ext      = 'webp';
            $contenido = $webp_cf;
        } else {
            $contenido = @file_get_contents($file['tmp_name']);
            if ($contenido === false || strlen($contenido) === 0) {
                $errors[] = "No se pudo leer el archivo temporal. Intenta de nuevo.";
                return null;
            }
        }

        $dest  = $dir_destino . $nombre_base . '.' . $ext;
        $bytes = @file_put_contents($dest, $contenido);
        if ($bytes === false || $bytes === 0) {
            $errors[] = "No se pudo escribir la imagen. Dir: $dir_destino";
            return null;
        }

        @unlink($file['tmp_name']);

        $ruta_publica = '/assets/img/distritales/' . $slug . '/' . $nombre_base . '.' . $ext;
        return $ruta_publica;
    };

    $nueva_foto        = !empty($_FILES['foto'])        ? $guardar_imagen($_FILES['foto'],        'foto')   : null;
    $nueva_foto_perfil = !empty($_FILES['foto_perfil']) ? $guardar_imagen($_FILES['foto_perfil'], 'perfil') : null;

    // --- propuestas_json ---
    $propuestas_guardadas = [];
    $raw_json = trim($_POST['propuestas_json'] ?? '');
    if ($raw_json !== '') {
        $decoded_props = json_decode($raw_json, true);
        if (!is_array($decoded_props)) {
            $errors[] = 'Las propuestas tienen un formato inválido.';
        } else {
            foreach ($decoded_props as $idx => $p) {
                if (!is_array($p) || !isset($p['icon'], $p['titulo'], $p['desc'])) {
                    $errors[] = 'Propuesta #' . ($idx + 1) . ' tiene campos faltantes.';
                    break;
                }
                $propuestas_guardadas[] = [
                    'icon'   => (string)$p['icon'],
                    'titulo' => (string)$p['titulo'],
                    'desc'   => (string)$p['desc'],
                ];
            }
        }
    }

    // --- plan_pdf (file upload, opcional) ---
    if (!empty($_FILES['plan_pdf']['tmp_name']) && $_FILES['plan_pdf']['error'] !== UPLOAD_ERR_NO_FILE) {
        $f = $_FILES['plan_pdf'];
        if ($f['error'] !== UPLOAD_ERR_OK) {
            $errors[] = 'Error al subir el PDF (código ' . $f['error'] . ').';
        } elseif ($f['size'] > 20 * 1024 * 1024) {
            $errors[] = 'El PDF no puede superar 20 MB.';
        } else {
            // Validar por extensión (más fiable en Windows)
            $orig_ext_pdf = strtolower(pathinfo($f['name'], PATHINFO_EXTENSION));
            if ($orig_ext_pdf !== 'pdf') {
                $errors[] = 'Solo se permiten archivos PDF.';
            } else {
                $pdf_dir = dirname(__DIR__) . '/assets/docs/planes/';
                if (!is_dir($pdf_dir)) @mkdir($pdf_dir, 0755, true);
                $dest = $pdf_dir . $slug . '.pdf';
                // Usar file_put_contents (compatible Windows, mismo método que imágenes)
                $contenido_pdf = @file_get_contents($f['tmp_name']);
                if ($contenido_pdf === false || strlen($contenido_pdf) === 0) {
                    $errors[] = 'No se pudo leer el PDF. Intenta de nuevo.';
                } else {
                    $bytes_pdf = @file_put_contents($dest, $contenido_pdf);
                    if ($bytes_pdf === false || $bytes_pdf === 0 || !file_exists($dest)) {
                        $errors[] = 'No se pudo guardar el PDF en: ' . $dest;
                    } else {
                        @unlink($f['tmp_name']);
                        $pdf_exists = true;
                    }
                }
            }
        }
    }

    // --- Guardar en BD si no hay errores ---
    if (empty($errors)) {
        try {
            $set_foto        = $nueva_foto        !== null ? ', foto = ?'        : '';
            $set_foto_perfil = $nueva_foto_perfil !== null ? ', foto_perfil = ?' : '';

            $params = [$nombre, $bio, json_encode($propuestas_guardadas, JSON_UNESCAPED_UNICODE)];
            if ($nueva_foto        !== null) $params[] = $nueva_foto;
            if ($nueva_foto_perfil !== null) $params[] = $nueva_foto_perfil;
            $params[] = $id;

            $pdo->prepare(
                "UPDATE candidatos_distritales
                    SET nombre = ?, bio = ?, propuestas = ?, actualizado = NOW()
                        {$set_foto}{$set_foto_perfil}
                  WHERE id = ?"
            )->execute($params);

            log_activity($pdo, 'Editó candidato: ' . $distrito, 'candidatos_distritales');

            header('Location: candidatos-distritales.php?msg=updated');
            exit;

        } catch (Exception $e) {
            $errors[] = 'Error al guardar en la base de datos: ' . $e->getMessage();
        }
    }

    // Repoblar para mostrar en el form si hay errores
    $candidato['nombre'] = $nombre;
    $candidato['bio']    = $bio;
    if ($nueva_foto        !== null) $candidato['foto']        = $nueva_foto;
    if ($nueva_foto_perfil !== null) $candidato['foto_perfil'] = $nueva_foto_perfil;
    $propuestas_actuales = $propuestas_guardadas;

    } // fin if ($action === 'save_profile')

    // Recargar noticias tras cualquier acción POST con errores
    $stmt_news = $pdo->prepare("SELECT * FROM candidato_noticias WHERE candidato_id = ? ORDER BY creado_en DESC, id DESC LIMIT 20");
    $stmt_news->execute([$id]);
    $noticias_candidato = $stmt_news->fetchAll();
}

// ── Incluir layout DESPUÉS de toda la lógica PHP ─────────────
include __DIR__ . '/layout.php';
?>
<!-- Quill CSS -->
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<style>
  .ql-container { font-size: 0.875rem; }
  .ql-toolbar { border-radius: 0.75rem 0.75rem 0 0 !important; border-color: #E5E7EB !important; background:#FAFAFA; }
  .ql-container { border-radius: 0 0 0.75rem 0.75rem !important; border-color: #E5E7EB !important; }
  .ql-editor.ql-blank::before { color: #9CA3AF; font-style: normal; }
  .ql-editor { min-height: 180px; }
  .ql-editor-sm .ql-editor { min-height: 120px; }
</style>

<!-- ── Back link ──────────────────────────────────────────────── -->
<div class="mb-5 flex items-center justify-between flex-wrap gap-3">
  <a href="candidatos-distritales.php"
     class="inline-flex items-center gap-1.5 text-sm text-gray-400 hover:text-[#1E3A8A] font-medium transition-colors">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
    </svg>
    Volver a candidatos
  </a>
  <a href="<?= BASE_URL ?>/distritales/<?= htmlspecialchars($slug) ?>.php" target="_blank"
     class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#1E3A8A] hover:text-blue-900 transition-colors
            bg-blue-50 px-3 py-1.5 rounded-xl border border-blue-200">
    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
    </svg>
    Ver página del candidato
  </a>
</div>

<!-- ── Errores ────────────────────────────────────────────────── -->
<?php if (!empty($errors)): ?>
<div class="mb-5 rounded-xl border border-red-200 bg-red-50 px-5 py-4">
  <div class="flex items-center gap-2 mb-2">
    <svg class="w-4 h-4 text-red-600 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
            d="M12 9v2m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/>
    </svg>
    <p class="text-sm font-bold text-red-700">Se encontraron errores al guardar:</p>
  </div>
  <ul class="list-disc list-inside space-y-0.5">
    <?php foreach ($errors as $e): ?>
      <li class="text-sm text-red-600"><?= htmlspecialchars($e) ?></li>
    <?php endforeach; ?>
  </ul>
</div>
<?php endif; ?>

<!-- ── Tabs ───────────────────────────────────────────────────── -->
<div x-data="tabManager('<?= $default_tab ?>')" x-init="init()">

  <!-- Tab header -->
  <div class="flex gap-1.5 bg-white rounded-2xl shadow-sm border border-gray-100 p-1.5 mb-6">
    <button @click="setTab('candidatura')"
            :class="tab==='candidatura'
              ? 'bg-[#1E3A8A] text-white shadow-sm'
              : 'text-gray-500 hover:bg-gray-50 hover:text-gray-800'"
            class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl font-black text-sm transition-all">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
      </svg>
      Candidatura
    </button>
    <button @click="setTab('noticias')"
            :class="tab==='noticias'
              ? 'bg-[#1E3A8A] text-white shadow-sm'
              : 'text-gray-500 hover:bg-gray-50 hover:text-gray-800'"
            class="flex-1 inline-flex items-center justify-center gap-2 px-4 py-2.5 rounded-xl font-black text-sm transition-all">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/>
      </svg>
      Noticias
      <?php if ($news_total > 0): ?>
      <span :class="tab==='noticias' ? 'bg-[#FACC15] text-[#1E3A8A]' : 'bg-gray-200 text-gray-600'"
            class="text-xs font-black px-2 py-0.5 rounded-full transition-colors">
        <?= $news_total ?>
      </span>
      <?php endif; ?>
    </button>
  </div>

<!-- ══ TAB: CANDIDATURA ════════════════════════════════════════ -->
<div x-show="tab==='candidatura'"
     x-transition:enter="transition ease-out duration-150"
     x-transition:enter-start="opacity-0 translate-y-1"
     x-transition:enter-end="opacity-100 translate-y-0">

<!-- ── Formulario ─────────────────────────────────────────────── -->
<form method="POST" enctype="multipart/form-data"
      class="space-y-6"
      x-data="candidatoForm()"
      data-propuestas="<?= htmlspecialchars(json_encode(array_values($propuestas_actuales), JSON_UNESCAPED_UNICODE), ENT_QUOTES, 'UTF-8') ?>"
      x-init="propuestas = JSON.parse($el.dataset.propuestas)">
  <input type="hidden" name="action" value="save_profile">

  <!-- ════ SECCIÓN 1: Información básica ═════════════════════ -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="bg-[#1E3A8A] px-6 py-4 flex items-center gap-3 flex-wrap">
      <svg class="w-5 h-5 text-[#FACC15] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
      </svg>
      <h3 class="text-white font-bold text-sm tracking-wide">Información del Candidato</h3>
      <span class="ml-auto inline-flex items-center gap-1.5 bg-[#FACC15] text-[#1E3A8A]
                   font-black text-xs px-3 py-1 rounded-full">
        <?= htmlspecialchars($distrito) ?>
      </span>
    </div>
    <div class="p-6 space-y-5">

      <!-- Nombre -->
      <div>
        <label for="nombre" class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-1.5">
          Nombre completo <span class="text-red-500">*</span>
        </label>
        <input type="text" id="nombre" name="nombre"
               value="<?= htmlspecialchars($candidato['nombre'] ?? '') ?>"
               placeholder="Ej: Juan Carlos Pérez López"
               required
               class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm
                      focus:ring-2 focus:ring-[#1E3A8A] focus:border-transparent outline-none transition-all">
      </div>


      <!-- Bio -->
      <div>
        <label for="bio" class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-1.5">
          Biografía
        </label>
        <textarea id="bio" name="bio" rows="4"
                  placeholder="Escribe una semblanza del candidato: trayectoria, logros, experiencia..."
                  class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm resize-y
                         focus:ring-2 focus:ring-[#1E3A8A] focus:border-transparent outline-none transition-all"
                  ><?= htmlspecialchars($candidato['bio'] ?? '') ?></textarea>
        <p class="text-xs text-gray-400 mt-1">Puedes usar saltos de línea para párrafos.</p>
      </div>

    </div>
  </div>

  <!-- ════ SECCIÓN 2: Fotos ══════════════════════════════════ -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="bg-[#1E3A8A] px-6 py-4 flex items-center gap-3">
      <svg class="w-5 h-5 text-[#FACC15] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
      </svg>
      <h3 class="text-white font-bold text-sm tracking-wide">Fotos</h3>
    </div>
    <div class="p-6 grid grid-cols-1 sm:grid-cols-2 gap-6">

      <!-- Foto Hero (circular) -->
      <div>
        <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-1">
          Foto circular del banner (hero)
        </label>
        <p class="text-xs text-blue-500 mb-3">
          📍 Aparece en el <strong>círculo amarillo</strong> del banner superior.
          Usa una foto de frente, busto o cuerpo completo. El sistema recorta desde arriba.
        </p>
        <div class="flex flex-col items-center gap-3 p-4 bg-gray-50 rounded-xl border border-gray-200">
          <?php if (!empty($candidato['foto'])): ?>
            <img src="<?= htmlspecialchars($candidato['foto']) ?>"
                 alt="Foto hero"
                 class="w-24 h-24 rounded-full object-cover object-top border-4 border-[#FACC15] shadow">
            <p class="text-xs text-gray-400 text-center font-mono break-all">
              <?= htmlspecialchars($candidato['foto']) ?>
            </p>
          <?php else: ?>
            <div class="w-24 h-24 rounded-full bg-gray-200 border-4 border-[#FACC15] shadow flex items-center justify-center">
              <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
              </svg>
            </div>
            <p class="text-xs text-gray-400 italic">Sin foto cargada</p>
          <?php endif; ?>
          <div class="w-full">
            <input type="file" name="foto" accept="image/*"
                   class="w-full text-xs text-gray-500 cursor-pointer
                          file:mr-2 file:py-1.5 file:px-3 file:rounded-full file:border-0
                          file:bg-[#1E3A8A] file:text-white file:font-semibold file:text-xs
                          hover:file:bg-blue-900 file:cursor-pointer">
            <p class="text-xs text-gray-400 mt-1.5">
              JPG, PNG o WebP · máx. 5 MB
              <?php if (!empty($candidato['foto'])): ?> · <span class="text-orange-500">Reemplaza la foto actual</span><?php endif; ?>
            </p>
          </div>
        </div>
      </div>

      <!-- Foto Perfil -->
      <div>
        <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-1">
          Foto de perfil — sección "¿Quién es?"
        </label>
        <p class="text-xs text-blue-500 mb-3">
          📍 Aparece en el <strong>cuadro rectangular</strong> de la sección "¿Quién es?" (600×350 px aprox.).
          Recomendado: foto <strong>horizontal o panorámica</strong> del candidato. Puede ser foto de cuerpo completo, busto o imagen de campaña amplia.
        </p>
        <div class="flex flex-col items-center gap-3 p-4 bg-gray-50 rounded-xl border border-gray-200">
          <?php if (!empty($candidato['foto_perfil'])): ?>
            <img src="<?= htmlspecialchars($candidato['foto_perfil']) ?>"
                 alt="Foto perfil"
                 class="rounded-xl w-full h-48 object-cover object-top border border-gray-200 shadow-sm">
            <p class="text-xs text-gray-400 text-center font-mono break-all">
              <?= htmlspecialchars($candidato['foto_perfil']) ?>
            </p>
          <?php else: ?>
            <div class="w-full h-36 rounded-xl bg-gray-200 border border-gray-200 flex flex-col items-center justify-center gap-2">
              <svg class="w-10 h-10 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z"/>
              </svg>
              <p class="text-xs text-gray-400 italic">Sin foto de perfil — se usará la foto del hero</p>
            </div>
          <?php endif; ?>
          <div class="w-full">
            <input type="file" name="foto_perfil" accept="image/*"
                   class="w-full text-xs text-gray-500 cursor-pointer
                          file:mr-2 file:py-1.5 file:px-3 file:rounded-full file:border-0
                          file:bg-[#1E3A8A] file:text-white file:font-semibold file:text-xs
                          hover:file:bg-blue-900 file:cursor-pointer">
            <p class="text-xs text-gray-400 mt-1.5">
              JPG, PNG o WebP · máx. 5 MB
              <?php if (!empty($candidato['foto_perfil'])): ?>· Reemplazar foto<?php endif; ?>
            </p>
          </div>
        </div>
      </div>

    </div>
  </div>

  <!-- ════ SECCIÓN 3: Propuestas (Alpine.js) ═════════════════ -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="bg-[#1E3A8A] px-6 py-4 flex items-center gap-3 flex-wrap">
      <svg class="w-5 h-5 text-[#FACC15] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2m-6 9l2 2 4-4"/>
      </svg>
      <h3 class="text-white font-bold text-sm tracking-wide">Propuestas de Gobierno</h3>
      <button type="button" @click="agregarPropuesta()"
              class="ml-auto inline-flex items-center gap-1.5 bg-green-500 hover:bg-green-600
                     text-white font-bold text-xs px-3 py-1.5 rounded-lg transition-colors">
        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
        </svg>
        Agregar Propuesta
      </button>
    </div>
    <div class="p-6">
      <p class="text-sm text-gray-500 mb-5">
        Se auto-detecta el ícono según las palabras del título.
        Puedes sobrescribirlo manualmente con cualquier emoji.
      </p>

      <!-- Input oculto con JSON de propuestas -->
      <input type="hidden" name="propuestas_json" :value="propuestasJson">

      <!-- Lista de propuestas -->
      <div class="space-y-3">
        <template x-for="(prop, i) in propuestas" :key="i">
          <div class="relative border border-gray-200 rounded-xl p-4 bg-gray-50 hover:border-[#1E3A8A]/30 transition-colors">

            <!-- Número de propuesta + botón eliminar -->
            <div class="flex items-center justify-between mb-3">
              <div class="flex items-center gap-2">
                <div class="w-6 h-6 bg-[#FACC15] rounded-md flex items-center justify-center flex-shrink-0">
                  <span class="text-[#1E3A8A] font-black text-xs" x-text="i + 1"></span>
                </div>
                <span class="text-xs font-black text-gray-500 uppercase tracking-wider">
                  Propuesta <span x-text="i + 1"></span>
                </span>
              </div>
              <button type="button"
                      @click="eliminarPropuesta(i)"
                      x-show="propuestas.length > 1"
                      class="inline-flex items-center gap-1 text-xs font-semibold text-red-500
                             hover:text-red-700 hover:bg-red-50 px-2 py-1 rounded-lg transition-colors">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                </svg>
                Eliminar
              </button>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-[90px_1fr] gap-3 mb-3">
              <!-- Ícono emoji -->
              <div>
                <label class="block text-xs font-semibold text-gray-500 mb-1">
                  Ícono <span class="text-gray-400 font-normal">(auto)</span>
                </label>
                <div class="flex flex-col items-center gap-1">
                  <span class="text-3xl leading-none" x-text="prop.icon"></span>
                  <input type="text"
                         x-model="prop.icon"
                         maxlength="10"
                         placeholder="📋"
                         class="w-16 border border-gray-200 rounded-lg px-2 py-1.5 text-center text-xl
                                focus:ring-2 focus:ring-[#1E3A8A] focus:border-transparent outline-none transition-all bg-white">
                </div>
              </div>
              <!-- Título -->
              <div>
                <label class="block text-xs font-semibold text-gray-500 mb-1">Título</label>
                <input type="text"
                       x-model="prop.titulo"
                       @input="updateIcon(i)"
                       placeholder="Título de la propuesta"
                       class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm
                              focus:ring-2 focus:ring-[#1E3A8A] focus:border-transparent outline-none transition-all bg-white">
              </div>
            </div>

            <!-- Descripción -->
            <div>
              <label class="block text-xs font-semibold text-gray-500 mb-1">Descripción</label>
              <textarea x-model="prop.desc"
                        rows="2"
                        placeholder="Descripción breve..."
                        class="w-full border border-gray-200 rounded-lg px-3 py-2 text-sm resize-none
                               focus:ring-2 focus:ring-[#1E3A8A] focus:border-transparent outline-none transition-all bg-white"
                        ></textarea>
            </div>

          </div>
        </template>
      </div>

      <!-- Empty state -->
      <div x-show="propuestas.length === 0"
           class="text-center py-10 border-2 border-dashed border-gray-200 rounded-xl">
        <p class="text-gray-400 text-sm mb-3">No hay propuestas aún.</p>
        <button type="button" @click="agregarPropuesta()"
                class="inline-flex items-center gap-1.5 bg-green-500 hover:bg-green-600
                       text-white font-bold text-sm px-4 py-2 rounded-lg transition-colors">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
          </svg>
          Agrega tu primera propuesta
        </button>
      </div>

    </div>
  </div>

  <!-- ════ SECCIÓN 4: Plan de Gobierno PDF ══════════════════ -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="bg-[#1E3A8A] px-6 py-4 flex items-center gap-3">
      <svg class="w-5 h-5 text-[#FACC15] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
              d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
      </svg>
      <h3 class="text-white font-bold text-sm tracking-wide">Plan de Gobierno (PDF)</h3>
    </div>
    <div class="p-6 space-y-4">

      <!-- Estado actual -->
      <div class="flex items-center justify-between gap-4 p-4 rounded-xl border-2
                  <?= $pdf_exists ? 'border-green-200 bg-green-50' : 'border-dashed border-orange-200 bg-orange-50' ?>">
        <div class="flex items-center gap-3">
          <?php if ($pdf_exists): ?>
            <div class="w-10 h-10 bg-green-100 rounded-xl flex items-center justify-center flex-shrink-0">
              <svg class="w-5 h-5 text-green-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
              </svg>
            </div>
            <div>
              <p class="text-sm font-bold text-green-800">PDF cargado ✓</p>
              <p class="text-xs text-green-600 font-mono"><?= htmlspecialchars($slug) ?>.pdf</p>
            </div>
          <?php else: ?>
            <div class="w-10 h-10 bg-orange-100 rounded-xl flex items-center justify-center flex-shrink-0">
              <svg class="w-5 h-5 text-orange-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M7 21h10a2 2 0 002-2V9.414a1 1 0 00-.293-.707l-5.414-5.414A1 1 0 0012.586 3H7a2 2 0 00-2 2v14a2 2 0 002 2z"/>
              </svg>
            </div>
            <div>
              <p class="text-sm font-bold text-orange-700">Sin PDF</p>
              <p class="text-xs text-orange-500">Aún no se ha subido el plan de gobierno.</p>
            </div>
          <?php endif; ?>
        </div>
        <?php if ($pdf_exists): ?>
          <a href="<?= BASE_URL ?>/<?= $pdf_rel ?>" target="_blank"
             class="flex-shrink-0 inline-flex items-center gap-1.5 text-xs font-bold text-green-700
                    hover:text-green-900 bg-green-100 hover:bg-green-200 px-3 py-2 rounded-xl transition-colors">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                    d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
            </svg>
            Ver PDF
          </a>
        <?php endif; ?>
      </div>

      <!-- Subir / reemplazar PDF -->
      <div>
        <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-2">
          Subir/Reemplazar Plan de Gobierno (PDF, máx. 20 MB)
        </label>
        <div class="flex items-center gap-3 bg-gray-50 border border-gray-200 rounded-xl px-4 py-3">
          <svg class="w-5 h-5 text-gray-400 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                  d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
          </svg>
          <input type="file" name="plan_pdf" accept=".pdf"
                 class="text-sm text-gray-600 flex-1 cursor-pointer
                        file:mr-3 file:py-1.5 file:px-4 file:rounded-full file:border-0
                        file:bg-[#1E3A8A] file:text-white file:font-semibold file:text-xs
                        hover:file:bg-blue-900 file:cursor-pointer">
        </div>
        <p class="text-xs text-gray-400 mt-1.5">
          Solo archivos PDF.
          <?= $pdf_exists ? 'Subir un nuevo PDF reemplazará el existente.' : '' ?>
          Se guardará como <code class="bg-gray-100 px-1 rounded font-mono"><?= htmlspecialchars($slug) ?>.pdf</code>.
        </p>
      </div>

    </div>
  </div>

  <!-- ════ Botones de acción ══════════════════════════════════ -->
  <div class="flex flex-col sm:flex-row items-stretch sm:items-center gap-3 pt-1">
    <a href="candidatos-distritales.php"
       class="inline-flex items-center justify-center gap-2 bg-white hover:bg-gray-50 border border-gray-200
              text-gray-600 hover:text-gray-800 font-semibold px-5 py-3 rounded-xl text-sm
              transition-all shadow-sm order-2 sm:order-1">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
      </svg>
      Volver
    </a>
    <button type="submit"
            class="sm:flex-1 inline-flex items-center justify-center gap-2 bg-[#1E3A8A] hover:bg-blue-900
                   text-white font-black px-8 py-3 rounded-xl text-sm
                   transition-all shadow-md hover:shadow-lg active:scale-95 order-1 sm:order-2">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
      </svg>
      Guardar cambios
    </button>
    <span class="text-xs text-gray-400 hidden sm:block order-3 ml-auto">
      ID: <?= $id ?> &bull; <?= htmlspecialchars($slug) ?>
    </span>
  </div>

</form>
</div><!-- fin tab candidatura -->

<!-- ══ TAB: NOTICIAS ══════════════════════════════════════════ -->
<div x-show="tab==='noticias'"
     x-transition:enter="transition ease-out duration-150"
     x-transition:enter-start="opacity-0 translate-y-1"
     x-transition:enter-end="opacity-100 translate-y-0">

  <div class="grid grid-cols-1 xl:grid-cols-2 gap-6">

    <!-- ── Columna izquierda: Nueva noticia ─────────────────── -->
    <div>
      <form id="form-nueva-noticia-cf"
            method="POST" enctype="multipart/form-data"
            class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden sticky top-4">
        <?= csrf_field() ?>
        <input type="hidden" name="action" value="save_news">
        <div class="bg-[#1E3A8A] px-6 py-4 flex items-center gap-3">
          <svg class="w-5 h-5 text-[#FACC15] flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
          </svg>
          <div>
            <h3 class="text-white font-black text-sm">Nueva noticia distrital</h3>
            <p class="text-blue-200 text-xs mt-0.5">Se mostrará en la página pública del distrito.</p>
          </div>
        </div>
        <div class="p-6 space-y-4">
          <div>
            <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-1.5">Título <span class="text-red-500">*</span></label>
            <input type="text" name="noticia_titulo" required
                   placeholder="Ej: Avances en obra vial del distrito..."
                   class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:ring-2 focus:ring-[#1E3A8A] focus:border-transparent outline-none transition-all">
          </div>
          <div>
            <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-1.5">Contenido <span class="text-red-500">*</span></label>
            <div id="quill-nueva-cf" class="rounded-xl bg-white"></div>
            <input type="hidden" name="noticia_contenido" id="quill-nueva-cf-hidden">
          </div>
          <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-1.5">Imagen</label>
              <div class="border border-dashed border-gray-300 rounded-xl p-3 text-center hover:border-[#1E3A8A] transition-colors">
                <input type="file" name="noticia_imagen" accept="image/*" class="w-full text-xs cursor-pointer">
                <p class="text-xs text-gray-400 mt-1">JPG, PNG o WebP · máx. 5 MB</p>
              </div>
            </div>
            <div>
              <label class="block text-xs font-black text-gray-500 uppercase tracking-widest mb-1.5">Estado</label>
              <select name="noticia_estado"
                      class="w-full rounded-xl border border-gray-200 px-4 py-2.5 text-sm focus:ring-2 focus:ring-[#1E3A8A] focus:border-transparent outline-none transition-all">
                <option value="publicado">✅ Publicado</option>
                <option value="borrador">📝 Borrador</option>
              </select>
            </div>
          </div>
          <button type="submit"
                  class="w-full rounded-xl bg-[#1E3A8A] hover:bg-blue-900 px-5 py-3 text-sm font-black text-white transition-all shadow-md hover:shadow-lg active:scale-95">
            Publicar noticia
          </button>
        </div>
      </form>
    </div>

    <!-- ── Columna derecha: Lista paginada ──────────────────── -->
    <div class="flex flex-col gap-4">

      <!-- Header lista -->
      <div class="flex items-center justify-between">
        <h3 class="font-black text-gray-900">
          Noticias del distrito
          <span class="ml-2 text-sm font-normal text-gray-400">(<?= $news_total ?> total)</span>
        </h3>
        <?php if ($news_pages > 1): ?>
        <span class="text-xs text-gray-400">Página <?= $news_page ?> de <?= $news_pages ?></span>
        <?php endif; ?>
      </div>

      <!-- Lista de noticias -->
      <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
        <?php if (!$noticias_candidato): ?>
        <div class="flex flex-col items-center justify-center py-16 px-6 text-center">
          <svg class="w-12 h-12 text-gray-300 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                  d="M19 20H5a2 2 0 01-2-2V6a2 2 0 012-2h10a2 2 0 012 2v1m2 13a2 2 0 01-2-2V7m2 13a2 2 0 002-2V9a2 2 0 00-2-2h-2m-4-3H9M7 16h6M7 8h6v4H7V8z"/>
          </svg>
          <p class="text-gray-400 font-semibold text-sm">Aún no hay noticias publicadas.</p>
          <p class="text-gray-300 text-xs mt-1">Crea la primera desde el formulario de la izquierda.</p>
        </div>
        <?php endif; ?>

        <div class="divide-y divide-gray-100">
        <?php foreach ($noticias_candidato as $n): ?>
          <?php
            $img_thumb = !empty($n['imagen']) ? BASE_URL . '/' . ltrim((string)$n['imagen'], '/') : '';
            $es_pub    = $n['estado'] === 'publicado';
          ?>
          <div class="group p-4" x-data="noticiaItem()"
               x-init="$watch('editando', v => { if(v && !qi) { $nextTick(() => initQuillEdit($el)); qi=true; } })">
            <!-- Vista compacta -->
            <div class="flex gap-3 items-start">
              <?php if ($img_thumb): ?>
              <img src="<?= htmlspecialchars($img_thumb) ?>" alt=""
                   class="w-14 h-14 rounded-xl object-cover flex-shrink-0 border border-gray-100">
              <?php else: ?>
              <div class="w-14 h-14 rounded-xl bg-gray-100 flex items-center justify-center flex-shrink-0">
                <svg class="w-6 h-6 text-gray-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                        d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01"/>
                </svg>
              </div>
              <?php endif; ?>

              <div class="flex-1 min-w-0">
                <a href="<?= BASE_URL ?>/distritales/<?= htmlspecialchars($slug) ?>.php" target="_blank"
                   class="font-black text-sm text-gray-900 hover:text-[#1E3A8A] transition-colors line-clamp-2 block">
                  <?= htmlspecialchars((string)$n['titulo']) ?>
                </a>
                <div class="flex items-center gap-2 mt-1 flex-wrap">
                  <span class="inline-flex items-center gap-1 text-xs font-bold px-2 py-0.5 rounded-full
                               <?= $es_pub ? 'bg-green-100 text-green-700' : 'bg-amber-100 text-amber-700' ?>">
                    <?= $es_pub ? '✅ Publicado' : '📝 Borrador' ?>
                  </span>
                  <span class="text-xs text-gray-400">
                    <?= htmlspecialchars(date('d/m/Y', strtotime((string)$n['creado_en']))) ?>
                  </span>
                </div>
              </div>

              <!-- Acciones -->
              <div class="flex gap-1.5 flex-shrink-0">
                <button @click="editando = !editando"
                        :class="editando ? 'bg-[#1E3A8A] text-white' : 'bg-blue-50 text-[#1E3A8A] hover:bg-blue-100'"
                        class="rounded-lg px-2.5 py-1.5 text-xs font-black transition-colors">
                  Editar
                </button>
                <form method="POST" onsubmit="return confirm('¿Eliminar esta noticia?')">
                  <?= csrf_field() ?>
                  <input type="hidden" name="action" value="delete_news">
                  <input type="hidden" name="news_id" value="<?= (int)$n['id'] ?>">
                  <button class="rounded-lg bg-red-50 px-2.5 py-1.5 text-xs font-black text-red-600 hover:bg-red-100 transition-colors">
                    Eliminar
                  </button>
                </form>
              </div>
            </div>

            <!-- Form edición inline (colapsable) -->
            <div x-show="editando"
                 x-transition:enter="transition ease-out duration-150"
                 x-transition:enter-start="opacity-0 -translate-y-1"
                 x-transition:enter-end="opacity-100 translate-y-0"
                 class="mt-4 pt-4 border-t border-gray-100">
              <form class="noticia-edit-form space-y-3" method="POST" enctype="multipart/form-data">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="update_news">
                <input type="hidden" name="news_id" value="<?= (int)$n['id'] ?>">
                <input type="text" name="noticia_titulo" value="<?= htmlspecialchars((string)$n['titulo']) ?>"
                       class="w-full rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-[#1E3A8A] outline-none">
                <div class="ql-editor-sm">
                  <div class="quill-edit-area rounded-xl bg-white"></div>
                  <input type="hidden" class="noticia-contenido-hidden" name="noticia_contenido"
                         value="<?= htmlspecialchars((string)$n['contenido']) ?>">
                </div>
                <div class="grid grid-cols-2 gap-3">
                  <select name="noticia_estado" class="rounded-xl border border-gray-200 px-3 py-2 text-sm focus:ring-2 focus:ring-[#1E3A8A] outline-none">
                    <option value="publicado" <?= $n['estado'] === 'publicado' ? 'selected' : '' ?>>✅ Publicado</option>
                    <option value="borrador"  <?= $n['estado'] === 'borrador'  ? 'selected' : '' ?>>📝 Borrador</option>
                  </select>
                  <input type="file" name="noticia_imagen" accept="image/*"
                         class="text-xs rounded-xl border border-gray-200 px-3 py-2 cursor-pointer">
                </div>
                <div class="flex gap-2">
                  <button type="submit"
                          class="flex-1 rounded-xl bg-[#1E3A8A] px-4 py-2 text-xs font-black text-white hover:bg-blue-900 transition-colors">
                    Guardar cambios
                  </button>
                  <button type="button" @click="editando = false"
                          class="rounded-xl bg-gray-100 px-4 py-2 text-xs font-black text-gray-600 hover:bg-gray-200 transition-colors">
                    Cancelar
                  </button>
                </div>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
        </div>
      </div>

      <!-- Paginación -->
      <?php if ($news_pages > 1): ?>
      <div class="flex items-center justify-center gap-1 flex-wrap">
        <?php if ($news_page > 1): ?>
        <a href="?id=<?= $id ?>&news_page=<?= $news_page - 1 ?>#noticias"
           class="w-9 h-9 rounded-xl bg-white border border-gray-200 flex items-center justify-center text-gray-500 hover:bg-gray-50 hover:text-[#1E3A8A] transition-colors shadow-sm">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
          </svg>
        </a>
        <?php endif; ?>

        <?php for ($p = 1; $p <= $news_pages; $p++): ?>
        <a href="?id=<?= $id ?>&news_page=<?= $p ?>#noticias"
           class="w-9 h-9 rounded-xl flex items-center justify-center text-sm font-black transition-all shadow-sm
                  <?= $p === $news_page
                    ? 'bg-[#1E3A8A] text-white shadow-md'
                    : 'bg-white border border-gray-200 text-gray-600 hover:bg-gray-50 hover:text-[#1E3A8A]' ?>">
          <?= $p ?>
        </a>
        <?php endfor; ?>

        <?php if ($news_page < $news_pages): ?>
        <a href="?id=<?= $id ?>&news_page=<?= $news_page + 1 ?>#noticias"
           class="w-9 h-9 rounded-xl bg-white border border-gray-200 flex items-center justify-center text-gray-500 hover:bg-gray-50 hover:text-[#1E3A8A] transition-colors shadow-sm">
          <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
          </svg>
        </a>
        <?php endif; ?>
      </div>
      <?php endif; ?>

    </div><!-- fin columna derecha -->
  </div><!-- fin grid -->
</div><!-- fin tab noticias -->

<!-- Quill JS -->
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script>
// ── Quill helpers ────────────────────────────────────────────
function _quillToolbar() {
  return [
    ['bold', 'italic', 'underline', 'strike'],
    [{ header: [2, 3, false] }],
    [{ list: 'ordered' }, { list: 'bullet' }],
    ['link'],
    ['clean'],
  ];
}

function initQuillEdit(el) {
  const area   = el.querySelector('.quill-edit-area');
  const hidden = el.querySelector('.noticia-contenido-hidden');
  const form   = el.querySelector('.noticia-edit-form');
  if (!area || area.__ql) return;
  const q = new Quill(area, { theme: 'snow', modules: { toolbar: _quillToolbar() } });
  area.__ql = q;
  const init = hidden ? hidden.value : '';
  if (init.trim()) {
    if (init.trim().startsWith('<')) q.root.innerHTML = init;
    else q.setText(init);
  }
  if (form) {
    form.addEventListener('submit', () => { if (hidden) hidden.value = q.root.innerHTML; });
  }
}

function noticiaItem() {
  return { editando: false, qi: false };
}

function tabManager(defaultTab) {
  return {
    tab: defaultTab || 'candidatura',
    _nuevoQl: false,
    init() {
      const hash = window.location.hash.replace('#', '');
      if (hash === 'noticias' || hash === 'candidatura') this.tab = hash;
      this.$watch('tab', v => {
        if (v === 'noticias' && !this._nuevoQl) {
          this.$nextTick(() => { _initNuevoQuill('quill-nueva-cf', 'quill-nueva-cf-hidden', 'form-nueva-noticia-cf'); this._nuevoQl = true; });
        }
      });
      if (this.tab === 'noticias') {
        this.$nextTick(() => { _initNuevoQuill('quill-nueva-cf', 'quill-nueva-cf-hidden', 'form-nueva-noticia-cf'); this._nuevoQl = true; });
      }
    },
    setTab(t) {
      this.tab = t;
      history.replaceState(null, '', window.location.pathname + window.location.search + '#' + t);
    },
  };
}

function _initNuevoQuill(edId, hidId, formId) {
  const ed = document.getElementById(edId);
  if (!ed || ed.__ql) return;
  const hid  = document.getElementById(hidId);
  const form = document.getElementById(formId);
  const q = new Quill(ed, {
    theme: 'snow',
    placeholder: 'Redacta el contenido completo de la noticia...',
    modules: { toolbar: _quillToolbar() },
  });
  ed.__ql = q;
  if (form) form.addEventListener('submit', () => { if (hid) hid.value = q.root.innerHTML; });
}

function candidatoForm() {
  const iconMap = [
    { keywords: ['agricult','cacao','café','cafe','ganader','cultiv','cosech','agropecu'], icon: '🌾' },
    { keywords: ['educ','escuela','colegio','docente','aula','enseñ','aprendiz'], icon: '📚' },
    { keywords: ['salud','hospital','médic','medic','enferm','clinic','posta','saneami'], icon: '🏥' },
    { keywords: ['agua','potable','desague','alcantarill','irrigacion','riego'], icon: '💧' },
    { keywords: ['vial','carretera','camino','pista','trocha','acceso','puente','via '], icon: '🛣️' },
    { keywords: ['seguridad','serenazgo','policia','delincuencia','orden'], icon: '🛡️' },
    { keywords: ['turismo','ecoturismo','atractiv','resort','lodge'], icon: '🌿' },
    { keywords: ['comunidad','nativa','indigena','pueblo','barrio'], icon: '🏘️' },
    { keywords: ['infraestructura','obra','construc','losa','estadio','mercado'], icon: '🏗️' },
    { keywords: ['empleo','trabajo','laboral','mype','empresa','negocio','comercio'], icon: '💼' },
    { keywords: ['ambiente','ecolog','reforest','bosque','residuo','basura','recicl'], icon: '🌱' },
    { keywords: ['deporte','recreac','cancha','futbol','atletism'], icon: '⚽' },
    { keywords: ['cultura','tradici','patrimoni','identidad','arte','festival'], icon: '🎭' },
    { keywords: ['digital','tecnolog','internet','conectiv','fibra','wifi'], icon: '💻' },
    { keywords: ['mujer','género','genero','familia','violencia','igualdad'], icon: '👨‍👩‍👧' },
  ];

  return {
    propuestas: [],
    autoIcon(titulo) {
      const t = titulo.toLowerCase();
      for (const entry of iconMap) {
        if (entry.keywords.some(k => t.includes(k))) return entry.icon;
      }
      return '📋';
    },
    agregarPropuesta() {
      this.propuestas.push({ icon: '📋', titulo: '', desc: '' });
    },
    eliminarPropuesta(i) {
      this.propuestas.splice(i, 1);
    },
    updateIcon(i) {
      const suggested = this.autoIcon(this.propuestas[i].titulo);
      if (suggested !== '📋' || this.propuestas[i].icon === '📋') {
        this.propuestas[i].icon = suggested;
      }
    },
    get propuestasJson() {
      return JSON.stringify(this.propuestas);
    }
  };
}
</script>

</div><!-- fin x-data tabManager -->


    </main>
  </div>
</body>
</html>
