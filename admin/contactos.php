<?php
header('Location: dashboard.php');
exit;

$page_title = 'Contactos';
include __DIR__ . '/layout.php';
require_modulo('contactos', $pdo);

// ── Marcar como leído ─────────────────────────────────────────
if (isset($_GET['leer']) && is_numeric($_GET['leer'])) {
    $pdo->prepare("UPDATE contactos SET leido=1 WHERE id=?")->execute([(int)$_GET['leer']]);
    header('Location: contactos.php');
    exit;
}

try {
    $contactos = $pdo->query("SELECT * FROM contactos ORDER BY leido ASC, fecha DESC")->fetchAll();
} catch (Exception $e) { $contactos = []; }
?>

<p class="text-sm text-gray-400 mb-6"><?= count($contactos) ?> mensajes en total</p>

<div class="space-y-4">
  <?php if (empty($contactos)): ?>
  <div class="bg-white rounded-2xl shadow-sm border border-gray-100 text-center py-16 text-gray-400 text-sm">
    No hay mensajes de contacto aún.
  </div>
  <?php else: ?>
    <?php foreach ($contactos as $c): ?>
    <div class="bg-white rounded-2xl shadow-sm border <?= $c['leido'] ? 'border-gray-100' : 'border-[#38BDF8]' ?> overflow-hidden"
         x-data="{ open: false }">
      <div class="flex items-center justify-between px-5 py-4 cursor-pointer hover:bg-gray-50 transition-colors"
           @click="open = !open">
        <div class="flex items-center gap-3">
          <?php if (!$c['leido']): ?>
          <span class="w-2.5 h-2.5 rounded-full bg-[#38BDF8] flex-shrink-0"></span>
          <?php else: ?>
          <span class="w-2.5 h-2.5 rounded-full bg-gray-200 flex-shrink-0"></span>
          <?php endif; ?>
          <div>
            <p class="font-semibold text-sm text-gray-800 <?= !$c['leido'] ? '' : 'font-medium' ?>">
              <?= htmlspecialchars($c['nombre'] ?: 'Sin nombre') ?>
              <?php if (!$c['leido']): ?>
              <span class="ml-2 bg-[#38BDF8] text-white text-[10px] font-bold px-1.5 py-0.5 rounded-full">NUEVO</span>
              <?php endif; ?>
            </p>
            <p class="text-xs text-gray-400"><?= htmlspecialchars($c['email'] ?: '—') ?></p>
          </div>
        </div>
        <div class="flex items-center gap-4">
          <span class="text-xs text-gray-400"><?= date('d/m/Y H:i', strtotime($c['fecha'])) ?></span>
          <svg class="w-4 h-4 text-gray-400 transition-transform" :class="open ? 'rotate-180' : ''"
               fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
          </svg>
        </div>
      </div>

      <!-- Vista previa inline -->
      <div x-show="open" x-transition class="px-5 pb-5 border-t border-gray-50">
        <p class="text-sm text-gray-700 leading-relaxed mt-4 whitespace-pre-line">
          <?= htmlspecialchars($c['mensaje'] ?: '(sin mensaje)') ?>
        </p>
        <?php if (!$c['leido']): ?>
        <a href="contactos.php?leer=<?= $c['id'] ?>"
           class="inline-block mt-4 text-xs text-[#1E3A8A] border border-[#1E3A8A] hover:bg-[#1E3A8A] hover:text-white px-4 py-1.5 rounded-full font-semibold transition-colors">
          Marcar como leído
        </a>
        <?php endif; ?>
      </div>
    </div>
    <?php endforeach; ?>
  <?php endif; ?>
</div>

    </main>
  </div>
</body>
</html>
