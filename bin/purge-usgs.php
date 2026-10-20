<?php
declare(strict_types=1);
// One-time removal of previously imported USGS records. Run manually via CLI.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$path=dirname(__DIR__).'/storage/news.sqlite';
if(!is_file($path)){echo "Database absent; nothing to purge.\n";exit;}
$db=new PDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->beginTransaction();
try{
 $tables=$db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
 if(in_array('event_locations',$tables,true)){
  $db->exec("DELETE FROM event_locations WHERE source='USGS · Erdbeben' OR url LIKE 'https://earthquake.usgs.gov/earthquakes/eventpage/%'");
 }
 if(in_array('articles',$tables,true)){
  $n=$db->exec("DELETE FROM articles WHERE source='USGS · Erdbeben' OR url LIKE 'https://earthquake.usgs.gov/earthquakes/eventpage/%'");
  echo "USGS article records removed: $n\n";
 }
 $db->commit();
}catch(Throwable $e){$db->rollBack();fwrite(STDERR,$e->getMessage()."\n");exit(1);}
