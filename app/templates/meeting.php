<?php include __DIR__ . '/layout_top.php'; ?>
<div class="card">
  <h2><?= e($meeting['title']) ?></h2>
  <p class="muted">Período para registrar presença:<br>
    <?= e($meeting['starts_at']) ?> até <?= e($meeting['ends_at']) ?> (UTC)</p>

  <?php if ($myAttendance): ?>
    <div class="flash ok">Presença registrada em <?= e($myAttendance['recorded_at']) ?> (UTC) a partir do IP <?= e($myAttendance['ip']) ?>.</div>
  <?php elseif (!$isOwner): ?>
    <p class="muted">Somente o criador da reunião pode registrar presença nesta versão limitada.</p>
  <?php elseif ($status === 'future'): ?>
    <div class="flash err">O período de registro ainda não começou.</div>
  <?php elseif ($status === 'past'): ?>
    <div class="flash err">O período de registro já encerrou.</div>
  <?php else: ?>
    <form method="post">
      <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
      <button type="submit">Registrar minha presença agora</button>
    </form>
  <?php endif; ?>
</div>

<?php if ($isOwner): ?>
  <h2>Lista de presenças</h2>
  <?php if (!$attendees): ?>
    <p class="muted">Nenhum registro ainda.</p>
  <?php else: ?>
    <table>
      <tr><th>E-mail</th><th>Dia/Hora do registro (UTC)</th><th>IP</th></tr>
      <?php foreach ($attendees as $a): ?>
        <tr><td><?= e($a['email']) ?></td><td><?= e($a['recorded_at']) ?></td><td><?= e($a['ip']) ?></td></tr>
      <?php endforeach; ?>
    </table>
  <?php endif; ?>
<?php endif; ?>
<?php include __DIR__ . '/layout_bottom.php'; ?>
