<?php
declare(strict_types=1);
// Immutable article collections. Each snapshot is independent of later RSS imports.
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
function outJson(int $status,array $value): never {
 http_response_code($status);
 header('Content-Type: application/json; charset=utf-8');
 header('Cache-Control: no-store');
 echo json_encode($value,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
 exit;
}
function validGeoPoint($point): ?array {
 if(!is_array($point)||!isset($point['name'],$point['coordinates'])||
    !is_string($point['name'])||mb_strlen($point['name'],'UTF-8')>110 ||
    !is_array($point['coordinates'])||count($point['coordinates'])!==2)return null;
 [$lon,$lat]=array_values($point['coordinates']);
 if(!is_numeric($lon)||!is_numeric($lat)||abs((float)$lon)>180||abs((float)$lat)>90)return null;
 return ['name'=>$point['name'],'coordinates'=>[(float)$lon,(float)$lat]];
}
function h(string $value): string {
 return htmlspecialchars($value,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');
}
$directory=dirname(__DIR__).'/storage/collections';
if($_SERVER['REQUEST_METHOD']==='POST'){
 if(!extension_loaded('pdo_sqlite'))outJson(503,['error'=>'SQLite ist derzeit nicht verfügbar.']);
 $origin=(string)($_SERVER['HTTP_ORIGIN']??'');
 if($origin!==''&&!in_array($origin,['https://news.ifis.net','http://news.ifis.net'],true))outJson(403,['error'=>'Ungültiger Ursprung.']);
 if((int)($_SERVER['CONTENT_LENGTH']??0)>25000)outJson(413,['error'=>'Zu viele Daten.']);
 $raw=file_get_contents('php://input',false,null,0,25001);
 $input=json_decode($raw?:'',true);
 if(!is_array($input))outJson(400,['error'=>'Ungültige Anfrage.']);
 $kind=(string)($input['kind']??'');
 $name=trim((string)($input['name']??''));
 $section=(string)($input['view']??'');
 $urls=$input['urls']??null;
 if(!in_array($kind,['source','event'],true) ||
    !in_array($section,['world','ifis'],true) ||
    $name===''||mb_strlen($name,'UTF-8')>100||
    !is_array($urls)||count($urls)<1||count($urls)>40) {
  outJson(400,['error'=>'Artikelauswahl ungültig.']);
 }
 $urls=array_values(array_unique(array_filter($urls,static function($url): bool {
  return is_string($url)&&strlen($url)<1800&&filter_var($url,FILTER_VALIDATE_URL)!==false&&
   in_array(strtolower((string)parse_url($url,PHP_URL_SCHEME)),['https','http'],true);
 })));
 if(!$urls||count($urls)>40)outJson(400,['error'=>'Keine gültigen Artikel-Links.']);
 $dbPath=dirname(__DIR__).'/storage/news.sqlite';
 if(!is_file($dbPath))outJson(503,['error'=>'Nachrichtendatenbank nicht verfügbar.']);
 try {
  $db=new PDO('sqlite:'.$dbPath,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  $placeholders=implode(',',array_fill(0,count($urls),'?'));
  $q=$db->prepare('SELECT title,url,source,published_at,summary FROM articles WHERE section=? AND url IN ('.$placeholders.')');
  $q->execute(array_merge([$section],$urls));
  $records=[];
  foreach($q->fetchAll(PDO::FETCH_ASSOC) as $article)$records[$article['url']]=$article;
  // Do not silently convert an archival selection into a partial collection.
  foreach($urls as $url)if(!isset($records[$url]))outJson(409,['error'=>'Mindestens ein Artikel ist nicht mehr in der Datenbank. Bitte Auswahl aktualisieren.']);
  $articles=array_map(static fn($url)=>$records[$url],$urls);
  if(!is_dir($directory)&&!@mkdir($directory,0750,true)&&!is_dir($directory))
   outJson(503,['error'=>'Sammlungsspeicher ist nicht beschreibbar.']);
  $inputGeo=is_array($input['geo']??null)?$input['geo']:[];
  $geoByUrl=[];
  foreach(array_slice($inputGeo,0,40) as $point){
   if(!is_array($point)||!is_string($point['url']??null)||!in_array($point['url'],$urls,true))continue;
   $geoByUrl[$point['url']]=[
    'source'=>validGeoPoint($point['source']??null),
    'event'=>validGeoPoint($point['event']??null)
   ];
  }
  $snapshot=[
   'version'=>2,'kind'=>$kind,'name'=>$name,'view'=>$section,
   'created_at'=>gmdate('c'),'articles'=>$articles,'geo'=>$geoByUrl
  ];
  $data=json_encode($snapshot,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_INVALID_UTF8_SUBSTITUTE);
  if($data===false)outJson(500,['error'=>'Sammlung konnte nicht serialisiert werden.']);
  $id=bin2hex(random_bytes(16));
  $file=$directory.'/'.$id.'.json';
  $handle=@fopen($file,'x');
  if(!$handle)outJson(503,['error'=>'Sammlung konnte nicht gespeichert werden.']);
  $written=fwrite($handle,$data);
  fclose($handle);
  if($written===false||$written!==strlen($data)){
   @unlink($file);
   outJson(503,['error'=>'Speichern fehlgeschlagen.']);
  }
  clearstatcache(true,$file);
  if(!is_file($file)||!is_readable($file)||!is_array(json_decode((string)@file_get_contents($file),true))){
   @unlink($file);
   outJson(503,['error'=>'Die gespeicherte Sammlung konnte nicht wieder eingelesen werden.']);
  }
  outJson(201,['url'=>'https://news.ifis.net/collection.php?id='.$id,'id'=>$id,'count'=>count($articles)]);
 }catch(Throwable $e){
  error_log('IFIS collection: '.$e->getMessage());
  outJson(503,['error'=>'Die feste Sammlung konnte gerade nicht erstellt werden.']);
 }
}
if($_SERVER['REQUEST_METHOD']!=='GET'){
 header('Allow: GET, POST');outJson(405,['error'=>'Methode nicht unterstützt.']);
}
$id=trim((string)($_GET['id']??''));
// Some share targets append the human-readable share caption to the URL.
// Accept that specific legacy suffix without relaxing the random 128-bit ID.
if(preg_match('/^([a-f0-9]{32})\s+Artikelsammlung auf IFIS News$/Du',$id,$sharedLinkMatch)){
 $id=$sharedLinkMatch[1];
}
if(!preg_match('/^[a-f0-9]{32}$/D',$id)){
 http_response_code(404);header('Content-Type: text/plain; charset=utf-8');echo 'Im Link fehlt eine gültige Sammlungs-ID. Bitte den vollständigen Teilen-Link einschließlich ?id=… öffnen.';exit;
}
$file=$directory.'/'.$id.'.json';
if(!is_file($file)){
 http_response_code(404);header('Content-Type: text/plain; charset=utf-8');echo 'Die gespeicherte Artikelsammlung ist nicht vorhanden. Bitte überprüfen, ob der vollständige Link benutzt wurde und ob der Sammlungsspeicher auf dem Server erhalten geblieben ist.';exit;
}
$data=json_decode((string)file_get_contents($file),true);
if(!is_array($data)||!isset($data['articles'])||!is_array($data['articles'])){
 http_response_code(500);echo 'Sammlung nicht lesbar.';exit;
}
$name=(string)($data['name']??'Sammlung');
$kind=(string)($data['kind']??'event');
$section=(string)($data['view']??'ifis');
$created=(string)($data['created_at']??'');
$articles=$data['articles'];
$geoByUrl=is_array($data['geo']??null)?$data['geo']:[];
// Historical snapshots are immutable too. Use the frozen Oct-2026 geography
// vocabulary for a best-effort map without consulting current RSS imports.
if((int)($data['version']??1)<2){
 $dictionary=json_decode((string)@file_get_contents(__DIR__.'/collection-geo.json'),true);
 if(is_array($dictionary)){
  $placeWords=array_merge($dictionary['cityLocations']??[],$dictionary['locations']??[]);
  $sourceWords=$dictionary['sourceLocations']??[];
  foreach($articles as $article){
   $url=(string)($article['url']??'');
   if($url==='')continue;
   $sample=' '.mb_strtolower((string)($article['title']??'').' '.(string)($article['summary']??''),'UTF-8').' ';
   $event=null;
   foreach($placeWords as $entry){
    if(!is_array($entry)||count($entry)<4)continue;
    foreach($entry[3] as $keyword){
     if(str_contains($sample,(string)$keyword)){
      $event=['name'=>(string)$entry[0],'coordinates'=>[(float)$entry[2],(float)$entry[1]]];
      break 2;
     }
    }
   }
   $source=null;
   foreach($sourceWords as $key=>$point){
    if(str_contains((string)($article['source']??''),(string)$key)){
     $source=validGeoPoint($point);break;
    }
   }
   $geoByUrl[$url]=['source'=>$source,'event'=>$event];
  }
 }
}
$mapEntries=[];
$mapNodes=[];
$mapLines=[];
foreach($articles as $article){
 $url=(string)($article['url']??'');
 $geo=$geoByUrl[$url]??[];
 $from=validGeoPoint($geo['source']??null);
 $to=validGeoPoint($geo['event']??null);
 $mapEntries[]=['url'=>$url,'source'=>$from,'event'=>$to];
 foreach(['source'=>$from,'event'=>$to] as $type=>$point){
  if(!$point)continue;
  $key=$type.'|'.$point['name'];
  $mapNodes[$key]=['type'=>$type,'name'=>$point['name'],'coordinates'=>$point['coordinates']];
 }
 if($from&&$to)$mapLines[]=['from'=>$from['coordinates'],'to'=>$to['coordinates']];
}
$mapNodes=array_values($mapNodes);

$title=$name.' · Feste Artikelsammlung · IFIS News';
$description=count($articles).' dauerhaft zusammengestellte Artikel zum '.($kind==='source'?'Quellenort ':'Ereignisort ').$name.'. Stand: '.($created?date('d.m.Y H:i',strtotime($created)).' UTC':'gespeichert').'.';
$canonical='https://news.ifis.net/collection.php?id='.$id;
$image='https://news.ifis.net/share-image.php?'.http_build_query(['collection'=>$kind,'name'=>$name]);
$dynamic='https://news.ifis.net/?'.http_build_query(['view'=>$section,'map'=>1,'collection'=>$kind,'name'=>$name]);
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: public, max-age=3600');
?><!doctype html>
<html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title><?=h($title)?></title><meta name="description" content="<?=h($description)?>">
<meta property="og:type" content="website"><meta property="og:site_name" content="IFIS News">
<meta property="og:title" content="<?=h($title)?>"><meta property="og:description" content="<?=h($description)?>">
<meta property="og:url" content="<?=h($canonical)?>">
<meta property="og:image" content="<?=h($image)?>"><meta property="og:image:width" content="1200"><meta property="og:image:height" content="630">
<meta name="twitter:card" content="summary_large_image"><meta name="twitter:title" content="<?=h($title)?>">
<meta name="twitter:description" content="<?=h($description)?>"><meta name="twitter:image" content="<?=h($image)?>">
<style>
:root{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Inter,Arial,sans-serif;color-scheme:light dark}
*{box-sizing:border-box}
body{margin:0;background:#f6f8fb;color:#17212e;font-size:15px;line-height:1.5}
a{color:#2468d5}header{background:white;border-bottom:1px solid #dbe2ea;padding:15px max(18px,calc((100vw - 850px)/2));display:flex;justify-content:space-between;align-items:center;gap:15px}
header a:first-child{font-weight:750;font-size:23px;color:#17212e;text-decoration:none}
main{max-width:850px;margin:34px auto;padding:0 18px}
section{background:white;border:1px solid #e3e7ed;border-radius:17px;padding:22px;box-shadow:0 4px 20px #00000009}
h1{font-size:26px;line-height:1.25;margin:8px 0 12px}
h2{font-size:17px;line-height:1.45;margin:0 0 9px}
.muted{color:#667589;font-size:13px}.type{color:#2468d5;font-size:12px;letter-spacing:.04em;font-weight:750;text-transform:uppercase}
.article{padding:19px 0;border-top:1px solid #e6eaf0}
.article h2 a{color:inherit;text-decoration:none}.article h2 a:hover{text-decoration:underline}
.summary{font-size:14px;margin:8px 0}.links{display:flex;gap:12px;flex-wrap:wrap;margin:17px 0 22px}
.links a,.links button{border:1px solid #cfd9e7;background:#fff;color:#2468d5;border-radius:9px;padding:9px 12px;font:inherit;text-decoration:none;cursor:pointer}
.links button{background:#2468d5;color:white;border-color:#2468d5}
.note{font-size:12px;color:#6e7b8a;margin-top:20px}
@media(max-width:600px){main{margin:16px auto}section{padding:17px}h1{font-size:22px}}

/* Archived collection geographical context. Map is intentionally independent
   of the dynamic feed; the card can be collapsed without hiding connections. */
.map-wrap{position:relative;margin:19px 0 22px;border:1px solid #dce5ee;border-radius:15px;overflow:hidden;background:#dbe9ef}
#collectionMap{height:470px;width:100%;background:#dce9f2}
.map-selection{position:absolute;top:12px;left:12px;z-index:3;padding:7px 10px;border-radius:9px;background:#ffffffed;border:1px solid #e2e9f0;font-size:12px;pointer-events:none;max-width:calc(100% - 24px)}
.map-key{display:flex;gap:15px;flex-wrap:wrap;margin:0 0 13px;font-size:12px;color:#667589}
.map-key span::before{content:'';display:inline-block;width:9px;height:9px;border-radius:100%;margin-right:6px;background:#0d9488}
.map-key .key-source::before{background:#df973d}.map-key .key-line::before{height:2px;width:16px;border-radius:0;vertical-align:4px;background:#d2933b}
.collection-articles{position:absolute;bottom:15px;right:15px;width:min(355px,calc(100% - 30px));max-height:calc(100% - 75px);overflow:hidden;z-index:5;border-radius:13px;background:#fff;box-shadow:0 4px 22px #152d4566;border:1px solid #d9e3ec}
.collection-articles>summary{cursor:pointer;font-size:13px;font-weight:700;padding:11px 14px;list-style:none;display:flex;justify-content:space-between;align-items:center;gap:10px}
.collection-articles>summary::-webkit-details-marker{display:none}
.collection-articles>summary::after{content:'⌄';font-size:21px;line-height:1;color:#2468d5}
.collection-articles[open]>summary::after{content:'⌃'}
.collection-articles-body{max-height:min(280px,45vh);overflow:auto;overscroll-behavior:contain;border-top:1px solid #e3eaf0;padding:0 12px}
.collection-articles-body .article{padding:11px 0}
.collection-articles-body h2{font-size:13px;margin:0 0 4px}
.collection-articles-body .summary{font-size:12px;margin:4px 0}
.collection-articles-body .muted{font-size:11px}
.map-dot{width:15px;height:15px;border-radius:50%;border:2px solid white;box-shadow:0 1px 7px #0006;cursor:pointer}
.map-dot.source{background:#e59a35}.map-dot.event{background:#0d9488}
.map-dot.selected{width:22px;height:22px;border:3px solid white;box-shadow:0 0 0 3px #2468d577,0 1px 9px #0006}
.map-empty{padding:18px;color:#5f7282}
@media(max-width:600px){
 #collectionMap{height:475px}
 .collection-articles{right:8px;bottom:8px;width:min(300px,calc(100% - 16px));max-height:calc(100% - 65px)}
 .collection-articles-body{max-height:min(245px,42vh)}
 .map-selection{top:8px;left:8px}
}

</style><link href="https://unpkg.com/maplibre-gl@5.7.1/dist/maplibre-gl.css" rel="stylesheet"></head><body>
<header><a href="https://news.ifis.net/">IFIS News</a><a href="<?=h($dynamic)?>">Aktuelle Entwicklung ↗</a></header>
<main><section>
<div class="type"><?= $kind==='source'?'Quellenort':'Ereignisort' ?> · Feste Sammlung</div>
<h1><?=h($name)?></h1>
<p class="muted"><?=count($articles)?> Artikel · Gespeichert am <?=h($created?gmdate('d.m.Y \u\m H:i',strtotime($created)).' UTC':'unbekannt')?></p>
<div class="links"><button id="share" type="button">↗ Diese feste Sammlung teilen</button><a href="<?=h($dynamic)?>">Zur dynamischen Sammlung</a></div>
<div class="map-key"><span class="key-source">Quellenorte</span><span>Ereignisorte</span><span class="key-line">Verbindungen</span></div>
<div class="map-wrap">
<div id="collectionMap" role="region" aria-label="Karte der in dieser Sammlung dokumentierten Quellen und Ereignisorte"></div>
<div class="map-selection"><?=h($kind==='source'?'Quellenort: ':'Ereignisort: ')?><?=h($name)?></div>
<details class="collection-articles" id="collectionArticles" open>
<summary><?=count($articles)?> Artikel · Liste ausblenden</summary>
<div class="collection-articles-body">
<?php foreach($articles as $item): ?>
<article class="article">
<div class="type"><?=h((string)($item['source']??''))?></div>
<h2><a target="_blank" rel="noopener noreferrer" href="<?=h((string)($item['url']??''))?>"><?=h((string)($item['title']??''))?> ↗</a></h2>
<?php if(!empty($item['summary'])): ?><p class="summary"><?=h((string)$item['summary'])?></p><?php endif; ?>
<div class="muted"><?=h((string)($item['published_at']??''))?></div>
</article>
<?php endforeach; ?>
</div></details>
</div>
<p class="note">Diese Auswahl bleibt unverändert. Die verlinkten Originalartikel liegen bei externen Herausgebern und können später geändert oder entfernt werden.</p>
</section></main>

<script src="https://unpkg.com/maplibre-gl@5.7.1/dist/maplibre-gl.js"></script>
<script>
(()=>{
 const items=<?=json_encode($mapEntries,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE)?>;
 const nodes=<?=json_encode($mapNodes,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE)?>;
 const lines=<?=json_encode($mapLines,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE)?>;
 const selection=<?=json_encode(['kind'=>$kind,'name'=>$name],JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE)?>;
 const card=document.getElementById('collectionArticles');
 const heading=card.querySelector('summary');
 const amount=<?=count($articles)?>;
 card.addEventListener('toggle',()=>{heading.textContent=amount+' Artikel · Liste '+(card.open?'ausblenden':'einblenden')});
 if(!window.maplibregl){
  document.getElementById('collectionMap').textContent='Karte konnte nicht geladen werden.';
  return;
 }
 const map=new maplibregl.Map({
  container:'collectionMap',
  style:{version:8,sources:{topography:{type:'raster',tiles:[
   'https://a.tile.opentopomap.org/{z}/{x}/{y}.png',
   'https://b.tile.opentopomap.org/{z}/{x}/{y}.png',
   'https://c.tile.opentopomap.org/{z}/{x}/{y}.png'
  ],tileSize:256,attribution:'© OpenStreetMap-Mitwirkende · OpenTopoMap'}},
  layers:[{id:'terrain',type:'raster',source:'topography'}]},
  center:[12,25],zoom:1.3,attributionControl:true
 });
 map.addControl(new maplibregl.NavigationControl({showCompass:true}),'top-right');
 map.on('load',()=>{
  try{map.setProjection({type:'globe'})}catch(e){}
  const features=lines.map(line=>({type:'Feature',geometry:{type:'LineString',coordinates:[line.from,line.to]}}));
  map.addSource('archived-connections',{type:'geojson',data:{type:'FeatureCollection',features}});
  map.addLayer({id:'archived-connections',type:'line',source:'archived-connections',
   paint:{'line-color':'#d28f3c','line-width':2,'line-opacity':.84}});
  const chosen=nodes.find(node=>node.type===selection.kind&&node.name===selection.name);
  for(const node of nodes){
   const dot=document.createElement('button');dot.type='button';
   dot.className='map-dot '+node.type+(chosen===node?' selected':'');
   dot.setAttribute('aria-label',(node.type==='source'?'Quellenort: ':'Ereignisort: ')+node.name);
   dot.title=node.name;
   const marker=new maplibregl.Marker({element:dot,anchor:'center'})
    .setLngLat(node.coordinates).addTo(map);
   dot.addEventListener('click',()=>{
    map.easeTo({center:node.coordinates,zoom:Math.max(2,map.getZoom()),duration:400});
    new maplibregl.Popup({offset:15,closeOnClick:true})
     .setLngLat(node.coordinates).setText(node.name).addTo(map);
   });
  }
  if(chosen){
   map.jumpTo({center:chosen.coordinates,zoom:Math.max(1.65,map.getZoom())});
  }else if(nodes.length){
   const bounds=new maplibregl.LngLatBounds();
   nodes.forEach(node=>bounds.extend(node.coordinates));
   map.fitBounds(bounds,{padding:65,maxZoom:3,duration:0});
  }else{
   const note=document.createElement('div');note.className='map-empty';
   note.textContent='Für diese Artikelsammlung sind keine eindeutigen geografischen Angaben hinterlegt.';
   document.getElementById('collectionMap').append(note);
  }
 });
})();
</script>
<script>
document.getElementById('share').addEventListener('click',async()=>{
 const button=document.getElementById('share');
 if(navigator.share){try{await navigator.share({title:document.title,url:location.href});return}catch(e){if(e.name==='AbortError')return}}
 try{await navigator.clipboard.writeText(location.href);button.textContent='✓ Link kopiert'}catch(e){button.textContent='Link in der Adresszeile kopieren'}
});
</script></body></html>