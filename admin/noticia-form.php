<?php
// ── Helpers ──────────────────────────────────────────────────
function slugify_noticia(string $text): string {
    $map  = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n',
             'Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ú'=>'u','Ü'=>'u','Ñ'=>'n'];
    $text = strtr($text, $map);
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    return substr(trim(preg_replace('/[\s-]+/', '-', $text), '-'), 0, 200);
}

$page_title = isset($_GET['id']) ? 'Editar Noticia' : 'Nueva Noticia';
include __DIR__ . '/layout.php';
require_once __DIR__ . '/../includes/helpers/webp.php';
require_modulo('noticias', $pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
}

// ── Cargar categorías ────────────────────────────────────────
$cats_db = [];
try {
    $cats_db = $pdo->query("SELECT nombre, color FROM categorias_noticias ORDER BY nombre ASC")->fetchAll();
} catch (Exception $e) {
    $cats_db = [
        ['nombre'=>'General',   'color'=>'#6B7280'],
        ['nombre'=>'Campaña',   'color'=>'#1E3A8A'],
        ['nombre'=>'Actividad', 'color'=>'#059669'],
        ['nombre'=>'Propuesta', 'color'=>'#7C3AED'],
        ['nombre'=>'Logros',    'color'=>'#D97706'],
        ['nombre'=>'Prensa',    'color'=>'#DC2626'],
    ];
}

// ── Datos iniciales ──────────────────────────────────────────
$noticia = [
    'id'              => null,
    'titulo'          => '',
    'contenido'       => '',
    'imagen'          => '',
    'categoria'       => 'General',
    'estado'          => 'borrador',
    'autor_id'        => null,
    'seo_title'       => '',
    'seo_description' => '',
    'seo_keywords'    => '',
    'slug'            => '',
];
$id      = isset($_GET['id']) ? (int)$_GET['id'] : null;
$msg     = '';
$tipo_msg = '';
$autor_nombre = '';

// ── Cargar si edición ────────────────────────────────────────
if ($id) {
    try {
        $r = $pdo->prepare("SELECT n.*, u.nombre AS autor_nombre
                            FROM noticias n
                            LEFT JOIN usuarios u ON u.id = n.autor_id
                            WHERE n.id = ?");
        $r->execute([$id]);
        $found = $r->fetch();
        if ($found) {
            $noticia      = $found;
            $autor_nombre = $found['autor_nombre'] ?? '';
        }
    } catch (Exception $e) {
        // columnas nuevas aún no existen — carga sin JOIN
        try {
            $r2 = $pdo->prepare("SELECT * FROM noticias WHERE id=?");
            $r2->execute([$id]);
            $found2 = $r2->fetch();
            if ($found2) $noticia = array_merge($noticia, $found2);
        } catch (Exception $e2) {}
    }
}

// ── Guardar ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo    = trim($_POST['titulo'] ?? '');
    $contenido = $_POST['contenido'] ?? '';
    // Sanitizar: eliminar scripts y event handlers peligrosos
    $contenido = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $contenido);
    $contenido = preg_replace('/\bon\w+\s*=/i', 'data-removed=', $contenido);

    $categoria = trim($_POST['categoria'] ?? 'General');
    $estado    = in_array($_POST['estado'] ?? '', ['publicado','borrador'])
                 ? $_POST['estado'] : 'borrador';
    $seo_title = trim($_POST['seo_title'] ?? '');
    $seo_desc  = trim($_POST['seo_description'] ?? '');
    $seo_kw    = trim($_POST['seo_keywords'] ?? '');
    $slug      = trim($_POST['slug'] ?? '');
    if (!$slug) $slug = slugify_noticia($titulo);

    $imagen_actual = $noticia['imagen'] ?? '';

    // Upload imagen destacada
    if (!empty($_FILES['imagen']['tmp_name']) && $_FILES['imagen']['error'] === UPLOAD_ERR_OK) {
        $ext_i = strtolower(pathinfo($_FILES['imagen']['name'], PATHINFO_EXTENSION));
        if (in_array($ext_i, ['jpg','jpeg','png','webp']) && $_FILES['imagen']['size'] > 0) {
            $dir_img = __DIR__ . '/../assets/img/noticias/';
            if (!is_dir($dir_img)) @mkdir($dir_img, 0755, true);
            $webp_n = img_to_webp($_FILES['imagen']['tmp_name'], $ext_i);
            if ($webp_n !== false) {
                $fname = uniqid('noticia_') . '.webp';
                $bytes = $webp_n;
            } else {
                $fname = uniqid('noticia_') . '.' . $ext_i;
                $bytes = @file_get_contents($_FILES['imagen']['tmp_name']);
            }
            if ($bytes !== false && @file_put_contents($dir_img . $fname, $bytes) !== false) {
                if ($imagen_actual) @unlink($dir_img . $imagen_actual);
                $imagen_actual = $fname;
            }
        }
    }

    $autor_id = $noticia['autor_id'] ?? null;
    if (!$noticia['id']) $autor_id = (int)($_SESSION['admin_id'] ?? 0) ?: null;

    try {
        if ($noticia['id']) {
            $pdo->prepare(
                "UPDATE noticias SET titulo=?,contenido=?,imagen=?,categoria=?,estado=?,
                 seo_title=?,seo_description=?,seo_keywords=?,slug=? WHERE id=?"
            )->execute([$titulo,$contenido,$imagen_actual,$categoria,$estado,
                        $seo_title?:null,$seo_desc?:null,$seo_kw?:null,$slug?:null,$noticia['id']]);
        } else {
            $pdo->prepare(
                "INSERT INTO noticias (titulo,contenido,imagen,categoria,estado,autor_id,seo_title,seo_description,seo_keywords,slug)
                 VALUES (?,?,?,?,?,?,?,?,?,?)"
            )->execute([$titulo,$contenido,$imagen_actual,$categoria,$estado,$autor_id,
                        $seo_title?:null,$seo_desc?:null,$seo_kw?:null,$slug?:null]);
            $noticia['id'] = (int)$pdo->lastInsertId();
        }
        try {
            $r3 = $pdo->prepare("SELECT n.*, u.nombre AS autor_nombre FROM noticias n LEFT JOIN usuarios u ON u.id=n.autor_id WHERE n.id=?");
            $r3->execute([$noticia['id']]);
            $refreshed = $r3->fetch();
        } catch (Exception $e) {
            $r3 = $pdo->prepare("SELECT * FROM noticias WHERE id=?");
            $r3->execute([$noticia['id']]);
            $refreshed = $r3->fetch();
        }
        if ($refreshed) { $noticia = array_merge($noticia, $refreshed); $autor_nombre = $refreshed['autor_nombre'] ?? $autor_nombre; }
        $msg      = '¡Noticia guardada correctamente!';
        $tipo_msg = 'success';
    } catch (Exception $e) {
        $msg      = 'Error al guardar. Asegúrate de haber ejecutado migration_noticias.sql';
        $tipo_msg = 'error';
    }
}

// Autor del formulario actual
if (!$autor_nombre) {
    $autor_nombre = $_SESSION['admin_nombre'] ?? 'Admin';
}
?>

<!-- CSS del editor Quill -->
<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<style>
  .ql-container { font-size: 0.9rem; min-height: 280px; }
  .ql-editor { min-height: 280px; }
  .ql-toolbar { border-radius: 0.75rem 0.75rem 0 0 !important; border-color: #E5E7EB !important; }
  .ql-container { border-radius: 0 0 0.75rem 0.75rem !important; border-color: #E5E7EB !important; }
  .ql-editor.ql-blank::before { color: #9CA3AF; font-style: normal; }
  #char-count { transition: color 0.2s; }
  /* Cursor pointer en imágenes y videos para indicar que son seleccionables */
  .ql-editor img, .ql-editor iframe { cursor: pointer; }
  .ql-editor img:hover { outline: 1px dashed #93C5FD; }
</style>

<div class="mb-5 flex items-center justify-between">
  <a href="noticias.php" class="text-sm text-gray-400 hover:text-[#1E3A8A] flex items-center gap-1 font-medium">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
    </svg>
    Volver a Noticias
  </a>
  <?php if ($noticia['id']): ?>
  <a href="<?= BASE_URL ?>/noticias/ver.php?id=<?= $noticia['id'] ?>" target="_blank"
     class="text-sm text-[#1E3A8A] hover:underline font-medium flex items-center gap-1">
    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
    </svg>
    Ver en sitio
  </a>
  <?php endif; ?>
</div>

<?php if ($msg): ?>
<div class="mb-5 rounded-xl px-5 py-3.5 text-sm font-semibold
            <?= $tipo_msg === 'success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
  <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<form method="POST" enctype="multipart/form-data" id="noticia-form"
      class="grid grid-cols-1 lg:grid-cols-3 gap-6">

  <!-- ── Columna principal (2/3) ───────────────────────────── -->
  <div class="lg:col-span-2 space-y-5">

    <!-- Título -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
      <label class="block text-sm font-semibold text-gray-700 mb-2">Título de la noticia *</label>
      <input type="text" name="titulo" id="titulo" required
             value="<?= htmlspecialchars($noticia['titulo']) ?>"
             placeholder="Escribe un título claro y descriptivo..."
             class="w-full border border-gray-200 rounded-xl px-4 py-3 text-base font-semibold text-gray-800 focus:ring-2 focus:ring-[#1E3A8A] outline-none transition-all">
    </div>

    <!-- Editor de contenido -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
      <div class="flex items-center justify-between mb-2">
        <label class="text-sm font-semibold text-gray-700">Contenido</label>
        <div class="flex items-center gap-3">
          <button type="button"
                  onclick="insertMediaImageInEditor()"
                  class="inline-flex items-center gap-1.5 text-xs font-semibold text-indigo-600 bg-indigo-50 hover:bg-indigo-100 px-3 py-1.5 rounded-lg transition-all border border-indigo-200">
            <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
              <path stroke-linecap="round" stroke-linejoin="round" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            Insertar desde Biblioteca
          </button>
          <span id="char-count" class="text-xs text-gray-400">0 caracteres</span>
        </div>
      </div>
      <!-- Editor visible -->
      <div id="quill-editor"></div>
      <!-- Campo oculto que se envía con el form -->
      <input type="hidden" name="contenido" id="contenido-hidden"
             value="<?= htmlspecialchars($noticia['contenido']) ?>">
    </div>

    <!-- Imagen destacada -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
      <label class="block text-sm font-semibold text-gray-700 mb-3">Imagen destacada</label>
      <div class="flex items-start gap-4 flex-wrap">
        <?php if (!empty($noticia['imagen'])): ?>
        <div class="relative group">
          <img src="<?= BASE_URL ?>/assets/img/noticias/<?= htmlspecialchars($noticia['imagen']) ?>"
               class="w-32 h-20 object-cover rounded-xl border border-gray-200"
               onerror="this.style.display='none'">
          <span class="absolute -top-1 -right-1 bg-green-500 text-white text-[9px] font-bold px-1.5 py-0.5 rounded-full">actual</span>
        </div>
        <?php endif; ?>
        <div>
          <input type="file" name="imagen" id="imagen-input" accept="image/jpeg,image/png,image/webp"
                 class="hidden">
          <label for="imagen-input"
                 class="inline-flex items-center gap-2 cursor-pointer border-2 border-dashed border-gray-300 hover:border-[#1E3A8A] text-gray-500 hover:text-[#1E3A8A] font-semibold text-sm px-5 py-3 rounded-xl transition-all">
            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
              <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/>
            </svg>
            <?= !empty($noticia['imagen']) ? 'Cambiar imagen' : 'Subir imagen' ?>
          </label>
          <p class="text-xs text-gray-400 mt-2">JPG, PNG o WebP — máx. 5 MB</p>
          <p id="img-preview-name" class="text-xs text-[#1E3A8A] font-semibold mt-1 hidden"></p>
        </div>
      </div>
    </div>

  </div>

  <!-- ── Sidebar derecho (1/3) ─────────────────────────────── -->
  <div class="space-y-5" x-data="{ seo: <?= (!empty($noticia['seo_title']) || !empty($noticia['seo_description'])) ? 'true' : 'false' ?> }">

    <!-- Publicar -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
      <h3 class="text-sm font-black text-gray-800 mb-4">Publicar</h3>

      <!-- Estado -->
      <div class="mb-4">
        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Estado</label>
        <select name="estado" id="estado-select"
                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-[#1E3A8A] outline-none">
          <option value="borrador"  <?= ($noticia['estado'] === 'borrador')  ? 'selected' : '' ?>>📝 Borrador</option>
          <option value="publicado" <?= ($noticia['estado'] === 'publicado') ? 'selected' : '' ?>>🌐 Publicado</option>
        </select>
      </div>

      <!-- Categoría -->
      <div class="mb-4">
        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Categoría</label>
        <select name="categoria"
                class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-[#1E3A8A] outline-none">
          <?php foreach ($cats_db as $cat): ?>
          <option value="<?= htmlspecialchars($cat['nombre']) ?>"
                  <?= ($noticia['categoria'] === $cat['nombre']) ? 'selected' : '' ?>>
            <?= htmlspecialchars($cat['nombre']) ?>
          </option>
          <?php endforeach; ?>
        </select>
        <a href="categorias-noticias.php" class="text-[10px] text-[#1E3A8A] hover:underline mt-1 block">
          + Gestionar categorías
        </a>
      </div>

      <!-- Autor -->
      <div class="mb-5 p-3 bg-gray-50 rounded-xl flex items-center gap-2.5">
        <div class="w-8 h-8 bg-gradient-to-br from-[#38BDF8] to-[#1E3A8A] rounded-full flex items-center justify-center flex-shrink-0">
          <span class="text-white text-xs font-black"><?= strtoupper(substr($autor_nombre, 0, 1)) ?></span>
        </div>
        <div class="min-w-0">
          <p class="text-xs font-semibold text-gray-700 truncate"><?= htmlspecialchars($autor_nombre) ?></p>
          <p class="text-[10px] text-gray-400">Autor de esta nota</p>
        </div>
      </div>

      <!-- Botón guardar -->
      <button type="submit"
              class="w-full bg-[#FACC15] hover:bg-yellow-400 text-[#1E3A8A] font-black py-3 rounded-xl text-sm transition-all shadow">
        <?= $noticia['id'] ? '💾 Actualizar noticia' : '🚀 Publicar noticia' ?>
      </button>
    </div>

    <!-- Slug -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
      <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">URL (slug)</label>
      <div class="flex items-center gap-2">
        <span class="text-[10px] text-gray-400 whitespace-nowrap">/noticias/ver.php?id=...</span>
      </div>
      <input type="text" name="slug" id="slug-input"
             value="<?= htmlspecialchars($noticia['slug'] ?? '') ?>"
             placeholder="se-genera-automaticamente"
             class="mt-2 w-full border border-gray-200 rounded-xl px-3 py-2 text-xs font-mono text-gray-600 focus:ring-2 focus:ring-[#1E3A8A] outline-none">
      <p class="text-[10px] text-gray-400 mt-1">Se genera del título si se deja vacío</p>
    </div>

    <!-- SEO (colapsable) -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
      <button type="button" @click="seo = !seo"
              class="flex items-center justify-between w-full text-left">
        <span class="text-sm font-black text-gray-800 flex items-center gap-2">
          <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
          </svg>
          SEO
        </span>
        <svg :class="seo ? 'rotate-180' : ''" class="w-4 h-4 text-gray-400 transition-transform"
             fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
      </button>

      <div x-show="seo" x-transition class="mt-4 space-y-3">
        <div>
          <label class="block text-xs font-semibold text-gray-500 mb-1">Meta título
            <span class="font-normal text-gray-400" id="seo-title-count"></span>
          </label>
          <input type="text" name="seo_title" id="seo-title-input" maxlength="100"
                 value="<?= htmlspecialchars($noticia['seo_title'] ?? '') ?>"
                 placeholder="Título para buscadores (máx. 60 car.)"
                 class="w-full border border-gray-200 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-[#1E3A8A] outline-none">
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-500 mb-1">Meta descripción
            <span class="font-normal text-gray-400" id="seo-desc-count"></span>
          </label>
          <textarea name="seo_description" id="seo-desc-input" rows="3" maxlength="200"
                    placeholder="Descripción para buscadores (máx. 160 car.)"
                    class="w-full border border-gray-200 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-[#1E3A8A] outline-none resize-none"
                    ><?= htmlspecialchars($noticia['seo_description'] ?? '') ?></textarea>
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-500 mb-1">Palabras clave</label>
          <input type="text" name="seo_keywords"
                 value="<?= htmlspecialchars($noticia['seo_keywords'] ?? '') ?>"
                 placeholder="Ej: alcalde, satipo, gestión"
                 class="w-full border border-gray-200 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-[#1E3A8A] outline-none">
        </div>
      </div>
    </div>

  </div>
</form>

<!-- Quill JS -->
<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script>
(function () {
  // ── Inicializar Quill ──────────────────────────────────────
  const quill = window.quillEditor = new Quill('#quill-editor', {
    theme: 'snow',
    placeholder: 'Redacta el contenido de la noticia. Puedes insertar texto con formato, imágenes y videos...',
    modules: {
      toolbar: {
        container: [
          [{ header: [1, 2, 3, false] }],
          ['bold', 'italic', 'underline', 'strike'],
          [{ color: [] }, { background: [] }],
          [{ list: 'ordered' }, { list: 'bullet' }],
          ['blockquote'],
          ['link', 'image', 'video'],
          ['clean']
        ],
        handlers: {
          image: imageUploadHandler,
          video: videoEmbedHandler
        }
      }
    }
  });

  window.insertMediaImageInEditor = function () {
    if (!window.openMediaPicker || !window.quillEditor) return;
    window.openMediaPicker(function (url) {
      const editor = window.quillEditor;
      const range = editor.getSelection() || { index: editor.getLength() };
      editor.insertEmbed(range.index, 'image', url, Quill.sources.USER);
      editor.setSelection(range.index + 1);
    }, 'image');
  };

  // ── Cargar contenido inicial (edición) ────────────────────
  const initialContent = <?= json_encode($noticia['contenido'] ?? '') ?>;
  if (initialContent && initialContent.trim()) {
    if (initialContent.trim().startsWith('<')) {
      quill.root.innerHTML = initialContent;
    } else {
      quill.setText(initialContent);
    }
  }

  // ── Contador de caracteres ─────────────────────────────────
  const charCount = document.getElementById('char-count');
  quill.on('text-change', function () {
    const len = quill.getLength() - 1;
    charCount.textContent = len + ' caracteres';
    charCount.style.color = len > 5000 ? '#EF4444' : '#9CA3AF';
  });

  // ── Sincronizar con hidden input al enviar ────────────────
  document.getElementById('noticia-form').addEventListener('submit', function () {
    document.getElementById('contenido-hidden').value = quill.root.innerHTML;
  });

  // ── Handler: subir imagen al servidor ────────────────────
  function imageUploadHandler() {
    const input = document.createElement('input');
    input.type  = 'file';
    input.accept = 'image/jpeg,image/png,image/webp,image/gif';
    input.click();

    input.onchange = async function () {
      const file = input.files[0];
      if (!file) return;
      if (file.size > 6 * 1024 * 1024) {
        alert('La imagen supera 6 MB.');
        return;
      }
      const formData = new FormData();
      formData.append('file', file);
      try {
        const resp = await fetch('upload-img-editor.php', {
          method: 'POST',
          headers: { 'X-CSRF-Token': window.CSRF_TOKEN || '' },
          body: formData
        });
        const data = await resp.json();
        if (data.url) {
          const range = quill.getSelection() || { index: quill.getLength() };
          quill.insertEmbed(range.index, 'image', data.url, Quill.sources.USER);
          quill.setSelection(range.index + 1);
        } else {
          alert('Error al subir imagen: ' + (data.error || 'desconocido'));
        }
      } catch (err) {
        alert('Error de conexión al subir la imagen.');
      }
    };
  }

  // ── Handler: insertar video (YouTube / Facebook) ──────────
  function videoEmbedHandler() {
    const url = prompt('Ingresa el link del video:\n(YouTube o Facebook)');
    if (!url || !url.trim()) return;

    let embedUrl = url.trim();

    // Convertir YouTube watch → embed
    const ytWatch  = embedUrl.match(/youtube\.com\/watch\?(?:.*&)?v=([^&\s]+)/);
    const ytShort  = embedUrl.match(/youtu\.be\/([^?&\s]+)/);
    const ytEmbed  = embedUrl.match(/youtube\.com\/embed\/([^?&\s]+)/);
    if (ytWatch) {
      embedUrl = 'https://www.youtube.com/embed/' + ytWatch[1];
    } else if (ytShort) {
      embedUrl = 'https://www.youtube.com/embed/' + ytShort[1];
    } else if (!ytEmbed) {
      // Facebook u otro: intentar embed directo (funciona si es URL pública de video fb)
      if (embedUrl.includes('facebook.com')) {
        embedUrl = 'https://www.facebook.com/plugins/video.php?href=' + encodeURIComponent(embedUrl) + '&show_text=0&width=560';
      }
    }

    const range = quill.getSelection() || { index: quill.getLength() };
    quill.insertEmbed(range.index, 'video', embedUrl, Quill.sources.USER);
    quill.setSelection(range.index + 1);

    // Aplicar tamaño predeterminado al iframe recién insertado
    setTimeout(function () {
      const iframes = quill.root.querySelectorAll('iframe');
      const last    = iframes[iframes.length - 1];
      if (last && !last.style.width) {
        last.style.width  = '600px';
        last.style.height = '338px'; // 600 * 9/16
      }
    }, 50);
  }

  // ── Resize de imágenes y videos ───────────────────────────
  initMediaResize(quill);

  // ── Auto-slug desde título ─────────────────────────────────
  const tituloInput = document.getElementById('titulo');
  const slugInput   = document.getElementById('slug-input');
  let slugManual    = slugInput.value.trim() !== '';

  slugInput.addEventListener('input', () => { slugManual = slugInput.value.trim() !== ''; });

  tituloInput.addEventListener('input', function () {
    if (slugManual) return;
    const slug = this.value
      .toLowerCase()
      .normalize('NFD').replace(/[̀-ͯ]/g, '')
      .replace(/[^a-z0-9\s-]/g, '')
      .replace(/[\s-]+/g, '-')
      .replace(/^-+|-+$/g, '')
      .substring(0, 100);
    slugInput.value = slug;
  });

  // ── Preview nombre imagen ─────────────────────────────────
  document.getElementById('imagen-input').addEventListener('change', function () {
    const el = document.getElementById('img-preview-name');
    if (this.files[0]) {
      el.textContent = '✓ ' + this.files[0].name;
      el.classList.remove('hidden');
    }
  });

  // ── Contadores SEO ─────────────────────────────────────────
  function seoCounter(inputId, counterId, warn) {
    const input = document.getElementById(inputId);
    const span  = document.getElementById(counterId);
    if (!input || !span) return;
    const update = () => {
      const len = input.value.length;
      span.textContent = ' (' + len + '/' + warn + ')';
      span.style.color = len > warn ? '#EF4444' : '#9CA3AF';
    };
    input.addEventListener('input', update);
    update();
  }
  seoCounter('seo-title-input', 'seo-title-count', 60);
  seoCounter('seo-desc-input',  'seo-desc-count',  160);

  // ── initMediaResize: resize drag para imágenes + presets para videos ──
  function initMediaResize(quill) {
    const editorEl = quill.root;
    let activeEl   = null;   // img o iframe activo
    let activeType = null;   // 'image' | 'video'
    let overlay    = null;   // div fijo con handles / toolbar
    let startX, startW, startH, aspectRatio;

    // ── Detectar clic en imagen o video ─────────────────────
    editorEl.addEventListener('click', function (e) {
      const img = e.target.tagName === 'IMG'    ? e.target : null;
      const vid = e.target.tagName === 'IFRAME' ? e.target : null;
      if (img) { e.preventDefault(); activate(img, 'image'); }
      else if (vid) { e.preventDefault(); activate(vid, 'video'); }
      else if (!e.target.closest('#mr-overlay')) { deactivate(); }
    });

    document.addEventListener('keydown', function (e) {
      if (e.key === 'Escape') deactivate();
    });

    document.addEventListener('click', function (e) {
      if (!editorEl.contains(e.target) && !e.target.closest('#mr-overlay')) deactivate();
    }, true);

    // ── Activar elemento ────────────────────────────────────
    function activate(el, type) {
      deactivate();
      activeEl   = el;
      activeType = type;
      el.style.outline       = '2px solid #2563EB';
      el.style.outlineOffset = '2px';
      el.style.cursor        = 'default';

      overlay = document.createElement('div');
      overlay.id = 'mr-overlay';
      overlay.style.cssText = 'position:fixed;z-index:9999;pointer-events:none;';
      document.body.appendChild(overlay);

      positionOverlay();
      if (type === 'image') buildImageUI();
      else                  buildVideoUI();
    }

    // ── Desactivar ──────────────────────────────────────────
    function deactivate() {
      if (activeEl) {
        activeEl.style.outline       = '';
        activeEl.style.outlineOffset = '';
        activeEl.style.cursor        = '';
        activeEl = null;
        activeType = null;
      }
      if (overlay) { overlay.remove(); overlay = null; }
    }

    // ── Posicionar overlay sobre el elemento ─────────────────
    function positionOverlay() {
      if (!activeEl || !overlay) return;
      const r = activeEl.getBoundingClientRect();
      overlay.style.left   = r.left   + 'px';
      overlay.style.top    = r.top    + 'px';
      overlay.style.width  = r.width  + 'px';
      overlay.style.height = r.height + 'px';
    }

    // ── UI de imagen: 4 handles de esquina + toolbar de presets ──
    function buildImageUI() {
      // Toolbar de presets arriba
      buildPresetBar(['25%', '50%', '75%', '100%', 'Original'], function (size) {
        const edW = editorEl.offsetWidth;
        if (size === 'Original') {
          activeEl.style.width  = '';
          activeEl.style.height = '';
        } else {
          activeEl.style.width  = Math.round(edW * parseInt(size) / 100) + 'px';
          activeEl.style.height = 'auto';
        }
        deactivate();
      });

      // 4 esquinas
      [
        { top: '-5px',  left:  '-5px',  cursor: 'nw-resize', dx:  1, dy:  1 },
        { top: '-5px',  right: '-5px',  cursor: 'ne-resize', dx: -1, dy:  1 },
        { bottom:'-5px',left:  '-5px',  cursor: 'sw-resize', dx:  1, dy: -1 },
        { bottom:'-5px',right: '-5px',  cursor: 'se-resize', dx: -1, dy: -1 },
      ].forEach(function (cfg) {
        const h = document.createElement('div');
        h.style.cssText = [
          'position:absolute',
          'width:10px', 'height:10px',
          'background:#2563EB',
          'border:2px solid white',
          'border-radius:2px',
          'pointer-events:auto',
          'cursor:' + cfg.cursor,
          cfg.top    ? 'top:'   + cfg.top    : '',
          cfg.bottom ? 'bottom:'+ cfg.bottom : '',
          cfg.left   ? 'left:'  + cfg.left   : '',
          cfg.right  ? 'right:' + cfg.right  : '',
        ].filter(Boolean).join(';');

        h.addEventListener('mousedown', function (e) {
          e.preventDefault();
          e.stopPropagation();
          startX      = e.clientX;
          startW      = activeEl.offsetWidth;
          startH      = activeEl.offsetHeight;
          aspectRatio = startH / startW;
          const signX = cfg.dx; // +1 = handle en izquierda (invierte), -1 = derecha (normal)

          function onMove(e) {
            const delta = (e.clientX - startX) * (signX === 1 ? -1 : 1);
            const newW  = Math.max(50, startW + delta);
            activeEl.style.width  = newW + 'px';
            activeEl.style.height = Math.round(newW * aspectRatio) + 'px';
            positionOverlay();
          }
          function onUp() {
            document.removeEventListener('mousemove', onMove);
            document.removeEventListener('mouseup',   onUp);
            positionOverlay();
          }
          document.addEventListener('mousemove', onMove);
          document.addEventListener('mouseup',   onUp);
        });

        overlay.appendChild(h);
      });

      // Handle central derecho (ancho libre sin bloquear el ratio)
      const hRight = document.createElement('div');
      hRight.title = 'Arrastrar para redimensionar';
      hRight.style.cssText = [
        'position:absolute', 'right:-5px', 'top:50%',
        'transform:translateY(-50%)',
        'width:10px', 'height:22px',
        'background:#2563EB', 'border:2px solid white',
        'border-radius:3px', 'pointer-events:auto',
        'cursor:ew-resize',
      ].join(';');

      hRight.addEventListener('mousedown', function (e) {
        e.preventDefault();
        e.stopPropagation();
        startX = e.clientX;
        startW = activeEl.offsetWidth;
        function onMove(e) {
          const newW = Math.max(50, startW + (e.clientX - startX));
          activeEl.style.width  = newW + 'px';
          activeEl.style.height = 'auto';
          positionOverlay();
        }
        function onUp() {
          document.removeEventListener('mousemove', onMove);
          document.removeEventListener('mouseup',   onUp);
        }
        document.addEventListener('mousemove', onMove);
        document.addEventListener('mouseup',   onUp);
      });
      overlay.appendChild(hRight);
    }

    // ── UI de video: solo presets de ancho ───────────────────
    function buildVideoUI() {
      buildPresetBar(['50%', '75%', '100%'], function (size) {
        const edW  = editorEl.offsetWidth;
        const newW = Math.round(edW * parseInt(size) / 100);
        activeEl.style.width  = newW + 'px';
        activeEl.style.height = Math.round(newW * 9 / 16) + 'px';
        deactivate();
      });
    }

    // ── Construir barra de presets flotante ──────────────────
    function buildPresetBar(sizes, onSelect) {
      const bar = document.createElement('div');
      bar.style.cssText = [
        'position:absolute', 'bottom:calc(100% + 8px)', 'left:50%',
        'transform:translateX(-50%)',
        'background:#1E3A8A',
        'border-radius:8px', 'padding:4px 6px',
        'display:flex', 'gap:4px', 'align-items:center',
        'white-space:nowrap',
        'box-shadow:0 4px 12px rgba(0,0,0,0.25)',
        'pointer-events:auto',
      ].join(';');

      // Etiqueta
      const lbl = document.createElement('span');
      lbl.textContent = activeType === 'video' ? '📹' : '🖼';
      lbl.style.cssText = 'color:rgba(255,255,255,0.6);font-size:12px;padding-right:2px;';
      bar.appendChild(lbl);

      sizes.forEach(function (size) {
        const btn = document.createElement('button');
        btn.type        = 'button';
        btn.textContent = size;
        btn.style.cssText = [
          'background:rgba(255,255,255,0.15)',
          'color:white', 'border:none',
          'border-radius:5px', 'padding:3px 9px',
          'font-size:11px', 'font-weight:700',
          'cursor:pointer',
        ].join(';');
        btn.onmouseover = () => btn.style.background = 'rgba(255,255,255,0.3)';
        btn.onmouseout  = () => btn.style.background = 'rgba(255,255,255,0.15)';
        btn.addEventListener('click', function (e) {
          e.stopPropagation();
          onSelect(size);
        });
        bar.appendChild(btn);
      });

      overlay.appendChild(bar);
    }
  }

})();
</script>

    </main>
  </div>

<?php include __DIR__ . '/_media-picker.php'; ?>
</body>
</html>
