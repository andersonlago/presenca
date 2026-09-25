<?php
declare(strict_types=1);

function env(string $key, string $default = ''): string
{
    $v = getenv($key);
    return $v === false || $v === '' ? $default : $v;
}

const APP_NAME = 'Registro de Presença';

// Banco: SQLite (arquivo simples, suficiente para aplicação limitada)
const DB_PATH = __DIR__ . '/../var/presenca.sqlite';

// SMTP para envio de e-mails (pode ser um MailHog em dev). Configuráveis por env.
function smtp_host(): string { return env('SMTP_HOST', 'mailhog'); }
function smtp_port(): int { return (int)env('SMTP_PORT', '1025'); }
function mail_from(): string { return env('MAIL_FROM', 'no-reply@presenca.local'); }

// Fuso horário das datas registradas/exibidas (UTC por padrão; ex.: America/Sao_Paulo)
date_default_timezone_set(env('TZ', 'UTC'));

// URL pública base (usada nos links de confirmação). Se vazio, deduzida da requisição.
function base_url(): string
{
    $u = rtrim(env('APP_URL', ''), '/');
    if ($u !== '') return $u;
    $https = (($_SERVER['HTTPS'] ?? '') === 'on') || (($_SERVER['X-Forwarded-Proto'] ?? '') === 'https');
    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    return ($https ? 'https' : 'http') . '://' . $host;
}

function db(): PDO
{
    static $pdo = null;
    if ($pdo === null) {
        $pdo = new PDO('sqlite:' . DB_PATH);
        $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $pdo->exec('PRAGMA foreign_keys = ON');
    }
    return $pdo;
}

function init_db(): void
{
    $pdo = db();
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS users (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            email TEXT NOT NULL UNIQUE,
            password_hash TEXT NOT NULL,
            confirmed INTEGER NOT NULL DEFAULT 0,
            confirm_token TEXT,
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS meetings (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            user_id INTEGER NOT NULL REFERENCES users(id),
            title TEXT NOT NULL,
            starts_at TEXT NOT NULL,   -- início do período de registro
            ends_at TEXT NOT NULL,     -- fim do período de registro
            created_at TEXT NOT NULL DEFAULT (datetime('now'))
        );

        CREATE TABLE IF NOT EXISTS attendances (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            meeting_id INTEGER NOT NULL REFERENCES meetings(id),
            user_id INTEGER NOT NULL REFERENCES users(id),
            recorded_at TEXT NOT NULL DEFAULT (datetime('now')), -- dia/hora do registro
            ip TEXT NOT NULL,                                     -- IP de acesso
            UNIQUE(meeting_id, user_id)                           -- 1 registro por pessoa/reunião
        );

        CREATE TABLE IF NOT EXISTS public_attendances (
            id INTEGER PRIMARY KEY AUTOINCREMENT,
            meeting_id INTEGER NOT NULL REFERENCES meetings(id),
            name TEXT NOT NULL,                                   -- nome informado
            cpf TEXT NOT NULL,                                    -- CPF informado
            email TEXT NOT NULL,                                  -- e-mail informado
            token TEXT NOT NULL UNIQUE,                           -- link p/ desfazer o registro
            recorded_at TEXT NOT NULL DEFAULT (datetime('now')),  -- dia/hora do registro
            ip TEXT NOT NULL                                      -- IP de acesso
        );
    ");
    migrate_db();
}

// Migrações simples (aplicação limitada: só ALTERs aditivos, tolerando "column already exists")
function migrate_db(): void
{
    $pdo = db();
    $migrations = [
        "ALTER TABLE meetings ADD COLUMN public_token TEXT",
        "ALTER TABLE meetings ADD COLUMN checkin_code TEXT",
    ];
    foreach ($migrations as $sql) {
        try { $pdo->exec($sql); } catch (PDOException $e) { /* coluna já existe */ }
    }
    // Preenche o código dinâmico do QR das reuniões existentes/sem código.
    try {
        $missing = db()->query('SELECT id FROM meetings WHERE checkin_code IS NULL')->fetchAll(PDO::FETCH_COLUMN);
        $upd = db()->prepare('UPDATE meetings SET checkin_code = ? WHERE id = ?');
        foreach ($missing as $mid) {
            $upd->execute([(string)floor(time() / 300), (int)$mid]);
        }
    } catch (PDOException $e) { /* tabela ainda não criada */ }
    try {
        $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS ux_pub_meeting_cpf ON public_attendances (meeting_id, cpf)');
        $pdo->exec('CREATE UNIQUE INDEX IF NOT EXISTS ux_pub_meeting_email ON public_attendances (meeting_id, email)');
    } catch (PDOException $e) { /* índice já existe */ }
}

init_db();
