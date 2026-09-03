<?php
if (!defined('ABSPATH')) { exit; }
final class EZEV_Operations_Secrets {
    public static function encrypt(string $plain): string { if($plain==='')return ''; $key=hash('sha256',wp_salt('secure_auth'),true);$iv=random_bytes(16);$cipher=openssl_encrypt($plain,'AES-256-CBC',$key,OPENSSL_RAW_DATA,$iv);return base64_encode($iv.$cipher); }
    public static function decrypt(string $enc): string { if($enc==='')return ''; $raw=base64_decode($enc,true);if($raw===false||strlen($raw)<17)return '';$iv=substr($raw,0,16);$cipher=substr($raw,16);$key=hash('sha256',wp_salt('secure_auth'),true);return (string)openssl_decrypt($cipher,'AES-256-CBC',$key,OPENSSL_RAW_DATA,$iv); }
    public static function masked(string $enc): string { $v=self::decrypt($enc); if($v==='')return 'Not set'; if(strlen($v)<=8)return '••••••••'; return substr($v,0,3).'••••••••'.substr($v,-4); }
}
