<?php
declare(strict_types=1);
require __DIR__ . '/../src/helpers.php';

$token = $_GET['token'] ?? '';
$ok = false;
if ($token) {
    $st = db()->prepare('SELECT * FROM users WHERE confirm_token = ?');
    $st->execute([$token]);
    if ($u = $st->fetch()) {
        db()->prepare('UPDATE users SET confirmed = 1, confirm_token = NULL WHERE id = ?')
            ->execute([$u['id']]);
        $ok = true;
    }
}

render('confirm', ['title' => 'Confirmação de conta', 'ok' => $ok]);
