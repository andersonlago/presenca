<?php include __DIR__ . '/layout_top.php'; ?>
<div class="card">
  <h2>Entrar</h2>
  <?php if ($error): ?><div class="flash err"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <label>E-mail<br><input type="email" name="email" required style="width:100%" value="<?= e($email) ?>"></label><br>
    <label>Senha<br><input type="password" name="password" required style="width:100%"></label><br>
    <button type="submit">Entrar</button>
  </form>
  <p class="muted">Não tem conta? <a href="/register.php">Cadastre-se</a>.</p>
</div>
<?php include __DIR__ . '/layout_bottom.php'; ?>
