<?php

declare(strict_types=1);

function loadEnvironment(string $path): void
{
    if (!is_readable($path)) {
        return;
    }

    foreach (file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES) as $line) {
        $line = trim($line);
        if ($line === '' || str_starts_with($line, '#') || !str_contains($line, '=')) {
            continue;
        }

        [$name, $value] = explode('=', $line, 2);
        $name = trim($name);
        $value = trim($value);
        $value = trim($value, " \t\n\r\0\x0B\"");

        if ($name !== '' && getenv($name) === false) {
            putenv($name . '=' . $value);
        }
    }
}

loadEnvironment(dirname(__DIR__) . DIRECTORY_SEPARATOR . '.env');

function envValue(string $name, ?string $default = null): ?string
{
    $value = getenv($name);
    return $value === false ? $default : $value;
}

function requiredEnv(string $name): string
{
    $value = envValue($name);
    if ($value === null || $value === '') {
        throw new RuntimeException('Missing required configuration: ' . $name);
    }

    return $value;
}

function databaseConnection(): PDO
{
    static $pdo = null;
    if ($pdo instanceof PDO) {
        return $pdo;
    }

    $dsn = sprintf(
        'mysql:host=%s;port=%s;dbname=%s;charset=utf8mb4',
        requiredEnv('DB_HOST'),
        envValue('DB_PORT', '3306'),
        requiredEnv('DB_NAME')
    );

    $pdo = new PDO($dsn, requiredEnv('DB_USER'), envValue('DB_PASSWORD', ''), [
    PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
    PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    PDO::ATTR_EMULATE_PREPARES => false,
]);

    return $pdo;
}