<?php
declare(strict_types=1);

$path = __DIR__ . '/../wp-content/plugins/ezev-core/assets/data/demo-stations.json';
$payload = json_decode((string) file_get_contents($path), true);
$stations = is_array($payload['stations'] ?? null) ? $payload['stations'] : [];
$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void { if (!$condition) { $failures[] = $message; } };

$assert(count($stations) === 60, 'Demo dataset must contain exactly 60 stations.');
$counts = array_count_values(array_column($stations, 'country_code'));
foreach (['VN', 'PH', 'CN'] as $country) { $assert(($counts[$country] ?? 0) === 20, "$country must contain exactly 20 demo stations."); }
$ids = array_column($stations, 'station_id');
$assert(count(array_unique($ids)) === 60, 'Demo station IDs must be unique.');
foreach ($stations as $index => $station) {
    $label = $station['station_id'] ?? "row $index";
    $assert(!empty($station['is_demo']), "$label is not marked is_demo=true.");
    $assert(in_array(($station['data_mode'] ?? ''), ['manual', 'demo_manual'], true), "$label must declare manual/demo data mode.");
    $assert(is_numeric($station['latitude'] ?? null) && (float) $station['latitude'] >= -90 && (float) $station['latitude'] <= 90, "$label has invalid latitude.");
    $assert(is_numeric($station['longitude'] ?? null) && (float) $station['longitude'] >= -180 && (float) $station['longitude'] <= 180, "$label has invalid longitude.");
}

$seed = file_get_contents(__DIR__ . '/../wp-content/plugins/ezev-core/includes/class-ezev-core-stations.php');
$assert(str_contains($seed, "\$row['is_demo'] = true"), 'Seeder does not force the demo flag.');
$assert(str_contains($seed, 'find_by_station_id'), 'Seeder is not idempotent by stable station ID.');

if ($failures) { fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL); exit(1); }
echo "Demo station dataset checks passed." . PHP_EOL;
