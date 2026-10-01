<?php

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
requireAdmin();

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id || !verifyCsrf($_POST['csrf_token'] ?? null)) {
    http_response_code(400);
    exit('Некорректный запрос.');
}

$statement = databaseConnection()->prepare('DELETE FROM leads WHERE id = :id');
$statement->execute(['id' => $id]);
redirect('index.php');