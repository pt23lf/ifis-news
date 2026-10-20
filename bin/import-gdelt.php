<?php
declare(strict_types=1);
// Classical GDELT 2.0 event stream, not GDELT Cloud.
// Fetch only the most recent daily 15-minute export from the official index.
// Keep factual geo/count data and original source URLs; never preserve article text.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__);
if(!extension_loaded('pdo_sqlite')||!class_exists('ZipArchive')){fwrite(STDERR,"PDO SQLite or ZipArchive missing\n");exit(1);}
function getGdelt(string $url,int $max): string {
 $ctx=stream_context_create(['http'=>['timeout'=>24,'follow_location'=>0,'user_agent'=>'IFIS-News/1.0 (+https://news.ifis.net)']]);
 $result=@file_get_contents($url,false,$ctx,0,$max+1);
 return is_string($result)&&strlen($result)<=$max?$result:'';
}
$index=getGdelt('https://data.gdeltproject.org/gdeltv2/lastupdate.txt',12000);
if(!preg_match('~https://data\.gdeltproject\.org/gdeltv2/(\d{14})\.export\.CSV\.zip~i',$index,$m)){fwrite(STDERR,"GDELT index unavailable; keeping old observation data\n");exit(1);}
$feed='https://data.gdeltproject.org/gdeltv2/'.$m[1].'.export.CSV.zip';
$archive=getGdelt($feed,12000000);
if(!$archive){fwrite(STDERR,"GDELT archive unavailable\n");exit(1);}
$tmp=tempnam(sys_get_temp_dir(),'ifis-gdelt-');
if(!$tmp)exit(1);
file_put_contents($tmp,$archive);
$zip=new ZipArchive();
if($zip->open($tmp)!==true||$zip->numFiles!==1){@unlink($tmp);fwrite(STDERR,"Invalid GDELT zip\n");exit(1);}
$stat=$zip->statIndex(0);
if(!$stat||($stat['size']??0)>80000000){$zip->close();@unlink($tmp);exit(1);}
$stream=$zip->getStream($zip->getNameIndex(0));
if(!$stream){$zip->close();@unlink($tmp);exit(1);}
$db=new PDO('sqlite:'.$root.'/storage/news.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('PRAGMA busy_timeout=15000');
$db->exec('CREATE TABLE IF NOT EXISTS gdelt_observations(id TEXT PRIMARY KEY, time TEXT NOT NULL, place TEXT NOT NULL, lat REAL NOT NULL, lon REAL NOT NULL, source_domain TEXT NOT NULL, source_url TEXT NOT NULL, mentions INTEGER NOT NULL, sources INTEGER NOT NULL, articles INTEGER NOT NULL)');
$db->exec('CREATE INDEX IF NOT EXISTS idx_gdelt_time ON gdelt_observations(time)');
$put=$db->prepare('INSERT OR REPLACE INTO gdelt_observations(id,time,place,lat,lon,source_domain,source_url,mentions,sources,articles) VALUES(?,?,?,?,?,?,?,?,?,?)');
$count=0;$db->beginTransaction();
try{
 while(($line=fgets($stream))!==false){
  $row=explode("\t",rtrim($line,"\r\n"));
  if(count($row)<60)continue;
  $id=$row[0];$date=$row[58];$url=$row[59];
  $lat=$row[55];$lon=$row[56];$place=trim($row[51]);
  if(!ctype_digit($id)||!preg_match('/^\d{14}$/',$date)||!is_numeric($lat)||!is_numeric($lon)||abs((float)$lat)>90||abs((float)$lon)>180||strlen($place)>160)continue;
  if(!filter_var($url,FILTER_VALIDATE_URL)||parse_url($url,PHP_URL_SCHEME)!=='https')continue;
  $domain=strtolower((string)parse_url($url,PHP_URL_HOST));
  if(!$domain||strlen($domain)>180)continue;
  $when=substr($date,0,4).'-'.substr($date,4,2).'-'.substr($date,6,2).' '.substr($date,8,2).':'.substr($date,10,2).':'.substr($date,12,2);
  $put->execute([$id,$when,$place,(float)$lat,(float)$lon,$domain,$url,max(0,(int)$row[31]),max(0,(int)$row[32]),max(0,(int)$row[33])]);$count++;
  if($count>=8000)break;
 }
 $db->exec("DELETE FROM gdelt_observations WHERE time < datetime('now','-48 hours')");
 $db->commit();
}catch(Throwable $e){$db->rollBack();fwrite(STDERR,"GDELT import failure: ".$e->getMessage()."\n");exit(1);}
finally{fclose($stream);$zip->close();@unlink($tmp);}
echo "GDELT factual observations imported: $count\n";
