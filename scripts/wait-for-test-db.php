<?php

declare(strict_types=1);

/*
 * Readiness check for the isolated PostgreSQL test database.
 *
 * Usage: php scripts/wait-for-test-db.php [max_seconds=60]
 * Reads DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD from the environment.
 * Retries until the server accepts connections and a query succeeds, then verifies the
 * connected database name and that it is an isolated *_test database.
 * Exit codes: 0 ready, 1 not ready before timeout, 2 unsafe or invalid configuration.
 * Never prints the password.
 */

$host = getenv('DB_HOST') ?: '';
$port = getenv('DB_PORT') ?: '5432';
$database = getenv('DB_DATABASE') ?: '';
$username = getenv('DB_USERNAME') ?: '';
$password = getenv('DB_PASSWORD');
$maxSeconds = max(1, (int) ($argv[1] ?? 60));

if ($host === '' || $database === '' || $username === '' || $password === false || !ctype_digit($port)) {
    fwrite(STDERR, "Test DB readiness: DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME and DB_PASSWORD are required.\n");
    exit(2);
}
if (preg_match('/(^|_)test$/D', $database) !== 1) {
    fwrite(STDERR, "Test DB readiness: refusing non-test database '{$database}'.\n");
    exit(2);
}

$dsn = sprintf('pgsql:host=%s;port=%d;dbname=%s;connect_timeout=3', $host, (int) $port, $database);
$deadline = microtime(true) + $maxSeconds;
$attempt = 0;
$lastError = 'unknown';

while (microtime(true) < $deadline) {
    $attempt++;
    try {
        $pdo = new PDO($dsn, $username, $password, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $row = $pdo->query('SELECT current_database() AS db, inet_server_port() AS port, 1 AS ok')->fetch(PDO::FETCH_ASSOC);
        if (($row['ok'] ?? null) != 1 || ($row['db'] ?? null) !== $database) {
            fwrite(STDERR, "Test DB readiness: connected to an unexpected database.\n");
            exit(2);
        }
        fwrite(STDOUT, sprintf("Test DB ready: %s:%s/%s (attempt %d)\n", $host, $port, $row['db'], $attempt));
        exit(0);
    } catch (PDOException $exception) {
        // SQLSTATE only: never echo DSN credentials.
        $lastError = (string) $exception->getCode();
        usleep(1_000_000);
    }
}

fwrite(STDERR, sprintf("Test DB NOT ready after %ds (%d attempts, last SQLSTATE %s).\n", $maxSeconds, $attempt, $lastError));
exit(1);
