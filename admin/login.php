<?php

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';

if (!empty($_SESSION['admin_authenticated'])) {
    redirect('index.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
        $error = 'Сессия устарела. Обновите страницу.';
    } elseif (!hash_equals((string) envValue('ADMIN_EMAIL', ''), trim((string) ($_POST['email'] ?? '')))
        || !password_verify((string) ($_POST['password'] ?? ''), (string) envValue('ADMIN_PASSWORD_HASH', ''))) {
        $error = 'Неверный email или пароль.';
    } else {
        session_regenerate_id(true);
        $_SESSION['admin_authenticated'] = true;
        redirect('index.php');
    }
}
?><!doctype html>
<html lang="ru">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Вход в админку</title></head>
<body>
<main>
  <h1>Вход в админку</h1>
  <?php if ($error !== ''): ?><p role="alert"><?php echo e($error); ?></p><?php endif; ?>
  <form method="post">
    <?php echo adminCsrfInput(); ?>
    <label>Email <input type="email" name="email" required autocomplete="username"></label>
    <label>Пароль <input type="password" name="password" required autocomplete="current-password"></label>
    <button type="submit">Войти</button>
  </form>
</main>
</body>
</html>