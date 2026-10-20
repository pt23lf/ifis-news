<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=120');
header('X-Content-Type-Options: nosniff');
$path=dirname(__DIR__).'/storage/news.sqlite';
if(!is_file($path)||!extension_loaded('pdo_sqlite')){echo '{"points":[],"credit":"GDELT Project"}';exit;}
try{
 $db=new PDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 $columns=$db->query('PRAGMA table_info(gdelt_observations)')->fetchAll(PDO::FETCH_ASSOC);
 $quadColumn=in_array('quad_class',array_column($columns,'name'),true)?'quad_class':'0 AS quad_class';
 $span=$db->query('SELECT MIN(time) AS oldest, MAX(time) AS newest, COUNT(*) AS total FROM gdelt_observations')->fetch(PDO::FETCH_ASSOC);
 $oldest=(string)($span['oldest']??'');
 $oldestHours=$oldest!==''?max(0.5,(time()-(strtotime($oldest.' UTC')?:time()))/3600):48;
 $requested=max(0.5,min(24*365*30,(float)($_GET['hours']??24)));
 $hours=min($requested,max(48,$oldestHours));
 // Return bounded query data; flag truncation rather than implying complete archive coverage.
 $stmt=$db->prepare("SELECT id,time,place,lat,lon,source_domain,source_url,mentions,sources,articles,$quadColumn FROM gdelt_observations WHERE time >= :since ORDER BY time DESC LIMIT 50001");
 $stmt->execute([':since'=>gmdate('Y-m-d H:i:s',time()-(int)round($hours*3600))]);
 $days=$stmt->fetchAll(PDO::FETCH_ASSOC);
 $truncated=count($days)>50000;
 if($truncated)array_pop($days);
 $points=[];
 foreach($days as $row){
  $lat=(float)$row['lat'];$lon=(float)$row['lon'];
  // About 110km latitude grid, only an approximate aggregation cell.
  $key=round($lat,0).':'.round($lon,0);
  if(!isset($points[$key]))$points[$key]=['lat'=>round($lat),'lon'=>round($lon),'observations'=>0,'reported_articles'=>0,'reported_sources'=>0,'places'=>[],'links'=>[],'hours'=>[],'time_bins'=>[],'quad_bins'=>[]];
  $p=&$points[$key];$p['observations']++;
  $hour=substr((string)$row['time'],0,13).':00:00';
  if(!isset($p['hours'][$hour]))$p['hours'][$hour]=['time'=>$hour,'observations'=>0,'reported_articles'=>0,'reported_sources'=>0];
  $p['hours'][$hour]['observations']++;
  $bin=substr((string)$row['time'],0,16);
  $p['time_bins'][$bin]=($p['time_bins'][$bin]??0)+1;
  $quad=(int)$row['quad_class'];if($quad<1||$quad>4)$quad=0;
  if(!isset($p['quad_bins'][$bin]))$p['quad_bins'][$bin]=[0,0,0,0,0];
  $p['quad_bins'][$bin][$quad]++;
  $p['hours'][$hour]['reported_articles']+=min(100000,(int)$row['articles']);
  $p['hours'][$hour]['reported_sources']+=min(100000,(int)$row['sources']);$p['reported_articles']+=min(100000,(int)$row['articles']);$p['reported_sources']+=min(100000,(int)$row['sources']);
  if(count($p['places'])<12&&$row['place']!=='')$p['places'][$row['place']]=true;
  if(count($p['links'])<45)$p['links'][$row['source_url']]=['domain'=>$row['source_domain'],'url'=>$row['source_url'],'time'=>$row['time']];
  unset($p);
 }
 usort($points,static fn($a,$b)=>$b['observations']<=>$a['observations']);
 $out=[];
 foreach(array_slice($points,0,250) as $p){
  $p['places']=array_keys($p['places']);$p['links']=array_values($p['links']);$p['hours']=array_values($p['hours']);$p['time_bins']=$p['time_bins'];
  $out[]=$p;
 }
 echo json_encode(['points'=>$out,'credit'=>'GDELT Project','generated_at'=>gmdate('Y-m-d H:i:s'),'oldest_at'=>$oldest,'oldest_hours'=>$oldestHours,'requested_hours'=>$hours,'sample_truncated'=>$truncated,'records_in_window'=>count($days),'total_stored'=>(int)($span['total']??0),'method'=>'Approximately 1-degree geographic grouping. Sources list consists of first-source URLs found in GDELT event records; it is not a complete list of all reports. Sum of per-record sources/articles is not unique media count.'],JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|JSON_UNESCAPED_SLASHES);
}catch(Throwable $e){http_response_code(503);echo '{"points":[],"credit":"GDELT Project","error":"Data currently unavailable"}';}
