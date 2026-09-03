<?php
if (!defined('ABSPATH')) { exit; }
final class EZEV_Operations_Provider_Manager {
    public static function active(): EZEV_Operations_Provider {
        $active=get_option('ezevo_active_provider','manual');
        if($active==='manual')return new EZEV_Operations_Manual_Provider();
        if(str_starts_with((string)$active,'integration_')){global $wpdb;$id=absint(str_replace('integration_','',(string)$active));$row=$wpdb->get_row($wpdb->prepare("SELECT * FROM ".EZEV_Operations_DB::table('integrations')." WHERE id=%d AND enabled=1",$id),ARRAY_A);if($row)return new EZEV_Operations_HTTP_Provider($row);}
        return new EZEV_Operations_Manual_Provider();
    }
    public static function integration(int $id): ?array { global $wpdb;$r=$wpdb->get_row($wpdb->prepare("SELECT * FROM ".EZEV_Operations_DB::table('integrations')." WHERE id=%d",$id),ARRAY_A);return $r?:null; }
}
