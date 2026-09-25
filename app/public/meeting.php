<?php
declare(strict_types=1);
require __DIR__ . '/../src/helpers.php';

$user = require_login();

$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare('SELECT * FROM meetings WHERE id = ?');
$st->execute([$id]);
$meeting = $st->fetch();
if (!$meeting) { http_response_code(404); exit('Reunião não encontrada.'); }

$isOwner = (int)$meeting['user_id'] === (int)$user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isOwner) {
    check_csrf();
    $now = date('Y-m-d H:i:s');
    if ($now < $meeting['starts_at']) {
        flash('O período de registro ainda não começou.', 'err');
    } elseif ($now > $meeting['ends_at']) {
        flash('O período de registro já encerrou.', 'err');
    } else {
        try {
            db()->prepare('INSERT INTO attendances (meeting_id, user_id, ip) VALUES (?,?,?)')
                ->execute([$id, $user['id'], client_ip()]);
            flash('Presença registrada com sucesso.');
        } catch (PDOException $ex) {
            flash('Você já registrou presença nesta reunião.', 'err');
        }
    }
    header('Location: /meeting.php?id=' . $id);
    exit;
}

$attendees = [];
if ($isOwner) {
    $st = db()->prepare('SELECT a.recorded_at, a.ip, u.email FROM attendances a JOIN users u ON u.id = a.user_id WHERE a.meeting_id = ? ORDER BY a.recorded_at');
    $st->execute([$id]);
    $attendees = $st->fetchAll();
}

$myAttendance = null;
$st = db()->prepare('SELECT * FROM attendances WHERE meeting_id = ? AND user_id = ?');
$st->execute([$id, $user['id']]);
$myAttendance = $st->fetch() ?: null;

$now = date('Y-m-d H:i:s');
$status = $now < $meeting['starts_at'] ? 'future' : ($now > $meeting['ends_at'] ? 'past' : 'open');

render('meeting', [
    'title' => $meeting['title'],
    'user' => $user,
    'meeting' => $meeting,
    'isOwner' => $isOwner,
    'attendees' => $attendees,
    'myAttendance' => $myAttendance,
    'status' => $status,
]);
