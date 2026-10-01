<?php

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
requireAdmin();

$id = filter_input(INPUT_GET, 'id', FILTER_VALIDATE_INT);
if (!$id) {
    redirect('index.php');
}

$statement = databaseConnection()->prepare('SELECT * FROM leads WHERE id = :id');
$statement->execute(['id' => $id]);
$lead = $statement->fetch();
if (!$lead) {
    http_response_code(404);
    exit('Заявка не найдена.');
}
?><!doctype html>
<html lang="ru">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Заявка #<?php echo (int) $lead['id']; ?></title></head>
<body>
<main>
  <p><a href="index.php">Назад к списку</a></p>
  <h1>Заявка #<?php echo (int) $lead['id']; ?></h1>
  <dl>
    <dt>Услуга</dt><dd><?php echo e($lead['service']); ?></dd>
    <dt>Желаемая дата</dt><dd><?php echo e($lead['preferred_date']); ?></dd>
    <dt>Имя</dt><dd><?php echo e($lead['name']); ?></dd>
    <dt>Email</dt><dd><?php echo e($lead['email']); ?></dd>
    <dt>Телефон</dt><dd><?php echo e($lead['phone']); ?></dd>
    <dt>Способ связи</dt><dd><?php echo e($lead['contact_method']); ?></dd>
    <dt>Создана</dt><dd><?php echo e($lead['created_at']); ?></dd>
  </dl>
  <form method="post" action="update-status.php">
    <?php echo adminCsrfInput(); ?><input type="hidden" name="id" value="<?php echo (int) $lead['id']; ?>">
    <label>Статус <select name="status">
      <?php foreach (['new', 'read', 'contacted', 'closed'] as $status): ?><option value="<?php echo e($status); ?>"<?php echo $lead['status'] === $status ? ' selected' : ''; ?>><?php echo e($status); ?></option><?php endforeach; ?>
    </select></label><button type="submit">Сохранить статус</button>
  </form>
  <form method="post" action="delete.php" onsubmit="return confirm('Удалить заявку?');">
    <?php echo adminCsrfInput(); ?><input type="hidden" name="id" value="<?php echo (int) $lead['id']; ?>"><button type="submit">Удалить заявку</button>
  </form>
</main>
</body>
</html>