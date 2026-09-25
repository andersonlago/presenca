<?php
declare(strict_types=1);

// Cliente SMTP mínimo (sem dependências), apenas para e-mails simples.
// Em dev, aponte para um MailHog (porta 1025, sem autenticação).

function send_mail(string $to, string $subject, string $body): bool
{
    $headers = [
        'From: ' . mail_from(),
        'To: ' . $to,
        'Subject: ' . $subject,
        'MIME-Version: 1.0',
        'Content-Type: text/plain; charset=UTF-8',
    ];

    // Tenta SMTP direto; se falhar, cai no mail() padrão do PHP.
    if (smtp_send($to, $subject, $body, $headers)) {
        return true;
    }
    return @mail($to, $subject, $body, implode("\r\n", $headers));
}

function smtp_send(string $to, string $subject, string $body, array $headers): bool
{
    $conn = @fsockopen(smtp_host(), smtp_port(), $errno, $errstr, 5);
    if (!$conn) {
        return false;
    }
    $ok = true;
    $read = function () use ($conn) {
        $data = '';
        while ($line = fgets($conn, 515)) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $data;
    };
    $cmd = function ($c) use ($conn, $read) {
        fwrite($conn, $c . "\r\n");
        return $read();
    };

    $read(); // greeting
    $cmd('EHLO presenca.local');
    $cmd('MAIL FROM:<' . mail_from() . '>');
    $cmd('RCPT TO:<' . $to . '>');
    $cmd('DATA');
    $msg = implode("\r\n", $headers) . "\r\n\r\n" . str_replace("\n.", "\n..", $body) . "\r\n.";
    $res = $cmd($msg);
    $cmd('QUIT');
    fclose($conn);
    return $ok && str_starts_with($res, '250');
}
