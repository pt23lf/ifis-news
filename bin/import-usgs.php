<?php
declare(strict_types=1);
// USGS government-generated earthquake event data. No copyrighted descriptions or images.
// CLI only; endpoint is fixed, no user-controlled URL.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__);
if(!extension_loaded('pdo_sqlite')){fwrite(STDERR,"SQLite unavailable\n");exit(1);}
$url='https://earthquake.usgs.gov/earthquakes/feed/v1.0/summary/all_day.geojson';
$ctx=stream_context_create(['http'=>['timeout'=>15,'follow_location'=>0,'user_agent'=>'IFIS-News/1.0 (+https://news.ifis.net)','header'=>"Accept: application/geo+json, application/json\r\n"]]);
$raw=@file_get_contents($url,false,$ctx,0,1500000);
$payload=is_string($raw)?json_decode($raw,true):null;
if(!is_array($payload)||($payload['type']??'')!=='FeatureCollection'||!is_array($payload['features']??null)){fwrite(STDERR,"USGS feed unavailable/invalid; retained previous data\n");exit(1);}
$db=new PDO('sqlite:'.$root.'/storage/news.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('PRAGMA busy_timeout=10000');
$db->exec('CREATE TABLE IF NOT EXISTS articles (id INTEGER PRIMARY KEY,url TEXT NOT NULL UNIQUE,title TEXT NOT NULL,source TEXT NOT NULL,section TEXT NOT NULL,published_at TEXT NOT NULL,summary TEXT NOT NULL DEFAULT "",image_url TEXT NOT NULL DEFAULT "",first_seen_at TEXT)');
$db->exec('CREATE TABLE IF NOT EXISTS event_locations (url TEXT PRIMARY KEY,lat REAL NOT NULL,lon REAL NOT NULL,source TEXT NOT NULL,observed_at TEXT NOT NULL)');
$db->beginTransaction();
try{
 $stmt=$db->prepare("INSERT INTO articles(url,title,source,section,published_at,summary,image_url,first_seen_at) VALUES(?,?,'USGS · Erdbeben','world',?,'','',?) ON CONFLICT(url) DO UPDATE SET title=excluded.title,summary='',image_url='',published_at=excluded.published_at,section='world'");
 $geo=$db->prepare("INSERT INTO event_locations(url,lat,lon,source,observed_at) VALUES(?,? ,?,'USGS · Erdbeben',?) ON CONFLICT(url) DO UPDATE SET lat=excluded.lat,lon=excluded.lon,observed_at=excluded.observed_at");
 $count=0;
 foreach(array_slice($payload['features'],0,500) as $feature){
  if(!is_array($feature))continue;
  $id=(string)($feature['id']??'');
  $coord=$feature['geometry']['coordinates']??[];
  $ms=$feature['properties']['time']??null;
  $mag=$feature['properties']['mag']??null;
  if(!preg_match('/^[a-z0-9_-]{2,32}$/i',$id)||!is_array($coord)||count($coord)<2||!is_numeric($coord[0])||!is_numeric($coord[1])||!is_numeric($ms))continue;
  $lon=(float)$coord[0];$lat=(float)$coord[1];
  if(abs($lat)>90||abs($lon)>180)continue;
  $when=gmdate('Y-m-d H:i:s',(int)floor((float)$ms/1000));
  if(strtotime($when)<time()-172800||strtotime($when)>time()+600)continue;
  $articleUrl='https://earthquake.usgs.gov/earthquakes/eventpage/'.rawurlencode($id);
  $label='Erdbebenereignis';
  if(is_numeric($mag))$label.=' · M '.number_format((float)$mag,1,',','');
  $stmt->execute([$articleUrl,$label,$when,gmdate('Y-m-d H:i:s')]);
  $geo->execute([$articleUrl,$lat,$lon,$when]);
  $count++;
 }
 $db->commit();echo "USGS observations: $count\n";
}catch(Throwable $e){$db->rollBack();fwrite(STDERR,"USGS import failed: ".$e->getMessage()."\n");exit(1);}
