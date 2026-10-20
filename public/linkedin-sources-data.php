<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
$out=['sources'=>[['type'=>'ifis','title'=>'IFIS – Institut für funktionale innere Strukturen','description'=>'Eigene IFIS-Artikel · Quellenstandort Lübeck (nicht Ereignisort)','url'=>'https://ifis.net/artikel','lat'=>53.866,'lon'=>10.686]],'method'=>'Own IFIS source at approximate Lübeck editorial location, plus approved voluntary submissions with verified coordinates. No scraping or automatic feed imports.'];
try {
 $path=dirname(__DIR__).'/storage/source-submissions.sqlite';
 if(!is_file($path)||!extension_loaded('pdo_sqlite')){echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
 $db=new PDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 $cols=array_column($db->query('PRAGMA table_info(submissions)')->fetchAll(PDO::FETCH_ASSOC),'name');
 if(!in_array('lat',$cols,true)||!in_array('lon',$cols,true)){$out['note']='No submissions have verified coordinates yet.';echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);exit;}
 $feedCol=in_array('feed_url',$cols,true)?'feed_url':"'' AS feed_url";
 $query=$db->query("SELECT type,title,description,url,$feedCol,lat,lon FROM submissions WHERE status='approved' AND lat IS NOT NULL AND lon IS NOT NULL ORDER BY id DESC LIMIT 150");
 foreach($query as $row){
  $lat=(float)$row['lat'];$lon=(float)$row['lon'];
  if(!is_finite($lat)||!is_finite($lon)||abs($lat)>90||abs($lon)>180)continue;
  $url=(string)$row['url'];$host=strtolower((string)parse_url($url,PHP_URL_HOST));
  $scheme=strtolower((string)parse_url($url,PHP_URL_SCHEME));
  if($scheme!=='https'||$host===''||$host==='localhost'||filter_var($host,FILTER_VALIDATE_IP))continue;
  $out['sources'][]=['type'=>in_array($row['type'],['feed','website','linkedin','network','post','profile'],true)?$row['type']:'website','title'=>$row['title'],'description'=>$row['description'],'url'=>$url,'feed_url'=>(string)($row['feed_url']??''),'lat'=>$lat,'lon'=>$lon];
 }
}catch(Throwable $e){http_response_code(503);$out=['sources'=>[],'error'=>'Not available'];}
echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|JSON_UNESCAPED_SLASHES);
