<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store, max-age=0');
header('Vary: Cookie');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
$root=dirname(__DIR__);$hashFile=$root.'/storage/admin-password.hash';
if(!is_file($hashFile)||trim((string)file_get_contents($hashFile))===''||empty($_SESSION['ifis_admin_ok'])){
 http_response_code(403);echo '{"error":"login_required"}';exit;
}
session_write_close();
require $root.'/lib/comment-map.php';
try{
 $path=$root.'/storage/network.sqlite';
 if(!is_file($path)){$data=['origin'=>['name'=>'Lars Focke · Lübeck','coordinates'=>[10.686,53.866]],'connections'=>[],'missing'=>[]];}
 else{
  $db=new PDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_TIMEOUT=>5]);
  $db->exec('PRAGMA query_only=ON');$data=commentMapData($db);
 }
 echo json_encode($data,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
}catch(Throwable $e){http_response_code(503);echo '{"error":"unavailable"}';}
