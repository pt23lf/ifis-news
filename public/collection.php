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
  $snapshot=[
   'version'=>1,'kind'=>$kind,'name'=>$name,'view'=>$section,
   'created_at'=>gmdate('c'),'articles'=>$articles
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
$id=(string)($_GET['id']??'');
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
</style></head><body>
<header><a href="https://news.ifis.net/">IFIS News</a><a href="<?=h($dynamic)?>">Aktuelle Entwicklung ↗</a></header>
<main><section>
<div class="type"><?= $kind==='source'?'Quellenort':'Ereignisort' ?> · Feste Sammlung</div>
<h1><?=h($name)?></h1>
<p class="muted"><?=count($articles)?> Artikel · Gespeichert am <?=h($created?gmdate('d.m.Y \u\m H:i',strtotime($created)).' UTC':'unbekannt')?></p>
<div class="links"><button id="share" type="button">↗ Diese feste Sammlung teilen</button><a href="<?=h($dynamic)?>">Zur dynamischen Sammlung</a></div>
<?php foreach($articles as $item): ?>
<article class="article">
<div class="type"><?=h((string)($item['source']??''))?></div>
<h2><a target="_blank" rel="noopener noreferrer" href="<?=h((string)($item['url']??''))?>"><?=h((string)($item['title']??''))?> ↗</a></h2>
<?php if(!empty($item['summary'])): ?><p class="summary"><?=h((string)$item['summary'])?></p><?php endif; ?>
<div class="muted"><?=h((string)($item['published_at']??''))?></div>
</article>
<?php endforeach; ?>
<p class="note">Diese Auswahl bleibt unverändert. Die verlinkten Originalartikel liegen bei externen Herausgebern und können später geändert oder entfernt werden.</p>
</section></main>
<script>
document.getElementById('share').addEventListener('click',async()=>{
 const button=document.getElementById('share');
 if(navigator.share){try{await navigator.share({title:document.title,url:location.href});return}catch(e){if(e.name==='AbortError')return}}
 try{await navigator.clipboard.writeText(location.href);button.textContent='✓ Link kopiert'}catch(e){button.textContent='Link in der Adresszeile kopieren'}
});
</script></body></html>