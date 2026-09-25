<?php
declare(strict_types=1);
require __DIR__ . '/../src/helpers.php';
require __DIR__ . '/../src/qrcode.php';

$user = require_login();

$id = (int)($_GET['id'] ?? 0);
$st = db()->prepare('SELECT * FROM meetings WHERE id = ?');
$st->execute([$id]);
$meeting = $st->fetch();
if (!$meeting) { http_response_code(404); exit('Reunião não encontrada.'); }

$isOwner = (int)$meeting['user_id'] === (int)$user['id'];

// Garante public_token/checkin_code também para reuniões antigas.
ensure_meeting_tokens($meeting);

// O QR Code aponta para o link público de presença (checkin.php).
// t = minutos desde a criação da página: o navegador regenera o QR com código novo,
// dificultando compartilhar o QR fora da reunião ("QR dinâmico").
$qrMinutes = max(1, min(60, (int)env('QR_ROTATE_MIN', '5')));
$qrSvg = qr_to_svg(qr_payload($meeting), 6);
$qrUrl = qr_payload($meeting);

// O organizador baixa o QR como PNG (para projetar/imprimir).
if (isset($_GET['qr'])) {
    if (!$isOwner) { http_response_code(403); exit('Somente o criador da reunião pode baixar o QR Code.'); }
    $png = qr_to_png(qr_payload($meeting), 8);
    header('Content-Type: image/png');
    header('Content-Disposition: inline; filename="qrcode-reuniao-' . $id . '.png"');
    header('Content-Length: ' . strlen($png));
    echo $png;
    exit;
}

// Endpoint do QR dinâmico: o navegador da tela do organizador pergunta qual
// código vale agora (janela de QR_ROTATE_MIN minutos). Somente o criador responde.
if (isset($_GET['code'])) {
    if (!$isOwner) { http_response_code(403); exit('{}'); }
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode(['code' => qr_current_code($meeting)]);
    exit;
}

if ($_SERVER['REQUEST_METHOD'] === 'POST' && $isOwner) {
    check_csrf();

    // Regenera o código dinâmico embutido no QR (invalida QRs antigos/fotografados):
    // zera a "janela" gravada, então o próximo acesso rejeita todos os códigos em circulação.
    if (($_POST['action'] ?? '') === 'regen_qr') {
        db()->prepare('UPDATE meetings SET checkin_code = NULL WHERE id = ?')->execute([$id]);
        flash('Novo código do QR gerado. Os QRs anteriores deixam de valer.');
        header('Location: /meeting.php?id=' . $id);
        exit;
    }

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

// Registro via QR é público e fica em checkin.php (nome + CPF, sem precisar de conta).

$attendees = [];
$publicAttendees = [];
if ($isOwner) {
    $st = db()->prepare('SELECT a.recorded_at, a.ip, u.email FROM attendances a JOIN users u ON u.id = a.user_id WHERE a.meeting_id = ? ORDER BY a.recorded_at');
    $st->execute([$id]);
    $attendees = $st->fetchAll();
    $st = db()->prepare('SELECT * FROM public_attendances WHERE meeting_id = ? ORDER BY recorded_at');
    $st->execute([$id]);
    $publicAttendees = $st->fetchAll();
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
    'publicAttendees' => $publicAttendees,
    'myAttendance' => $myAttendance,
    'status' => $status,
    'qrSvg' => $qrSvg,
    'qrUrl' => $qrUrl,
    'qrMinutes' => $qrMinutes,
]);
