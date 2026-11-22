<?php
declare(strict_types=1);

require __DIR__ . '/../app/Models/Store.php';
require __DIR__ . '/../app/Models/Round.php';
require __DIR__ . '/../app/Controllers/Web.php';

header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: same-origin');
header("Content-Security-Policy: default-src 'self'; base-uri 'none'; frame-ancestors 'none'; form-action 'self'");
ini_set('session.use_strict_mode', '1');
session_set_cookie_params(['path' => '/', 'httponly' => true, 'samesite' => 'Lax', 'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off']);
session_name('UCENI_SESSION_V2');
session_start();
$_SESSION['csrf'] ??= bin2hex(random_bytes(32));

function e(string|int $value): string
{
    return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function csrf(): void
{
    echo '<input type="hidden" name="csrf" value="' . e($_SESSION['csrf']) . '">';
}

$packs = json_decode(file_get_contents(__DIR__ . '/../app/words.json'), true, flags: JSON_THROW_ON_ERROR);
(new Web(new Store(__DIR__ . '/../storage/uceni.sqlite'), $packs))->run();
