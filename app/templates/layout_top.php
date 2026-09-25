<?php /* layout simples usado pelos templates via render() */ ?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? APP_NAME) ?> — <?= e(APP_NAME) ?></title>
<style>
  body { font-family: system-ui, sans-serif; max-width: 760px; margin: 2rem auto; padding: 0 1rem; color:#222; }
  h1 { font-size: 1.4rem; }
  nav a { margin-right: 1rem; }
  .card { border:1px solid #ddd; border-radius:8px; padding:1rem; margin:1rem 0; }
  input, button { font: inherit; padding:.4rem .6rem; margin:.2rem 0; }
  button, .btn { background:#2563eb; color:#fff; border:0; border-radius:6px; cursor:pointer; text-decoration:none; display:inline-block; }
  .flash { padding:.6rem 1rem; border-radius:6px; margin:1rem 0; }
  .flash.ok { background:#dcfce7; } .flash.err { background:#fee2e2; }
  table { border-collapse: collapse; width: 100%; }
  th, td { border:1px solid #ddd; padding:.4rem .6rem; text-align:left; font-size:.95rem; }
  .muted { color:#666; font-size:.85rem; }
  .badge { font-size:.75rem; padding:.1rem .5rem; border-radius:99px; background:#e5e7eb; }
  .badge.open { background:#dcfce7; } .badge.closed { background:#fee2e2; }
</style>
</head>
<body>
<header>
  <h1><?= e(APP_NAME) ?></h1>
  <nav>
    <?php if (!empty($user)): ?>
      <a href="/index.php">Minhas reuniões</a>
      <a href="/meetings.php">Registrar presença</a>
      <span class="muted">(<?= e($user['email']) ?>)</span>
      <a href="/logout.php">Sair</a>
    <?php else: ?>
      <a href="/login.php">Entrar</a>
      <a href="/register.php">Criar conta</a>
    <?php endif; ?>
  </nav>
</header>
<?php if ($f = flash()): ?>
  <div class="flash <?= e($f['type']) ?>"><?= e($f['msg']) ?></div>
<?php endif; ?>
<main>
