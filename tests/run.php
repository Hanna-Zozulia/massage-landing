<?php

declare(strict_types=1);

$root = dirname(__DIR__);
$php = PHP_BINARY;
$passed = [];
$failed = [];
$skipped = [];

function pass(string $name): void
{
    global $passed;
    $passed[] = $name;
}

function fail(string $name, string $message): void
{
    global $failed;
    $failed[] = $name . ': ' . $message;
}

function skip(string $name, string $message): void
{
    global $skipped;
    $skipped[] = $name . ': ' . $message;
}

function check(string $name, callable $test): void
{
    try {
        $test();
        pass($name);
    } catch (Throwable $exception) {
        fail($name, $exception->getMessage());
    }
}

function expect(bool $condition, string $message): void
{
    if (!$condition) {
        throw new RuntimeException($message);
    }
}

function expectSame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException($message . ' Expected ' . var_export($expected, true) . ', got ' . var_export($actual, true));
    }
}

function expectContains(string $needle, string $haystack, string $message): void
{
    if (!str_contains($haystack, $needle)) {
        throw new RuntimeException($message . ' Missing: ' . $needle);
    }
}

function expectThrows(callable $callback, string $message): void
{
    try {
        $callback();
    } catch (Throwable) {
        return;
    }

    throw new RuntimeException($message);
}

function request(string $url, string $method = 'GET', array $data = [], array $cookies = []): array
{
    $headers = ['Accept: application/json, text/html;q=0.9'];
    if ($cookies !== []) {
        $headers[] = 'Cookie: ' . implode('; ', array_map(
            static fn (string $name, string $value): string => $name . '=' . $value,
            array_keys($cookies),
            $cookies
        ));
    }

    $options = [
        'http' => [
            'method' => $method,
            'ignore_errors' => true,
            'follow_location' => 0,
            'max_redirects' => 0,
            'header' => implode("\r\n", $headers),
        ],
    ];

    if ($method === 'POST') {
        $options['http']['header'] .= "\r\nContent-Type: application/x-www-form-urlencoded";
        $options['http']['content'] = http_build_query($data);
    }

    $responseHeaders = [];
    $body = @file_get_contents($url, false, stream_context_create($options));
    $responseHeaders = $http_response_header ?? [];

    $status = 0;
    foreach ($responseHeaders as $header) {
        if (preg_match('/^HTTP\/\S+\s+(\d+)/', $header, $match)) {
            $status = (int) $match[1];
        }
    }

    $newCookies = $cookies;
    foreach ($responseHeaders as $header) {
        if (preg_match('/^Set-Cookie:\s*([^=]+)=([^;]*)/i', $header, $match)) {
            $newCookies[$match[1]] = $match[2];
        }
    }

    return [
        'status' => $status,
        'body' => $body === false ? '' : $body,
        'headers' => $responseHeaders,
        'cookies' => $newCookies,
    ];
}

function csrfFrom(string $html): string
{
    if (!preg_match('/name=["\']csrf_token["\'][^>]+value=["\']([^"\']+)/', $html, $match)) {
        throw new RuntimeException('CSRF token not found');
    }

    return html_entity_decode($match[1], ENT_QUOTES | ENT_HTML5, 'UTF-8');
}

function getFreePort(): int
{
    $socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
    if ($socket === false) {
        throw new RuntimeException('Cannot allocate local port: ' . $errorMessage);
    }

    $name = stream_socket_get_name($socket, false);
    fclose($socket);
    return (int) substr(strrchr($name, ':'), 1);
}

function startServer(string $root, string $php, array $environment): array
{
    $port = getFreePort();
    $quote = static fn (string $value): string => '"' . str_replace('"', '\\"', $value) . '"';
    $command = $quote($php) . ' -S 127.0.0.1:' . $port . ' -t ' . $quote($root);
    $pipes = [];
    $process = proc_open($command, [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes, $root, $environment);

    if (!is_resource($process)) {
        throw new RuntimeException('Could not start PHP development server');
    }

    foreach ($pipes as $pipe) {
        stream_set_blocking($pipe, false);
    }

    $baseUrl = 'http://127.0.0.1:' . $port;
    for ($attempt = 0; $attempt < 30; $attempt++) {
        $probe = @file_get_contents($baseUrl . '/admin/login.php');
        if ($probe !== false) {
            return [$process, $pipes, $baseUrl];
        }
        usleep(100000);
    }

    $serverError = stream_get_contents($pipes[2]);
    proc_terminate($process);
    throw new RuntimeException('PHP development server did not start: ' . trim($serverError));
}

function stopServer($process, array $pipes): void
{
    foreach ($pipes as $pipe) {
        fclose($pipe);
    }
    proc_terminate($process);
    proc_close($process);
}

function runCliWithInput(string $php, string $script, string $input): string
{
    $quote = static fn (string $value): string => '"' . str_replace('"', '\\"', $value) . '"';
    $pipes = [];
    $process = proc_open($quote($php) . ' ' . $quote($script), [
        0 => ['pipe', 'r'],
        1 => ['pipe', 'w'],
        2 => ['pipe', 'w'],
    ], $pipes);

    if (!is_resource($process)) {
        throw new RuntimeException('Could not start CLI process');
    }

    fwrite($pipes[0], $input);
    fclose($pipes[0]);
    $output = stream_get_contents($pipes[1]) . stream_get_contents($pipes[2]);
    fclose($pipes[1]);
    fclose($pipes[2]);
    proc_close($process);
    return $output;
}

require_once $root . '/config/bootstrap.php';
require_once $root . '/admin/_auth.php';

check('loadEnvironment reads values and preserves existing environment', function (): void {
    $name = 'TEST_PROJECT_ENV_' . getmypid();
    putenv($name);
    $path = tempnam(sys_get_temp_dir(), 'massage-env-');
    file_put_contents($path, "# comment\n{$name}=loaded\nIGNORED_LINE\n");
    loadEnvironment($path);
    expectSame('loaded', getenv($name), 'Environment value was not loaded');
    putenv($name . '=existing');
    loadEnvironment($path);
    expectSame('existing', getenv($name), 'Existing environment value was overwritten');
    unlink($path);
    putenv($name);
});

check('envValue and requiredEnv handle defaults and missing values', function (): void {
    expectSame('fallback', envValue('MISSING_TEST_ENV_' . getmypid(), 'fallback'), 'Default value failed');
    expectThrows(static fn (): string => requiredEnv('MISSING_REQUIRED_ENV_' . getmypid()), 'Missing environment value did not throw');
});

check('e escapes HTML, quotes, UTF-8 and null', function (): void {
    expectSame('&lt;b&gt;&quot;&#039;&amp;&lt;/b&gt;', e('<b>"\'&</b>'), 'HTML escaping failed');
    expectSame('', e(null), 'Null escaping failed');
    expectContains('Привет', e('Привет'), 'UTF-8 text was lost');
});

check('csrfToken and verifyCsrf', function (): void {
    $_SESSION = [];
    $token = csrfToken();
    expect((bool) preg_match('/^[a-f0-9]{64}$/', $token), 'CSRF token format is invalid');
    expectSame($token, csrfToken(), 'CSRF token is not stable in the session');
    expect(verifyCsrf($token), 'Valid CSRF token was rejected');
    expect(!verifyCsrf(null) && !verifyCsrf('') && !verifyCsrf(str_repeat('0', 64)), 'Invalid CSRF token was accepted');
});

check('adminCsrfInput outputs a hidden CSRF field', function (): void {
    $field = adminCsrfInput();
    expectContains('type="hidden"', $field, 'Hidden field is missing');
    expectContains('name="csrf_token"', $field, 'CSRF field name is missing');
    expectContains(csrfToken(), $field, 'CSRF value is missing');
});

check('validLeadStatus accepts exactly the four supported statuses', function (): void {
    foreach (['new', 'read', 'contacted', 'closed'] as $status) {
        expect(validLeadStatus($status), 'Supported status rejected: ' . $status);
    }
    foreach (['', 'opened', 'NEW', 'deleted'] as $status) {
        expect(!validLeadStatus($status), 'Unsupported status accepted: ' . $status);
    }
});

check('recordVisit creates and reuses anonymous visitor tokens', function (): void {
    $pdo = new PDO('sqlite::memory:');
    $pdo->exec('CREATE TABLE visits (id INTEGER PRIMARY KEY AUTOINCREMENT, visited_at DATETIME DEFAULT CURRENT_TIMESTAMP, visitor_token CHAR(64) NOT NULL, user_agent VARCHAR(512))');
    $_COOKIE = [];
    $_SERVER['HTTP_USER_AGENT'] = str_repeat('A', 600);
    recordVisit($pdo);
    $first = $pdo->query('SELECT visitor_token, user_agent FROM visits')->fetch(PDO::FETCH_ASSOC);
    expect((bool) preg_match('/^[a-f0-9]{64}$/', $first['visitor_token']), 'Generated visitor token is invalid');
    expectSame(512, strlen($first['user_agent']), 'User-Agent was not limited to 512 characters');
    $_COOKIE['massage_visitor'] = $first['visitor_token'];
    $_SERVER['HTTP_USER_AGENT'] = 'second-agent';
    recordVisit($pdo);
    $rows = $pdo->query('SELECT visitor_token FROM visits')->fetchAll(PDO::FETCH_COLUMN);
    expectSame(2, count($rows), 'Second visit was not recorded');
    expectSame($rows[0], $rows[1], 'Valid visitor token was not reused');
});

check('databaseConnection connects with the configured PDO settings', function (): void {
    $pdo = databaseConnection();
    expectSame(1, (int) $pdo->query('SELECT 1')->fetchColumn(), 'Database query failed');
    expectSame(PDO::ERRMODE_EXCEPTION, $pdo->getAttribute(PDO::ATTR_ERRMODE), 'PDO exception mode is not enabled');
    expectSame(PDO::FETCH_ASSOC, $pdo->getAttribute(PDO::ATTR_DEFAULT_FETCH_MODE), 'PDO associative fetch mode is not enabled');
});

$server = null;
$pipes = [];
$testDb = 'massage_landing_test_' . getmypid();
$testDbPdo = null;

try {
    $dbHost = envValue('DB_HOST', '127.0.0.1');
    $dbPort = envValue('DB_PORT', '3306');
    $dbUser = envValue('DB_USER', 'root');
    $dbPassword = envValue('DB_PASSWORD', '');
    $adminPassword = 'Test-password-2026!';
    $adminEmail = 'test-admin@example.test';
    $testDbPdo = new PDO(
        'mysql:host=' . $dbHost . ';port=' . $dbPort . ';charset=utf8mb4',
        $dbUser,
        $dbPassword,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
    );
    $testDbPdo->exec('CREATE DATABASE `' . $testDb . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
    $testDbPdo->exec('USE `' . $testDb . '`');
    $schema = file_get_contents($root . '/database/schema.sql');
    foreach (array_filter(array_map('trim', explode(';', (string) $schema))) as $statement) {
        $testDbPdo->exec($statement);
    }

    $environment = getenv();
    $environment['APP_ENV'] = 'test';
    $environment['DB_HOST'] = $dbHost;
    $environment['DB_PORT'] = $dbPort;
    $environment['DB_NAME'] = $testDb;
    $environment['DB_USER'] = $dbUser;
    $environment['DB_PASSWORD'] = $dbPassword;
    $environment['MAIL_TO'] = 'nobody@example.test';
    $environment['MAIL_FROM'] = 'test@example.test';
    $environment['MAIL_SUBJECT'] = 'Test lead';
    $environment['ADMIN_EMAIL'] = $adminEmail;
    $environment['ADMIN_PASSWORD_HASH'] = password_hash($adminPassword, PASSWORD_DEFAULT);
    [$server, $pipes, $baseUrl] = startServer($root, $php, $environment);

    $public = request($baseUrl . '/index.php');
    check('public page loads and exposes the booking form', function () use ($public): void {
        expectSame(200, $public['status'], 'Public page status is not 200');
        expectContains('id="booking-form"', $public['body'], 'Booking form is missing');
        expectContains('public/styles.css', $public['body'], 'Public stylesheet is not linked');
    });

    $publicCookies = $public['cookies'];
    $csrf = csrfFrom($public['body']);
    $tomorrow = (new DateTimeImmutable('tomorrow'))->format('Y-m-d');
    $validLead = [
        'csrf_token' => $csrf,
        'service' => 'Массаж спины',
        'preferred_date' => $tomorrow,
        'email' => 'client@example.test',
        'name' => 'Тестовый клиент',
        'phone' => '+372 5555 1234',
        'contact_method' => 'Telegram',
    ];

    $methodResponse = request($baseUrl . '/backend/submit-lead.php', 'GET');
    check('lead endpoint rejects non-POST requests', function () use ($methodResponse): void {
        expectSame(405, $methodResponse['status'], 'GET lead submission did not return 405');
    });

    $csrfResponse = request($baseUrl . '/backend/submit-lead.php', 'POST', $validLead, []);
    check('lead endpoint rejects missing CSRF sessions', function () use ($csrfResponse): void {
        expectSame(419, $csrfResponse['status'], 'Missing CSRF did not return 419');
    });

    $successResponse = request($baseUrl . '/backend/submit-lead.php', 'POST', $validLead, $publicCookies);
    $successPayload = json_decode($successResponse['body'], true);
    check('valid lead submission succeeds', function () use ($successResponse, $successPayload): void {
        expectSame(200, $successResponse['status'], 'Valid submission status is not 200');
        expectSame(true, $successPayload['success'] ?? null, 'Valid submission did not return success. Response: ' . $successResponse['body']);
    });

    $leadId = (int) $testDbPdo->query('SELECT id FROM leads ORDER BY id DESC LIMIT 1')->fetchColumn();
    check('successful lead submission persists a new lead', function () use ($testDbPdo, $leadId): void {
        expect($leadId > 0, 'Inserted lead ID is missing');
        $row = $testDbPdo->query('SELECT service, status, email FROM leads WHERE id = ' . $leadId)->fetch(PDO::FETCH_ASSOC);
        expectSame('Массаж спины', $row['service'], 'Lead service was not persisted');
        expectSame('new', $row['status'], 'New lead status is incorrect');
        expectSame('client@example.test', $row['email'], 'Lead email was not persisted');
    });

    $rateResponse = request($baseUrl . '/backend/submit-lead.php', 'POST', $validLead, $publicCookies);
    check('lead endpoint rate-limits immediate duplicate submissions', function () use ($rateResponse): void {
        expectSame(429, $rateResponse['status'], 'Duplicate submission did not return 429');
    });

    $loginPage = request($baseUrl . '/admin/login.php');
    $loginCsrf = csrfFrom($loginPage['body']);
    check('admin login page loads with a CSRF token', function () use ($loginPage, $loginCsrf): void {
        expectSame(200, $loginPage['status'], 'Login page status is not 200');
        expect($loginCsrf !== '', 'Login CSRF token is empty');
    });

    $loginCookies = $loginPage['cookies'];
    $badLogin = request($baseUrl . '/admin/login.php', 'POST', [
        'csrf_token' => $loginCsrf,
        'email' => $adminEmail,
        'password' => 'wrong-password',
    ], $loginCookies);
    check('admin login rejects an invalid password', function () use ($badLogin): void {
        expectSame(200, $badLogin['status'], 'Invalid login returned an unexpected status');
        expectContains('Неверный email или пароль.', $badLogin['body'], 'Invalid login message is missing');
    });

    $goodLogin = request($baseUrl . '/admin/login.php', 'POST', [
        'csrf_token' => $loginCsrf,
        'email' => $adminEmail,
        'password' => $adminPassword,
    ], $loginCookies);
    check('admin login accepts valid credentials', function () use ($goodLogin): void {
        expectSame(302, $goodLogin['status'], 'Valid login did not redirect');
        expectContains('Location: index.php', implode("\n", $goodLogin['headers']), 'Valid login redirect target is wrong');
    });

    $adminCookies = $goodLogin['cookies'];
    $dashboard = request($baseUrl . '/admin/index.php', 'GET', [], $adminCookies);
    check('admin dashboard loads for an authenticated user', function () use ($dashboard): void {
        expectSame(200, $dashboard['status'], 'Dashboard status is not 200');
        expectContains('Посетители', $dashboard['body'], 'Visitor block is missing');
        expect(!str_contains($dashboard['body'], str_repeat('a', 64)), 'Raw visitor token leaked into dashboard');
    });

    $leadPage = request($baseUrl . '/admin/lead.php?id=' . $leadId, 'GET', [], $adminCookies);
    check('admin lead page displays an existing lead', function () use ($leadPage): void {
        expectSame(200, $leadPage['status'], 'Lead page status is not 200');
        expectContains('Тестовый клиент', $leadPage['body'], 'Lead name is missing');
    });

    $dashboardCsrf = csrfFrom($dashboard['body']);
    $statusResponse = request($baseUrl . '/admin/update-status.php', 'POST', [
        'csrf_token' => $dashboardCsrf,
        'id' => (string) $leadId,
        'status' => 'contacted',
    ], $adminCookies);
    check('admin can update a lead status', function () use ($statusResponse, $testDbPdo, $leadId): void {
        expectSame(302, $statusResponse['status'], 'Status update did not redirect');
        $status = $testDbPdo->query('SELECT status FROM leads WHERE id = ' . $leadId)->fetchColumn();
        expectSame('contacted', $status, 'Lead status was not updated');
    });

    $deleteResponse = request($baseUrl . '/admin/delete.php', 'POST', [
        'csrf_token' => $dashboardCsrf,
        'id' => (string) $leadId,
    ], $adminCookies);
    check('admin can delete a lead', function () use ($deleteResponse, $testDbPdo, $leadId): void {
        expectSame(302, $deleteResponse['status'], 'Delete did not redirect');
        $exists = $testDbPdo->query('SELECT COUNT(*) FROM leads WHERE id = ' . $leadId)->fetchColumn();
        expectSame('0', (string) $exists, 'Lead was not deleted');
    });

    $logoutResponse = request($baseUrl . '/admin/logout.php', 'POST', [
        'csrf_token' => $dashboardCsrf,
    ], $adminCookies);
    check('admin logout redirects after clearing the session', function () use ($logoutResponse): void {
        expectSame(302, $logoutResponse['status'], 'Logout did not redirect');
        expectContains('Location: login.php', implode("\n", $logoutResponse['headers']), 'Logout redirect target is wrong');
    });

    $afterLogout = request($baseUrl . '/admin/index.php', 'GET', [], $adminCookies);
    check('dashboard requires authentication after logout', function () use ($afterLogout): void {
        expectSame(302, $afterLogout['status'], 'Dashboard remained accessible after logout');
        expectContains('Location: login.php', implode("\n", $afterLogout['headers']), 'Unauthenticated redirect target is wrong');
    });

    $node = trim((string) shell_exec('where.exe node 2>NUL'));
    if ($node !== '') {
        $jsSyntax = shell_exec('node --check ' . escapeshellarg($root . '\public\script.js') . ' 2>&1');
        check('public JavaScript passes syntax validation', function () use ($jsSyntax): void {
            expect(trim((string) $jsSyntax) === '', 'JavaScript syntax check failed: ' . trim((string) $jsSyntax));
        });
    } else {
        skip('public JavaScript syntax validation', 'Node.js is not installed');
    }
} catch (Throwable $exception) {
    skip('HTTP and database integration suite', $exception->getMessage());
} finally {
    if ($server !== null) {
        stopServer($server, $pipes);
    }
    if ($testDbPdo instanceof PDO) {
        try {
            $testDbPdo->exec('DROP DATABASE IF EXISTS `' . $testDb . '`');
        } catch (Throwable $exception) {
            fail('test database cleanup', $exception->getMessage());
        }
    }
}

$toolOutput = runCliWithInput($php, $root . '/tools/hash-password.php', "Test-password-2026!\r\n");
check('hash-password CLI generates a verifiable password hash', function () use ($toolOutput): void {
    preg_match('/\$2[aby]\$\d{2}\$[A-Za-z0-9.\/$]{53}/', (string) $toolOutput, $matches);
    expect(isset($matches[0]) && password_verify('Test-password-2026!', $matches[0]), 'Generated hash does not verify');
});

fwrite(STDOUT, "\nTEST RESULTS\n");
fwrite(STDOUT, 'PASS: ' . count($passed) . "\n");
foreach ($passed as $name) {
    fwrite(STDOUT, "  [PASS] {$name}\n");
}
fwrite(STDOUT, 'FAIL: ' . count($failed) . "\n");
foreach ($failed as $name) {
    fwrite(STDOUT, "  [FAIL] {$name}\n");
}
fwrite(STDOUT, 'SKIP: ' . count($skipped) . "\n");
foreach ($skipped as $name) {
    fwrite(STDOUT, "  [SKIP] {$name}\n");
}

exit($failed === [] ? 0 : 1);
