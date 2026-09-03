<?php
if (!defined('ABSPATH')) { exit; }
final class EZEV_Operations_HTTP_Provider implements EZEV_Operations_Provider {
    private array $integration;
    private array $settings;
    public function __construct(array $integration){$this->integration=$integration;$this->settings=json_decode((string)($integration['settings_json']??'{}'),true)?:[];}
    public function key(): string { return 'integration_'.(int)$this->integration['id']; }
    public function label(): string { return (string)$this->integration['name']; }
    public function mode(): string { return 'api'; }
    private function headers(): array {
        $headers=['Accept'=>'application/json'];$auth=$this->integration['auth_type']??'bearer';$key=EZEV_Operations_Secrets::decrypt((string)($this->integration['api_key_enc']??''));$secret=EZEV_Operations_Secrets::decrypt((string)($this->integration['api_secret_enc']??''));$clientSecret=EZEV_Operations_Secrets::decrypt((string)($this->integration['client_secret_enc']??''));
        if($auth==='bearer'&&$key)$headers['Authorization']='Bearer '.$key;
        elseif($auth==='api_key'&&$key)$headers[$this->settings['api_key_header']??'X-API-Key']=$key;
        elseif($auth==='basic'&&$key)$headers['Authorization']='Basic '.base64_encode($key.':'.$secret);
        elseif($auth==='oauth2'&&!empty($this->integration['client_id'])&&$clientSecret){$headers['X-EZEV-Client-ID']=$this->integration['client_id'];$headers['X-EZEV-Client-Secret']=$clientSecret;}
        return $headers;
    }
    private function url(string $kind): string { $base=rtrim((string)$this->integration['base_url'],'/');$endpoint=$this->settings[$kind.'_endpoint']??'';return $endpoint?($base.'/'.ltrim((string)$endpoint,'/')):$base; }
    private function get(string $kind): array { $url=$this->url($kind); if(!$url)return []; $r=wp_remote_get($url,['timeout'=>(int)($this->settings['timeout']??15),'headers'=>$this->headers()]); if(is_wp_error($r))throw new RuntimeException($r->get_error_message());$code=wp_remote_retrieve_response_code($r);if($code<200||$code>=300)throw new RuntimeException('HTTP '.$code);$d=json_decode(wp_remote_retrieve_body($r),true);return is_array($d)?$d:[]; }
    public function test_connection(): array { try{$kind=!empty($this->settings['health_endpoint'])?'health':'chargers';$r=$this->get($kind);return ['ok'=>true,'message'=>'Connection successful. Response received from provider.','sample'=>array_slice($r,0,1)];}catch(Throwable $e){return ['ok'=>false,'message'=>$e->getMessage()];} }
    public function fetch_chargers(): array { return $this->normalize($this->get('chargers'),'chargers'); }
    public function fetch_connectors(): array { return $this->normalize($this->get('connectors'),'connectors'); }
    public function fetch_sessions(): array { return $this->normalize($this->get('sessions'),'sessions'); }
    public function fetch_energy(): array { return $this->normalize($this->get('energy'),'energy'); }
    public function fetch_alerts(): array { return $this->normalize($this->get('alerts'),'alerts'); }
    private function normalize(array $payload,string $kind): array { $map=json_decode((string)($this->integration['mapping_json']??'{}'),true)?:[];$list=$payload[$this->settings[$kind.'_root']??$kind]??$payload;if(!is_array($list))return []; $out=[];foreach($list as $row){if(!is_array($row))continue;$n=[];foreach(($map[$kind]??[]) as $target=>$source){$n[$target]=$this->dot($row,(string)$source);} $out[]=$n?:$row;}return $out; }
    private function dot(array $row,string $path): mixed { $v=$row;foreach(explode('.',$path) as $p){if(!is_array($v)||!array_key_exists($p,$v))return null;$v=$v[$p];}return $v; }
}
