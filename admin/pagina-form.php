<?php
if (ob_get_level() === 0) ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_login();
require_rol('editor');
require_modulo('paginas', $pdo);

function slugify_pagina(string $text): string {
    $map  = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n',
             'Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ú'=>'u','Ü'=>'u','Ñ'=>'n'];
    $text = strtr($text, $map);
    $text = mb_strtolower($text, 'UTF-8');
    $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
    return substr(trim(preg_replace('/[\s-]+/', '-', $text), '-'), 0, 200);
}

$page_title = isset($_GET['id']) ? 'Editar Página' : 'Nueva Página';
include __DIR__ . '/layout.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
}

$pagina = ['id'=>null,'titulo'=>'','contenido'=>'','slug'=>'','estado'=>'borrador',
           'seo_title'=>'','seo_description'=>'','seo_keywords'=>''];
$id       = isset($_GET['id']) ? (int)$_GET['id'] : null;
$msg      = '';
$tipo_msg = '';
$autor_nombre = $_SESSION['admin_nombre'] ?? 'Admin';

// ── Cargar si edición ────────────────────────────────────────
if ($id) {
    try {
        $r = $pdo->prepare("SELECT p.*, u.nombre AS autor_nombre FROM paginas p LEFT JOIN usuarios u ON u.id=p.autor_id WHERE p.id=?");
        $r->execute([$id]);
        $found = $r->fetch();
        if ($found) { $pagina = $found; $autor_nombre = $found['autor_nombre'] ?? $autor_nombre; }
    } catch (Exception $e) {
        try {
            $r2 = $pdo->prepare("SELECT * FROM paginas WHERE id=?");
            $r2->execute([$id]);
            $found2 = $r2->fetch();
            if ($found2) $pagina = array_merge($pagina, $found2);
        } catch (Exception $e2) {}
    }
}

// ── Guardar ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $titulo    = trim($_POST['titulo']    ?? '');
    $contenido = $_POST['contenido']      ?? '';
    $contenido = preg_replace('/<script\b[^>]*>.*?<\/script>/is', '', $contenido);
    $estado    = in_array($_POST['estado'] ?? '', ['publicado','borrador']) ? $_POST['estado'] : 'borrador';
    $seo_title = trim($_POST['seo_title']       ?? '');
    $seo_desc  = trim($_POST['seo_description'] ?? '');
    $seo_kw    = trim($_POST['seo_keywords']    ?? '');
    $slug      = trim($_POST['slug'] ?? '');
    if (!$slug) $slug = slugify_pagina($titulo);

    $autor_id = $pagina['autor_id'] ?? null;
    if (!$pagina['id']) $autor_id = (int)($_SESSION['admin_id'] ?? 0) ?: null;

    try {
        if ($pagina['id']) {
            $pdo->prepare("UPDATE paginas SET titulo=?,contenido=?,slug=?,estado=?,seo_title=?,seo_description=?,seo_keywords=?,actualizado=NOW() WHERE id=?")
                ->execute([$titulo,$contenido,$slug,$estado,$seo_title?:null,$seo_desc?:null,$seo_kw?:null,$pagina['id']]);
        } else {
            $pdo->prepare("INSERT INTO paginas (titulo,contenido,slug,estado,autor_id,seo_title,seo_description,seo_keywords) VALUES (?,?,?,?,?,?,?,?)")
                ->execute([$titulo,$contenido,$slug,$estado,$autor_id,$seo_title?:null,$seo_desc?:null,$seo_kw?:null]);
            $pagina['id'] = (int)$pdo->lastInsertId();
        }
        try {
            $r3 = $pdo->prepare("SELECT p.*, u.nombre AS autor_nombre FROM paginas p LEFT JOIN usuarios u ON u.id=p.autor_id WHERE p.id=?");
            $r3->execute([$pagina['id']]);
            $refreshed = $r3->fetch();
        } catch (Exception $e) { $refreshed = null; }
        if ($refreshed) { $pagina = array_merge($pagina, $refreshed); $autor_nombre = $refreshed['autor_nombre'] ?? $autor_nombre; }
        $msg = '¡Página guardada correctamente!'; $tipo_msg = 'success';
    } catch (Exception $e) {
        $msg = 'Error al guardar. Verifica que el slug no esté duplicado.'; $tipo_msg = 'error';
    }
}
?>

<link href="https://cdn.quilljs.com/1.3.7/quill.snow.css" rel="stylesheet">
<style>
  .ql-container { font-size:.9rem; min-height:280px; }
  .ql-editor { min-height:280px; }
  .ql-toolbar { border-radius:.75rem .75rem 0 0 !important; border-color:#E5E7EB !important; }
  .ql-container { border-radius:0 0 .75rem .75rem !important; border-color:#E5E7EB !important; }
  .ql-editor.ql-blank::before { color:#9CA3AF; font-style:normal; }
</style>

<div class="mb-5 flex items-center justify-between">
  <a href="paginas.php" class="text-sm text-gray-400 hover:text-[#1E3A8A] flex items-center gap-1 font-medium">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
      <path stroke-linecap="round" stroke-linejoin="round" d="M10 19l-7-7m0 0l7-7m-7 7h18"/>
    </svg>
    Volver a Páginas
  </a>
  <?php if ($pagina['id'] && ($pagina['estado']==='publicado')): ?>
  <a href="<?= BASE_URL ?>/pagina.php?slug=<?= urlencode($pagina['slug']) ?>" target="_blank"
     class="text-sm text-[#1E3A8A] hover:underline font-medium flex items-center gap-1">
    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
      <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
    </svg>
    Ver página
  </a>
  <?php endif; ?>
</div>

<?php if ($msg): ?>
<div class="mb-5 rounded-xl px-5 py-3.5 text-sm font-semibold
            <?= $tipo_msg==='success' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' ?>">
  <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<form method="POST" id="pagina-form" class="grid grid-cols-1 lg:grid-cols-3 gap-6">

  <!-- ── Columna principal ── -->
  <div class="lg:col-span-2 space-y-5">

    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
      <label class="block text-sm font-semibold text-gray-700 mb-2">Título de la página *</label>
      <input type="text" name="titulo" id="titulo-input" required
             value="<?= htmlspecialchars($pagina['titulo']) ?>"
             placeholder="Escribe el título de la página..."
             class="w-full border border-gray-200 rounded-xl px-4 py-3 text-base font-semibold text-gray-800 focus:ring-2 focus:ring-[#1E3A8A] outline-none transition-all">
    </div>

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
      <div id="quill-editor"></div>
      <input type="hidden" name="contenido" id="contenido-hidden" value="<?= htmlspecialchars($pagina['contenido']) ?>">
    </div>

  </div>

  <!-- ── Sidebar ── -->
  <div class="space-y-5" x-data="{ seo: <?= (!empty($pagina['seo_title']) || !empty($pagina['seo_description'])) ? 'true' : 'false' ?> }">

    <!-- Publicar -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
      <h3 class="text-sm font-black text-gray-800 mb-4">Publicar</h3>

      <div class="mb-4">
        <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">Estado</label>
        <select name="estado" class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm font-semibold focus:ring-2 focus:ring-[#1E3A8A] outline-none">
          <option value="borrador"  <?= $pagina['estado']==='borrador'  ? 'selected' : '' ?>>📝 Borrador</option>
          <option value="publicado" <?= $pagina['estado']==='publicado' ? 'selected' : '' ?>>🌐 Publicado</option>
        </select>
      </div>

      <!-- Autor -->
      <div class="mb-5 p-3 bg-gray-50 rounded-xl flex items-center gap-2.5">
        <div class="w-8 h-8 bg-gradient-to-br from-[#38BDF8] to-[#1E3A8A] rounded-full flex items-center justify-center flex-shrink-0">
          <span class="text-white text-xs font-black"><?= strtoupper(substr($autor_nombre, 0, 1)) ?></span>
        </div>
        <div class="min-w-0">
          <p class="text-xs font-semibold text-gray-700 truncate"><?= htmlspecialchars($autor_nombre) ?></p>
          <p class="text-[10px] text-gray-400">Autor de esta página</p>
        </div>
      </div>

      <button type="submit"
              class="w-full bg-[#FACC15] hover:bg-yellow-400 text-[#1E3A8A] font-black py-3 rounded-xl text-sm transition-all shadow">
        <?= $pagina['id'] ? '💾 Actualizar página' : '🚀 Guardar página' ?>
      </button>
    </div>

    <!-- Slug / URL -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
      <label class="block text-xs font-semibold text-gray-500 uppercase tracking-wide mb-1.5">URL de la página</label>
      <p class="text-[10px] text-gray-400 mb-2 font-mono">/pagina.php?slug=</p>
      <input type="text" name="slug" id="slug-input"
             value="<?= htmlspecialchars($pagina['slug'] ?? '') ?>"
             placeholder="se-genera-del-titulo"
             class="w-full border border-gray-200 rounded-xl px-3 py-2 text-xs font-mono text-gray-600 focus:ring-2 focus:ring-[#1E3A8A] outline-none">
      <p class="text-[10px] text-gray-400 mt-1">Se genera automáticamente del título</p>
    </div>

    <!-- SEO -->
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
      <button type="button" @click="seo=!seo" class="flex items-center justify-between w-full text-left">
        <span class="text-sm font-black text-gray-800 flex items-center gap-2">
          <svg class="w-4 h-4 text-green-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/>
          </svg>
          SEO
        </span>
        <svg :class="seo?'rotate-180':''" class="w-4 h-4 text-gray-400 transition-transform" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
        </svg>
      </button>
      <div x-show="seo" x-transition class="mt-4 space-y-3">
        <div>
          <label class="block text-xs font-semibold text-gray-500 mb-1">Meta título</label>
          <input type="text" name="seo_title" maxlength="100"
                 value="<?= htmlspecialchars($pagina['seo_title'] ?? '') ?>"
                 placeholder="Título para buscadores (máx. 60)"
                 class="w-full border border-gray-200 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-[#1E3A8A] outline-none">
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-500 mb-1">Meta descripción</label>
          <textarea name="seo_description" rows="3" maxlength="200"
                    placeholder="Descripción para buscadores (máx. 160)"
                    class="w-full border border-gray-200 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-[#1E3A8A] outline-none resize-none"
                    ><?= htmlspecialchars($pagina['seo_description'] ?? '') ?></textarea>
        </div>
        <div>
          <label class="block text-xs font-semibold text-gray-500 mb-1">Palabras clave</label>
          <input type="text" name="seo_keywords"
                 value="<?= htmlspecialchars($pagina['seo_keywords'] ?? '') ?>"
                 placeholder="Ej: satipo, joyer, gobierno"
                 class="w-full border border-gray-200 rounded-xl px-3 py-2 text-xs focus:ring-2 focus:ring-[#1E3A8A] outline-none">
        </div>
      </div>
    </div>

  </div>
</form>

<script src="https://cdn.quilljs.com/1.3.7/quill.min.js"></script>
<script>
(function(){
  const quill = window.quillEditor = new Quill('#quill-editor', {
    theme: 'snow',
    placeholder: 'Escribe el contenido de la página...',
    modules: {
      toolbar: {
        container: [
          [{ header: [1,2,3,false] }],
          ['bold','italic','underline','strike'],
          [{ color:[] },{ background:[] }],
          [{ list:'ordered' },{ list:'bullet' }],
          ['blockquote'],
          ['link','image','video'],
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

  const init = <?= json_encode($pagina['contenido'] ?? '') ?>;
  if (init && init.trim()) {
    if (init.trim().startsWith('<')) quill.root.innerHTML = init;
    else quill.setText(init);
  }

  const charCount = document.getElementById('char-count');
  quill.on('text-change', () => {
    const len = quill.getLength() - 1;
    charCount.textContent = len + ' caracteres';
    charCount.style.color = len > 5000 ? '#EF4444' : '#9CA3AF';
  });

  document.getElementById('pagina-form').addEventListener('submit', () => {
    document.getElementById('contenido-hidden').value = quill.root.innerHTML;
  });

  function imageUploadHandler() {
    const input = document.createElement('input');
    input.type = 'file'; input.accept = 'image/jpeg,image/png,image/webp,image/gif'; input.click();
    input.onchange = async () => {
      const file = input.files[0]; if (!file) return;
      const fd = new FormData(); fd.append('file', file);
      try {
        const r = await fetch('upload-img-editor.php', {
          method:'POST',
          headers: { 'X-CSRF-Token': window.CSRF_TOKEN || '' },
          body:fd
        });
        const d = await r.json();
        if (d.url) { const rng = quill.getSelection() || { index: quill.getLength() }; quill.insertEmbed(rng.index, 'image', d.url, Quill.sources.USER); }
      } catch(e) { alert('Error al subir imagen'); }
    };
  }

  function videoEmbedHandler() {
    const url = prompt('Link del video (YouTube o Facebook):');
    if (!url) return;
    let embed = url.trim();
    const yt = embed.match(/youtube\.com\/watch\?(?:.*&)?v=([^&\s]+)/);
    const ys = embed.match(/youtu\.be\/([^?&\s]+)/);
    if (yt) embed = 'https://www.youtube.com/embed/' + yt[1];
    else if (ys) embed = 'https://www.youtube.com/embed/' + ys[1];
    const rng = quill.getSelection() || { index: quill.getLength() };
    quill.insertEmbed(rng.index, 'video', embed, Quill.sources.USER);
    setTimeout(() => {
      const iframes = quill.root.querySelectorAll('iframe');
      const last = iframes[iframes.length-1];
      if (last && !last.style.width) { last.style.width='600px'; last.style.height='338px'; }
    }, 50);
  }

  // Auto-slug
  const tituloInput = document.getElementById('titulo-input');
  const slugInput   = document.getElementById('slug-input');
  let slugManual    = slugInput.value.trim() !== '';
  slugInput.addEventListener('input', () => { slugManual = slugInput.value.trim() !== ''; });
  tituloInput.addEventListener('input', function() {
    if (slugManual) return;
    slugInput.value = this.value.toLowerCase()
      .normalize('NFD').replace(/[̀-ͯ]/g,'')
      .replace(/[^a-z0-9\s-]/g,'').replace(/[\s-]+/g,'-').replace(/^-+|-+$/g,'').substring(0,100);
  });
})();
</script>

    </main>
  </div>
<?php include __DIR__ . '/_media-picker.php'; ?>
</body>
</html>
