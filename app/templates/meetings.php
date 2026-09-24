<?php include __DIR__ . '/layout_top.php'; ?>
<h2>Todas as reuniões</h2>
<p class="muted">Nesta versão limitada, o registro de presença só é permitido ao criador da reunião, dentro do período definido.</p>
<?php if (!$meetings): ?><p class="muted">Nenhuma reunião cadastrada.</p><?php endif; ?>
<?php foreach ($meetings as $m):
  $now = date('Y-m-d H:i:s');
  $open = $m['starts_at'] <= $now && $now <= $m['ends_at'];
?>
  <div class="card">
    <strong><?= e($m['title']) ?></strong>
    <span class="badge <?= $open ? 'open' : 'closed' ?>"><?= $open ? 'aberto' : 'fechado' ?></span>
    <p class="muted">por <?= e($m['owner_email']) ?> · período: <?= e($m['starts_at']) ?> → <?= e($m['ends_at']) ?> (UTC) · <?= (int)$m['n_att'] ?> presença(s)</p>
    <a href="/meeting.php?id=<?= (int)$m['id'] ?>">Abrir</a>
  </div>
<?php endforeach; ?>
<?php include __DIR__ . '/layout_bottom.php'; ?>
