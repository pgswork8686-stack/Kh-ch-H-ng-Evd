<?php
if (!defined('ABSPATH')) { exit; }
final class EZEV_Operations_REST {
    public static function init(): void { add_action('rest_api_init',[self::class,'routes']); }
    public static function routes(): void {
        register_rest_route('ezev-ops/v1','/overview',['methods'=>WP_REST_Server::READABLE,'callback'=>[self::class,'overview'],'permission_callback'=>[self::class,'can_view']]);
        register_rest_route('ezev-ops/v1','/chargers',['methods'=>WP_REST_Server::READABLE,'callback'=>[self::class,'chargers'],'permission_callback'=>[self::class,'can_view']]);
        register_rest_route('ezev-ops/v1','/energy',['methods'=>WP_REST_Server::READABLE,'callback'=>[self::class,'energy'],'permission_callback'=>[self::class,'can_view']]);
        register_rest_route('ezev-ops/v1','/sessions',['methods'=>WP_REST_Server::READABLE,'callback'=>[self::class,'sessions'],'permission_callback'=>[self::class,'can_view']]);
        register_rest_route('ezev-ops/v1','/alerts',['methods'=>WP_REST_Server::READABLE,'callback'=>[self::class,'alerts'],'permission_callback'=>[self::class,'can_view']]);
        register_rest_route('ezev-ops/v1','/webhook/(?P<integration_id>\d+)',['methods'=>WP_REST_Server::CREATABLE,'callback'=>[self::class,'webhook'],'permission_callback'=>'__return_true']);
    }
    public static function can_view(): bool { return is_user_logged_in(); }
    private static function allowed_station_keys(): ?array {
        if(current_user_can('manage_options') || current_user_can('ezev_view_internal')) return null;
        if(!class_exists('EZEV_Core_Auth') || !class_exists('EZEV_Core_Stations')) return [];
        $post_ids=EZEV_Core_Auth::allowed_station_ids(get_current_user_id());$keys=[];
        foreach($post_ids as $pid){$row=EZEV_Core_Stations::to_array((int)$pid);if(!empty($row['station_id']))$keys[]=(string)$row['station_id'];}
        return array_values(array_unique($keys));
    }
    private static function filter_rows(array $rows): array { $allowed=self::allowed_station_keys();if($allowed===null)return $rows;if(!$allowed)return [];return array_values(array_filter($rows,static fn($r)=>isset($r['station_id'])&&in_array((string)$r['station_id'],$allowed,true))); }
    public static function overview(): WP_REST_Response {
        $p=EZEV_Operations_Provider_Manager::active();$chargers=self::filter_rows($p->fetch_chargers());$sessions=self::filter_rows($p->fetch_sessions());$energy=self::filter_rows($p->fetch_energy());$alerts=self::filter_rows($p->fetch_alerts());$today=gmdate('Y-m-d');
        $data=['chargers_total'=>count($chargers),'chargers_available'=>count(array_filter($chargers,fn($r)=>($r['status']??'')==='available')),'chargers_charging'=>count(array_filter($chargers,fn($r)=>($r['status']??'')==='charging')),'chargers_faulted'=>count(array_filter($chargers,fn($r)=>($r['status']??'')==='faulted')),'current_power'=>array_sum(array_map(fn($r)=>(float)($r['current_power_kw']??0),$chargers)),'energy_today'=>array_sum(array_map(fn($r)=>str_starts_with((string)($r['recorded_at']??''),$today)?(float)($r['ev_kwh']??0):0,$energy)),'sessions_today'=>count(array_filter($sessions,fn($r)=>str_starts_with((string)($r['started_at']??''),$today))),'open_alerts'=>count(array_filter($alerts,fn($r)=>($r['status']??'open')==='open'))];
        return rest_ensure_response(['provider'=>$p->label(),'scope'=>self::allowed_station_keys()===null?'all':'restricted','data'=>$data]);
    }
    public static function chargers(): WP_REST_Response { return rest_ensure_response(['chargers'=>self::filter_rows(EZEV_Operations_Provider_Manager::active()->fetch_chargers())]); }
    public static function sessions(): WP_REST_Response { return rest_ensure_response(['sessions'=>self::filter_rows(EZEV_Operations_Provider_Manager::active()->fetch_sessions())]); }
    public static function energy(): WP_REST_Response { return rest_ensure_response(['energy'=>self::filter_rows(EZEV_Operations_Provider_Manager::active()->fetch_energy())]); }
    public static function alerts(): WP_REST_Response { return rest_ensure_response(['alerts'=>self::filter_rows(EZEV_Operations_Provider_Manager::active()->fetch_alerts())]); }
    public static function webhook(WP_REST_Request $request): WP_REST_Response|WP_Error {
        $id=absint($request['integration_id']);$integration=EZEV_Operations_Provider_Manager::integration($id);if(!$integration)return new WP_Error('not_found','Integration not found.',['status'=>404]);
        $secret=EZEV_Operations_Secrets::decrypt((string)($integration['webhook_secret_enc']??''));$raw=$request->get_body();
        if($secret!==''){ $given=(string)$request->get_header('x-ezev-signature');$expected=hash_hmac('sha256',$raw,$secret);if(!$given||!hash_equals($expected,$given))return new WP_Error('invalid_signature','Invalid webhook signature.',['status'=>401]); }
        EZEV_Operations_DB::log('webhook_received','Webhook received from '.$integration['name'],'info',$id,['payload'=>json_decode($raw,true)]);
        return rest_ensure_response(['received'=>true]);
    }
}
