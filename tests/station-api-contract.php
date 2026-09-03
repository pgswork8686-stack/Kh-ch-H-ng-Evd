<?php
declare(strict_types=1);

define('ABSPATH', __DIR__ . '/');
function sanitize_text_field(string $value): string { return trim(strip_tags($value)); }
function wp_generate_password(int $length): string { return substr(str_repeat('A1B2C3D4', 4), 0, $length); }

require_once __DIR__ . '/../wp-content/plugins/ezev-core/includes/class-ezev-core-domain.php';
require_once __DIR__ . '/../wp-content/plugins/ezev-core/includes/class-ezev-core-stations.php';

$source = [
    'station_id' => 'EZEV-VN-001', 'name' => 'Station 1', 'description' => 'Test',
    'address' => '1 Test Street', 'city' => 'HCM', 'region' => 'South', 'country' => 'Vietnam', 'country_code' => 'VN',
    'latitude' => 10.75, 'longitude' => 106.67, 'connector_types' => ['CCS2'], 'max_power_kw' => 180,
    'ports_total' => 4, 'ports_available_manual' => 2, 'opening_hours' => '24/7', 'operational_status_manual' => 'active',
    'amenities' => ['parking'], 'data_mode' => 'manual', 'is_demo' => true, 'organization_id' => 'EZEV-ORG-001',
    'site_id' => 'EZEV-SITE-001', 'public_notes' => '', 'url' => 'https://example.test/stations/1', 'thumbnail' => '',
];
$domain = EZEV_Core_Stations::to_domain_array($source);
$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void { if (!$condition) { $failures[] = $message; } };
$assert($domain['station_id'] === 'EZEV-VN-001', 'Stable station ID missing.');
$assert($domain['location'] === ['lat' => 10.75, 'lng' => 106.67], 'Location schema mismatch.');
$assert($domain['connectors'] === ['CCS2'], 'Connector schema mismatch.');
$assert($domain['ownership']['organization_id'] === 'EZEV-ORG-001', 'Stable organization ID missing.');
$assert($domain['data']['is_demo'] === true, 'Demo flag missing.');
$assert(!array_key_exists('post_id', $domain), 'Public domain schema exposes post_id.');
$assert(!array_key_exists('post_meta', $domain), 'Public domain schema exposes post_meta.');

$rest = file_get_contents(__DIR__ . '/../wp-content/plugins/ezev-core/includes/class-ezev-core-rest.php');
$assert(str_contains($rest, "'/stations/(?P<station_id>[A-Za-z0-9._-]+)'"), 'Stable station detail route missing.');
$assert(str_contains($rest, "'callback' => [self::class, 'create_station']"), 'Station create route missing.');
$assert(str_contains($rest, "'callback' => [self::class, 'update_station']"), 'Station update route missing.');
$assert(str_contains($rest, "'ezev_manage_stations'"), 'Station mutation capability check missing.');

if ($failures) { fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL); exit(1); }
echo "Station API contract checks passed." . PHP_EOL;
