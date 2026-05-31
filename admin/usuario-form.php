<?php
// ============================================================
// usuario-form.php — Crear / editar usuario
// SuperAdmin: gestiona Admin y Editor
// Admin: solo gestiona Editores (y solo sus módulos)
// ============================================================
session_start();
require_once __DIR__ . '/../includes/config/db.php';
require_once __DIR__ . '/config/auth.php';
require_login();
require_rol('admin'); // admin y superadmin pueden acceder
require_modulo('usuarios', $pdo);
ensure_candidate_access_schema($pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
}

$id     = isset($_GET['id']) ? (int)$_GET['id'] : null;
$errors = [];
$u      = ['id' => null, 'nombre' => '', 'email' => '', 'rol' => 'editor', 'activo' => 1, 'candidato_id' => null];

// Módulos que puede asignar el usuario actual
$modulos_asignables = is_superadmin()
    ? array_keys(MODULOS_SISTEMA)
    : get_modulos_usuario((int)($_SESSION['admin_id'] ?? 0), $pdo);

// Módulos actualmente asignados al usuario editado
$modulos_usuario_editado = [];

// ── Cargar usuario si es edición ─────────────────────────────
if ($id) {
    try {
        $stmt = $pdo->prepare("SELECT id, nombre, email, rol, activo, candidato_id FROM usuarios WHERE id = ?");
        $stmt->execute([$id]);
        $found = $stmt->fetch();
        if ($found) {
            // Admin solo puede editar Editores, no otros Admins ni SuperAdmins
            if (!is_superadmin() && !in_array($found['rol'], ['editor', 'candidato_distrital'], true)) {
                header('Location: usuarios.php?msg=error'); exit;
            }
            $u          = $found;
            $page_title = 'Editar Usuario';
            $modulos_usuario_editado = get_modulos_usuario($id, $pdo);
        } else {
            header('Location: usuarios.php?msg=error'); exit;
        }
    } catch (Exception $e) {
        header('Location: usuarios.php?msg=error'); exit;
    }
}

// Admin solo puede crear Editores (no Admins)
$roles_disponibles = is_superadmin()
    ? ['admin' => 'Admin', 'editor' => 'Editor', 'candidato_distrital' => 'Candidato Distrital']
    : ['editor' => 'Editor', 'candidato_distrital' => 'Candidato Distrital'];

$candidatos_select = [];
try {
    $candidatos_select = $pdo->query("SELECT id, distrito, nombre, slug FROM candidatos_distritales ORDER BY distrito ASC, id ASC")->fetchAll(PDO::FETCH_ASSOC);
} catch (Exception $e) {
    $candidatos_select = [];
}

$modulos_form_grupos = [
    'Contenido' => [
        'candidatos_distritales',
        'noticias',
        'actividades',
        'paginas',
        'media',
        'material_publicitario',
    ],
    'Partido Politico' => [
        'simpatizantes',
        'militantes',
        'personeros',
    ],
    'Configuracion' => [
        'menu_web',
        'configuracion_global',
        'seo',
    ],
    'Sistema' => [
        'auditoria',
        'usuarios',
    ],
];

// ── Procesar formulario ──────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre   = trim($_POST['nombre']   ?? '');
    $email    = trim($_POST['email']    ?? '');
    $rol      = $_POST['rol']    ?? 'editor';
    $activo   = isset($_POST['activo']) ? 1 : 0;
    $password = $_POST['password'] ?? '';
    $candidato_id = (int)($_POST['candidato_id'] ?? 0);

    // Repoblar para mostrar en caso de error
    $u['nombre'] = $nombre;
    $u['email']  = $email;
    $u['rol']    = $rol;
    $u['activo'] = $activo;
    $u['candidato_id'] = $candidato_id ?: null;

    // Validación: nombre
    if ($nombre === '') {
        $errors[] = 'El nombre es obligatorio.';
    }

    // Validación: email
    if ($email === '') {
        $errors[] = 'El email es obligatorio.';
    } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'El email no tiene un formato válido.';
    } else {
        // Unicidad: excluir el propio id en edición
        try {
            $q = $id
                ? $pdo->prepare("SELECT id FROM usuarios WHERE email = ? AND id != ?")
                : $pdo->prepare("SELECT id FROM usuarios WHERE email = ?");
            $id ? $q->execute([$email, $id]) : $q->execute([$email]);
            if ($q->fetch()) {
                $errors[] = 'El email ya está registrado por otro usuario.';
            }
        } catch (Exception $e) {
            $errors[] = 'Error al verificar el email.';
        }
    }

    // Validación: rol (no se permite asignar superadmin desde la UI)
    if (!array_key_exists($rol, $roles_disponibles)) {
        $rol = 'editor';
    }

    if ($rol === 'candidato_distrital') {
        if ($candidato_id <= 0) {
            $errors[] = 'Selecciona el distrito/candidato asignado.';
        } else {
            $cand_ok = false;
            foreach ($candidatos_select as $cand) {
                if ((int)$cand['id'] === $candidato_id) {
                    $cand_ok = true;
                    break;
                }
            }
            if (!$cand_ok) $errors[] = 'El distrito seleccionado no es valido.';
        }
    }

    // Validación: contraseña
    if (!$id && $password === '') {
        $errors[] = 'La contraseña es obligatoria para nuevos usuarios.';
    } elseif ($password !== '' && mb_strlen($password) < 6) {
        $errors[] = 'La contraseña debe tener al menos 6 caracteres.';
    }

    // Módulos seleccionados (solo los que el editor actual puede asignar)
    $modulos_seleccionados = [];
    foreach ($_POST['modulos'] ?? [] as $m) {
        if (in_array($m, $modulos_asignables, true)) {
            $modulos_seleccionados[] = $m;
        }
    }
    if ($rol === 'candidato_distrital') {
        $modulos_seleccionados = [];
    }
    $modulos_seleccionados = array_values(array_unique($modulos_seleccionados));

    // Guardar si no hay errores
    if (empty($errors)) {
        try {
            if ($id) {
                if ($password !== '') {
                    $hash = password_hash($password, PASSWORD_DEFAULT);
                    $pdo->prepare("UPDATE usuarios SET nombre=?,email=?,rol=?,activo=?,candidato_id=?,password=? WHERE id=?")
                        ->execute([$nombre,$email,$rol,$activo,($rol === 'candidato_distrital' ? $candidato_id : null),$hash,$id]);
                } else {
                    $pdo->prepare("UPDATE usuarios SET nombre=?,email=?,rol=?,activo=?,candidato_id=? WHERE id=?")
                        ->execute([$nombre,$email,$rol,$activo,($rol === 'candidato_distrital' ? $candidato_id : null),$id]);
                }
                guardar_modulos_usuario($id, $modulos_seleccionados, $pdo);
                log_activity($pdo, 'Editó usuario ' . $nombre . ' (módulos: ' . implode(', ', $modulos_seleccionados) . ')', 'usuarios');
                header('Location: usuarios.php?msg=updated'); exit;
            } else {
                $hash = password_hash($password, PASSWORD_DEFAULT);
                $pdo->prepare("INSERT INTO usuarios (nombre,email,rol,activo,candidato_id,password,creado_en) VALUES (?,?,?,?,?,?,NOW())")
                    ->execute([$nombre,$email,$rol,$activo,($rol === 'candidato_distrital' ? $candidato_id : null),$hash]);
                $new_id = (int)$pdo->lastInsertId();
                guardar_modulos_usuario($new_id, $modulos_seleccionados, $pdo);
                log_activity($pdo, 'Creó usuario ' . $nombre . ' (módulos: ' . implode(', ', $modulos_seleccionados) . ')', 'usuarios');
                header('Location: usuarios.php?msg=created'); exit;
            }
        } catch (Exception $e) {
            error_log('usuario-form guardar: ' . $e->getMessage());
            $errors[] = 'Error al guardar. Inténtalo de nuevo.';
        }
    }
    // Repoblar módulos para el form en caso de error
    $modulos_usuario_editado = $modulos_seleccionados;
}

$page_title = $id ? 'Editar Usuario' : 'Nuevo Usuario';
include __DIR__ . '/layout.php';
?>

<!-- Breadcrumb / volver -->
<div class="mb-5 flex items-center gap-2 text-sm text-gray-400">
  <a href="usuarios.php" class="hover:text-[#1E3A8A] transition-colors font-medium">← Volver a Usuarios</a>
  <span class="text-gray-300">/</span>
  <span class="text-gray-600 font-semibold"><?= htmlspecialchars($page_title) ?></span>
</div>

<!-- Errores -->
<?php if (!empty($errors)): ?>
<div class="mb-5 bg-red-50 border border-red-200 rounded-xl px-5 py-4 space-y-1">
  <?php foreach ($errors as $err): ?>
  <p class="text-sm text-red-700 font-medium">✗ <?= htmlspecialchars($err) ?></p>
  <?php endforeach; ?>
</div>
<?php endif; ?>

<!-- Formulario -->
<div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">

  <!-- Cabecera del card -->
  <div class="bg-[#1E3A8A] px-6 py-4 flex items-center gap-3">
    <div class="w-8 h-8 bg-[#FACC15] rounded-lg flex items-center justify-center flex-shrink-0">
      <svg class="w-4 h-4 text-[#0F2057]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
              d="<?= $id ? 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z' : 'M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z' ?>"/>
      </svg>
    </div>
    <div>
      <h2 class="text-white font-black text-base"><?= htmlspecialchars($page_title) ?></h2>
      <p class="text-blue-300 text-xs">
        <?= $id ? 'Modifica los datos del usuario seleccionado.' : 'Completa el formulario para crear un nuevo usuario.' ?>
      </p>
    </div>
  </div>

  <form method="POST" class="p-6 sm:p-8 space-y-6" x-data="{ rol: '<?= htmlspecialchars((string)$u['rol'], ENT_QUOTES, 'UTF-8') ?>' }">

    <!-- Fila: nombre + email -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1.5">
          Nombre completo <span class="text-red-400">*</span>
        </label>
        <input type="text" name="nombre" required
               value="<?= htmlspecialchars($u['nombre']) ?>"
               placeholder="Ej: María García"
               class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm
                      focus:ring-2 focus:ring-[#1E3A8A] focus:border-transparent outline-none transition-all">
      </div>
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1.5">
          Email <span class="text-red-400">*</span>
        </label>
        <input type="email" name="email" required
               value="<?= htmlspecialchars($u['email']) ?>"
               placeholder="correo@ejemplo.com"
               class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm
                      focus:ring-2 focus:ring-[#1E3A8A] focus:border-transparent outline-none transition-all">
      </div>
    </div>

    <!-- Fila: rol + activo -->
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Rol</label>
        <select name="rol" x-model="rol"
                class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm
                       focus:ring-2 focus:ring-[#1E3A8A] focus:border-transparent outline-none transition-all bg-white">
          <?php foreach ($roles_disponibles as $rv => $rl): ?>
          <option value="<?= $rv ?>" <?= $u['rol'] === $rv ? 'selected' : '' ?>><?= $rl ?></option>
          <?php endforeach; ?>
        </select>
        <p class="text-xs text-gray-400 mt-1.5">
          <?= is_superadmin() ? 'El rol SuperAdmin no se puede asignar desde aquí.' : 'Solo puedes crear usuarios con rol Editor.' ?>
        </p>
      </div>
      <div class="flex flex-col justify-center">
        <label class="block text-sm font-semibold text-gray-700 mb-3">Estado</label>
        <label class="inline-flex items-center gap-3 cursor-pointer group">
          <div class="relative">
            <input type="checkbox" name="activo" value="1" class="sr-only peer"
                   <?= $u['activo'] ? 'checked' : '' ?>>
            <div class="w-11 h-6 bg-gray-200 rounded-full peer
                        peer-checked:bg-[#1E3A8A] transition-colors duration-200"></div>
            <div class="absolute top-0.5 left-0.5 w-5 h-5 bg-white rounded-full shadow
                        transition-transform duration-200
                        peer-checked:translate-x-5"></div>
          </div>
          <span class="text-sm text-gray-600 group-hover:text-gray-800 transition-colors">
            Usuario activo
          </span>
        </label>
        <p class="text-xs text-gray-400 mt-2">Los usuarios inactivos no pueden iniciar sesión.</p>
      </div>
    </div>

    <div x-show="rol === 'candidato_distrital'" x-cloak class="border border-blue-100 bg-blue-50/70 rounded-2xl p-5">
      <div class="mb-3">
        <h3 class="text-sm font-black text-[#0F2057] uppercase tracking-wide">Acceso del candidato distrital</h3>
        <p class="text-xs text-gray-500 mt-1">
          Este usuario solo podra administrar la informacion, plan, imagenes y noticias de su distrito asignado.
        </p>
      </div>
      <label class="block text-sm font-semibold text-gray-700 mb-1.5">
        Distrito / candidato asignado <span class="text-red-400">*</span>
      </label>
      <select name="candidato_id"
              class="w-full border border-blue-100 rounded-xl px-4 py-2.5 text-sm bg-white
                     focus:ring-2 focus:ring-[#1E3A8A] focus:border-transparent outline-none transition-all">
        <option value="">Selecciona un distrito...</option>
        <?php foreach ($candidatos_select as $cand): ?>
        <option value="<?= (int)$cand['id'] ?>" <?= (int)($u['candidato_id'] ?? 0) === (int)$cand['id'] ? 'selected' : '' ?>>
          <?= htmlspecialchars(($cand['distrito'] ?: $cand['slug']) . ' - ' . ($cand['nombre'] ?: '[Sin candidato]')) ?>
        </option>
        <?php endforeach; ?>
      </select>
    </div>

    <!-- Contraseña -->
    <div class="border-t border-gray-100 pt-6">
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1.5">
            Contraseña
            <?php if (!$id): ?><span class="text-red-400">*</span><?php endif; ?>
          </label>
          <div class="relative" x-data="{ show: false }">
            <input :type="show ? 'text' : 'password'"
                   name="password"
                   placeholder="<?= $id ? 'Dejar vacío para no cambiar' : 'Mínimo 6 caracteres' ?>"
                   <?= !$id ? 'required' : '' ?>
                   class="w-full border border-gray-200 rounded-xl px-4 py-2.5 pr-10 text-sm
                          focus:ring-2 focus:ring-[#1E3A8A] focus:border-transparent outline-none transition-all">
            <button type="button" @click="show = !show"
                    class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors">
              <svg x-show="!show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M15 12a3 3 0 11-6 0 3 3 0 016 0z M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
              </svg>
              <svg x-show="show" class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24" x-cloak>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                      d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
              </svg>
            </button>
          </div>
          <?php if ($id): ?>
          <p class="text-xs text-gray-400 mt-1.5">
            Dejar vacío para mantener la contraseña actual.
          </p>
          <?php else: ?>
          <p class="text-xs text-gray-400 mt-1.5">Mínimo 6 caracteres.</p>
          <?php endif; ?>
        </div>
      </div>
    </div>

    <!-- ══ MÓDULOS ASIGNADOS ══════════════════════════════════ -->
    <?php if (false && !empty($modulos_asignables)): ?>
    <div class="border-t border-gray-100 pt-6">
      <div class="mb-4">
        <h3 class="text-sm font-black text-gray-700 uppercase tracking-wide">Módulos del sistema</h3>
        <p class="text-xs text-gray-400 mt-1">
          <?php if (is_superadmin()): ?>
            Selecciona los módulos a los que tendrá acceso este usuario.
          <?php else: ?>
            Solo puedes asignar módulos que tú mismo tienes activos.
          <?php endif; ?>
        </p>
      </div>
      <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">
        <?php foreach ($modulos_asignables as $modulo_slug):
          $modulo_label   = MODULOS_SISTEMA[$modulo_slug] ?? $modulo_slug;
          $esta_asignado  = in_array($modulo_slug, $modulos_usuario_editado);
        ?>
        <label class="flex items-center gap-3 p-3 border-2 rounded-xl cursor-pointer transition-all duration-200
                      <?= $esta_asignado ? 'border-[#1E3A8A] bg-blue-50' : 'border-gray-200 hover:border-blue-300' ?>"
               x-data
               :class="$el.querySelector('input').checked ? 'border-[#1E3A8A] bg-blue-50' : 'border-gray-200 hover:border-blue-300'">
          <input type="checkbox" name="modulos[]" value="<?= htmlspecialchars($modulo_slug) ?>"
                 class="w-4 h-4 accent-[#1E3A8A] rounded flex-shrink-0"
                 <?= $esta_asignado ? 'checked' : '' ?>
                 @change="$el.closest('label').classList.toggle('border-[#1E3A8A]', $el.checked);
                          $el.closest('label').classList.toggle('bg-blue-50', $el.checked);
                          $el.closest('label').classList.toggle('border-gray-200', !$el.checked);">
          <span class="text-sm font-medium text-gray-700"><?= htmlspecialchars($modulo_label) ?></span>
        </label>
        <?php endforeach; ?>
      </div>
      <!-- Acceso rápido: seleccionar todos / ninguno -->
      <div class="flex items-center gap-4 mt-3">
        <button type="button" onclick="document.querySelectorAll('[name=\'modulos[]\']').forEach(c => { c.checked=true; c.dispatchEvent(new Event('change')); })"
                class="text-xs text-[#1E3A8A] font-semibold hover:underline">
          Seleccionar todos
        </button>
        <button type="button" onclick="document.querySelectorAll('[name=\'modulos[]\']').forEach(c => { c.checked=false; c.dispatchEvent(new Event('change')); })"
                class="text-xs text-gray-400 font-semibold hover:underline">
          Quitar todos
        </button>
      </div>
    </div>
    <?php endif; ?>

    <?php if (!empty($modulos_asignables)): ?>
    <div class="border-t border-gray-100 pt-6" x-show="rol !== 'candidato_distrital'">
      <div class="mb-4">
        <h3 class="text-sm font-black text-gray-700 uppercase tracking-wide">Modulos del sistema</h3>
        <p class="text-xs text-gray-400 mt-1">
          <?php if (is_superadmin()): ?>
            Selecciona los modulos a los que tendra acceso este usuario.
          <?php else: ?>
            Solo puedes asignar modulos que tu mismo tienes activos.
          <?php endif; ?>
        </p>
      </div>

      <div class="grid grid-cols-1 xl:grid-cols-2 gap-4">
        <?php foreach ($modulos_form_grupos as $grupo_nombre => $grupo_modulos):
          $grupo_visibles = array_values(array_filter($grupo_modulos, fn($mod) => in_array($mod, $modulos_asignables, true)));
          if (empty($grupo_visibles)) continue;
        ?>
        <div class="rounded-2xl border border-blue-100 bg-gradient-to-b from-blue-50/70 to-white p-4">
          <div class="flex items-center justify-between gap-3 mb-3 pb-3 border-b border-blue-100">
            <div>
              <h4 class="text-xs font-black text-[#0F2057] uppercase tracking-wide"><?= htmlspecialchars($grupo_nombre) ?></h4>
              <p class="text-[11px] text-gray-400 mt-0.5"><?= count($grupo_visibles) ?> modulo<?= count($grupo_visibles) !== 1 ? 's' : '' ?> disponible<?= count($grupo_visibles) !== 1 ? 's' : '' ?></p>
            </div>
          </div>

          <div class="grid grid-cols-1 sm:grid-cols-2 gap-3">
            <?php foreach ($grupo_visibles as $modulo_slug):
              $modulo_label   = MODULOS_SISTEMA[$modulo_slug] ?? $modulo_slug;
              $esta_asignado  = in_array($modulo_slug, $modulos_usuario_editado, true);
            ?>
            <label class="flex items-center gap-3 p-3 border-2 rounded-xl cursor-pointer transition-all duration-200 bg-white
                          <?= $esta_asignado ? 'border-[#1E3A8A] bg-blue-50' : 'border-gray-200 hover:border-blue-300' ?>"
                   x-data
                   :class="$el.querySelector('input').checked ? 'border-[#1E3A8A] bg-blue-50' : 'border-gray-200 hover:border-blue-300 bg-white'">
              <input type="checkbox" name="modulos[]" value="<?= htmlspecialchars($modulo_slug) ?>"
                     class="w-4 h-4 accent-[#1E3A8A] rounded flex-shrink-0"
                     <?= $esta_asignado ? 'checked' : '' ?>
                     @change="$el.closest('label').classList.toggle('border-[#1E3A8A]', $el.checked);
                              $el.closest('label').classList.toggle('bg-blue-50', $el.checked);
                              $el.closest('label').classList.toggle('bg-white', !$el.checked);
                              $el.closest('label').classList.toggle('border-gray-200', !$el.checked);">
              <span class="text-sm font-medium text-gray-700"><?= htmlspecialchars($modulo_label) ?></span>
            </label>
            <?php endforeach; ?>
          </div>
        </div>
        <?php endforeach; ?>
      </div>

      <div class="flex items-center gap-4 mt-3">
        <button type="button" onclick="document.querySelectorAll('[name=\'modulos[]\']').forEach(c => { c.checked=true; c.dispatchEvent(new Event('change')); })"
                class="text-xs text-[#1E3A8A] font-semibold hover:underline">
          Seleccionar todos
        </button>
        <button type="button" onclick="document.querySelectorAll('[name=\'modulos[]\']').forEach(c => { c.checked=false; c.dispatchEvent(new Event('change')); })"
                class="text-xs text-gray-400 font-semibold hover:underline">
          Quitar todos
        </button>
      </div>
    </div>
    <?php endif; ?>

    <div x-show="rol === 'candidato_distrital'" x-cloak class="border-t border-gray-100 pt-6">
      <div class="rounded-2xl border border-amber-100 bg-amber-50 px-5 py-4">
        <h3 class="text-sm font-black text-amber-800 uppercase tracking-wide">Permisos controlados por distrito</h3>
        <p class="text-xs text-amber-700 mt-1">
          Los candidatos distritales no usan permisos generales. El sistema les habilita solo el panel Mi Distrito.
        </p>
      </div>
    </div>

    <!-- Botones -->
    <div class="flex items-center gap-3 border-t border-gray-100 pt-6">
      <button type="submit"
              class="inline-flex items-center gap-2 bg-[#FACC15] hover:bg-yellow-400
                     text-[#1E3A8A] font-black px-8 py-3 rounded-xl text-sm transition-all shadow-sm">
        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                d="M5 13l4 4L19 7"/>
        </svg>
        <?= $id ? 'Guardar cambios' : 'Crear usuario' ?>
      </button>
      <a href="usuarios.php"
         class="text-sm text-gray-400 hover:text-gray-600 font-medium transition-colors px-4 py-3">
        Cancelar
      </a>
    </div>

  </form>
</div>

    </main>
  </div>
</body>
</html>
