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
    "/define\(\s*['\"]EZEVO_DB_VERSION['\"]\s*,\s*['\"]1\.1\.0['\"]\s*\)/",
    "EZEVO_DB_VERSION constant is defined as 1.1.0"
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
    'wp-content/plugins/ezev-operations/includes/class-ezevo-db.php',
    "/provider_station_time/",
    "Operations DB defines provider_station_time index"
);

if (!empty($errors)) {
    echo "\nSTATIC CONTRACT FAILURES:\n";
    foreach ($errors as $e) {
        echo "  [FAIL] $e\n";
    }
    exit(1);
}

echo "\nAll static contracts PASSED.\n";
exit(0);
