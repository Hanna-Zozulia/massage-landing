<?php

declare(strict_types=1);

require_once __DIR__ . '/config.php';

if (session_status() !== PHP_SESSION_ACTIVE) {
    session_set_cookie_params([
        'httponly' => true,
        'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
        'samesite' => 'Lax',
    ]);
    session_start();
}

function csrfToken(): string
{
    if (empty($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
    }

    return $_SESSION['csrf_token'];
}

function verifyCsrf(?string $token): bool
{
    return is_string($token)
        && isset($_SESSION['csrf_token'])
        && hash_equals($_SESSION['csrf_token'], $token);
}

function jsonResponse(array $payload, int $status = 200): never
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit;
}

function e(?string $value): string
{
    return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function recordVisit(PDO $pdo): void
{
    $cookieName = 'massage_visitor';
    $visitorToken = $_COOKIE[$cookieName] ?? '';

    if (!is_string($visitorToken) || !preg_match('/^[a-f0-9]{64}$/', $visitorToken)) {
        $visitorToken = hash('sha256', bin2hex(random_bytes(32)));
        setcookie($cookieName, $visitorToken, [
            'expires' => time() + 60 * 60 * 24 * 365,
            'httponly' => true,
            'secure' => !empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off',
            'samesite' => 'Lax',
        ]);
    }

    $statement = $pdo->prepare(
        'INSERT INTO visits (visitor_token, user_agent) VALUES (:visitor_token, :user_agent)'
    );
    $statement->execute([
        'visitor_token' => $visitorToken,
        'user_agent' => substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512),
    ]);
}

function redirect(string $location): never
{
    header('Location: ' . $location);
    exit;
}