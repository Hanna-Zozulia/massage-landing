<?php

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
requireAdmin();

$id = filter_input(INPUT_POST, 'id', FILTER_VALIDATE_INT);
$status = (string) ($_POST['status'] ?? '');
if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !$id || !verifyCsrf($_POST['csrf_token'] ?? null) || !validLeadStatus($status)) {
    http_response_code(400);
    exit('Некорректный запрос.');
}

$statement = databaseConnection()->prepare('UPDATE leads SET status = :status WHERE id = :id');
$statement->execute(['status' => $status, 'id' => $id]);
redirect('lead.php?id=' . $id);