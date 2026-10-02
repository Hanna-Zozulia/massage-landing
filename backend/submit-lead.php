<?php

declare(strict_types=1);

require_once dirname(__DIR__) . '/config/bootstrap.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    jsonResponse(['success' => false, 'message' => 'Недопустимый метод запроса.'], 405);
}

if (!verifyCsrf($_POST['csrf_token'] ?? null)) {
    jsonResponse(['success' => false, 'message' => 'Сессия формы устарела. Обновите страницу и попробуйте снова.'], 419);
}

if (isset($_SESSION['last_lead_submission']) && time() - (int) $_SESSION['last_lead_submission'] < 20) {
    jsonResponse(['success' => false, 'message' => 'Подождите немного перед повторной отправкой.'], 429);
}

$service = trim((string) ($_POST['service'] ?? ''));
$preferredDate = trim((string) ($_POST['preferred_date'] ?? ''));
$email = trim((string) ($_POST['email'] ?? ''));
$name = trim((string) ($_POST['name'] ?? ''));
$phone = trim((string) ($_POST['phone'] ?? ''));
$contactMethod = trim((string) ($_POST['contact_method'] ?? ''));

$allowedServices = [
    'Тайский массаж стоп',
    'Шейно-воротниковый массаж',
    'Массаж спины',
    'Общий массаж тела',
    'Массаж лицевой зоны',
    'Подарочный сертификат',
];
$allowedContactMethods = ['WhatsApp', 'Telegram', 'Телефон'];
$errors = [];

if (!in_array($service, $allowedServices, true)) {
    $errors[] = 'Выберите услугу.';
}
if (!preg_match('/^\d{4}-\d{2}-\d{2}$/', $preferredDate)) {
    $errors[] = 'Укажите корректную дату.';
} else {
    $date = DateTimeImmutable::createFromFormat('!Y-m-d', $preferredDate);
    $dateErrors = DateTimeImmutable::getLastErrors();
    if ($date === false || ($dateErrors !== false && ($dateErrors['warning_count'] > 0 || $dateErrors['error_count'] > 0))) {
        $errors[] = 'Укажите корректную дату.';
    } elseif ($preferredDate < date('Y-m-d')) {
        $errors[] = 'Выберите актуальную дату.';
    }
}
if (!filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($email) > 254) {
    $errors[] = 'Укажите корректный email.';
}
if ($name === '' || mb_strlen($name) > 150) {
    $errors[] = 'Укажите имя.';
}
if ($phone === '' || mb_strlen($phone) > 50) {
    $errors[] = 'Укажите телефон.';
}
if (!in_array($contactMethod, $allowedContactMethods, true)) {
    $errors[] = 'Выберите способ связи.';
}

if ($errors !== []) {
    jsonResponse(['success' => false, 'message' => implode(' ', $errors)], 422);
}

try {
    $pdo = databaseConnection();
    $statement = $pdo->prepare(
        'INSERT INTO leads (service, preferred_date, email, name, phone, contact_method)
         VALUES (:service, :preferred_date, :email, :name, :phone, :contact_method)'
    );
    $statement->execute([
        'service' => $service,
        'preferred_date' => $preferredDate,
        'email' => $email,
        'name' => $name,
        'phone' => $phone,
        'contact_method' => $contactMethod,
    ]);

    $_SESSION['last_lead_submission'] = time();

    $mailSent = false;
    try {
        $mailSubject = envValue('MAIL_SUBJECT', 'Новая заявка с сайта');
        $mailBody = implode("\n", [
            'Новая заявка с сайта',
            '',
            'Услуга: ' . $service,
            'Желаемая дата: ' . $preferredDate,
            'Имя: ' . $name,
            'Email: ' . $email,
            'Телефон: ' . $phone,
            'Способ связи: ' . $contactMethod,
            'Дата и время заявки: ' . date('Y-m-d H:i:s'),
        ]);
        $headers = [
            'From: ' . envValue('MAIL_FROM', 'no-reply@localhost'),
            'Reply-To: ' . $email,
            'Content-Type: text/plain; charset=UTF-8',
        ];
        $mailSent = @mail(requiredEnv('MAIL_TO'), $mailSubject, $mailBody, implode("\r\n", $headers));
    } catch (Throwable $mailException) {
        error_log('Lead email preparation failed: ' . $mailException->getMessage());
    }

    if (!$mailSent) {
        error_log('Lead #' . (string) $pdo->lastInsertId() . ' was saved, but email delivery failed.');
    }

    jsonResponse(['success' => true]);
} catch (Throwable $exception) {
    error_log('Lead submission failed: ' . $exception->getMessage());
    jsonResponse(['success' => false, 'message' => 'Не удалось сохранить заявку. Попробуйте ещё раз позже.'], 500);
}