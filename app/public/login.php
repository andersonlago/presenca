<?php
declare(strict_types=1);
require __DIR__ . '/../src/helpers.php';

if (current_user()) { header('Location: /index.php'); exit; }

$error = null;
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $email = trim(strtolower($_POST['email'] ?? ''));
    $pass  = $_POST['password'] ?? '';

    $st = db()->prepare('SELECT * FROM users WHERE email = ?');
    $st->execute([$email]);
    $u = $st->fetch();
    if ($u && password_verify($pass, $u['password_hash'])) {
        if (!$u['confirmed']) {
            $error = 'Sua conta ainda não foi ativada. Clique no link enviado para o seu e-mail.';
        } else {
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int)$u['id'];
            header('Location: /index.php');
            exit;
        }
    } else {
        $error = 'E-mail ou senha incorretos.';
    }
}

render('login', ['title' => 'Entrar', 'error' => $error, 'email' => $email]);
