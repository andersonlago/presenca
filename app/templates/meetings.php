<?php $active = 'meetings'; include __DIR__ . '/layout_top.php'; ?>
<h1 class="page-title">Todas as reuniões</h1>
<div class="flash info">Nesta versão limitada, o registro de presença só é permitido ao criador da reunião, dentro do período definido.</div>
<?php if (!$meetings): ?><div class="card"><p class="muted" style="margin:0">Nenhuma reunião cadastrada.</p></div><?php endif; ?>
<?php foreach ($meetings as $m):
  $now = date('Y-m-d H:i:s');
  $open = $m['starts_at'] <= $now && $now <= $m['ends_at'];
?>
  <div class="card">
    <div style="display:flex; align-items:center; gap:.75rem; flex-wrap:wrap; margin-bottom:.5rem">
      <strong style="font-size:1.05rem; color:var(--ds-primary)"><?= e($m['title']) ?></strong>
      <span class="badge <?= $open ? 'open' : 'closed' ?>"><?= $open ? 'aberto' : 'fechado' ?></span>
    </div>
    <p class="muted">por <?= e($m['owner_email']) ?> · período: <?= e($m['starts_at']) ?> → <?= e($m['ends_at']) ?> (UTC) · <?= (int)$m['n_att'] ?> presença(s)</p>
    <a class="btn btn-outline btn-sm" href="/meeting.php?id=<?= (int)$m['id'] ?>">Abrir</a>
  </div>
<?php endforeach; ?>
<?php include __DIR__ . '/layout_bottom.php'; ?>
