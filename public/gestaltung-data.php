<?php
declare(strict_types=1);
header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: public, max-age=300');
$themes=[
 'entsiegelung'=>'("depaving" OR "sponge city" OR "permeable pavement")',
 'fluesse'=>'("river restoration" OR "river rewilding" OR "dam removal")',
 'stadtgruen'=>'("urban forest" OR "urban greening" OR "urban tree planting")',
 'lebensraeume'=>'("habitat restoration" OR "wetland restoration" OR "rewilding")',
 'gemeinschaft'=>'("community garden" OR "community rewilding" OR "community restoration")'
];
$key=(string)($_GET['topic']??'entsiegelung');
if(!isset($themes[$key])){http_response_code(400);echo json_encode(['error'=>'Unbekanntes Thema']);exit;}
$cacheDir=dirname(__DIR__).'/storage/gdelt-pilot-cache';
if(!is_dir($cacheDir))@mkdir($cacheDir,0700,true);
function gdeltPilotFetch(string $url,string $cacheFile):array {
 $read=function()use($cacheFile):?array{
  if(!is_file($cacheFile))return null;
  $data=json_decode((string)@file_get_contents($cacheFile),true);
  return is_array($data)?$data:null;
 };
 $cached=$read();
 if($cached!==null && time()-(int)($cached['_cached_at']??0)<1800)return ['data'=>$cached['payload'],'stale'=>false];
 // Avoid many simultaneous GDELT queries from visitors; reuse stale data if rate-limited.
 $ctx=stream_context_create(['http'=>['method'=>'GET','timeout'=>14,'ignore_errors'=>true,'header'=>"User-Agent: IFISNewsPilot/1.0 (https://news.ifis.net)\r\nAccept: application/json\r\n"]]);
 $raw=@file_get_contents($url,false,$ctx);
 $status=$http_response_header[0]??'';
 if($raw!==false&&strlen($raw)<5000000&&preg_match('~^HTTP/\S+ 200\b~',$status)){
  $decoded=json_decode($raw,true);
  if(is_array($decoded)){
   @file_put_contents($cacheFile,json_encode(['_cached_at'=>time(),'payload'=>$decoded],JSON_UNESCAPED_SLASHES),LOCK_EX);
   return ['data'=>$decoded,'stale'=>false];
  }
 }
 return ['data'=>$cached['payload']??null,'stale'=>$cached!==null];
}
$query=$themes[$key];$base='https://api.gdeltproject.org/api/v2/';
$docUrl=$base.'doc/doc?'.http_build_query(['query'=>$query,'mode'=>'artlist','format'=>'json','timespan'=>'1week','maxrecords'=>75]);
$geoUrl=$base.'geo/geo?'.http_build_query(['query'=>$query,'mode'=>'pointdata','format'=>'geojson','timespan'=>'1week','maxpoints'=>100]);
$doc=gdeltPilotFetch($docUrl,$cacheDir.'/'.$key.'-doc.json');
$geo=gdeltPilotFetch($geoUrl,$cacheDir.'/'.$key.'-geo.json');
$articles=[];
foreach(($doc['data']['articles']??[]) as $a){
 if(!is_array($a))continue;
 $url=(string)($a['url']??'');$scheme=parse_url($url,PHP_URL_SCHEME);
 if($scheme!=='https')continue;
 $articles[]=['title'=>mb_substr((string)($a['title']??''),0,240),'url'=>$url,'domain'=>(string)($a['domain']??''),'date'=>(string)($a['seendate']??''),'language'=>(string)($a['language']??'')];
}
$features=[];
foreach(($geo['data']['features']??[]) as $f){
 if(!is_array($f)||($f['geometry']['type']??'')!=='Point')continue;
 $p=$f['geometry']['coordinates']??null;
 if(!is_array($p)||count($p)<2||!is_numeric($p[0])||!is_numeric($p[1])||abs((float)$p[0])>180||abs((float)$p[1])>90)continue;
 // Never render raw GEO HTML popups from a third party.
 $properties=$f['properties']??[];
 $name=(string)($properties['name']??$properties['location']??$properties['label']??'Geografische Erwähnung');
 $features[]=['type'=>'Feature','geometry'=>['type'=>'Point','coordinates'=>[(float)$p[0],(float)$p[1]]],'properties'=>['name'=>mb_substr($name,0,120)]];
}
echo json_encode(['topic'=>$key,'articles'=>$articles,'geo'=>['type'=>'FeatureCollection','features'=>$features],
 'doc_available'=>$doc['data']!==null,'geo_available'=>$geo['data']!==null,
 'stale'=>$doc['stale']||$geo['stale'],
 'notice'=>'GDELT-Medienbeobachtungen: Punkte sind in Artikeln erwähnte Orte, keine verifizierten Projektstandorte.',
 'source'=>'GDELT DOC 2.0 + GEO 2.0','window'=>'1week'],JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
