<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
$out=['sources'=>[],'method'=>'Only explicitly approved voluntary submissions with verified coordinates. No LinkedIn scraping.'];
try {
 $path=dirname(__DIR__).'/storage/source-submissions.sqlite';
 if(!is_file($path)||!extension_loaded('pdo_sqlite')){echo json_encode($out);exit;}
 $db=new PDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
 $cols=array_column($db->query('PRAGMA table_info(submissions)')->fetchAll(PDO::FETCH_ASSOC),'name');
 if(!in_array('lat',$cols,true)||!in_array('lon',$cols,true)){$out['note']='No submissions have verified coordinates yet.';echo json_encode($out);exit;}
 $query=$db->query("SELECT title,description,url,lat,lon FROM submissions WHERE status='approved' AND lat IS NOT NULL AND lon IS NOT NULL ORDER BY id DESC LIMIT 150");
 foreach($query as $row){
  $lat=(float)$row['lat'];$lon=(float)$row['lon'];
  if(!is_finite($lat)||!is_finite($lon)||abs($lat)>90||abs($lon)>180)continue;
  $url=(string)$row['url'];$host=strtolower((string)parse_url($url,PHP_URL_HOST));
  if(!in_array($host,['linkedin.com','www.linkedin.com','lnkd.in'],true)&&!str_ends_with($host,'.linkedin.com'))continue;
  $out['sources'][]=['title'=>$row['title'],'description'=>$row['description'],'url'=>$url,'lat'=>$lat,'lon'=>$lon];
 }
}catch(Throwable $e){http_response_code(503);$out=['sources'=>[],'error'=>'Not available'];}
echo json_encode($out,JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE|JSON_UNESCAPED_SLASHES);
