<?php
require_once 'includes/db.php';

try {
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
