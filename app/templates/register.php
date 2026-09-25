<?php include __DIR__ . '/layout_top.php'; ?>
<div class="card">
  <h2>Criar conta</h2>
  <?php if ($errors): ?>
    <div class="flash err"><?= e(implode(' ', $errors)) ?></div>
  <?php endif; ?>
  <form method="post">
    <input type="hidden" name="csrf" value="<?= e(csrf_token()) ?>">
    <label>E-mail<br><input type="email" name="email" required style="width:100%" value="<?= e($email) ?>"></label><br>
    <label>Senha (mín. 8 caracteres)<br><input type="password" name="password" required minlength="8" style="width:100%"></label><br>
    <label>Repita a senha<br><input type="password" name="password2" required minlength="8" style="width:100%"></label><br>
    <button type="submit">Cadastrar</button>
  </form>
  <p class="muted">Você receberá um e-mail para confirmar e ativar a conta.</p>
</div>
<?php include __DIR__ . '/layout_bottom.php'; ?>
