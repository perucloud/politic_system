<?php
if (ob_get_level() === 0) ob_start();
if (session_status() === PHP_SESSION_NONE) session_start();
require_once __DIR__ . '/../includes/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_login();
require_rol('editor');
require_modulo('paginas', $pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
}

// ── Eliminar ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    try {
        $pdo->prepare("DELETE FROM paginas WHERE id=?")->execute([(int)($_POST['id'] ?? 0)]);
    } catch (Exception $e) {}
    header('Location: paginas.php?ok=del');
    exit;
}

// ── Toggle estado ─────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle') {
    try {
        $pdo->prepare("UPDATE paginas SET estado = IF(estado='publicado','borrador','publicado') WHERE id=?")
            ->execute([(int)($_POST['id'] ?? 0)]);
    } catch (Exception $e) {}
    header('Location: paginas.php?ok=toggle');
    exit;
}

// ── Cargar páginas ───────────────────────────────────────────
$paginas = [];
$total   = 0;
try {
    $total   = (int)$pdo->query("SELECT COUNT(*) FROM paginas")->fetchColumn();
    $stmt    = $pdo->query("SELECT p.id, p.titulo, p.slug, p.estado, p.creado_en, u.nombre AS autor
                            FROM paginas p LEFT JOIN usuarios u ON u.id=p.autor_id
                            ORDER BY p.creado_en DESC");
    $paginas = $stmt->fetchAll();
} catch (Exception $e) {}

$page_title = 'Páginas';
include __DIR__ . '/layout.php';
?>

<?php if (isset($_GET['ok'])): ?>
<div class="mb-4 bg-green-50 border border-green-200 text-green-700 text-sm rounded-xl px-4 py-3 font-semibold">
  <?= $_GET['ok'] === 'del' ? 'Página eliminada.' : 'Estado actualizado.' ?>
</div>
<?php endif; ?>

<div x-data="{ delId: null, delTitulo: '' }">

  <!-- Modal eliminar -->
  <div x-show="delId" x-cloak
       class="fixed inset-0 z-50 flex items-center justify-center px-4"
       x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0" x-transition:enter-end="opacity-100">
    <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="delId=null"></div>
    <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-sm overflow-hidden"
         x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 scale-95" x-transition:enter-end="opacity-100 scale-100">
      <div class="flex flex-col items-center pt-8 pb-4 px-6 text-center">
        <div class="w-14 h-14 rounded-full bg-red-100 flex items-center justify-center mb-3">
          <svg class="w-7 h-7 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
            <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
          </svg>
        </div>
        <h3 class="font-black text-gray-900 text-lg mb-2">¿Eliminar página?</h3>
        <div class="bg-gray-50 rounded-xl px-4 py-2.5 w-full text-sm font-semibold text-gray-700 mb-5 line-clamp-2" x-text="'«' + delTitulo + '»'"></div>
        <div class="flex gap-3 w-full">
          <button @click="delId=null" class="flex-1 border-2 border-gray-200 text-gray-600 font-bold py-2.5 rounded-xl text-sm hover:bg-gray-50 transition-all">Cancelar</button>
          <form method="POST" class="flex-1">
            <input type="hidden" name="action" value="delete">
            <input type="hidden" name="id" :value="delId">
            <button type="submit" class="w-full bg-red-600 hover:bg-red-700 text-white font-bold py-2.5 rounded-xl text-sm text-center transition-all">Eliminar</button>
          </form>
        </div>
      </div>
    </div>
  </div>

  <!-- Header -->
  <div class="flex items-center justify-between mb-6">
    <div>
      <h2 class="text-xl font-black text-gray-800">Páginas</h2>
      <p class="text-sm text-gray-500"><?= $total ?> página<?= $total !== 1 ? 's' : '' ?> en total</p>
    </div>
    <a href="pagina-form.php"
       class="inline-flex items-center gap-2 bg-[#1E3A8A] hover:bg-blue-900 text-white font-bold text-sm px-5 py-2.5 rounded-xl transition-all shadow">
      <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
        <path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/>
      </svg>
      Nueva Página
    </a>
  </div>

  <!-- Tabla -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <?php if (empty($paginas)): ?>
    <div class="py-20 text-center">
      <div class="w-16 h-16 bg-gray-100 rounded-2xl flex items-center justify-center mx-auto mb-4">
        <svg class="w-8 h-8 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
      </div>
      <p class="text-gray-500 font-semibold text-sm">No hay páginas creadas</p>
      <a href="pagina-form.php" class="mt-2 inline-block text-[#1E3A8A] font-bold text-sm hover:underline">Crear primera página</a>
    </div>
    <?php else: ?>
    <div class="overflow-x-auto">
      <table class="w-full text-sm">
        <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wide">
          <tr>
            <th class="px-5 py-3 text-left">Título</th>
            <th class="px-5 py-3 text-left">Slug / URL</th>
            <th class="px-5 py-3 text-left">Estado</th>
            <th class="px-5 py-3 text-left">Autor</th>
            <th class="px-5 py-3 text-left">Creado</th>
            <th class="px-5 py-3 text-center">Acciones</th>
          </tr>
        </thead>
        <tbody class="divide-y divide-gray-50">
          <?php foreach ($paginas as $p): ?>
          <tr class="hover:bg-gray-50 transition-colors">
            <td class="px-5 py-3 font-semibold text-gray-800 max-w-[200px] truncate">
              <?= htmlspecialchars($p['titulo']) ?>
            </td>
            <td class="px-5 py-3">
              <code class="text-xs text-indigo-600 bg-indigo-50 px-2 py-1 rounded-lg font-mono">
                /pagina.php?slug=<?= htmlspecialchars($p['slug']) ?>
              </code>
            </td>
            <td class="px-5 py-3">
              <form method="POST" class="inline-flex">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="id" value="<?= $p['id'] ?>">
                <button type="submit" title="Clic para cambiar"
                 class="inline-flex items-center gap-1.5 text-xs px-2.5 py-1 rounded-full font-semibold cursor-pointer transition-all
                        <?= $p['estado']==='publicado' ? 'bg-green-100 text-green-700 hover:bg-green-200' : 'bg-gray-100 text-gray-500 hover:bg-gray-200' ?>">
                <?= $p['estado']==='publicado' ? '● Publicado' : '○ Borrador' ?>
                </button>
              </form>
            </td>
            <td class="px-5 py-3 text-gray-500 text-xs"><?= htmlspecialchars($p['autor'] ?? '—') ?></td>
            <td class="px-5 py-3 text-gray-400 text-xs whitespace-nowrap">
              <?= date('d/m/Y', strtotime($p['creado_en'])) ?>
            </td>
            <td class="px-5 py-3">
              <div class="flex items-center justify-center gap-1.5">
                <a href="pagina-form.php?id=<?= $p['id'] ?>"
                   class="inline-flex items-center gap-1 bg-[#1E3A8A] hover:bg-blue-800 text-white text-xs font-bold px-3 py-1.5 rounded-lg transition-all">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                  </svg>
                  Editar
                </a>
                <?php if ($p['estado']==='publicado'): ?>
                <a href="<?= BASE_URL ?>/pagina.php?slug=<?= urlencode($p['slug']) ?>" target="_blank"
                   class="inline-flex items-center gap-1 bg-sky-400 hover:bg-sky-500 text-white text-xs font-bold px-3 py-1.5 rounded-lg transition-all">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0zM2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                  </svg>
                  Ver
                </a>
                <?php endif; ?>
                <button @click="delId=<?= $p['id'] ?>; delTitulo=<?= htmlspecialchars(json_encode($p['titulo']), ENT_QUOTES) ?>"
                        class="inline-flex items-center gap-1 bg-red-500 hover:bg-red-700 text-white text-xs font-bold px-3 py-1.5 rounded-lg transition-all">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                  </svg>
                  Eliminar
                </button>
              </div>
            </td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
    <?php endif; ?>
  </div>

</div>

    </main>
  </div>
</body>
</html>
