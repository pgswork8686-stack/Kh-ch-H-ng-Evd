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

check_file_contains_regex(
    'wp-content/plugins/ezev-operations/ezev-operations.php',
    "/define\(\s*['\"]EZEVO_VERSION['\"]\s*,\s*['\"]4\.1\.0['\"]\s*\)/",
    "EZEVO_VERSION constant is defined as 4.1.0"
);

// Verify Core CRUD and Invitations
check_file_contains_regex(
    'wp-content/plugins/ezev-core/includes/class-ezev-core-rest.php',
    "/'\/organizations'/",
    "Core REST registers /organizations route"
);

check_file_contains_regex(
    'wp-content/plugins/ezev-core/includes/class-ezev-core-rest.php',
    "/'\/sites'/",
    "Core REST registers /sites route"
);

check_file_contains_regex(
    'wp-content/plugins/ezev-core/includes/class-ezev-core-rest.php',
    "/invitations/",
    "Core REST registers invitations routes"
);

// Verify Operations Detail, Maintenance, and Reports
check_file_contains_regex(
    'wp-content/plugins/ezev-operations/includes/class-ezevo-rest.php',
    "/'\/maintenance'/",
    "Operations REST registers /maintenance route"
);

check_file_contains_regex(
    'wp-content/plugins/ezev-operations/includes/class-ezevo-rest.php',
    "/'\/reports\/summary'/",
    "Operations REST registers /reports/summary route"
);

check_file_contains_regex(
    'wp-content/plugins/ezev-operations/includes/class-ezevo-rest.php',
    "/'\/reports\/performance'/",
    "Operations REST registers /reports/performance route"
);

check_file_contains_regex(
    'wp-content/plugins/ezev-operations/includes/class-ezevo-rest.php',
    "/can_view_telemetry/",
    "Operations REST implements granular can_view_telemetry capability"
);

check_file_contains_regex(
    'wp-content/plugins/ezev-operations/includes/class-ezevo-sync.php',
    "/ezevo_cleanup_receipts_event/",
    "Operations Sync registers scheduled receipt cleanup event"
);

// GATE 3.1: Reusable Core Authorizer & Tenancy Integrity
check_file_contains_regex(
    'wp-content/plugins/ezev-core/includes/class-ezev-core-auth.php',
    '/function\s+can_read_organization/',
    'Core Auth defines reusable can_read_organization authorizer'
);

check_file_contains_regex(
    'wp-content/plugins/ezev-core/includes/class-ezev-core-auth.php',
    '/function\s+can_manage_organization/',
    'Core Auth defines reusable can_manage_organization authorizer'
);

check_file_contains_regex(
    'wp-content/plugins/ezev-core/includes/class-ezev-core-auth.php',
    '/function\s+can_read_site/',
    'Core Auth defines reusable can_read_site authorizer'
);

check_file_contains_regex(
    'wp-content/plugins/ezev-core/includes/class-ezev-core-rest.php',
    '/cross_organization_mismatch/',
    'Core REST enforces cross_organization_mismatch on Site/Station scope assignment'
);

check_file_contains_regex(
    'wp-content/plugins/ezev-core/includes/class-ezev-core-rest.php',
    '/resource_has_dependencies/',
    'Core REST enforces resource_has_dependencies on Organization/Site safe deletion'
);

check_file_contains_regex(
    'wp-content/plugins/ezev-core/includes/class-ezev-core-rest.php',
    '/email_mismatch/',
    'Core REST verifies recipient email matching on invitation acceptance'
);

check_file_contains_regex(
    'wp-content/plugins/ezev-core/includes/class-ezev-core-rest.php',
    '/invitation_already_claimed/',
    'Core REST implements atomic single-use claim against race conditions'
);

check_file_contains_regex(
    'wp-content/plugins/ezev-operations/includes/class-ezevo-rest.php',
    '/calculate_freshness/',
    'Operations REST implements calculate_freshness with real storage timestamps'
);

check_file_contains_regex(
    'wp-content/plugins/ezev-operations/includes/class-ezevo-rest.php',
    '/data_mode/',
    'Operations REST returns data_mode metadata distinguishing manual/demo from API'
);

// Verify that ezev_view_internal is NOT used as bypass in allowed_station_post_ids
$authFile = 'wp-content/plugins/ezev-core/includes/class-ezev-core-auth.php';
if (file_exists($authFile)) {
    $authContent = file_get_contents($authFile);
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

