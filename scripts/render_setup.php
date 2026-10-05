<?php
// Opt-in deployment setup. Never import personal local database records.
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/database.php';
try {
    $pdo = getDBConnection();
    // Serialize startup setup if two deploys overlap.
    if ((int)$pdo->query("SELECT GET_LOCK('hemopulse_deploy_setup', 60)")->fetchColumn() !== 1) {
        throw new RuntimeException('Another deployment is still initializing the database.');
    }
    try {
        $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
        if (!$tables) {
            if (getenv('HEMOPULSE_INIT_EMPTY_DB') !== '1') {
                throw new RuntimeException('Import the clean schema, or explicitly set HEMOPULSE_INIT_EMPTY_DB=1 for an empty dedicated database.');
            }
            $pdo->exec(file_get_contents(__DIR__ . '/../deployment/schema.sql'));
            echo "Clean schema imported.\n";
        } elseif (!in_array('users', $tables, true) || !in_array('roles', $tables, true)) {
            throw new RuntimeException('Database is not an initialized HemoPulse database. No schema was imported.');
        }
        require_once __DIR__ . '/../database/migrate.php';
        applyPortalMigration($pdo);
        echo "Migrations applied.\n";
        if (getenv('HEMOPULSE_ADMIN_EMAIL') && getenv('HEMOPULSE_ADMIN_PASSWORD')) {
            $admins = $pdo->query("SELECT COUNT(*) FROM users u JOIN roles r ON r.role_id=u.role_id WHERE r.role_name='Admin' AND u.account_status='Active'")->fetchColumn();
            if (!(int)$admins) require __DIR__ . '/create_admin.php';
            else echo "Existing administrator preserved.\n";
        }
    } finally {
        $pdo->query("SELECT RELEASE_LOCK('hemopulse_deploy_setup')");
    }
} catch (Throwable $e) {
    // No credentials or server diagnostic details are printed to deploy logs.
    fwrite(STDERR, "Deployment setup failed. Verify the dedicated MariaDB database, schema, credentials and TLS settings.\n");
    exit(1);
}
