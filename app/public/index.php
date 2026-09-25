<?php
declare(strict_types=1);
require __DIR__ . '/../src/helpers.php';

$user = require_login();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    check_csrf();
    $title  = trim($_POST['title'] ?? '');
    $starts = $_POST['starts_at'] ?? '';
    $ends   = $_POST['ends_at'] ?? '';

    $sTs = strtotime(str_replace('T', ' ', $starts));
    $eTs = strtotime(str_replace('T', ' ', $ends));
    if ($title === '' || $sTs === false || $eTs === false) {
        flash('Preencha título, início e fim do período.', 'err');
    } elseif ($eTs <= $sTs) {
        flash('O fim do período deve ser depois do início.', 'err');
    } else {
        // public_token: identifica a reunião no link/QR público de presença.
        // checkin_code: código dinâmico embutido no QR (muda a cada regeneração).
        $publicToken = bin2hex(random_bytes(16));
        $checkinCode = strtoupper(bin2hex(random_bytes(3)));
        db()->prepare('INSERT INTO meetings (user_id, title, starts_at, ends_at, public_token, checkin_code) VALUES (?,?,?,?,?,?)')
            ->execute([$user['id'], $title, date('Y-m-d H:i:s', $sTs), date('Y-m-d H:i:s', $eTs), $publicToken, $checkinCode]);
        flash('Reunião criada. O QR Code de presença está na página da reunião.');
    }
    header('Location: /index.php');
    exit;
}

$meetings = db()->query('
    SELECT m.*, (SELECT COUNT(*) FROM attendances a WHERE a.meeting_id = m.id) AS n_att
    FROM meetings m WHERE m.user_id = ' . (int)$user['id'] . '
    ORDER BY m.starts_at DESC
')->fetchAll();

render('dashboard', ['title' => 'Minhas reuniões', 'user' => $user, 'meetings' => $meetings]);
