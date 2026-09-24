<?php
declare(strict_types=1);
require __DIR__ . '/../src/helpers.php';

$st = db()->prepare('
    SELECT m.*, u.email AS owner_email,
        (SELECT COUNT(*) FROM attendances a WHERE a.meeting_id = m.id) AS n_att
    FROM meetings m JOIN users u ON u.id = m.user_id
    ORDER BY m.starts_at DESC
');
$st->execute();
$meetings = $st->fetchAll();

render('meetings', ['title' => 'Reuniões', 'user' => current_user(), 'meetings' => $meetings]);
