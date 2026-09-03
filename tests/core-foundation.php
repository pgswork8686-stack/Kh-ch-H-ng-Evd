<?php
declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');

function sanitize_text_field(string $value): string { return trim(strip_tags($value)); }
function wp_generate_password(int $length): string { return substr(str_repeat('A1B2C3D4', 4), 0, $length); }

require_once __DIR__ . '/../wp-content/plugins/ezev-core/includes/class-ezev-core-domain.php';

$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void {
    if (!$condition) { $failures[] = $message; }
};

$assert(EZEV_Core_Domain::normalize_id(' ezev org / hcm 01 ') === 'EZEV-ORG-HCM-01', 'Stable ID normalization failed.');
$assert(EZEV_Core_Domain::normalize_id('EZEV_SITE.01') === 'EZEV_SITE.01', 'Allowed stable ID characters changed.');
$assert(str_starts_with(EZEV_Core_Domain::new_id('membership'), 'EZEV-MEM-'), 'Membership ID prefix is invalid.');

$plugin = file_get_contents(__DIR__ . '/../wp-content/plugins/ezev-core/ezev-core.php');
$readme = file_get_contents(__DIR__ . '/../wp-content/plugins/ezev-core/readme.txt');
$db = file_get_contents(__DIR__ . '/../wp-content/plugins/ezev-core/includes/class-ezev-core-db.php');
$assert(str_contains($plugin, "Version: 4.1.0"), 'Plugin header version is not synchronized.');
$assert(str_contains($plugin, "EZEV_CORE_VERSION', '4.1.0"), 'Runtime plugin version is not synchronized.');
$assert(str_contains($readme, 'Version: 4.1.0'), 'Readme version is not synchronized.');
$assert(str_contains($plugin, "EZEV_CORE_DB_VERSION', '1.1.0"), 'Schema version is missing.');
foreach (['organization_id VARCHAR', 'site_id VARCHAR', 'membership_id VARCHAR', 'station_id VARCHAR'] as $column) {
    $assert(str_contains($db, $column), "Schema is missing $column.");
}
$assert(str_contains($db, 'backfill_business_ids'), 'Stable-ID migration backfill is missing.');
$assert(str_contains($db, 'maybe_upgrade'), 'Runtime migration trigger is missing.');

if ($failures) {
    fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL);
    exit(1);
}

echo "Core foundation checks passed." . PHP_EOL;
