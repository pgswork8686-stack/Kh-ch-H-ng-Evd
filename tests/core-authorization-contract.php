<?php
declare(strict_types=1);

$auth = file_get_contents(__DIR__ . '/../wp-content/plugins/ezev-core/includes/class-ezev-core-auth.php');
$rest = file_get_contents(__DIR__ . '/../wp-content/plugins/ezev-core/includes/class-ezev-core-rest.php');
$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void { if (!$condition) { $failures[] = $message; } };

$assert(str_contains($auth, "current_user_can('manage_options')"), 'wp-admin gate does not require administrator capability.');
$assert(str_contains($auth, 'allowed_station_keys'), 'Stable station scope resolver missing.');
$assert(str_contains($auth, 'can_access_station'), 'Direct resource authorization missing.');
$assert(str_contains($auth, "['owner', 'admin', 'operations', 'site_manager']"), 'Write-role policy missing.');
$assert(str_contains($rest, "'/me/stations'"), 'Scoped station collection route missing.');
$assert(str_contains($rest, "'/me/stations/(?P<station_id>[A-Za-z0-9._-]+)'"), 'Scoped station detail route missing.');
$assert(str_contains($rest, "'ezev_station_forbidden'"), 'Stable 403 resource error missing.');
$assert(str_contains($rest, "'status' => 403"), 'HTTP 403 enforcement missing.');
$assert(str_contains($rest, "wp_create_nonce('wp_rest')"), 'Frontend REST nonce missing after login.');

if ($failures) { fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL); exit(1); }
echo "Core authorization contract checks passed." . PHP_EOL;
