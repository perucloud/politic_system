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

// ── Toggle estado ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle') {
    $tid = (int)($_POST['id'] ?? 0);
    try {
        $pdo->prepare("UPDATE noticias SET estado = IF(estado='publicado','borrador','publicado') WHERE id=?")
            ->execute([$tid]);
    } catch (Exception $e) {}
    header('Location: noticias.php?ok=toggle');
    exit;
}

// ── Eliminar ────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $del_id = (int)($_POST['id'] ?? 0);
    try {
        $stmt = $pdo->prepare("SELECT imagen FROM noticias WHERE id=?");
        $stmt->execute([$del_id]);
        $nrow = $stmt->fetch();
        if ($nrow && $nrow['imagen']) @unlink(__DIR__ . '/../assets/img/noticias/' . $nrow['imagen']);
        $pdo->prepare("DELETE FROM noticias WHERE id=?")->execute([$del_id]);
    } catch (Exception $e) {}
    header('Location: noticias.php?ok=del');
    exit;
}

// ── Paginación ──────────────────────────────────────────────
$por_pag       = 15;
$pag           = max(1, (int)($_GET['pag'] ?? 1));
$offset        = ($pag - 1) * $por_pag;
$filtro_estado = $_GET['estado'] ?? 'todos';
$where         = $filtro_estado !== 'todos' ? "WHERE n.estado = ?" : '';
$params        = $filtro_estado !== 'todos' ? [$filtro_estado] : [];

try {
    $total_q = $pdo->prepare("SELECT COUNT(*) FROM noticias n $where");
    $total_q->execute($params);
    $total = (int)$total_q->fetchColumn();

    $items_q = $pdo->prepare(
        "SELECT n.id, n.titulo, n.imagen, n.categoria, n.estado, n.fecha,
                u.nombre AS autor_nombre
         FROM noticias n
         LEFT JOIN usuarios u ON u.id = n.autor_id
         $where
         ORDER BY n.fecha DESC LIMIT ? OFFSET ?"
    );
    $items_q->execute(array_merge($params, [$por_pag, $offset]));
    $noticias = $items_q->fetchAll();
} catch (Exception $e) {
    try {
        $total    = (int)$pdo->query("SELECT COUNT(*) FROM noticias")->fetchColumn();
        $items_q2 = $pdo->prepare("SELECT id,titulo,imagen,categoria,estado,fecha FROM noticias ORDER BY fecha DESC LIMIT ? OFFSET ?");
        $items_q2->execute([$por_pag, $offset]);
        $noticias = $items_q2->fetchAll();
    } catch (Exception $e2) { $total = 0; $noticias = []; }
}

$pages      = max(1, (int)ceil($total / $por_pag));
$page_title = 'Noticias';
include __DIR__ . '/layout.php';
?>

<!-- Alpine component: modal de confirmación eliminar -->
<div x-data="{ delId: null, delTitulo: '' }">

<?php if (isset($_GET['ok'])): ?>
<div class="mb-4 bg-green-50 border border-green-200 text-green-700 text-sm rounded-xl px-4 py-3 font-semibold">
  <?= $_GET['ok'] === 'toggle' ? 'Estado actualizado correctamente.' : 'Noticia eliminada.' ?>
</div>
<?php endif; ?>

<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 mb-6">
  <div class="flex items-center gap-3 flex-wrap">
    <p class="text-sm text-gray-500"><?= $total ?> noticias</p>
    <!-- Filtros estado -->
    <div class="flex gap-1.5">
      <?php foreach (['todos'=>'Todas','publicado'=>'Publicadas','borrador'=>'Borradores'] as $k=>$v): ?>
      <a href="noticias.php?estado=<?= $k ?>"
         class="text-xs font-semibold px-3 py-1.5 rounded-full transition-all
                <?= $filtro_estado === $k ? 'bg-[#1E3A8A] text-white' : 'bg-gray-100 text-gray-500 hover:bg-gray-200' ?>">
        <?= $v ?>
      </a>
      <?php endforeach; ?>
    </div>
  </div>
  <div class="flex gap-2">
    <a href="categorias-noticias.php"
       class="inline-flex items-center gap-1.5 border border-gray-200 text-gray-600 hover:bg-gray-50 font-semibold text-sm px-4 py-2.5 rounded-xl transition-all">
      🏷️ Categorías
    </a>
    <a href="noticia-form.php"
       class="inline-flex items-center gap-1.5 bg-[#1E3A8A] hover:bg-blue-900 text-white font-bold text-sm px-5 py-2.5 rounded-xl transition-all shadow">
      ➕ Nueva Noticia
    </a>
  </div>
</div>

<!-- Modal confirmar eliminar -->
<div x-show="delId" x-cloak
     class="fixed inset-0 z-50 flex items-center justify-center px-4"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0">

  <!-- Fondo oscuro -->
  <div class="absolute inset-0 bg-black/50 backdrop-blur-sm" @click="delId = null"></div>

  <!-- Tarjeta modal -->
  <div class="relative bg-white rounded-2xl shadow-2xl w-full max-w-md overflow-hidden"
       x-transition:enter="transition ease-out duration-200"
       x-transition:enter-start="opacity-0 scale-95 translate-y-2"
       x-transition:enter-end="opacity-100 scale-100 translate-y-0">

    <!-- Icono de advertencia centrado -->
    <div class="flex flex-col items-center pt-8 pb-4 px-6 text-center">
      <div class="w-16 h-16 rounded-full bg-red-100 flex items-center justify-center mb-4">
        <svg class="w-8 h-8 text-red-500" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="1.8">
          <path stroke-linecap="round" stroke-linejoin="round"
                d="M12 9v3.75m-9.303 3.376c-.866 1.5.217 3.374 1.948 3.374h14.71c1.73 0 2.813-1.874 1.948-3.374L13.949 3.378c-.866-1.5-3.032-1.5-3.898 0L2.697 16.126zM12 15.75h.007v.008H12v-.008z"/>
        </svg>
      </div>

      <h3 class="text-gray-900 font-black text-xl mb-2">¿Eliminar publicación?</h3>
      <p class="text-gray-500 text-sm leading-relaxed mb-3">
        ¿Está seguro que desea eliminar esta publicación?<br>
        <strong class="text-gray-700">Esta acción no se puede deshacer.</strong>
      </p>

      <!-- Título de la noticia -->
      <div class="w-full bg-gray-50 border border-gray-200 rounded-xl px-4 py-3 text-sm text-gray-700 font-semibold text-left line-clamp-2"
           x-text="'«' + delTitulo + '»'"></div>

      <p class="text-xs text-gray-400 mt-2.5">La imagen asociada también será eliminada permanentemente.</p>
    </div>

    <!-- Botones -->
    <div class="flex gap-3 px-6 pb-7 pt-2">
      <!-- NO -->
      <button @click="delId = null"
              class="flex-1 inline-flex items-center justify-center gap-2
                     border-2 border-gray-200 text-gray-600 font-bold py-3 rounded-xl text-sm
                     hover:bg-gray-50 hover:border-gray-300 transition-all">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/>
        </svg>
        No, cancelar
      </button>
      <!-- SÍ -->
      <form method="POST" class="flex-1">
        <input type="hidden" name="action" value="delete">
        <input type="hidden" name="id" :value="delId">
        <button type="submit"
         class="w-full inline-flex items-center justify-center gap-2
                bg-red-600 hover:bg-red-700 text-white font-bold py-3 rounded-xl text-sm
                transition-all shadow-sm hover:shadow-md">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2.5">
          <path stroke-linecap="round" stroke-linejoin="round" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
        </svg>
        Sí, eliminar
        </button>
      </form>
    </div>

  </div>
</div>

<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
  <?php if (empty($noticias)): ?>
  <div class="text-center py-16">
    <p class="text-gray-400 text-sm mb-3">No hay noticias en esta categoría.</p>
    <a href="noticia-form.php" class="text-sm text-[#1E3A8A] font-semibold hover:underline">Crear primera noticia</a>
  </div>
  <?php else: ?>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wide">
        <tr>
          <th class="px-4 py-3 text-left w-16">Img</th>
          <th class="px-4 py-3 text-left">Título / Autor</th>
          <th class="px-4 py-3 text-left">Categoría</th>
          <th class="px-4 py-3 text-left">Estado</th>
          <th class="px-4 py-3 text-left">Fecha</th>
          <th class="px-4 py-3 text-center">Acciones</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-50">
        <?php foreach ($noticias as $n): ?>
        <tr class="hover:bg-gray-50 transition-colors">

          <!-- Miniatura -->
          <td class="px-4 py-3">
            <?php if ($n['imagen']): ?>
            <img src="<?= BASE_URL ?>/assets/img/noticias/<?= htmlspecialchars($n['imagen']) ?>"
                 class="w-12 h-10 object-cover rounded-lg"
                 onerror="this.src='data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 width=%2248%22 height=%2240%22><rect fill=%22%23F3F4F6%22 width=%2248%22 height=%2240%22/></svg>'">
            <?php else: ?>
            <div class="w-12 h-10 bg-gray-100 rounded-lg flex items-center justify-center text-gray-300 text-lg">📷</div>
            <?php endif; ?>
          </td>

          <!-- Título + autor -->
          <td class="px-4 py-3">
            <a href="noticia-form.php?id=<?= $n['id'] ?>"
               class="font-semibold text-gray-800 hover:text-[#1E3A8A] line-clamp-2 block max-w-[220px] transition-colors">
              <?= htmlspecialchars($n['titulo']) ?>
            </a>
            <?php if (!empty($n['autor_nombre'])): ?>
            <span class="text-xs text-gray-400 mt-0.5 block">por <?= htmlspecialchars($n['autor_nombre']) ?></span>
            <?php endif; ?>
          </td>

          <!-- Categoría -->
          <td class="px-4 py-3">
            <span class="bg-blue-50 text-blue-700 text-xs px-2.5 py-1 rounded-full font-semibold">
              <?= htmlspecialchars($n['categoria'] ?: 'General') ?>
            </span>
          </td>

          <!-- Estado con toggle -->
          <td class="px-4 py-3">
            <form method="POST" class="inline-flex">
              <input type="hidden" name="action" value="toggle">
              <input type="hidden" name="id" value="<?= $n['id'] ?>">
              <button type="submit"
               title="Clic para cambiar estado"
               class="inline-flex items-center gap-1.5 text-xs px-2.5 py-1 rounded-full font-semibold cursor-pointer transition-all
                      <?= $n['estado'] === 'publicado'
                          ? 'bg-green-100 text-green-700 hover:bg-green-200'
                          : 'bg-gray-100 text-gray-500 hover:bg-gray-200' ?>">
              <?= $n['estado'] === 'publicado' ? '● Publicado' : '○ Borrador' ?>
              </button>
            </form>
          </td>

          <!-- Fecha -->
          <td class="px-4 py-3 text-gray-400 text-xs whitespace-nowrap">
            <?= date('d/m/Y', strtotime($n['fecha'])) ?>
          </td>

          <!-- Acciones -->
          <td class="px-4 py-3">
            <div class="flex items-center justify-center gap-1.5">

              <!-- Editar -->
              <a href="noticia-form.php?id=<?= $n['id'] ?>"
                 title="Editar noticia"
                 class="inline-flex items-center gap-1.5 bg-[#1E3A8A] hover:bg-blue-800 text-white
                        text-xs font-semibold px-3 py-1.5 rounded-lg transition-all shadow-sm hover:shadow">
                <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Editar
              </a>

              <!-- Ver: celeste si publicado, disabled si borrador -->
              <?php if ($n['estado'] === 'publicado'): ?>
              <a href="<?= BASE_URL ?>/noticias/ver.php?id=<?= $n['id'] ?>" target="_blank"
                 title="Ver en el sitio"
                 class="inline-flex items-center gap-1.5 bg-sky-400 hover:bg-sky-500 text-white
                        text-xs font-semibold px-3 py-1.5 rounded-lg transition-all shadow-sm">
                <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                Ver
              </a>
              <?php else: ?>
              <span title="No disponible — aún en borrador"
                    class="inline-flex items-center gap-1.5 bg-gray-100 text-gray-300
                           text-xs font-semibold px-3 py-1.5 rounded-lg cursor-not-allowed select-none">
                <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
                  <path stroke-linecap="round" stroke-linejoin="round" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                  <path stroke-linecap="round" stroke-linejoin="round" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                </svg>
                Ver
              </span>
              <?php endif; ?>

              <!-- Eliminar: fondo sólido rojo, letras blancas -->
              <button @click="delId = <?= $n['id'] ?>; delTitulo = <?= htmlspecialchars(json_encode($n['titulo']), ENT_QUOTES) ?>"
                      title="Eliminar noticia"
                      class="inline-flex items-center gap-1.5 bg-red-500 hover:bg-red-700 text-white
                             text-xs font-semibold px-3 py-1.5 rounded-lg transition-all shadow-sm">
                <svg class="w-3.5 h-3.5 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24" stroke-width="2">
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

  <!-- Paginación -->
  <?php if ($pages > 1): ?>
  <div class="flex items-center justify-center gap-2 py-5 border-t border-gray-50">
    <?php for ($i = 1; $i <= $pages; $i++): ?>
    <a href="?estado=<?= $filtro_estado ?>&pag=<?= $i ?>"
       class="w-8 h-8 flex items-center justify-center rounded-lg text-sm font-semibold transition-colors
              <?= $i === $pag ? 'bg-[#1E3A8A] text-white' : 'text-gray-500 hover:bg-gray-100' ?>">
      <?= $i ?>
    </a>
    <?php endfor; ?>
  </div>
  <?php endif; ?>
  <?php endif; ?>
</div>

</div><!-- /x-data -->

    </main>
  </div>
</body>
</html>
