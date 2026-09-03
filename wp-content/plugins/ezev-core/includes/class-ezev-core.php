<?php
if (!defined('ABSPATH')) { exit; }

final class EZEV_Core {
    private static ?EZEV_Core $instance = null;

    public static function instance(): EZEV_Core {
        if (self::$instance === null) { self::$instance = new self(); }
        return self::$instance;
    }

    public static function activate(): void {
        EZEV_Core_DB::install();
        EZEV_Core_Roles::install();
        EZEV_Core_Stations::register_post_type();
        EZEV_Core_Stations::seed_demo_if_empty();
        self::maybe_create_demo_pages();
        flush_rewrite_rules();
    }

    public static function deactivate(): void {
        flush_rewrite_rules();
    }

    private static function maybe_create_demo_pages(): void {
        $pages = [
            'find-a-charger' => ['Find a Charger', '[ezev_find_charger]'],
            'login'          => ['EZEV Login', '[ezev_login]'],
            'account'        => ['Customer Account', '[ezev_account]'],
            'business'       => ['Business Portal', '[ezev_business]'],
            'partner'        => ['Partner Portal', '[ezev_partner]'],
            'internal'       => ['Internal Operations', '[ezev_internal]'],
        ];
        foreach ($pages as $slug => [$title, $content]) {
            if (!get_page_by_path($slug)) {
                wp_insert_post([
                    'post_type' => 'page',
                    'post_status' => 'publish',
                    'post_title' => $title,
                    'post_name' => $slug,
                    'post_content' => $content,
                ]);
            }
        }
    }

    public function boot(): void {
        EZEV_Core_DB::maybe_upgrade();
        EZEV_Core_Stations::init();
        EZEV_Core_Auth::init();
        EZEV_Core_REST::init();
        EZEV_Core_Admin::init();
        EZEV_Core_Shortcodes::init();
    }
}
