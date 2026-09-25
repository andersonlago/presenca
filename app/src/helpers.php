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

// URL pública que o QR Code aponta (formulário de presença).
function qr_payload(array $meeting): string
{
    return base_url() . '/checkin.php?token=' . $meeting['public_token']
         . '&c=' . qr_current_code($meeting);
}

function ensure_meeting_tokens(array &$meeting): void
{
    if (empty($meeting['public_token'])) {
        $meeting['public_token'] = bin2hex(random_bytes(16));
        db()->prepare('UPDATE meetings SET public_token = ? WHERE id = ?')
            ->execute([$meeting['public_token'], $meeting['id']]);
    }
    // checkin_code guarda a "janela" do código dinâmico. Se estiver adiantado em
    // relação à janela atual ("Gerar novo código agora"), os códigos antigos são
    // rejeitados até o relógio alcançar essa janela.
    $window = qr_window();
    if ((int)$meeting['checkin_code'] < $window) {
        $meeting['checkin_code'] = (string)$window;
        db()->prepare('UPDATE meetings SET checkin_code = ? WHERE id = ?')
            ->execute([(string)$window, $meeting['id']]);
    }
}

// Código dinâmico do QR: muda a cada janela de QR_ROTATE_MIN minutos.
// checkin.php aceita o código da janela atual e da anterior (tolerância de 1 janela).
function qr_window(): int
{
    return (int)floor(microtime(true) / qr_rotate_sec());
}

function qr_code_for_window(array $meeting, int $window): string
{
    return strtoupper(substr(md5($meeting['id'] . ':' . $window), 0, 6));
}

function qr_current_code(array $meeting): string
{
    return qr_code_for_window($meeting, qr_window());
}

function qr_rotate_sec(): int
{
    return max(60, min(3600, (int)env('QR_ROTATE_MIN', '5') * 60));
}

function render(string $tpl, array $data = []): void
{
    extract($data);
    require __DIR__ . '/../templates/' . $tpl . '.php';
}

// Roteamento simples por arquivo em public/ — nada de frameworks.
