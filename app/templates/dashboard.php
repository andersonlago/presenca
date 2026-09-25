<?php $active = 'dashboard'; include __DIR__ . '/layout_top.php'; ?>
<h1 class="page-title">Minhas reuniões</h1>

<div class="card accent">
  <h2>Criar reunião</h2>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <div class="form-field">
      <label for="title">Título da reunião</label>
      <input id="title" name="title" required maxlength="120" placeholder="Ex.: Reunião ordinária do colegiado">
    </div>
    <div class="form-field">
      <label for="starts_at">Início do período de registro</label>
      <input id="starts_at" type="datetime-local" name="starts_at" required>
    </div>
    <div class="form-field">
      <label for="ends_at">Fim do período de registro</label>
      <input id="ends_at" type="datetime-local" name="ends_at" required>
    </div>
    <div class="form-actions">
      <button type="submit">Criar reunião</button>
    </div>
  </form>
</div>

<h2 class="page-title" style="font-size:1.25rem">Reuniões criadas</h2>
<?php if (!$meetings): ?>
  <div class="card"><p class="muted" style="margin:0">Nenhuma reunião ainda. Use o formulário acima para criar a primeira.</p></div>
<?php endif; ?>
<?php foreach ($meetings as $m):
  $now = date('Y-m-d H:i:s');
  $open = $m['starts_at'] <= $now && $now <= $m['ends_at'];
?>
  <div class="card">
    <div style="display:flex; align-items:center; gap:.75rem; flex-wrap:wrap; margin-bottom:.5rem">
      <strong style="font-size:1.05rem; color:var(--ds-primary)"><?= e($m['title']) ?></strong>
      <span class="badge <?= $open ? 'open' : 'closed' ?>"><?= $open ? 'registro aberto' : 'registro fechado' ?></span>
    </div>
    <p class="muted">Período: <?= e($m['starts_at']) ?> até <?= e($m['ends_at']) ?> (UTC) ·
      <?= (int)$m['n_att'] ?> presença(s)</p>
    <a class="btn btn-outline btn-sm" href="/meeting.php?id=<?= (int)$m['id'] ?>">Ver / registrar presença</a>
  </div>
<?php endforeach; ?>
<?php include __DIR__ . '/layout_bottom.php'; ?>
