<?php /* layout gov.br usado pelos templates via render() */ ?>
<!DOCTYPE html>
<html lang="pt-br">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title ?? APP_NAME) ?> — <?= e(APP_NAME) ?></title>
<link rel="stylesheet" href="/assets/govbr.css">
</head>
<body>
<a class="skip-link" href="#conteudo">Pular para o conteúdo</a>
<header class="govbr-header">
  <div class="header-bar">
    <a class="govbr-brand" href="<?= !empty($user) ? '/index.php' : '/' ?>">
      <svg width="36" height="36" viewBox="0 0 36 36" aria-hidden="true" focusable="false">
        <rect x="2" y="2" width="14" height="14" rx="2" fill="#FF7B00"/>
        <rect x="20" y="2" width="14" height="14" rx="2" fill="#FFFFFF" opacity=".9"/>
        <rect x="2" y="20" width="14" height="14" rx="2" fill="#FFFFFF" opacity=".9"/>
        <rect x="20" y="20" width="14" height="14" rx="2" fill="#23BA51"/>
      </svg>
      <span class="brand-text">
        <span class="brand-gov">Governo Federal</span>
        <span class="brand-app"><?= e(APP_NAME) ?></span>
      </span>
    </a>
    <nav class="govbr-nav" aria-label="Navegação principal">
      <?php if (!empty($user)): ?>
        <a href="/index.php"<?= ($active ?? '') === 'dashboard' ? ' class="active"' : '' ?>>Minhas reuniões</a>
        <a href="/meetings.php"<?= ($active ?? '') === 'meetings' ? ' class="active"' : '' ?>>Registrar presença</a>
        <span class="user-chip"><?= e($user['email']) ?></span>
        <a href="/logout.php">Sair</a>
      <?php else: ?>
        <a href="/login.php"<?= ($active ?? '') === 'login' ? ' class="active"' : '' ?>>Entrar</a>
        <a href="/register.php"<?= ($active ?? '') === 'register' ? ' class="active"' : '' ?>>Criar conta</a>
      <?php endif; ?>
    </nav>
  </div>
</header>
<div class="govbr-breadcrumb" aria-label="Você está em">
  <span><a href="/">Início</a></span>
  <span><?= e($title ?? APP_NAME) ?></span>
</div>
<main id="conteudo">
<?php if ($f = flash()): ?>
  <div class="flash <?= e($f['type']) ?>" role="alert"><?= e($f['msg']) ?></div>
<?php endif; ?>
