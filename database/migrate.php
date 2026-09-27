<?php
if (PHP_SAPI !== 'cli') { http_response_code(404); exit; }
require_once __DIR__ . '/../config/database.php';
function applyUserFlowMigration(PDO $pdo): void {
    $pdo->exec(file_get_contents(__DIR__ . '/user_flows.sql'));
    $column = $pdo->query("SHOW COLUMNS FROM campaigns LIKE 'registration_closes_at'")->fetch();
    if (!$column) $pdo->exec('ALTER TABLE campaigns ADD COLUMN registration_closes_at DATETIME NULL');
}
function applyPortalMigration(PDO $pdo): void {
    applyUserFlowMigration($pdo);
    foreach (['middle_name' => 'VARCHAR(50) NULL', 'profile_image' => 'VARCHAR(80) NULL'] as $column => $definition) {
        if (!$pdo->query("SHOW COLUMNS FROM users LIKE '$column'")->fetch()) $pdo->exec("ALTER TABLE users ADD COLUMN $column $definition");
    }
    if (!$pdo->query("SHOW COLUMNS FROM appointments LIKE 'cancellation_reason'")->fetch()) $pdo->exec('ALTER TABLE appointments ADD COLUMN cancellation_reason VARCHAR(1000) NULL');
    // Remove only an unused legacy role; never reassign existing accounts silently.
    $pdo->exec("DELETE FROM roles WHERE role_name='Recipient' AND NOT EXISTS (SELECT 1 FROM users WHERE users.role_id=roles.role_id)");
    $pdo->exec(file_get_contents(__DIR__ . '/portal.sql'));
    if (!$pdo->query("SHOW COLUMNS FROM campaigns LIKE 'category_id'")->fetch()) {
        $pdo->exec('ALTER TABLE campaigns ADD COLUMN category_id INT NULL, ADD CONSTRAINT fk_campaign_category FOREIGN KEY (category_id) REFERENCES campaign_categories(category_id)');
    }
    if (!$pdo->query("SHOW COLUMNS FROM blood_inventory LIKE 'volume_ml_per_unit'")->fetch()) $pdo->exec('ALTER TABLE blood_inventory ADD COLUMN volume_ml_per_unit INT NOT NULL DEFAULT 450');
    foreach (['donation_id' => 'donation_records', 'request_id' => 'blood_requests'] as $column => $table) {
        if (!$pdo->query("SHOW COLUMNS FROM inventory_transactions LIKE '$column'")->fetch()) $pdo->exec("ALTER TABLE inventory_transactions ADD COLUMN $column INT NULL, ADD CONSTRAINT fk_portal_$column FOREIGN KEY ($column) REFERENCES $table($column)");
    }
}
if (realpath($_SERVER['SCRIPT_FILENAME'] ?? '') === __FILE__) {
    applyPortalMigration(getDBConnection());
    echo "User-flow and role portal migrations applied. Existing records were preserved.\n";
}
