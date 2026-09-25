<?php include __DIR__ . '/layout_top.php'; ?>
<div class="card">
  <h2>Criar reunião</h2>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <label>Título<br><input name="title" required maxlength="120" style="width:100%"></label><br>
    <label>Início do período de registro<br><input type="datetime-local" name="starts_at" required></label>
    <label>Fim do período de registro<br><input type="datetime-local" name="ends_at" required></label><br>
    <button type="submit">Criar</button>
  </form>
</div>

<h2>Reuniões criadas</h2>
<?php if (!$meetings): ?>
  <p class="muted">Nenhuma reunião ainda.</p>
<?php endif; ?>
<?php foreach ($meetings as $m):
  $now = date('Y-m-d H:i:s');
  $open = $m['starts_at'] <= $now && $now <= $m['ends_at'];
?>
  <div class="card">
    <strong><?= e($m['title']) ?></strong>
    <span class="badge <?= $open ? 'open' : 'closed' ?>"><?= $open ? 'registro aberto' : 'registro fechado' ?></span>
    <p class="muted">Período: <?= e($m['starts_at']) ?> até <?= e($m['ends_at']) ?> (UTC) ·
      <?= (int)$m['n_att'] ?> presença(s)</p>
    <a href="/meeting.php?id=<?= (int)$m['id'] ?>">Ver / registrar presença</a>
  </div>
<?php endforeach; ?>
<?php include __DIR__ . '/layout_bottom.php'; ?>
