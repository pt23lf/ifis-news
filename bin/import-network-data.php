<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
require dirname(__DIR__).'/lib/screenshot-network.php';
try{
 $dir=realpath((string)($argv[1]??''));
 if($dir===false||!is_dir($dir)||!is_dir($dir.'/.git'))throw new RuntimeException('Pfad zum geklonten privaten ifis-network-data Repository angeben.');
 $code=realpath(dirname(__DIR__));
 if($dir===$code||str_starts_with($dir,$code.DIRECTORY_SEPARATOR))throw new RuntimeException('Private Daten außerhalb des Codeverzeichnisses ablegen.');
 $storage=dirname(__DIR__).'/storage';
 $lock=fopen($storage.'/network-import.lock','c');
 if(!$lock||!flock($lock,LOCK_EX|LOCK_NB))throw new RuntimeException('Ein Netzwerkimport läuft bereits.');
 $db=new PDO('sqlite:'.$storage.'/network.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_TIMEOUT=>10]);
 captureSchema($db);
 $db->exec('CREATE TABLE IF NOT EXISTS capture_import_batches(path TEXT PRIMARY KEY,digest TEXT NOT NULL,imported_at TEXT NOT NULL)');
 $files=glob($dir.'/batches/*.json')?:[];sort($files,SORT_STRING);
 $saved=0;$skipped=0;
 foreach($files as $file){
  $name=basename($file);
  if(is_link($file)||filesize($file)>1048576)throw new RuntimeException('Ungültige oder zu große Paketdatei: '.$name);
  $json=file_get_contents($file);if($json===false)throw new RuntimeException('Paket nicht lesbar: '.$name);
  $digest=hash('sha256',$json);
  $old=captureFind($db,'SELECT digest FROM capture_import_batches WHERE path=?',[$name]);
  if($old){
   if(!hash_equals($old['digest'],$digest))throw new RuntimeException('Bereits importiertes Paket verändert: '.$name.'. Bitte neue Datei verwenden.');
   $skipped++;continue;
  }
  $batch=captureBatch($json);
  // A failed package rolls itself back. Earlier successful packages stay imported.
  $result=captureImport($db,$batch,true);
  $q=$db->prepare('INSERT INTO capture_import_batches(path,digest,imported_at) VALUES(?,?,?)');
  $q->execute([$name,$digest,gmdate('c')]);
  $saved++;
  echo $name.': '.$result['profiles'].' Profile, '.$result['posts'].' Beiträge, '.$result['comments'].' Kommentare neu; '.($result['updated']??0).' Texte ergänzt; '.$result['existing']." bestehend.\n";
 }
 echo "$saved Pakete übernommen, $skipped unverändert übersprungen.\n";
}catch(Throwable $e){
 fwrite(STDERR,'Netzwerkimport abgebrochen: '.$e->getMessage()."\n");exit(1);
}
