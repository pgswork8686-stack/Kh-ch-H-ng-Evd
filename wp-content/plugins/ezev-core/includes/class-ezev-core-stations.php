<?php
if (!defined('ABSPATH')) { exit; }

final class EZEV_Core_Stations {
    public const POST_TYPE = 'ezev_station';

    public static function init(): void {
        add_action('init', [self::class, 'register_post_type']);
        add_action('save_post_' . self::POST_TYPE, [self::class, 'save_station_meta'], 10, 2);
    }

    public static function register_post_type(): void {
        register_post_type(self::POST_TYPE, [
            'labels' => [
                'name' => 'EZEV Stations',
                'singular_name' => 'EZEV Station',
            ],
            'public' => true,
            'show_ui' => false,
            'show_in_rest' => false,
            'has_archive' => false,
            'rewrite' => ['slug' => 'stations', 'with_front' => false],
            'supports' => ['title', 'editor', 'thumbnail'],
            'capability_type' => 'post',
            'map_meta_cap' => true,
        ]);
    }

    public static function meta_keys(): array {
        return [
            'station_id', 'country_code', 'country', 'city', 'region', 'address',
            'latitude', 'longitude', 'connector_types', 'max_power_kw', 'ports_total',
            'ports_available_manual', 'opening_hours', 'operational_status_manual',
            'amenities', 'data_mode', 'is_demo', 'organization_id', 'site_id', 'public_notes'
        ];
    }

    public static function save_station_meta(int $post_id, WP_Post $post): void {
        if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) { return; }
        if (!current_user_can('edit_post', $post_id)) { return; }
        if (empty($_POST['ezev_station_nonce']) || !wp_verify_nonce(sanitize_text_field(wp_unslash($_POST['ezev_station_nonce'])), 'ezev_save_station')) { return; }
        self::save_fields($post_id, $_POST);
    }

    public static function save_fields(int $post_id, array $source): void {
        $text = ['station_id','country_code','country','city','region','address','opening_hours','operational_status_manual','data_mode','public_notes'];
        foreach ($text as $key) {
            if (isset($source[$key])) {
                $value = $key === 'public_notes' ? sanitize_textarea_field(wp_unslash($source[$key])) : sanitize_text_field(wp_unslash($source[$key]));
                update_post_meta($post_id, '_ezev_' . $key, $value);
            }
        }
        foreach (['latitude','longitude','max_power_kw'] as $key) {
            if (isset($source[$key])) { update_post_meta($post_id, '_ezev_' . $key, (float) $source[$key]); }
        }
        foreach (['ports_total','ports_available_manual'] as $key) {
            if (isset($source[$key])) { update_post_meta($post_id, '_ezev_' . $key, absint($source[$key])); }
        }
        foreach (['organization_id','site_id'] as $key) {
            if (isset($source[$key])) { update_post_meta($post_id, '_ezev_' . $key, EZEV_Core_Domain::normalize_id((string) wp_unslash($source[$key]))); }
        }
        foreach (['connector_types','amenities'] as $key) {
            $value = isset($source[$key]) ? $source[$key] : [];
            if (!is_array($value)) { $value = preg_split('/[,\n]+/', (string) $value); }
            $value = array_values(array_filter(array_map('sanitize_text_field', array_map('wp_unslash', $value))));
            update_post_meta($post_id, '_ezev_' . $key, $value);
        }
        if (isset($source['is_demo'])) { update_post_meta($post_id, '_ezev_is_demo', (bool) $source['is_demo']); }
    }

    public static function create(array $data): int|WP_Error {
        $station_id = sanitize_text_field($data['station_id'] ?? '');
        if ($station_id === '') { return new WP_Error('missing_station_id', 'Station ID is required.'); }
        $existing = self::find_by_station_id($station_id);
        $post_data = [
            'post_type' => self::POST_TYPE,
            'post_status' => sanitize_key($data['post_status'] ?? 'publish'),
            'post_title' => sanitize_text_field($data['name'] ?? $data['post_title'] ?? $station_id),
            'post_content' => sanitize_textarea_field($data['description'] ?? ''),
        ];
        if ($existing) {
            $post_data['ID'] = $existing;
            $post_id = wp_update_post($post_data, true);
        } else {
            $post_id = wp_insert_post($post_data, true);
        }
        if (is_wp_error($post_id)) { return $post_id; }
        self::save_fields((int) $post_id, $data);
        EZEV_Core_DB::log($existing ? 'station_updated' : 'station_created', 'station', (string) $post_id, ['station_id' => $station_id]);
        return (int) $post_id;
    }

    public static function delete_demo(): int {
        $ids = get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => 'any',
            'numberposts' => -1,
            'fields' => 'ids',
            'meta_key' => '_ezev_is_demo',
            'meta_value' => '1',
        ]);
        $count = 0;
        foreach ($ids as $id) { if (wp_delete_post((int) $id, true)) { $count++; } }
        return $count;
    }

    public static function find_by_station_id(string $station_id): int {
        $ids = get_posts([
            'post_type' => self::POST_TYPE,
            'post_status' => 'any',
            'numberposts' => 1,
            'fields' => 'ids',
            'meta_key' => '_ezev_station_id',
            'meta_value' => $station_id,
        ]);
        return $ids ? (int) $ids[0] : 0;
    }

    public static function seed_demo_if_empty(bool $force = false): array {
        if (!$force) {
            $count = (int) wp_count_posts(self::POST_TYPE)->publish;
            if ($count > 0) { return ['created' => 0, 'updated' => 0, 'skipped' => $count]; }
        }
        $file = EZEV_CORE_DIR . 'assets/data/demo-stations.json';
        if (!is_readable($file)) { return ['created' => 0, 'updated' => 0, 'error' => 'Demo file not found']; }
        $payload = json_decode((string) file_get_contents($file), true);
        if (!is_array($payload) || empty($payload['stations'])) { return ['created' => 0, 'updated' => 0, 'error' => 'Invalid demo data']; }
        $created = 0; $updated = 0;
        foreach ($payload['stations'] as $row) {
            $existing = self::find_by_station_id((string) ($row['station_id'] ?? ''));
            $row['name'] = $row['name'] ?? $row['station_id'];
            if (empty($row['address']) && !empty($row['address_label'])) {
                $row['address'] = $row['address_label'];
            }
            $row['is_demo'] = true;
            $result = self::create($row);
            if (!is_wp_error($result)) { $existing ? $updated++ : $created++; }
        }
        update_option('ezev_demo_station_seeded', current_time('mysql', true), false);
        return ['created' => $created, 'updated' => $updated];
    }

    public static function list(array $args = []): array {
        $query_args = [
            'post_type' => self::POST_TYPE,
            'post_status' => 'publish',
            'posts_per_page' => $args['limit'] ?? -1,
            'orderby' => 'title',
            'order' => 'ASC',
        ];
        if (!empty($args['country_code'])) {
            $query_args['meta_query'] = [[
                'key' => '_ezev_country_code',
                'value' => sanitize_text_field($args['country_code']),
                'compare' => '=',
            ]];
        }
        $q = new WP_Query($query_args);
        $rows = [];
        foreach ($q->posts as $post) { $rows[] = self::to_array($post); }
        return $rows;
    }

    public static function domain_list(array $args = []): array {
        return array_map([self::class, 'to_domain_array'], self::list($args));
    }

    public static function to_domain_array(WP_Post|int|array $station): array {
        if (is_array($station)) {
            $row = $station;
            $post = !empty($row['post_id']) ? get_post((int) $row['post_id']) : null;
        } else {
            $post = is_int($station) ? get_post($station) : $station;
            $row = self::to_array($station);
        }
        if (!$row) { return []; }
        $modified = $post instanceof WP_Post && $post->post_modified_gmt && $post->post_modified_gmt !== '0000-00-00 00:00:00'
            ? gmdate('c', strtotime($post->post_modified_gmt . ' UTC'))
            : null;
        return [
            'station_id' => (string) $row['station_id'],
            'name' => (string) $row['name'],
            'description' => (string) $row['description'],
            'address' => [
                'line' => (string) $row['address'],
                'city' => (string) $row['city'],
                'region' => (string) $row['region'],
                'country' => (string) $row['country'],
                'country_code' => (string) $row['country_code'],
            ],
            'location' => ['lat' => (float) $row['latitude'], 'lng' => (float) $row['longitude']],
            'connectors' => array_values((array) $row['connector_types']),
            'max_power_kw' => (float) $row['max_power_kw'],
            'ports' => ['total' => (int) $row['ports_total'], 'available' => (int) $row['ports_available_manual']],
            'opening_hours' => (string) $row['opening_hours'],
            'status' => (string) $row['operational_status_manual'],
            'amenities' => array_values((array) $row['amenities']),
            'data' => ['mode' => (string) $row['data_mode'], 'is_demo' => (bool) $row['is_demo']],
            'ownership' => [
                'organization_id' => (string) $row['organization_id'],
                'site_id' => (string) $row['site_id'],
            ],
            'public_notes' => (string) $row['public_notes'],
            'url' => (string) $row['url'],
            'thumbnail' => (string) $row['thumbnail'],
            'updated_at' => $modified,
        ];
    }

    public static function to_array(WP_Post|int $post): array {
        $post = is_int($post) ? get_post($post) : $post;
        if (!$post) { return []; }
        $get = static fn(string $k, mixed $default = '') => (($v = get_post_meta($post->ID, '_ezev_' . $k, true)) !== '' ? $v : $default);
        $lat = (float) $get('latitude', 0);
        $lng = (float) $get('longitude', 0);
        return [
            'post_id' => (int) $post->ID,
            'station_id' => (string) $get('station_id'),
            'name' => get_the_title($post),
            'description' => wp_strip_all_tags($post->post_content),
            'country_code' => (string) $get('country_code'),
            'country' => (string) $get('country'),
            'city' => (string) $get('city'),
            'region' => (string) $get('region'),
            'address' => (string) $get('address'),
            'latitude' => $lat,
            'longitude' => $lng,
            'connector_types' => (array) $get('connector_types', []),
            'max_power_kw' => (float) $get('max_power_kw', 0),
            'ports_total' => (int) $get('ports_total', 0),
            'ports_available_manual' => (int) $get('ports_available_manual', 0),
            'opening_hours' => (string) $get('opening_hours', '24/7'),
            'operational_status_manual' => (string) $get('operational_status_manual', 'active'),
            'amenities' => (array) $get('amenities', []),
            'data_mode' => (string) $get('data_mode', 'manual'),
            'is_demo' => (bool) $get('is_demo', false),
            'organization_id' => (string) $get('organization_id'),
            'site_id' => (string) $get('site_id'),
            'public_notes' => (string) $get('public_notes', ''),
            'url' => get_permalink($post),
            'thumbnail' => get_the_post_thumbnail_url($post, 'medium') ?: '',
        ];
    }
}
