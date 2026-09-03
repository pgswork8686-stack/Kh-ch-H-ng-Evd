<?php
if (!defined('ABSPATH')) { exit; }

final class EZEV_Core_Roles {
    private static array $custom_caps = [
        'ezev_view_core', 'ezev_manage_stations', 'ezev_manage_organizations',
        'ezev_manage_access', 'ezev_view_internal', 'ezev_view_operations',
        'ezev_manage_integrations'
    ];

    public static function install(): void {
        $roles = [
            'ezev_customer' => ['EZEV Customer', ['read' => true]],
            'ezev_business' => ['EZEV Business User', ['read' => true, 'ezev_view_core' => true, 'ezev_view_operations' => true]],
            'ezev_partner' => ['EZEV Partner', ['read' => true, 'ezev_view_core' => true, 'ezev_view_operations' => true]],
            'ezev_investor' => ['EZEV Investor', ['read' => true, 'ezev_view_core' => true, 'ezev_view_operations' => true]],
            'ezev_internal_ops' => ['EZEV Internal - Operations', ['read' => true, 'ezev_view_core' => true, 'ezev_view_internal' => true, 'ezev_view_operations' => true]],
            'ezev_internal_technical' => ['EZEV Internal - Technical', ['read' => true, 'ezev_view_core' => true, 'ezev_view_internal' => true, 'ezev_view_operations' => true]],
            'ezev_internal_business' => ['EZEV Internal - Business', ['read' => true, 'ezev_view_core' => true, 'ezev_view_internal' => true, 'ezev_manage_organizations' => true]],
            'ezev_internal_content' => ['EZEV Internal - Content', ['read' => true, 'edit_posts' => true, 'ezev_view_core' => true, 'ezev_view_internal' => true]],
        ];
        foreach ($roles as $key => [$label, $caps]) {
            remove_role($key);
            add_role($key, $label, $caps);
        }
        $admin = get_role('administrator');
        if ($admin) {
            foreach (self::$custom_caps as $cap) { $admin->add_cap($cap); }
        }
    }

    public static function role_label(string $role): string {
        $labels = [
            'owner' => 'Owner', 'admin' => 'Admin', 'operations' => 'Operations',
            'finance' => 'Finance', 'viewer' => 'Viewer', 'support' => 'Support',
            'site_manager' => 'Site Manager'
        ];
        return $labels[$role] ?? ucwords(str_replace('_', ' ', $role));
    }
}
