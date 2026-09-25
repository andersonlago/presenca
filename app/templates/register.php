<?php $active = 'register'; include __DIR__ . '/layout_top.php'; ?>
<h1 class="page-title">Criar conta</h1>
<div class="card" style="max-width:480px">
  <?php if ($errors): ?>
    <div class="flash err" role="alert"><?= e(implode(' ', $errors)) ?></div>
  <?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <div class="form-field">
      <label for="email">E-mail</label>
      <input id="email" type="email" name="email" required autocomplete="email" value="<?= e($email) ?>">
    </div>
    <div class="form-field">
      <label for="password">Senha</label>
      <input id="password" type="password" name="password" required minlength="8" autocomplete="new-password">
      <p class="form-hint">Mínimo de 8 caracteres.</p>
    </div>
    <div class="form-field">
      <label for="password2">Repita a senha</label>
      <input id="password2" type="password" name="password2" required minlength="8" autocomplete="new-password">
    </div>
    <div class="form-actions">
      <button type="submit">Cadastrar</button>
    </div>
  </form>
  <p class="muted" style="margin-top:1rem">Você receberá um e-mail para confirmar e ativar a conta.</p>
</div>
<?php include __DIR__ . '/layout_bottom.php'; ?>
