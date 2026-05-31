<?php
$page_title = 'Nueva Actividad';
include __DIR__ . '/layout.php';

require_rol('editor');
require_modulo('actividades', $pdo);

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_verify();
}

$distritos = [
    'Satipo (Capital)', 'Río Negro', 'Pangoa', 'Río Tambo',
    'Coviriali', 'Llaylla', 'Vizcatán del Ene', 'Pampa Hermosa', 'Mazamari',
];

$defaults = [
    'id'          => null,
    'nombre'      => '',
    'fecha'       => '',
    'hora'        => '',
    'lugar'       => '',
    'distrito'    => 'Satipo (Capital)',
    'descripcion' => '',
    'estado'      => 'borrador',
];

$actividad = $defaults;
$id        = isset($_GET['id']) ? (int)$_GET['id'] : null;
$msg       = '';
$tipo_msg  = '';

// ── Cargar si es edición ─────────────────────────────────────
if ($id) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM actividades WHERE id = ?");
        $stmt->execute([$id]);
        $found = $stmt->fetch();
        if ($found) {
            $actividad  = $found;
            $page_title = 'Editar Actividad';
        } else {
            $id = null; // ID inválido, crear modo
        }
    } catch (Exception $e) {
        $id = null;
    }
}

// ── Guardar ──────────────────────────────────────────────────
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nombre      = trim($_POST['nombre']      ?? '');
    $fecha       = trim($_POST['fecha']       ?? '');
    $hora        = trim($_POST['hora']        ?? '') ?: null;
    $lugar       = trim($_POST['lugar']       ?? '') ?: null;
    $distrito    = trim($_POST['distrito']    ?? 'Satipo (Capital)');
    $descripcion = trim($_POST['descripcion'] ?? '') ?: null;
    $estado      = in_array($_POST['estado'] ?? '', ['publicado', 'borrador'])
                     ? $_POST['estado'] : 'borrador';

    if (!in_array($distrito, $distritos)) {
        $distrito = 'Satipo (Capital)';
    }

    // Mantener datos del formulario en $actividad para repoblar si hay error
    $actividad = array_merge($actividad, compact(
        'nombre', 'fecha', 'hora', 'lugar', 'distrito', 'descripcion', 'estado'
    ));

    // Validación básica
    if ($nombre === '' || $fecha === '') {
        $msg = 'El nombre y la fecha son campos obligatorios.';
        $tipo_msg = 'error';
    } else {
        try {
            if ($id) {
                // Edición
                $pdo->prepare(
                    "UPDATE actividades
                        SET nombre = ?, fecha = ?, hora = ?, lugar = ?, distrito = ?,
                            descripcion = ?, estado = ?, actualizado = NOW()
                      WHERE id = ?"
                )->execute([$nombre, $fecha, $hora, $lugar, $distrito, $descripcion, $estado, $id]);

                log_activity($pdo, 'Editó actividad: ' . $nombre, 'actividades');
                header('Location: actividades.php?msg=updated');
            } else {
                // Creación
                $pdo->prepare(
                    "INSERT INTO actividades
                        (nombre, fecha, hora, lugar, distrito, descripcion, estado, creado_en, actualizado)
                     VALUES (?, ?, ?, ?, ?, ?, ?, NOW(), NOW())"
                )->execute([$nombre, $fecha, $hora, $lugar, $distrito, $descripcion, $estado]);

                log_activity($pdo, 'Creó actividad: ' . $nombre, 'actividades');
                header('Location: actividades.php?msg=created');
            }
            exit;
        } catch (Exception $e) {
            $msg = 'Error al guardar la actividad. Inténtalo de nuevo.';
            $tipo_msg = 'error';
        }
    }
}
?>

<!-- Botón volver -->
<div class="mb-5">
  <a href="actividades.php"
     class="inline-flex items-center gap-1.5 text-sm text-gray-400 hover:text-[#1E3A8A] transition-colors font-medium">
    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
      <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/>
    </svg>
    Volver a actividades
  </a>
</div>

<?php if ($msg): ?>
<div class="mb-5 rounded-xl px-5 py-3.5 text-sm font-semibold border
            <?= $tipo_msg === 'success' ? 'bg-green-50 text-green-700 border-green-200' : 'bg-red-50 text-red-700 border-red-200' ?>">
  <?= htmlspecialchars($msg) ?>
</div>
<?php endif; ?>

<form method="POST" class="space-y-5">

  <!-- Tarjeta: Información principal -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="bg-[#1E3A8A] px-6 py-4 flex items-center gap-3">
      <div class="w-8 h-8 bg-[#FACC15] rounded-lg flex items-center justify-center flex-shrink-0">
        <svg class="w-4 h-4 text-[#1E3A8A]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/>
        </svg>
      </div>
      <h3 class="text-white font-bold text-sm">Información de la actividad</h3>
    </div>
    <div class="p-6 space-y-5">

      <!-- Nombre -->
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1.5">
          Nombre <span class="text-red-500">*</span>
        </label>
        <input type="text" name="nombre" required
               value="<?= htmlspecialchars($actividad['nombre']) ?>"
               placeholder="Nombre de la actividad"
               class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm
                      focus:ring-2 focus:ring-[#1E3A8A] focus:border-[#1E3A8A] outline-none transition-shadow">
      </div>

      <!-- Fecha y Hora -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1.5">
            Fecha <span class="text-red-500">*</span>
          </label>
          <input type="date" name="fecha" required
                 value="<?= htmlspecialchars($actividad['fecha']) ?>"
                 class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm
                        focus:ring-2 focus:ring-[#1E3A8A] focus:border-[#1E3A8A] outline-none transition-shadow">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1.5">
            Hora <span class="text-xs text-gray-400 font-normal">(opcional)</span>
          </label>
          <input type="time" name="hora"
                 value="<?= htmlspecialchars($actividad['hora'] ?? '') ?>"
                 class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm
                        focus:ring-2 focus:ring-[#1E3A8A] focus:border-[#1E3A8A] outline-none transition-shadow">
        </div>
      </div>

      <!-- Lugar y Distrito -->
      <div class="grid grid-cols-1 sm:grid-cols-2 gap-5">
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1.5">
            Lugar <span class="text-xs text-gray-400 font-normal">(opcional)</span>
          </label>
          <input type="text" name="lugar"
                 value="<?= htmlspecialchars($actividad['lugar'] ?? '') ?>"
                 placeholder="Nombre del lugar o dirección"
                 class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm
                        focus:ring-2 focus:ring-[#1E3A8A] focus:border-[#1E3A8A] outline-none transition-shadow">
        </div>
        <div>
          <label class="block text-sm font-semibold text-gray-700 mb-1.5">Distrito</label>
          <select name="distrito"
                  class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm
                         focus:ring-2 focus:ring-[#1E3A8A] focus:border-[#1E3A8A] outline-none transition-shadow">
            <?php foreach ($distritos as $d): ?>
            <option value="<?= htmlspecialchars($d) ?>"
                    <?= ($actividad['distrito'] === $d) ? 'selected' : '' ?>>
              <?= htmlspecialchars($d) ?>
            </option>
            <?php endforeach; ?>
          </select>
        </div>
      </div>

    </div>
  </div>

  <!-- Tarjeta: Descripción y Estado -->
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 overflow-hidden">
    <div class="bg-[#1E3A8A] px-6 py-4 flex items-center gap-3">
      <div class="w-8 h-8 bg-[#FACC15] rounded-lg flex items-center justify-center flex-shrink-0">
        <svg class="w-4 h-4 text-[#1E3A8A]" fill="none" stroke="currentColor" viewBox="0 0 24 24">
          <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5"
                d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/>
        </svg>
      </div>
      <h3 class="text-white font-bold text-sm">Detalles adicionales</h3>
    </div>
    <div class="p-6 space-y-5">

      <!-- Descripción -->
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1.5">
          Descripción <span class="text-xs text-gray-400 font-normal">(opcional)</span>
        </label>
        <textarea name="descripcion" rows="5"
                  placeholder="Describe brevemente la actividad, objetivos, invitados especiales..."
                  class="w-full border border-gray-200 rounded-xl px-4 py-2.5 text-sm
                         focus:ring-2 focus:ring-[#1E3A8A] focus:border-[#1E3A8A] outline-none resize-y transition-shadow"
                  ><?= htmlspecialchars($actividad['descripcion'] ?? '') ?></textarea>
      </div>

      <!-- Estado -->
      <div>
        <label class="block text-sm font-semibold text-gray-700 mb-1.5">Estado de publicación</label>
        <div class="flex items-center gap-4">
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="radio" name="estado" value="borrador"
                   <?= ($actividad['estado'] === 'borrador') ? 'checked' : '' ?>
                   class="accent-[#1E3A8A] w-4 h-4">
            <span class="text-sm text-gray-600 font-medium">Borrador</span>
            <span class="text-xs text-gray-400">(solo visible en el admin)</span>
          </label>
          <label class="flex items-center gap-2 cursor-pointer">
            <input type="radio" name="estado" value="publicado"
                   <?= ($actividad['estado'] === 'publicado') ? 'checked' : '' ?>
                   class="accent-[#1E3A8A] w-4 h-4">
            <span class="text-sm text-gray-600 font-medium">Publicado</span>
            <span class="text-xs text-gray-400">(visible en el sitio)</span>
          </label>
        </div>
      </div>

    </div>
  </div>

  <!-- Botones de acción -->
  <div class="flex items-center gap-3 pb-2">
    <button type="submit"
            class="bg-[#FACC15] hover:bg-yellow-400 text-[#1E3A8A] font-black px-8 py-3 rounded-xl text-sm transition-all shadow">
      <?= $id ? 'Guardar cambios' : 'Crear actividad' ?>
    </button>
    <a href="actividades.php"
       class="text-sm text-gray-400 hover:text-gray-600 font-medium px-4 py-3">
      Cancelar
    </a>
  </div>

</form>

    </main>
  </div>
</body>
</html>
