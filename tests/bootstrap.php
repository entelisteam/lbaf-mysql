<?php
declare(strict_types=1);

require_once __DIR__ . '/../vendor/autoload.php';

$host = getenv('LBAF_TEST_DB_HOST') ?: '127.0.0.1';
$port = (int)(getenv('LBAF_TEST_DB_PORT') ?: 33306);
$user = getenv('LBAF_TEST_DB_USER') ?: 'root';
$password = getenv('LBAF_TEST_DB_PASSWORD');
$password = $password === false ? '' : $password;
$database = getenv('LBAF_TEST_DB_NAME') ?: 'lbaf_test';

mysqli_report(MYSQLI_REPORT_OFF);

$mysqli = null;
$lastError = '';
$attempts = 20;
for ($i = 1; $i <= $attempts; $i++) {
    $candidate = @new mysqli($host, $user, $password, $database, $port);
    if (!$candidate->connect_errno) {
        $mysqli = $candidate;
        break;
    }
    $lastError = $candidate->connect_error ?? 'unknown error';
    usleep(500_000);
}

if ($mysqli === null) {
    fwrite(STDERR, sprintf(
        "\n[bootstrap] Cannot connect to test database %s@%s:%d/%s after %d attempts.\nLast error: %s\nStart with: composer db:up\n\n",
        $user,
        $host,
        $port,
        $database,
        $attempts,
        $lastError,
    ));
    exit(1);
}

$schema = file_get_contents(__DIR__ . '/Integration/schema.sql');
if ($schema === false) {
    fwrite(STDERR, "[bootstrap] Cannot read schema.sql\n");
    exit(1);
}

if (!$mysqli->multi_query($schema)) {
    fwrite(STDERR, "[bootstrap] Schema load failed: {$mysqli->error}\n");
    exit(1);
}
do {
    if ($result = $mysqli->store_result()) {
        $result->free();
    }
} while ($mysqli->more_results() && $mysqli->next_result());

if ($mysqli->errno) {
    fwrite(STDERR, "[bootstrap] Schema load error: {$mysqli->error}\n");
    exit(1);
}

$mysqli->close();
