<?php $active = 'login'; include __DIR__ . '/layout_top.php'; ?>
<h1 class="page-title">Entrar</h1>
<div class="card" style="max-width:480px">
  <?php if ($error): ?><div class="flash err" role="alert"><?= e($error) ?></div><?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <div class="form-field">
      <label for="email">E-mail</label>
      <input id="email" type="email" name="email" required autocomplete="email" value="<?= e($email) ?>">
    </div>
    <div class="form-field">
      <label for="password">Senha</label>
      <input id="password" type="password" name="password" required autocomplete="current-password">
    </div>
    <div class="form-actions">
      <button type="submit">Entrar</button>
    </div>
  </form>
  <p class="muted" style="margin-top:1rem">Não tem conta? <a href="/register.php">Cadastre-se</a>.</p>
</div>
<?php include __DIR__ . '/layout_bottom.php'; ?>
