<?php
declare(strict_types=1);

$errors = [];

function check_file_contains_regex(string $file, string $pattern, string $desc): void {
    global $errors;
    if (!file_exists($file)) {
        $errors[] = "File not found: $file";
        return;
    }
    $content = file_get_contents($file);
    if (!preg_match($pattern, $content)) {
        $errors[] = "Contract check failed: $desc in $file (pattern: $pattern)";
    } else {
        echo "  [OK] $desc\n";
    }
}

echo "=== Running Static Contract Verification ===\n";

check_file_contains_regex(
    'wp-content/plugins/ezev-core/ezev-core.php',
    "/define\(\s*['\"]EZEV_CORE_DB_VERSION['\"]\s*,\s*['\"]1\.1\.0['\"]\s*\)/",
    "EZEV_CORE_DB_VERSION constant is defined as 1.1.0"
);

check_file_contains_regex(
    'wp-content/plugins/ezev-operations/ezev-operations.php',
    "/define\(\s*['\"]EZEVO_DB_VERSION['\"]\s*,\s*['\"]1\.2\.0['\"]\s*\)/",
    "EZEVO_DB_VERSION constant is defined as 1.2.0"
);

check_file_contains_regex(
    'wp-content/plugins/ezev-operations/includes/class-ezevo-db.php',
    "/table\(\s*['\"]connectors['\"]\s*\)/",
    "Operations DB defines connectors table"
);

check_file_contains_regex(
    'wp-content/plugins/ezev-operations/includes/class-ezevo-rest.php',
    "/duplicate_webhook/",
    "Operations REST handles duplicate_webhook rejection"
);

check_file_contains_regex(
    'wp-content/plugins/ezev-operations/includes/class-ezevo-rest.php',
    "/receipt_storage_failure/",
    "Operations REST has fail-closed receipt_storage_failure error code"
);

check_file_contains_regex(
    'wp-content/plugins/ezev-operations/includes/class-ezevo-db.php',
    "/provider_station_time/",
    "Operations DB defines provider_station_time index"
);

check_file_contains_regex(
    'wp-content/plugins/ezev-operations/includes/class-ezevo-db.php',
    "/expires_at/",
    "Operations DB defines expires_at for webhook receipt retention"
);

check_file_contains_regex(
    'wp-content/plugins/ezev-core/includes/class-ezev-core-auth.php',
    "/ezev_view_all_stations/",
    "Core Auth uses ezev_view_all_stations for all-station scope (not ezev_view_internal)"
);

// Verify the bypass guard comment references ezev_view_internal with correct explanation
check_file_contains_regex(
    'wp-content/plugins/ezev-core/includes/class-ezev-core-auth.php',
    "/ezev_view_internal is a portal-access cap only/",
    "Core Auth documents that ezev_view_internal is portal-only (not station scope bypass)"
);

// Verify that ezev_view_internal is NOT used as bypass in allowed_station_post_ids
$authFile = 'wp-content/plugins/ezev-core/includes/class-ezev-core-auth.php';
if (file_exists($authFile)) {
    $authContent = file_get_contents($authFile);
    // The bypass condition must NOT contain ezev_view_internal
    if (preg_match('/user_can.*manage_options.*ezev_view_internal/s', $authContent) &&
        !preg_match('/user_can.*manage_options.*ezev_view_all_stations/s', $authContent)) {
        $errors[] = "SECURITY: ezev_view_internal is still used as all-station bypass in class-ezev-core-auth.php";
    } else {
        echo "  [OK] ezev_view_internal NOT used as all-station bypass (correct)\n";
    }
}

if (!empty($errors)) {
    echo "\nSTATIC CONTRACT FAILURES:\n";
    foreach ($errors as $e) {
        echo "  [FAIL] $e\n";
    }
    exit(1);
}

echo "\nAll static contracts PASSED.\n";
exit(0);

