<?php
declare(strict_types=1);

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/user_notification_helper.php';

// This script should run every hour via cron
// Cron: 0 * * * * /usr/bin/php /path/to/api/cron_expiry_warnings.php

try {
    $pdo = db();
    
    // Create expiry warnings for files expiring in 24 hours
    $count = createExpiryWarningBatch($pdo);
    
    echo date('Y-m-d H:i:s') . " - Created {$count} expiry warnings\n";
    
} catch (Throwable $e) {
    error_log('Cron expiry warnings failed: ' . $e->getMessage());
    echo "ERROR: " . $e->getMessage() . "\n";
    exit(1);
}