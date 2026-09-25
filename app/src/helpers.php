<?php
declare(strict_types=1);

require __DIR__ . '/config.php';
require __DIR__ . '/mailer.php';

session_start();

function e(?string $s): string
{
    return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function current_user(): ?array
{
    if (empty($_SESSION['user_id'])) return null;
    $st = db()->prepare('SELECT * FROM users WHERE id = ?');
    $st->execute([$_SESSION['user_id']]);
    $u = $st->fetch();
    return $u ?: null;
}

function require_login(): array
{
    $u = current_user();
    if (!$u || !$u['confirmed']) {
        header('Location: /login.php');
        exit;
    }
    return $u;
}

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(16));
    }
    return $_SESSION['csrf'];
}

function check_csrf(): void
{
    if (($_POST['csrf'] ?? '') !== ($_SESSION['csrf'] ?? '')) {
        http_response_code(403);
        exit('CSRF invalido.');
    }
}

function flash(string $msg = null, string $type = 'ok'): ?array
{
    if ($msg !== null) {
        $_SESSION['flash'] = ['msg' => $msg, 'type' => $type];
        return null;
    }
    $f = $_SESSION['flash'] ?? null;
    unset($_SESSION['flash']);
    return $f;
}

function client_ip(): string
{
    // Sem proxy na frente desta aplicação limitada, REMOTE_ADDR é suficiente.
    return $_SERVER['REMOTE_ADDR'] ?? 'unknown';
}

function render(string $tpl, array $data = []): void
{
    extract($data);
    require __DIR__ . '/../templates/' . $tpl . '.php';
}

// Roteamento simples por arquivo em public/ — nada de frameworks.
