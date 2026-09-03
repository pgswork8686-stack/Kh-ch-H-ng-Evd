<?php
declare(strict_types=1);

$admin = file_get_contents(__DIR__ . '/../wp-content/plugins/ezev-core/assets/js/admin.js');
$public = file_get_contents(__DIR__ . '/../wp-content/plugins/ezev-core/assets/js/public-map.js');
$shortcodes = file_get_contents(__DIR__ . '/../wp-content/plugins/ezev-core/includes/class-ezev-core-shortcodes.php');
$failures = [];
$assert = static function (bool $condition, string $message) use (&$failures): void { if (!$condition) { $failures[] = $message; } };

foreach ([$admin, $public] as $source) {
    $assert(str_contains($source, 'maps.googleapis.com/maps/api/js'), 'Google Maps JavaScript API loader missing.');
    $assert(str_contains($source, 'libraries=places,marker'), 'Places/marker libraries missing.');
    $assert(!str_contains($source, 'map-demo.jpg'), 'Static fake map fallback detected.');
}
$assert(str_contains($admin, 'google.maps.places.Autocomplete'), 'Admin Places autocomplete missing.');
$assert(str_contains($admin, 'new google.maps.Geocoder'), 'Admin geocoding missing.');
$assert(str_contains($admin, 'gmpDraggable:true'), 'Draggable station marker missing.');
$assert(str_contains($admin, "marker.addListener('dragend'"), 'Dragged coordinates are not synchronized.');
$assert(str_contains($admin, "status==='OK'&&results?.length"), 'Connection test does not verify a successful Geocoding response.');
$assert(str_contains($public, 'navigator.geolocation.getCurrentPosition'), 'Browser geolocation missing.');
$assert(str_contains($shortcodes, "rest_url('ezev/v1/stations')"), 'Public map is not connected to the Station API.');

if ($failures) { fwrite(STDERR, implode(PHP_EOL, $failures) . PHP_EOL); exit(1); }
echo "Google Maps contract checks passed." . PHP_EOL;
