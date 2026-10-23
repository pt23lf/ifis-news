<?php
declare(strict_types=1);
session_start();
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: private, no-store, max-age=0');
header('Vary: Cookie');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
$root=dirname(__DIR__);$hashFile=$root.'/storage/admin-password.hash';
if(!is_file($hashFile)||trim((string)file_get_contents($hashFile))===''||empty($_SESSION['ifis_admin_ok'])){http_response_code(403);echo '{"error":"login_required"}';exit;}
session_write_close();
require $root.'/lib/profile-threads.php';
try{
 if(!is_file($root.'/storage/network.sqlite')){http_response_code(404);echo '{"error":"not_found"}';exit;}
 $db=new PDO('sqlite:'.$root.'/storage/network.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_TIMEOUT=>5]);$db->exec('PRAGMA query_only=ON');
 $id=filter_var($_GET['contact_id']??null,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
 if(!$id){
  $urls=$_GET['post_urls']??[];
  if(!is_array($urls)||count($urls)>40)throw new InvalidArgumentException();
  foreach($urls as $url)if(!is_string($url)||strlen($url)>1000)throw new InvalidArgumentException();
  $id=profileThreadAuthor($db,$urls);
 }
 $profile=$id?profileThreadData($db,(int)$id):null;
 if(!$profile){http_response_code(404);echo '{"error":"not_found"}';exit;}
 echo json_encode($profile,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR);
}catch(InvalidArgumentException $e){http_response_code(400);echo '{"error":"invalid_request"}';}
catch(Throwable $e){http_response_code(503);echo '{"error":"unavailable"}';}
