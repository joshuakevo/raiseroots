<?php
// Shared setup for the ElTech Credit Registry endpoints.

declare(strict_types=1);

$config = require __DIR__ . '/config.php';

function db(): PDO
{
    static $pdo = null;
    global $config;
    if ($pdo === null) {
        $pdo = new PDO(
            "mysql:host={$config['db_host']};dbname={$config['db_name']};charset=utf8mb4",
            $config['db_user'],
            $config['db_pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC]
        );
    }
    return $pdo;
}

/** Uppercase, letters and digits only - so "cm 9001 2345abc" and "CM90012345ABC" match. */
function normalise_nid(string $nationalId): ?string
{
    $normalised = substr(preg_replace('/[^A-Z0-9]/', '', strtoupper($nationalId)), 0, 50);
    return $normalised === '' ? null : $normalised;
}

function respond(int $status, array $body): void
{
    http_response_code($status);
    header('Content-Type: application/json');
    echo json_encode($body);
    exit;
}

/** The calling lender, from "Authorization: Bearer <api key>". Ends the request if invalid. */
function authenticate(): array
{
    $header = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? '';
    if (!preg_match('/^Bearer\s+(\S+)$/', $header, $m)) {
        respond(401, ['error' => 'Missing API key.']);
    }
    $stmt = db()->prepare('SELECT id, name FROM lenders WHERE api_key_hash = ? AND is_active = 1');
    $stmt->execute([hash('sha256', $m[1])]);
    $lender = $stmt->fetch();
    if (!$lender) {
        respond(401, ['error' => 'Invalid or disabled API key.']);
    }
    return $lender;
}
