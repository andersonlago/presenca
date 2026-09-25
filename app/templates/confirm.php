<?php include __DIR__ . '/layout_top.php'; ?>
<div class="card" style="max-width:560px">
  <?php if ($ok): ?>
    <h2>Conta ativada ✔</h2>
    <p>Sua conta foi confirmada com sucesso. Agora você pode entrar.</p>
    <a class="btn btn-primary" href="/login.php">Entrar</a>
  <?php else: ?>
    <h2>Link inválido</h2>
    <p>Este link de confirmação é inválido ou já foi utilizado.</p>
    <a class="btn btn-outline" href="/register.php">Criar nova conta</a>
  <?php endif; ?>
</div>
<?php include __DIR__ . '/layout_bottom.php'; ?>
