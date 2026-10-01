<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

function requireAdmin(): void
{
    if (empty($_SESSION['admin_authenticated'])) {
        redirect('login.php');
    }
}

function adminCsrfInput(): string
{
    return '<input type="hidden" name="csrf_token" value="' . e(csrfToken()) . '">';
}

function validLeadStatus(string $status): bool
{
    return in_array($status, ['new', 'read', 'contacted', 'closed'], true);
}