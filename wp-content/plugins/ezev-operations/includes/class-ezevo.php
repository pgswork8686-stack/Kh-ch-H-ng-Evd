<?php
if (!defined('ABSPATH')) { exit; }
final class EZEV_Operations {
    private static ?EZEV_Operations $instance=null;
    public static function instance(): EZEV_Operations { if(self::$instance===null){self::$instance=new self();} return self::$instance; }
    public static function activate(): void { EZEV_Operations_DB::install(); EZEV_Operations_Sync::schedule(); }
    public static function deactivate(): void { EZEV_Operations_Sync::unschedule(); }
    public function boot(): void { EZEV_Operations_DB::maybe_upgrade(); EZEV_Operations_Admin::init(); EZEV_Operations_REST::init(); EZEV_Operations_Sync::init(); add_action('admin_notices',[$this,'dependency_notice']); }
    public function dependency_notice(): void { if(!class_exists('EZEV_Core_Stations')){ echo '<div class="notice notice-warning"><p><strong>EZEV Operations:</strong> EZEV Core is recommended. Operations can run in manual mode, but station master data and map integration require EZEV Core.</p></div>'; } }
}
