<?php
// ============================================================
// usuarios.php — Gestión de usuarios (solo superadmin)
// ============================================================
$page_title = 'Usuarios';
include __DIR__ . '/layout.php';

require_rol('superadmin');
ensure_candidate_access_schema($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
}

$mi_id = (int)($_SESSION['admin_id'] ?? 0);

// ── Toggle activo ────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'toggle') {
    $tid = (int)($_POST['id'] ?? 0);
    if ($tid && $tid !== $mi_id) {
        try {
            $stmt = $pdo->prepare("SELECT activo, rol FROM usuarios WHERE id = ?");
            $stmt->execute([$tid]);
            $u = $stmt->fetch();
            if ($u && $u['rol'] !== 'superadmin') {
                $nuevo = $u['activo'] ? 0 : 1;
                $pdo->prepare("UPDATE usuarios SET activo = ? WHERE id = ?")->execute([$nuevo, $tid]);
                log_activity($pdo, ($nuevo ? 'Activó' : 'Desactivó') . ' usuario #' . $tid, 'usuarios');
            }
        } catch (Exception $e) {}
    }
    header('Location: usuarios.php');
    exit;
}

// ── Eliminar ─────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($_POST['action'] ?? '') === 'delete') {
    $did = (int)($_POST['id'] ?? 0);
    if ($did && $did !== $mi_id) {
        try {
            $stmt = $pdo->prepare("SELECT rol FROM usuarios WHERE id = ?");
            $stmt->execute([$did]);
            $u = $stmt->fetch();
            if ($u && $u['rol'] !== 'superadmin') {
                $pdo->prepare("DELETE FROM usuarios WHERE id = ?")->execute([$did]);
                log_activity($pdo, 'Eliminó usuario #' . $did, 'usuarios');
                header('Location: usuarios.php?msg=deleted');
                exit;
            }
        } catch (Exception $e) {}
    }
    header('Location: usuarios.php?msg=error');
    exit;
}

// ── Listar usuarios ──────────────────────────────────────────
try {
    $usuarios = $pdo->query(
        "SELECT u.id, u.nombre, u.email, u.rol, u.activo, u.foto, u.ultimo_acceso, u.creado_en,
                u.candidato_id, cd.distrito AS candidato_distrito, cd.nombre AS candidato_nombre,
                (SELECT COUNT(*) FROM user_modulos um WHERE um.usuario_id = u.id) as total_modulos
         FROM usuarios u
         LEFT JOIN candidatos_distritales cd ON cd.id = u.candidato_id
         ORDER BY u.creado_en DESC"
    )->fetchAll();
} catch (Exception $e) {
    $usuarios = [];
}

// ── Mensajes flash ────────────────────────────────────────────
$flash_map = [
    'created' => ['bg-green-50 border-green-200 text-green-700', '✓ Usuario creado correctamente.'],
    'updated' => ['bg-blue-50 border-blue-200 text-blue-700',   '✓ Usuario actualizado correctamente.'],
    'deleted' => ['bg-red-50 border-red-200 text-red-700',      '✓ Usuario eliminado correctamente.'],
    'error'   => ['bg-red-50 border-red-200 text-red-700',      '✗ No se pudo completar la operación.'],
];
$msg_key = $_GET['msg'] ?? '';
?>

<?php if (isset($flash_map[$msg_key])): ?>
<div class="mb-5 border rounded-xl px-4 py-3 text-sm font-semibold <?= $flash_map[$msg_key][0] ?>">
  <?= $flash_map[$msg_key][1] ?>
</div>
<?php endif; ?>

<!-- Cabecera -->
<div class="flex items-center justify-between mb-6">
  <div>
    <h2 class="text-lg font-black text-gray-800">Gestión de Usuarios</h2>
    <p class="text-sm text-gray-400 mt-0.5"><?= count($usuarios) ?> usuario<?= count($usuarios) !== 1 ? 's' : '' ?> registrado<?= count($usuarios) !== 1 ? 's' : '' ?></p>
  </div>
  <a href="usuario-form.php"
     class="inline-flex items-center gap-2 bg-[#1E3A8A] hover:bg-blue-900 text-white font-bold text-sm px-5 py-2.5 rounded-xl transition-all shadow">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 4v16m8-8H4"/>
    </svg>
    Nuevo Usuario
  </a>
</div>

<!-- Tabla -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
  <?php if (empty($usuarios)): ?>
  <p class="text-center text-gray-400 py-16 text-sm">No hay usuarios registrados.</p>
  <?php else: ?>
  <div class="overflow-x-auto">
    <table class="w-full text-sm">
      <thead class="bg-gray-50 text-gray-500 uppercase text-xs tracking-wide">
        <tr>
          <th class="px-4 py-3 text-left">Usuario</th>
          <th class="px-4 py-3 text-left">Email</th>
          <th class="px-4 py-3 text-left">Rol</th>
          <th class="px-4 py-3 text-left">Asignacion</th>
          <th class="px-4 py-3 text-left">Estado</th>
          <th class="px-4 py-3 text-left">Modulos</th>
          <th class="px-4 py-3 text-left">Último acceso</th>
          <th class="px-4 py-3 text-left">Registrado</th>
          <th class="px-4 py-3 text-center">Acciones</th>
        </tr>
      </thead>
      <tbody class="divide-y divide-gray-50">
        <?php foreach ($usuarios as $u): ?>
        <?php
          $es_yo       = ((int)$u['id'] === $mi_id);
          $es_superadmin = ($u['rol'] === 'superadmin');
          $puede_toggle = !$es_yo && !$es_superadmin;
          $puede_delete = !$es_yo && !$es_superadmin;
          $inicial      = strtoupper(mb_substr($u['nombre'], 0, 1));
          $colores_avatar = [
            'superadmin' => 'from-purple-400 to-purple-700',
            'admin'      => 'from-blue-400 to-blue-700',
            'editor'     => 'from-green-400 to-green-600',
            'candidato_distrital' => 'from-amber-400 to-amber-600',
          ];
          $avatar_color = $colores_avatar[$u['rol']] ?? 'from-gray-400 to-gray-600';
        ?>
        <tr class="hover:bg-gray-50 transition-colors">
          <!-- Avatar + nombre -->
          <td class="px-4 py-3">
            <div class="flex items-center gap-3">
              <?php if (!empty($u['foto'])): ?>
              <img src="<?= BASE_URL ?>/assets/img/usuarios/<?= htmlspecialchars($u['foto']) ?>"
                   class="w-9 h-9 rounded-full object-cover flex-shrink-0"
                   onerror="this.style.display='none';this.nextElementSibling.style.display='flex'">
              <div class="w-9 h-9 rounded-full bg-gradient-to-br <?= $avatar_color ?> flex items-center justify-center flex-shrink-0 hidden">
                <span class="text-white text-sm font-black"><?= htmlspecialchars($inicial) ?></span>
              </div>
              <?php else: ?>
              <div class="w-9 h-9 rounded-full bg-gradient-to-br <?= $avatar_color ?> flex items-center justify-center flex-shrink-0">
                <span class="text-white text-sm font-black"><?= htmlspecialchars($inicial) ?></span>
              </div>
              <?php endif; ?>
              <div class="min-w-0">
                <p class="font-semibold text-gray-800 truncate max-w-[160px]">
                  <?= htmlspecialchars($u['nombre']) ?>
                  <?php if ($es_yo): ?>
                  <span class="text-[10px] text-gray-400 font-normal ml-1">(yo)</span>
                  <?php endif; ?>
                </p>
              </div>
            </div>
          </td>
          <!-- Email -->
          <td class="px-4 py-3 text-gray-500 max-w-[200px] truncate">
            <?= htmlspecialchars($u['email']) ?>
          </td>
          <!-- Rol badge -->
          <td class="px-4 py-3">
            <?= rol_badge($u['rol']) ?>
          </td>
          <!-- Asignacion candidato distrital -->
          <td class="px-4 py-3">
            <?php if ($u['rol'] === 'candidato_distrital'): ?>
              <?php if (!empty($u['candidato_id'])): ?>
              <div class="min-w-[150px]">
                <span class="inline-flex items-center bg-amber-50 text-amber-700 text-xs font-black px-2.5 py-1 rounded-full">
                  <?= htmlspecialchars($u['candidato_distrito'] ?: 'Distrito') ?>
                </span>
                <p class="text-[11px] text-gray-400 mt-1 truncate max-w-[190px]">
                  <?= htmlspecialchars($u['candidato_nombre'] ?: 'Sin nombre registrado') ?>
                </p>
              </div>
              <?php else: ?>
              <span class="inline-flex items-center bg-red-50 text-red-600 text-xs font-bold px-2.5 py-1 rounded-full">
                Sin distrito
              </span>
              <?php endif; ?>
            <?php else: ?>
              <span class="text-xs text-gray-300">-</span>
            <?php endif; ?>
          </td>
          <!-- Estado activo -->
          <td class="px-4 py-3">
            <?php if ($u['activo']): ?>
            <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-green-700">
              <span class="w-2 h-2 bg-green-500 rounded-full"></span>
              Activo
            </span>
            <?php else: ?>
            <span class="inline-flex items-center gap-1.5 text-xs font-semibold text-red-500">
              <span class="w-2 h-2 bg-red-400 rounded-full"></span>
              Inactivo
            </span>
            <?php endif; ?>
          </td>
          <!-- Módulos asignados -->
          <td class="px-4 py-3">
            <?php if ($u['rol'] === 'superadmin'): ?>
              <span class="text-xs text-purple-500 font-bold">Todos</span>
            <?php elseif ($u['rol'] === 'candidato_distrital'): ?>
              <span class="inline-flex items-center gap-1 bg-amber-50 text-amber-700 text-xs font-bold px-2.5 py-1 rounded-full">
                Mi Distrito
              </span>
            <?php else: ?>
              <span class="inline-flex items-center gap-1 bg-blue-50 text-blue-700 text-xs font-bold px-2.5 py-1 rounded-full">
                <?= (int)$u['total_modulos'] ?> / <?= count(MODULOS_SISTEMA) ?>
              </span>
            <?php endif; ?>
          </td>
          <!-- Último acceso -->
          <td class="px-4 py-3 text-gray-400 text-xs whitespace-nowrap">
            <?= $u['ultimo_acceso'] ? date('d/m/Y H:i', strtotime($u['ultimo_acceso'])) : '—' ?>
          </td>
          <!-- Creado en -->
          <td class="px-4 py-3 text-gray-400 text-xs whitespace-nowrap">
            <?= $u['creado_en'] ? date('d/m/Y', strtotime($u['creado_en'])) : '—' ?>
          </td>
          <!-- Acciones -->
          <td class="px-4 py-3">
            <div class="flex items-center justify-center gap-1.5 flex-wrap">

              <!-- Editar / Mi cuenta -->
              <?php if (!$es_superadmin || $es_yo): ?>
              <a href="usuario-form.php?id=<?= (int)$u['id'] ?>"
                 class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold
                        bg-blue-50 text-blue-700 border border-blue-200
                        hover:bg-[#1E3A8A] hover:text-white hover:border-[#1E3A8A]
                        transition-all duration-150 shadow-sm hover:shadow">
                <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                  <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                        d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                <?= $es_yo ? 'Mi cuenta' : 'Editar' ?>
              </a>
              <?php endif; ?>

              <!-- Toggle activo / inactivo -->
              <?php if ($puede_toggle): ?>
              <form method="POST" class="inline">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="toggle">
                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                <?php if ($u['activo']): ?>
                <button type="submit"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold
                               bg-amber-50 text-amber-700 border border-amber-200
                               hover:bg-amber-500 hover:text-white hover:border-amber-500
                               transition-all duration-150 shadow-sm hover:shadow">
                  <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                          d="M18.364 18.364A9 9 0 005.636 5.636m12.728 12.728A9 9 0 015.636 5.636m12.728 12.728L5.636 5.636"/>
                  </svg>
                  Desactivar
                </button>
                <?php else: ?>
                <button type="submit"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold
                               bg-green-50 text-green-700 border border-green-200
                               hover:bg-green-500 hover:text-white hover:border-green-500
                               transition-all duration-150 shadow-sm hover:shadow">
                  <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                          d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/>
                  </svg>
                  Activar
                </button>
                <?php endif; ?>
              </form>
              <?php endif; ?>

              <!-- Eliminar -->
              <?php if ($puede_delete): ?>
              <form method="POST" class="inline"
                    onsubmit="return confirm('¿Eliminar a <?= htmlspecialchars(addslashes($u['nombre'])) ?>? Esta acción no se puede deshacer.')">
                <?= csrf_field() ?>
                <input type="hidden" name="action" value="delete">
                <input type="hidden" name="id" value="<?= (int)$u['id'] ?>">
                <button type="submit"
                        class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-bold
                               bg-red-50 text-red-600 border border-red-200
                               hover:bg-red-500 hover:text-white hover:border-red-500
                               transition-all duration-150 shadow-sm hover:shadow">
                  <svg class="w-3 h-3 flex-shrink-0" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                          d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/>
                  </svg>
                  Eliminar
                </button>
              </form>
              <?php else: ?>
              <span class="text-xs text-gray-300 px-2">—</span>
              <?php endif; ?>

            </div>
          </td>
        </tr>
        <?php endforeach; ?>
      </tbody>
    </table>
  </div>
  <?php endif; ?>
</div>

<!-- Leyenda de roles -->
<div class="mt-6 flex flex-wrap items-center gap-4 text-xs text-gray-400">
  <span class="font-semibold text-gray-500">Roles:</span>
  <?= rol_badge('superadmin') ?>
  <?= rol_badge('admin') ?>
  <?= rol_badge('editor') ?>
  <?= rol_badge('candidato_distrital') ?>
  <span class="ml-4 text-gray-300">|</span>
  <span class="inline-flex items-center gap-1.5"><span class="w-2 h-2 bg-green-500 rounded-full"></span>Activo</span>
  <span class="inline-flex items-center gap-1.5"><span class="w-2 h-2 bg-red-400 rounded-full"></span>Inactivo</span>
</div>

    </main>
  </div>
</body>
</html>
