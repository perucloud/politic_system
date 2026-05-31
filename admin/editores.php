<?php
// ============================================================
// editores.php — Gestión de Editores (solo rol Admin)
// ============================================================
session_start();
require_once __DIR__ . '/../includes/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_login();
require_rol('admin');
require_modulo('usuarios', $pdo);

// Solo el rol exacto 'admin' puede entrar — SuperAdmin usa usuarios.php
if (is_superadmin()) {
    header('Location: usuarios.php');
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
}

$mi_id = (int)($_SESSION['admin_id'] ?? 0);

// ── POST: toggle activo ───────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle') {
    $tid = (int)($_POST['id'] ?? 0);
    if ($tid > 0) {
        try {
            // Solo puede modificar editores
            $stmt = $pdo->prepare("SELECT rol FROM usuarios WHERE id = ?");
            $stmt->execute([$tid]);
            $target = $stmt->fetch();
            if ($target && $target['rol'] === 'editor') {
                $pdo->prepare("UPDATE usuarios SET activo = NOT activo WHERE id = ?")
                    ->execute([$tid]);
                log_activity($pdo, 'Cambió estado de editor id=' . $tid, 'usuarios');
            }
        } catch (Exception $e) {}
    }
    header('Location: editores.php?msg=toggled');
    exit;
}

// ── POST: delete ──────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $did = (int)($_POST['id'] ?? 0);
    if ($did > 0) {
        try {
            $stmt = $pdo->prepare("SELECT nombre, rol FROM usuarios WHERE id = ?");
            $stmt->execute([$did]);
            $target = $stmt->fetch();
            if ($target && $target['rol'] === 'editor') {
                $pdo->prepare("DELETE FROM user_modulos WHERE usuario_id = ?")->execute([$did]);
                $pdo->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$did]);
                log_activity($pdo, 'Eliminó editor: ' . $target['nombre'], 'usuarios');
            }
        } catch (Exception $e) {}
    }
    header('Location: editores.php?msg=deleted');
    exit;
}

// ── Listar editores ───────────────────────────────────────────
$editores = [];
try {
    $editores = $pdo->query(
        "SELECT u.id, u.nombre, u.email, u.activo, u.ultimo_acceso,
                COUNT(um.modulo) as total_modulos
         FROM usuarios u
         LEFT JOIN user_modulos um ON um.usuario_id = u.id
         WHERE u.rol = 'editor'
         GROUP BY u.id
         ORDER BY u.creado_en DESC"
    )->fetchAll();
} catch (Exception $e) {}

// ── Flash messages ────────────────────────────────────────────
$msg     = trim($_GET['msg'] ?? '');
$msg_map = [
    'created' => ['ok', 'Editor creado correctamente.'],
    'updated' => ['ok', 'Editor actualizado correctamente.'],
    'deleted' => ['ok', 'Editor eliminado correctamente.'],
    'toggled' => ['ok', 'Estado del editor actualizado.'],
    'error'   => ['err', 'Ocurrió un error. Inténtalo de nuevo.'],
];
$flash = $msg_map[$msg] ?? null;

$page_title = 'Gestionar Editores';
include __DIR__ . '/layout.php';
?>

<!-- Flash -->
<?php if ($flash): ?>
<div class="mb-5 flex items-center gap-3 rounded-xl px-5 py-3.5 text-sm font-semibold
            <?= $flash[0] === 'ok' ? 'bg-green-50 text-green-700 border border-green-200' : 'bg-red-50 text-red-700 border border-red-200' ?>"
     x-data="{ show: true }" x-show="show">
  <?= $flash[0] === 'ok' ? '✓' : '✗' ?> <?= htmlspecialchars($flash[1]) ?>
  <button @click="show=false" class="ml-auto opacity-50 hover:opacity-100">✕</button>
</div>
<?php endif; ?>

<!-- Cabecera -->
<div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3 mb-6">
  <div>
    <h2 class="text-xl font-black text-gray-800">Gestionar Editores</h2>
    <p class="text-sm text-gray-400 mt-0.5">
      Crea y administra los usuarios de tipo Editor de tu equipo.
    </p>
  </div>
  <a href="usuario-form.php"
     class="inline-flex items-center gap-2 bg-[#1E3A8A] hover:bg-blue-900 text-white text-sm font-bold px-4 py-2.5 rounded-xl transition-all shadow-sm">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/>
    </svg>
    Nuevo Editor
  </a>
</div>

<!-- Info de módulos asignables -->
<?php
$mis_modulos = get_modulos_usuario($mi_id, $pdo);
?>
<div class="mb-5 bg-blue-50 border border-blue-200 rounded-xl px-5 py-3 flex items-start gap-3">
  <svg class="w-4 h-4 text-blue-500 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 16h-1v-4h-1m1-4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/>
  </svg>
  <p class="text-xs text-blue-700">
    Puedes asignar a tus Editores solo los módulos que tú tienes activos:
    <strong><?= implode(', ', array_map(fn($m) => MODULOS_SISTEMA[$m] ?? $m, $mis_modulos)) ?></strong>.
  </p>
</div>

<!-- Tabla de editores -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
  <?php if (empty($editores)): ?>
  <div class="text-center py-16 text-gray-400">
    <svg class="w-12 h-12 mx-auto mb-3 opacity-30" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
            d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/>
    </svg>
    <p class="text-sm font-semibold">No tienes editores creados aún.</p>
    <a href="usuario-form.php"
       class="mt-3 inline-block text-[#1E3A8A] font-bold text-sm hover:underline">
      + Crear el primer editor
    </a>
  </div>
  <?php else: ?>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wide">
        <tr>
          <th class="px-5 py-3 text-left">Editor</th>
          <th class="px-5 py-3 text-left">Email</th>
          <th class="px-5 py-3 text-left">Módulos</th>
          <th class="px-5 py-3 text-left">Estado</th>
          <th class="px-5 py-3 text-left">Último acceso</th>
          <th class="px-5 py-3 text-center">Acciones</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-50">
        <?php foreach ($editores as $e): ?>
        <tr class="hover:bg-gray-50 transition-colors">
          <!-- Nombre -->
          <td class="px-5 py-3">
            <div class="flex items-center gap-3">
              <div class="w-8 h-8 rounded-full bg-gradient-to-br from-green-500 to-teal-600
                          flex items-center justify-center flex-shrink-0">
                <span class="text-white text-xs font-black">
                  <?= strtoupper(substr($e['nombre'], 0, 1)) ?>
                </span>
              </div>
              <span class="font-semibold text-gray-800"><?= htmlspecialchars($e['nombre']) ?></span>
            </div>
          </td>
          <!-- Email -->
          <td class="px-5 py-3 text-gray-500"><?= htmlspecialchars($e['email']) ?></td>
          <!-- Módulos -->
          <td class="px-5 py-3">
            <span class="inline-flex items-center gap-1 bg-blue-50 text-blue-700 text-xs font-bold px-2.5 py-1 rounded-full">
              <?= (int)$e['total_modulos'] ?> / <?= count($mis_modulos) ?>
            </span>
          </td>
          <!-- Estado -->
          <td class="px-5 py-3">
            <?php if ($e['activo']): ?>
            <span class="inline-flex items-center gap-1.5 text-xs font-bold px-2.5 py-1 rounded-full bg-green-100 text-green-700">
              <span class="w-1.5 h-1.5 rounded-full bg-green-500"></span> Activo
            </span>
            <?php else: ?>
            <span class="inline-flex items-center gap-1.5 text-xs font-bold px-2.5 py-1 rounded-full bg-gray-100 text-gray-500">
              <span class="w-1.5 h-1.5 rounded-full bg-gray-400"></span> Inactivo
            </span>
            <?php endif; ?>
          </td>
          <!-- Último acceso -->
          <td class="px-5 py-3 text-gray-400 text-xs whitespace-nowrap">
            <?= $e['ultimo_acceso'] ? date('d/m/Y H:i', strtotime($e['ultimo_acceso'])) : '—' ?>
          </td>
          <!-- Acciones -->
          <td class="px-5 py-3">
            <div class="flex items-center justify-center gap-2">
              <!-- Editar -->
              <a href="usuario-form.php?id=<?= $e['id'] ?>"
                 title="Editar"
                 class="w-8 h-8 flex items-center justify-center bg-[#1E3A8A] hover:bg-blue-900
                        text-white rounded-lg transition-all">
                <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
              </a>
              <!-- Toggle activo -->
              <form method="POST" class="inline">
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="id" value="<?= $e['id'] ?>">
                <button type="submit" title="<?= $e['activo'] ? 'Desactivar' : 'Activar' ?>"
                        class="w-8 h-8 flex items-center justify-center rounded-lg transition-all
                               <?= $e['activo'] ? 'bg-amber-50 hover:bg-amber-100 text-amber-600 border border-amber-200' : 'bg-green-50 hover:bg-green-100 text-green-600 border border-green-200' ?>">
                  <?php if ($e['activo']): ?>
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636"/>
                  </svg>
                  <?php else: ?>
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                  </svg>
                  <?php endif; ?>
                </button>
              </form>
              <!-- Eliminar -->
              <form method="POST" class="inline"
                    onsubmit="return confirm('¿Eliminar al editor <?= htmlspecialchars(addslashes($e['nombre'])) ?>? Esta acción no se puede deshacer.')">
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= $e['id'] ?>">
                <button type="submit" title="Eliminar"
                        class="w-8 h-8 flex items-center justify-center bg-red-50 hover:bg-red-100
                               text-red-600 border border-red-200 rounded-lg transition-all">
                  <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                  </svg>
                </button>
              </form>
            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

    </main>
  </div>
</body>
</html>
