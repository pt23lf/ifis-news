<?php
declare(strict_types=1);
// Webcron bridge for ALL-INKL KAS. Never expose the importer itself over HTTP.
header('Content-Type: text/plain; charset=utf-8');
header('Cache-Control: no-store');
$root=dirname(__DIR__);
$secretFile=$root.'/storage/gdelt-cron-secret.txt';
$secret=is_file($secretFile)?trim((string)file_get_contents($secretFile)):'';
$provided=(string)($_GET['key']??'');
if(strlen($secret)<32||strlen($provided)<32||!hash_equals($secret,$provided)){
 http_response_code(404);
 exit("Not found\n");
}
$lock=fopen($root.'/storage/gdelt-cron.lock','c');
if(!$lock||!flock($lock,LOCK_EX|LOCK_NB)){
 http_response_code(409);
 exit("Import already running\n");
}
try{
 if(!function_exists('exec')){
  http_response_code(503);
  exit("PHP exec() is disabled on this host\n");
 }
 $output=[];$status=1;
 exec('/usr/bin/php '.escapeshellarg($root.'/bin/import-gdelt.php').' 2>&1',$output,$status);
 $log='['.gmdate('c').'] exit='.$status."\n".implode("\n",$output)."\n";
 file_put_contents($root.'/storage/gdelt-cron.log',$log,FILE_APPEND|LOCK_EX);
 http_response_code($status===0?200:500);
 echo $status===0?"Import successful\n":"Import failed; see storage/gdelt-cron.log\n";
}finally{
 flock($lock,LOCK_UN);
 fclose($lock);
}
