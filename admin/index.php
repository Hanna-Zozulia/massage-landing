<?php

declare(strict_types=1);

require_once __DIR__ . '/_auth.php';
requireAdmin();

$pdo = databaseConnection();
$leadCount = (int) $pdo->query('SELECT COUNT(*) FROM leads')->fetchColumn();
$newLeadCount = (int) $pdo->query("SELECT COUNT(*) FROM leads WHERE status = 'new'")->fetchColumn();
$visitCount = (int) $pdo->query('SELECT COUNT(*) FROM visits')->fetchColumn();
$uniqueVisitCount = (int) $pdo->query('SELECT COUNT(DISTINCT visitor_token) FROM visits')->fetchColumn();
$leads = $pdo->query('SELECT * FROM leads ORDER BY created_at DESC LIMIT 20')->fetchAll();
$dailyVisits = $pdo->query(
    'SELECT DATE(visited_at) AS visit_date, COUNT(*) AS total, COUNT(DISTINCT visitor_token) AS unique_total
     FROM visits GROUP BY DATE(visited_at) ORDER BY visit_date DESC LIMIT 30'
)->fetchAll();
$recentVisits = $pdo->query(
    'SELECT visited_at, visitor_token, user_agent FROM visits ORDER BY visited_at DESC LIMIT 30'
)->fetchAll();
?><!doctype html>
<html lang="ru">
<head><meta charset="UTF-8"><meta name="viewport" content="width=device-width, initial-scale=1.0"><title>Админка</title></head>
<body>
<header><h1>Админка</h1><form method="post" action="logout.php"><?php echo adminCsrfInput(); ?><button type="submit">Выйти</button></form></header>
<main>
  <section>
    <h2>Сводка</h2>
    <p>Всего заявок: <?php echo $leadCount; ?></p>
    <p>Новых заявок: <?php echo $newLeadCount; ?></p>
    <p>Всего посещений: <?php echo $visitCount; ?></p>
    <p>Уникальных посетителей: <?php echo $uniqueVisitCount; ?></p>
  </section>
  <section><h2>Последние заявки</h2>
    <table><thead><tr><th>Имя</th><th>Email</th><th>Телефон</th><th>Дата</th><th>Статус</th><th></th></tr></thead><tbody>
    <?php foreach ($leads as $lead): ?><tr>
      <td><?php echo e($lead['name']); ?></td><td><?php echo e($lead['email']); ?></td><td><?php echo e($lead['phone']); ?></td>
      <td><?php echo e($lead['created_at']); ?></td><td><?php echo e($lead['status']); ?></td>
      <td><a href="lead.php?id=<?php echo (int) $lead['id']; ?>">Открыть</a></td>
    </tr><?php endforeach; ?>
    </tbody></table>
  </section>
  <section><h2>Посещения по дням</h2>
    <table><thead><tr><th>Дата</th><th>Всего</th><th>Уникальных</th></tr></thead><tbody>
    <?php foreach ($dailyVisits as $visit): ?><tr><td><?php echo e($visit['visit_date']); ?></td><td><?php echo (int) $visit['total']; ?></td><td><?php echo (int) $visit['unique_total']; ?></td></tr><?php endforeach; ?>
    </tbody></table>
  </section>
  <section><h2>Последние посещения</h2>
    <table><thead><tr><th>Дата и время</th><th>Анонимный ID</th><th>User-Agent</th></tr></thead><tbody>
    <?php foreach ($recentVisits as $visit): ?><tr><td><?php echo e($visit['visited_at']); ?></td><td><?php echo e($visit['visitor_token']); ?></td><td><?php echo e($visit['user_agent']); ?></td></tr><?php endforeach; ?>
    </tbody></table>
  </section>
</main>
</body>
</html>