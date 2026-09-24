<?php
declare(strict_types=1);
require __DIR__ . '/../src/helpers.php';

if (current_user()) { header('Location: /index.php'); exit; }

$errors = [];
$email = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $email = trim(strtolower($_POST['email'] ?? ''));
    $pass  = $_POST['password'] ?? '';
    $pass2 = $_POST['password2'] ?? '';

    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errors[] = 'E-mail inválido.';
    if (strlen($pass) < 8) $errors[] = 'A senha deve ter pelo menos 8 caracteres.';
    if ($pass !== $pass2) $errors[] = 'As senhas não conferem.';

    if (!$errors) {
        $st = db()->prepare('SELECT id FROM users WHERE email = ?');
        $st->execute([$email]);
        if ($st->fetch()) {
            $errors[] = 'Já existe uma conta com este e-mail.';
        } else {
            $token = bin2hex(random_bytes(24));
            $st = db()->prepare('INSERT INTO users (email, password_hash, confirm_token) VALUES (?,?,?)');
            $st->execute([$email, password_hash($pass, PASSWORD_DEFAULT), $token]);

            $link = base_url() . '/confirm.php?token=' . $token;
            send_mail($email, 'Confirme sua conta',
                "Clique no link abaixo para ativar sua conta:\n\n$link\n\nSe você não criou esta conta, ignore este e-mail."
            );
            flash('Conta criada. Verifique seu e-mail e clique no link de confirmação para ativá-la.');
            header('Location: /login.php');
            exit;
        }
    }
}

render('register', ['title' => 'Criar conta', 'errors' => $errors, 'email' => $email]);
