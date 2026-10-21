<?php
declare(strict_types=1);
// Classical GDELT 2.0 event stream, not GDELT Cloud.
// Fetch only the most recent daily 15-minute export from the official index.
// Keep factual geo/count data and original source URLs; never preserve article text.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$root=dirname(__DIR__);
if(!extension_loaded('pdo_sqlite')||!class_exists('ZipArchive')){fwrite(STDERR,"PDO SQLite or ZipArchive missing\n");exit(1);}
function getGdelt(string $url,int $max): string {
 // HTTPS-only transport for fetching; index entries may historically contain HTTP URLs.
 $opts=['http'=>['timeout'=>25,'follow_location'=>1,'max_redirects'=>3,
   'user_agent'=>'IFIS-News/1.0 (+https://news.ifis.net)',
   'header'=>"Accept: text/plain, application/zip, */*\\r\\nConnection: close\\r\\n"]];
 $ctx=stream_context_create($opts);
 $result=@file_get_contents($url,false,$ctx,0,$max+1);
 if($result===false){
  $error=error_get_last();
  fwrite(STDERR,"GDELT request failed (".parse_url($url,PHP_URL_HOST)."): ".($error['message']??'Unknown network error')."\\n");
  return '';
 }
 if(strlen($result)>$max){fwrite(STDERR,"GDELT response exceeds size limit\\n");return '';}
 return $result;
}
$index=getGdelt('https://data.gdeltproject.org/gdeltv2/lastupdate.txt',12000);
if($index===''){fwrite(STDERR,"GDELT index could not be downloaded; previous data preserved\\n");exit(1);}
if(!preg_match('~(?:https?://)?data\\.gdeltproject\\.org/gdeltv2/(\\d{14})\\.export\\.CSV\\.zip~i',$index,$m)){
 fwrite(STDERR,"GDELT index was received but did not contain an event export URL. Check GDELT pointer format.\\n");exit(1);
}
// Never use arbitrary locations supplied by the index, always build the official HTTPS endpoint.
// The GDELT pointer can lead the actual published archive (404 / CDN delay).
// Try the referenced quarter-hour, then up to 3 hours of older official exports.
// Only use fixed-path HTTPS requests; never follow arbitrary source URLs from the index.
$archive='';
$selected='';
$pointer=DateTimeImmutable::createFromFormat('!YmdHis',$m[1],new DateTimeZone('UTC'));
if(!$pointer){fwrite(STDERR,"GDELT pointer timestamp malformed\\n");exit(1);}
for($attempt=0;$attempt<=12;$attempt++){
 $stamp=$pointer->sub(new DateInterval('PT'.($attempt*15).'M'))->format('YmdHis');
 $candidate='https://data.gdeltproject.org/gdeltv2/'.$stamp.'.export.CSV.zip';
 // Quiet individual 404s while seeking the latest actually published archive.
 $ctx=stream_context_create(['http'=>['timeout'=>12,'follow_location'=>0,
   'user_agent'=>'IFIS-News/1.0 (+https://news.ifis.net)']]);
 $bytes=@file_get_contents($candidate,false,$ctx,0,16000001);
 if(!is_string($bytes)||strlen($bytes)>16000000||substr($bytes,0,2)!=='PK')continue;
 $archive=$bytes;$selected=$stamp;break;
}
if($archive===''){fwrite(STDERR,"No GDELT event archive accessible within 3 hours before index pointer; keeping old data\\n");exit(1);}
echo "GDELT export selected: ".$selected."\\n";
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
$cols=$db->query('PRAGMA table_info(gdelt_observations)')->fetchAll(PDO::FETCH_ASSOC);
if(!in_array('quad_class',array_column($cols,'name'),true))$db->exec('ALTER TABLE gdelt_observations ADD COLUMN quad_class INTEGER NOT NULL DEFAULT 0');
// GDELT 2.0 column mappings: Actor1Name=7, Actor2Name=17,
 // EventCode=27, EventBaseCode=28, EventRootCode=29 (1-based).
 foreach(['event_code'=>'TEXT','event_base_code'=>'TEXT','event_root_code'=>'TEXT','actor1_name'=>'TEXT','actor2_name'=>'TEXT'] as $col=>$type){
  if(!in_array($col,array_column($cols,'name'),true))$db->exec('ALTER TABLE gdelt_observations ADD COLUMN '.$col.' '.$type);
 }
 $db->exec('CREATE INDEX IF NOT EXISTS idx_gdelt_time ON gdelt_observations(time)');
 $db->exec('CREATE INDEX IF NOT EXISTS idx_gdelt_root_time ON gdelt_observations(event_root_code,time)');
$put=$db->prepare('INSERT OR REPLACE INTO gdelt_observations(id,time,place,lat,lon,source_domain,source_url,mentions,sources,articles,quad_class,event_code,event_base_code,event_root_code,actor1_name,actor2_name) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
$count=0;$rowsRead=0;$db->beginTransaction();
try{
 while(($line=fgets($stream))!==false){
  $rowsRead++;
  $row=explode("\t",rtrim($line,"\r\n"));
  if(count($row)<61)continue;
  // Official GDELT 2.0 event schema: ActionGeo_FullName=53, Lat=57, Long=58,
  // DATEADDED=60, SOURCEURL=61 (1-based field numbers).
  $id=$row[0];$date=$row[59];$url=$row[60];
  $lat=$row[56];$lon=$row[57];$place=trim($row[52]);
  if(!ctype_digit($id)||!preg_match('/^\d{14}$/',$date)||!is_numeric($lat)||!is_numeric($lon)||abs((float)$lat)>90||abs((float)$lon)>180||strlen($place)>160)continue;
  if(!filter_var($url,FILTER_VALIDATE_URL)||parse_url($url,PHP_URL_SCHEME)!=='https')continue;
  $domain=strtolower((string)parse_url($url,PHP_URL_HOST));
  if(!$domain||strlen($domain)>180)continue;
  $when=substr($date,0,4).'-'.substr($date,4,2).'-'.substr($date,6,2).' '.substr($date,8,2).':'.substr($date,10,2).':'.substr($date,12,2);
  $eventCode=trim($row[26]);$baseCode=trim($row[27]);$rootCode=trim($row[28]);
  $codeOk=static fn(string $value,int $min,int $max): ?string =>
   preg_match('/^[0-9]{'.$min.','.$max.'}$/',$value)?$value:null;
  $actor1=trim($row[6]);$actor2=trim($row[16]);
  $put->execute([$id,$when,$place,(float)$lat,(float)$lon,$domain,$url,max(0,(int)$row[31]),max(0,(int)$row[32]),max(0,(int)$row[33]),in_array((int)$row[29],[1,2,3,4],true)?(int)$row[29]:0,
   $codeOk($eventCode,2,4),$codeOk($baseCode,2,3),$codeOk($rootCode,2,2),$actor1!==''?substr($actor1,0,180):null,$actor2!==''?substr($actor2,0,180):null]);$count++;
  if($count>=8000)break;
 }
 if($count===0)throw new RuntimeException('GDELT archive contained '.$rowsRead.' rows but none matched the expected 61-field event schema; existing observations preserved');
 // Keep factual observation metadata for the historical timeline; no article bodies are stored.
 $db->commit();
}catch(Throwable $e){$db->rollBack();fwrite(STDERR,"GDELT import failure: ".$e->getMessage()."\n");exit(1);}
finally{fclose($stream);$zip->close();@unlink($tmp);}
echo "GDELT factual observations imported: $count\n";
