<?php
// Disposable database checks for Render startup initialization.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/database.php';
$name = 'hemopulse_render_test_' . bin2hex(random_bytes(6));
$server = new PDO('mysql:host=' . DB_HOST . ';port=' . DB_PORT . ';charset=utf8mb4', DB_USER, DB_PASS, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$checks = 0;
function setupCheck(bool $ok, string $message): void {
    global $checks;
    if (!$ok) throw new RuntimeException($message);
    $checks++;
    echo "PASS: $message\n";
}
function runRenderSetup(): int {
    $process = proc_open([PHP_BINARY, __DIR__ . '/../scripts/render_setup.php'], [0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']], $pipes);
    if (!is_resource($process)) throw new RuntimeException('Cannot start setup process.');
    fclose($pipes[0]);
    stream_get_contents($pipes[1]); fclose($pipes[1]);
    stream_get_contents($pipes[2]); fclose($pipes[2]);
    return proc_close($process);
}
try {
    $server->exec("CREATE DATABASE `$name` CHARACTER SET utf8mb4");
    putenv('HEMOPULSE_DB_NAME=' . $name);
    putenv('HEMOPULSE_INIT_EMPTY_DB=0');
    putenv('HEMOPULSE_ADMIN_EMAIL='); putenv('HEMOPULSE_ADMIN_PASSWORD=');
    setupCheck(runRenderSetup() !== 0, 'Empty database requires explicit initialization');
    setupCheck((int)$server->query("SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='$name'")->fetchColumn() === 0, 'Rejected setup leaves empty database untouched');
    putenv('HEMOPULSE_INIT_EMPTY_DB=1');
    putenv('HEMOPULSE_ADMIN_EMAIL=render-admin@example.test');
    putenv('HEMOPULSE_ADMIN_PASSWORD=A!9' . bin2hex(random_bytes(12)));
    setupCheck(runRenderSetup() === 0, 'Fresh setup imports schema, migrations and administrator');
    setupCheck((int)$server->query("SELECT COUNT(*) FROM `$name`.users")->fetchColumn() === 1, 'Only one setup account created');
    setupCheck(runRenderSetup() === 0, 'Repeated setup succeeds');
    setupCheck((int)$server->query("SELECT COUNT(*) FROM `$name`.users")->fetchColumn() === 1, 'Repeated setup preserves administrator without duplicates');
    echo "$checks Render setup checks passed.\n";
} finally {
    $server->exec("DROP DATABASE IF EXISTS `$name`");
    putenv('HEMOPULSE_ADMIN_EMAIL'); putenv('HEMOPULSE_ADMIN_PASSWORD');
}
