<?php
require_once 'includes/db.php';

try {
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS Security_Log (
            log_ID INT AUTO_INCREMENT PRIMARY KEY,
            event_type VARCHAR(50) NOT NULL,
            username VARCHAR(100) NULL,
            user_ID INT NULL,
            ip_address VARCHAR(45) NULL,
            success TINYINT(1) NOT NULL DEFAULT 0,
            details VARCHAR(255) NULL,
            created_at DATETIME NOT NULL
        )
    ");
    echo "✓ Security_Log table ready.\n";

    // Check if pay_method column exists
    $check = $pdo->query("SHOW COLUMNS FROM Payment LIKE 'pay_method'");
    if ($check->rowCount() === 0) {
        $pdo->exec("ALTER TABLE Payment ADD COLUMN pay_method VARCHAR(50) NULL AFTER pay_date");
        echo "✓ pay_method column added to Payment table.\n";
    } else {
        echo "✓ pay_method column already exists.\n";
    }
} catch (Exception $e) {
    echo "Error: " . $e->getMessage() . "\n";
}
?>
