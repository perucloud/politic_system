<?php
// Bootstrap ANTES del layout para que header() funcione
if (ob_get_level() === 0) ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_login();
require_modulo('noticias', $pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
}

$msg      = '';
$tipo_msg = '';
$editing  = null;

// ── Eliminar ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete_category') {
    $del_id = (int)($_POST['id'] ?? 0);
    $en_uso = $pdo->prepare("SELECT COUNT(*) FROM noticias WHERE categoria = (SELECT nombre FROM categorias_noticias WHERE id=?)");
    $en_uso->execute([$del_id]);
    if ((int)$en_uso->fetchColumn() > 0) {
        $msg      = 'No se puede eliminar: hay noticias usando esta categoría.';
        $tipo_msg = 'error';
    } else {
        $pdo->prepare("DELETE FROM categorias_noticias WHERE id=?")->execute([$del_id]);
        header('Location: categorias-noticias.php?ok=1');
        exit;
    }
}

// ── Cargar para editar ────────────────────────────────────────
if (isset($_GET['edit']) && is_numeric($_GET['edit'])) {
    $r = $pdo->prepare("SELECT * FROM categorias_noticias WHERE id=?");
    $r->execute([(int)$_GET['edit']]);
    $editing = $r->fetch();
}

// ── Guardar (crear / actualizar) ──────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $cat_id  = (int)($_POST['cat_id'] ?? 0);
    $nombre  = trim($_POST['nombre'] ?? '');
    $color   = trim($_POST['color']  ?? '#1E3A8A');

    $slug_map = ['á'=>'a','é'=>'e','í'=>'i','ó'=>'o','ú'=>'u','ü'=>'u','ñ'=>'n',
                 'Á'=>'a','É'=>'e','Í'=>'i','Ó'=>'o','Ú'=>'u','Ü'=>'u','Ñ'=>'n'];
    $slug = strtr($nombre, $slug_map);
    $slug = mb_strtolower($slug, 'UTF-8');
    $slug = trim(preg_replace('/[\s-]+/', '-', preg_replace('/[^a-z0-9\s-]/', '', $slug)), '-');

    if (!$nombre) {
        $msg      = 'El nombre es obligatorio.';
        $tipo_msg = 'error';
    } else {
        try {
            if ($cat_id) {
                $pdo->prepare("UPDATE categorias_noticias SET nombre=?,slug=?,color=? WHERE id=?")
                    ->execute([$nombre, $slug, $color, $cat_id]);
            } else {
                $pdo->prepare("INSERT INTO categorias_noticias (nombre,slug,color) VALUES (?,?,?)")
                    ->execute([$nombre, $slug, $color]);
            }
            header('Location: categorias-noticias.php?ok=1');
            exit;
        } catch (Exception $e) {
            $msg      = 'Error: el nombre ya existe o hubo un problema al guardar.';
            $tipo_msg = 'error';
        }
    }
}

// ── Cargar lista ──────────────────────────────────────────────
$categorias = [];
try {
    $categorias = $pdo->query(
        "SELECT cn.*, COUNT(n.id) AS total
         FROM categorias_noticias cn
         LEFT JOIN noticias n ON n.categoria = cn.nombre
         GROUP BY cn.id ORDER BY cn.nombre ASC"
    )->fetchAll();
} catch (Exception $e) { $categorias = []; }

$page_title = 'Categorías de Noticias';
include __DIR__ . '/layout.php';
?>

<div class="flex items-center justify-between mb-6">
  <p class="text-sm text-gray-500"><?= count($categorias) ?> categorías en total</p>
  <a href="noticias.php" class="text-sm text-gray-400 hover:text-[#1E3A8A] font-medium">← Volver a Noticias</a>
</div>

<?php if (isset($_GET['ok'])): ?>
<div class="mb-4 bg-green-50 border border-green-200 text-green-700 text-sm rounded-xl px-4 py-3 font-semibold">
  Operación realizada correctamente.
</div>
<?php endif; ?>

<?php if ($msg): ?>
<div class="mb-4 rounded-xl px-4 py-3 text-sm font-semibold
            <?= $tipo_msg === 'error' ? 'bg-red-50 text-red-700 border border-red-200' : 'bg-green-50 text-green-700 border border-green-200' ?>">
  <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

  <!-- Lista -->
  <div class="lg:col-span-2">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden"
         x-data="{ delId: null, delNombre: '' }">

      <!-- Modal eliminar -->
      <div x-show="delId" x-cloak
           class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 backdrop-blur-sm"
           x-transition>
        <div class="bg-white rounded-2xl shadow-2xl max-w-sm w-full mx-4 overflow-hidden">
          <div class="bg-gradient-to-r from-red-600 to-red-700 px-6 py-4">
            <h3 class="text-white font-black text-lg">Eliminar categoría</h3>
          </div>
          <div class="p-6">
            <p class="text-gray-600 text-sm mb-4">
              ¿Eliminar <strong x-text="delNombre" class="text-gray-900"></strong>?
              Las noticias con esta categoría quedarán sin categoría asignada.
            </p>
            <div class="flex gap-3">
              <button @click="delId = null"
                      class="flex-1 border border-gray-200 text-gray-600 font-semibold py-2.5 rounded-xl text-sm hover:bg-gray-50 transition-all">
                Cancelar
              </button>
              <form method="POST" class="flex-1">
                <input type="hidden" name="action" value="delete_category">
                <input type="hidden" name="id" :value="delId">
                <button type="submit"
                        class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-2.5 rounded-xl text-sm text-center transition-all">
                  Eliminar
                </button>
              </form>
            </div>
          </div>
        </div>
      </div>

      <?php if (empty($categorias)): ?>
      <p class="text-center text-gray-400 py-12 text-sm">No hay categorías creadas aún.</p>
      <?php else: ?>
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wide">
          <tr>
            <th class="px-5 py-3 text-left">Categoría</th>
            <th class="px-5 py-3 text-left">Slug</th>
            <th class="px-5 py-3 text-center">Noticias</th>
            <th class="px-5 py-3 text-center">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <?php foreach ($categorias as $cat): ?>
          <tr class="hover:bg-gray-50 transition-colors">
            <td class="px-5 py-3">
              <div class="flex items-center gap-2.5">
                <span class="w-3 h-3 rounded-full flex-shrink-0"
                      style="background:<?= htmlspecialchars($cat['color']) ?>"></span>
                <span class="font-semibold text-gray-800"><?= htmlspecialchars($cat['nombre']) ?></span>
              </div>
            </td>
            <td class="px-5 py-3 text-gray-400 font-mono text-xs"><?= htmlspecialchars($cat['slug']) ?></td>
            <td class="px-5 py-3 text-center">
              <span class="bg-gray-100 text-gray-600 text-xs font-bold px-2.5 py-1 rounded-full">
                <?= (int)$cat['total'] ?>
              </span>
            </td>
            <td class="px-5 py-3 text-center">
              <div class="flex items-center justify-center gap-3">
                <a href="?edit=<?= $cat['id'] ?>"
                   class="text-[#1E3A8A] hover:underline text-xs font-semibold">Editar</a>
                <button @click="delId = <?= $cat['id'] ?>; delNombre = '<?= addslashes(htmlspecialchars($cat['nombre'])) ?>'"
                        class="text-red-400 hover:text-red-600 text-xs font-semibold">Eliminar</button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
      <?php endif; ?>
    </div>
  </div>

  <!-- Formulario -->
  <div>
    <div class="bg-white rounded-2xl shadow-sm border border-gray-100 p-5">
      <h2 class="font-black text-gray-800 text-sm mb-4">
        <?= $editing ? 'Editar categoría' : 'Nueva categoría' ?>
      </h2>
      <form method="POST" class="space-y-4">
        <input type="hidden" name="cat_id" value="<?= $editing ? (int)$editing['id'] : 0 ?>">

        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">Nombre *</label>
          <input type="text" name="nombre" required
                 value="<?= htmlspecialchars($editing['nombre'] ?? '') ?>"
                 placeholder="Ej: Prensa"
                 class="w-full border border-gray-200 rounded-xl px-3 py-2.5 text-sm focus:ring-2 focus:ring-[#1E3A8A] outline-none">
        </div>

        <div>
          <label class="block text-xs font-semibold text-gray-600 mb-1">Color</label>
          <div class="flex items-center gap-3">
            <input type="color" name="color"
                   value="<?= htmlspecialchars($editing['color'] ?? '#1E3A8A') ?>"
                   class="w-10 h-10 rounded-lg border border-gray-200 cursor-pointer p-0.5">
            <span class="text-xs text-gray-400">Clic para elegir color del badge</span>
          </div>
        </div>

        <div class="flex gap-2 pt-1">
          <button type="submit"
                  class="flex-1 bg-[#1E3A8A] hover:bg-blue-900 text-white font-bold py-2.5 rounded-xl text-sm transition-all">
            <?= $editing ? 'Actualizar' : 'Guardar' ?>
          </button>
          <?php if ($editing): ?>
          <a href="categorias-noticias.php"
             class="px-4 py-2.5 border border-gray-200 text-gray-500 rounded-xl text-sm font-semibold hover:bg-gray-50 transition-all">
            Cancelar
          </a>
          <?php endif; ?>
        </div>
      </form>
    </div>
  </div>

</div>

    </main>
  </div>
</body>
</html>
