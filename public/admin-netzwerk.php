<?php
declare(strict_types=1);
session_start();
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
function h($v):string{return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
$root=dirname(__DIR__);
$hashFile=$root.'/storage/admin-password.hash';
$hash=is_file($hashFile)?trim((string)file_get_contents($hashFile)):'';
if(empty($_SESSION['ifis_admin_token']))$_SESSION['ifis_admin_token']=bin2hex(random_bytes(24));
$error='';$notice='';
if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')==='login'){
 if(!hash_equals((string)$_SESSION['ifis_admin_token'],(string)($_POST['csrf']??'')))$error='Sitzung abgelaufen.';
 elseif(($_SESSION['ifis_admin_tries']??0)>=5&&time()-($_SESSION['ifis_admin_last']??0)<900)$error='Zu viele Versuche. Später erneut versuchen.';
 elseif($hash===''||!password_verify((string)($_POST['password']??''),$hash)){
  $_SESSION['ifis_admin_tries']=($_SESSION['ifis_admin_tries']??0)+1;$_SESSION['ifis_admin_last']=time();$error='Anmeldung fehlgeschlagen.';
 }else{
  session_regenerate_id(true);$_SESSION['ifis_admin_ok']=true;$_SESSION['ifis_admin_tries']=0;
  header('Location: admin-netzwerk.php');exit;
 }
}
$logged=$hash!==''&&!empty($_SESSION['ifis_admin_ok']);
if($logged&&($_POST['action']??'')==='logout'&&hash_equals((string)$_SESSION['ifis_admin_token'],(string)($_POST['csrf']??''))){
 unset($_SESSION['ifis_admin_ok']);session_regenerate_id(true);header('Location: admin-netzwerk.php');exit;
}
if(!$logged){
?><!doctype html><html lang="de"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>IFIS News · Mein Netzwerk</title><style>body{font:16px system-ui;max-width:480px;margin:12vh auto;padding:20px;line-height:1.5}input,button{font:inherit;padding:11px}input{width:100%;box-sizing:border-box}button{margin-top:12px}</style><h1>Mein Netzwerk</h1><p>Private Kontaktverwaltung. Bitte mit dem bestehenden IFIS-News-Adminpasswort anmelden.</p><?php if($hash===''):?><p>Der Adminzugang ist noch nicht eingerichtet: <code>storage/admin-password.hash</code>.</p><?php else:?><form method="post"><input type="hidden" name="action" value="login"><input type="hidden" name="csrf" value="<?=h($_SESSION['ifis_admin_token'])?>"><label>Adminpasswort <input type="password" name="password" autocomplete="current-password" required></label><button>Anmelden</button></form><?php endif;?><?php if($error):?><p role="alert"><?=h($error)?></p><?php endif;?></html><?php exit;
}
$db=new PDO('sqlite:'.$root.'/storage/network.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_TIMEOUT=>10]);
$db->exec("CREATE TABLE IF NOT EXISTS contacts(id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT NOT NULL,linkedin_url TEXT NOT NULL DEFAULT '',place TEXT NOT NULL DEFAULT '',lat REAL,lon REAL,topic TEXT NOT NULL DEFAULT '',relation TEXT NOT NULL DEFAULT 'commented',note TEXT NOT NULL DEFAULT '',created_at TEXT NOT NULL,updated_at TEXT NOT NULL)");
$db->exec('CREATE UNIQUE INDEX IF NOT EXISTS contacts_linkedin_unique ON contacts(linkedin_url) WHERE linkedin_url <> ""');
function field(string $key,int $limit):string{return mb_substr(trim((string)($_POST[$key]??'')),0,$limit,'UTF-8');}
function linkedinUrl(string $value):bool{
 if($value==='')return true;
 $p=parse_url($value);if(!is_array($p)||strtolower((string)($p['scheme']??''))!=='https'||empty($p['host'])||isset($p['user'])||isset($p['pass']))return false;
 $host=strtolower($p['host']);
 return $host==='linkedin.com'||str_ends_with($host,'.linkedin.com')||$host==='lnkd.in';
}
function coord(string $s,float $max):?float{
 if($s==='')return null;
 if(!is_numeric($s)||!is_finite((float)$s)||abs((float)$s)>$max)throw new RuntimeException('Ungültige Koordinaten.');
 return (float)$s;
}
function saveContact(PDO $db,array $row,?int $id=null):void{
 $name=trim((string)($row['name']??''));$url=trim((string)($row['linkedin_url']??''));
 if($name===''||mb_strlen($name,'UTF-8')>140||!linkedinUrl($url))throw new RuntimeException('Name oder LinkedIn-Link ungültig.');
 $relation=(string)($row['relation']??'commented');
 if(!in_array($relation,['commented','contact','interesting'],true))$relation='interesting';
 $lat=coord(trim((string)($row['lat']??'')),90);$lon=coord(trim((string)($row['lon']??'')),180);
 if(($lat===null)!==($lon===null))throw new RuntimeException('Bitte beide Koordinaten angeben.');
 $values=[$name,$url,mb_substr((string)($row['place']??''),0,160,'UTF-8'),$lat,$lon,mb_substr((string)($row['topic']??''),0,220,'UTF-8'),$relation,mb_substr((string)($row['note']??''),0,1200,'UTF-8'),gmdate('c')];
 if($id!==null){
  $stmt=$db->prepare('UPDATE contacts SET name=?,linkedin_url=?,place=?,lat=?,lon=?,topic=?,relation=?,note=?,updated_at=? WHERE id=?');
  $stmt->execute([...$values,$id]);
 }else{
  $stmt=$db->prepare('INSERT INTO contacts(name,linkedin_url,place,lat,lon,topic,relation,note,updated_at,created_at) VALUES(?,?,?,?,?,?,?,?,?,?)');
  $stmt->execute([...$values,gmdate('c')]);
 }
}
if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')==='geocode'){
 header('Content-Type: application/json; charset=utf-8');
 if(!hash_equals((string)$_SESSION['ifis_admin_token'],(string)($_POST['csrf']??''))){http_response_code(403);echo '{"error":"Sitzung abgelaufen"}';exit;}
 $id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT);
 $lat=filter_var($_POST['lat']??null,FILTER_VALIDATE_FLOAT);
 $lon=filter_var($_POST['lon']??null,FILTER_VALIDATE_FLOAT);
 $original=trim((string)($_POST['place']??''));
 if(!$id||$lat===false||$lon===false||abs($lat)>90||abs($lon)>180||strlen($original)>160){http_response_code(400);echo '{"error":"Koordinaten ungültig"}';exit;}
 $stmt=$db->prepare('UPDATE contacts SET lat=?,lon=?,updated_at=? WHERE id=? AND place=? AND lat IS NULL AND lon IS NULL');
 $stmt->execute([$lat,$lon,gmdate('c'),$id,$original]);
 echo json_encode(['ok'=>$stmt->rowCount()===1]);exit;
}
if($_SERVER['REQUEST_METHOD']==='POST'&&($_POST['action']??'')!=='login'){
 try{
  if(!hash_equals((string)$_SESSION['ifis_admin_token'],(string)($_POST['csrf']??'')))throw new RuntimeException('Sitzung abgelaufen; neu laden.');
  $action=(string)($_POST['action']??'');
  if($action==='save'){
   $id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT)?:null;
   if($id!==null&&!$db->query('SELECT id FROM contacts WHERE id='.(int)$id)->fetchColumn())throw new RuntimeException('Kontakt nicht gefunden.');
   $row=[];foreach(['name','linkedin_url','place','lat','lon','topic','relation','note'] as $key)$row[$key]=field($key,$key==='note'?1200:500);
   saveContact($db,$row,$id);$notice='Kontakt gespeichert.';
  }elseif($action==='delete'){
   $id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT);
   if(!$id||($_POST['confirm']??'')!=='yes')throw new RuntimeException('Löschen nicht bestätigt.');
   $db->beginTransaction();
   try{
    if($db->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='capture_profiles'")->fetchColumn()){
     $stmt=$db->prepare('DELETE FROM capture_comments WHERE author_id=?');$stmt->execute([$id]);
     $stmt=$db->prepare('UPDATE capture_posts SET author_id=NULL WHERE author_id=?');$stmt->execute([$id]);
     $stmt=$db->prepare('DELETE FROM capture_profiles WHERE contact_id=?');$stmt->execute([$id]);
    }
    $stmt=$db->prepare('DELETE FROM contacts WHERE id=?');$stmt->execute([$id]);$db->commit();
   }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
   $notice='Kontakt gelöscht.';
  }elseif($action==='csv'){
   if(!isset($_FILES['csv'])||($_FILES['csv']['error']??1)!==UPLOAD_ERR_OK||($_FILES['csv']['size']??0)>1048576)throw new RuntimeException('CSV-Datei fehlt oder ist größer als 1 MB.');
   $f=fopen($_FILES['csv']['tmp_name'],'rb');if(!$f)throw new RuntimeException('Datei nicht lesbar.');
   $header=fgetcsv($f,0,',','"','\\');if(!$header)throw new RuntimeException('Leere CSV.');
   $header=array_map(static fn($v)=>strtolower(trim((string)$v)), $header);
   if(!in_array('name',$header,true))throw new RuntimeException('CSV benötigt eine Spalte name.');
   $required=['name','linkedin_url','place','lat','lon','topic','relation','note'];
   $count=0;$skipped=0;
   $db->beginTransaction();
   try{
    while(($cells=fgetcsv($f,0,',','"','\\'))!==false){
     if($count+$skipped>=500)break;
     $row=array_combine($header,array_pad(array_slice($cells,0,count($header)),count($header),''));
     if(!is_array($row)){$skipped++;continue;}
     $data=[];foreach($required as $key)$data[$key]=(string)($row[$key]??'');
     if(trim($data['place'])==='')$data['place']=implode(', ',array_filter([trim((string)($row['city']??'')),trim((string)($row['country']??''))]));
     try{
      if($data['linkedin_url']!==''){
       $check=$db->prepare('SELECT id FROM contacts WHERE linkedin_url=?');$check->execute([$data['linkedin_url']]);
       if($check->fetchColumn()){$skipped++;continue;}
      }
      saveContact($db,$data);$count++;
     }catch(Throwable $e){$skipped++;}
    }
    $db->commit();
   }catch(Throwable $e){$db->rollBack();throw $e;}finally{fclose($f);}
   $notice=$count.' Kontakte importiert, '.$skipped.' übersprungen. Orte ohne Koordinaten erscheinen noch nicht auf der Karte.';
  }
 }catch(Throwable $e){$error=$e instanceof PDOException?'Speichern fehlgeschlagen (möglicherweise doppelter LinkedIn-Link).':$e->getMessage();}
}
$hasComments=(bool)$db->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='linkedin_comments'")->fetchColumn();
$hasCapture=(bool)$db->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='capture_comments'")->fetchColumn();
$commentCount=$hasComments?'(SELECT COUNT(*) FROM linkedin_comments k WHERE k.contact_id=c.id)':'0';
if($hasCapture)$commentCount.='+(SELECT COUNT(*) FROM capture_comments k WHERE k.author_id=c.id)';
$rows=$db->query('SELECT c.*, '.$commentCount.' AS comments_count FROM contacts c ORDER BY c.updated_at DESC,c.id DESC LIMIT 1000')->fetchAll(PDO::FETCH_ASSOC);
$geo=array_values(array_map(static fn($r)=>['id'=>(int)$r['id'],'name'=>$r['name'],'url'=>$r['linkedin_url'],'place'=>$r['place'],'lat'=>(float)$r['lat'],'lon'=>(float)$r['lon'],'topic'=>$r['topic'],'relation'=>$r['relation'],'comments_count'=>(int)$r['comments_count']],array_filter($rows,static fn($r)=>$r['lat']!==null&&$r['lon']!==null)));
?><!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Mein Netzwerk · IFIS News</title>
<link href="https://unpkg.com/maplibre-gl@5.7.1/dist/maplibre-gl.css" rel="stylesheet">
<style>
:root{font:15px system-ui,-apple-system,sans-serif;color-scheme:light dark}*{box-sizing:border-box}body{margin:0;background:Canvas;color:CanvasText}header{display:flex;gap:12px;align-items:center;justify-content:space-between;padding:12px 20px;border-bottom:1px solid #aaa5}header a{color:inherit}header form{margin:0}main{max-width:1300px;margin:auto;padding:18px;display:grid;grid-template-columns:minmax(330px,430px) minmax(0,1fr);gap:20px}.panel{border:1px solid #aaa6;border-radius:12px;padding:16px}h1{font-size:1.4rem;margin:0}h2{font-size:1.1rem;margin:0 0 12px}label{display:grid;gap:4px;font-size:.9rem}input,select,textarea,button{font:inherit;padding:9px;border:1px solid #9998;border-radius:7px;max-width:100%}textarea{min-height:65px}button{cursor:pointer;background:transparent;color:inherit}.primary{background:#258457;color:#fff;border:0}.fields{display:grid;gap:11px}.two{display:grid;grid-template-columns:1fr 1fr;gap:10px}.muted{opacity:.7;font-size:.86rem}.message{background:#27896318;border-radius:8px;padding:9px}#map{height:580px;border-radius:12px;overflow:hidden;background:#e8eced}.contact{border-top:1px solid #aaa5;padding:13px 0;overflow-wrap:anywhere}.contact a{color:inherit}.contact strong{display:block}.actions{display:flex;gap:8px;margin-top:7px}.maplibregl-popup-content{color:#222;border-radius:12px}.compass{width:19px;height:19px;border-radius:50%;background:#fff;border:2px solid #277b63;display:grid;place-items:center;cursor:pointer}.homepin{width:22px;height:22px;background:#2d557e;border:2px solid #fff;border-radius:50%;box-shadow:0 0 0 2px #2d557e}.map-tools{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin:10px 0}.map-tools label{display:flex;align-items:center;gap:6px}.map-tools input{width:auto}.compass::after{content:'';border-left:5px solid transparent;border-right:5px solid transparent;border-bottom:9px solid #26895e}#list{max-height:550px;overflow-y:auto}#list:empty::after{content:'Noch keine Kontakte.'}.row{display:flex;justify-content:space-between;gap:12px;align-items:center}.small{font-size:.83rem}@media(max-width:850px){main{grid-template-columns:1fr}#map{height:360px}}
</style></head><body>
<header><div><a href="/">← IFIS News</a> · <strong>Mein Netzwerk</strong> <span class="muted">privat</span></div><form method="post"><input type="hidden" name="csrf" value="<?=h($_SESSION['ifis_admin_token'])?>"><input type="hidden" name="action" value="logout"><button>Abmelden</button></form></header>
<main><section class="panel"><h1>LinkedIn-Kontakte</h1><p><a href="admin-erfassung.php"><strong>→ Screenshots erfassen · Profil- und Gesprächsnetz</strong></a></p><p><a href="admin-kommentare.php"><strong>→ LinkedIn-Kommentare importieren und ansehen</strong></a></p><p class="muted">Manuell erfassen oder CSV importieren. Diese Daten sind nicht öffentlich; keine LinkedIn-Abfragen.</p>
<?php if($error):?><p role="alert" class="message"><?=h($error)?></p><?php endif;?><?php if($notice):?><p role="status" class="message"><?=h($notice)?></p><?php endif;?>
<form method="post" id="contactForm" class="fields">
<input type="hidden" name="csrf" value="<?=h($_SESSION['ifis_admin_token'])?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" id="contactId">
<label>Name oder Organisation<input name="name" id="name" maxlength="140" required></label>
<label>LinkedIn-Profil oder Beitragslink (optional)<input type="url" name="linkedin_url" id="linkedin_url" placeholder="https://www.linkedin.com/..." maxlength="500"></label>
<div class="two"><label>Stadt / Region<input id="city" maxlength="100" placeholder="z. B. Nairobi"></label><label>Land<input id="country" maxlength="100" placeholder="z. B. Kenia"></label></div>
<input type="hidden" name="place" id="place"><input type="hidden" name="lat" id="lat"><input type="hidden" name="lon" id="lon">
<small class="muted" id="geoStatus">Beim Speichern wird die ungefähre Position automatisch aus Stadt und Land ermittelt. Kein exakter Wohnort.</small>
<label>Thema / Schlagwörter<input name="topic" id="topic" maxlength="220" placeholder="z. B. Biodiversität, Wasser"></label>
<label>Unsere Verbindung<select name="relation" id="relation"><option value="commented">Ich habe kommentiert</option><option value="contact">LinkedIn-Kontakt</option><option value="interesting">Interessantes Profil</option></select></label>
<label>Private Notiz<textarea name="note" id="note" maxlength="1200"></textarea></label>
<div class="row"><button class="primary" type="submit">Kontakt speichern</button><button type="button" id="reset">Neuer Kontakt</button></div>
</form><hr><details><summary>CSV-Datei importieren</summary><p class="muted">Bis 500 Kontakte und 1 MB pro Import. Erste Zeile: <code>name,linkedin_url,city,country,topic,relation,note</code> (alternativ <code>place,lat,lon</code>). Kommagetrennte UTF-8-CSV. Doppelte LinkedIn-Links werden übersprungen. Ohne Koordinaten bleibt der Kontakt in der Liste.</p><form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=h($_SESSION['ifis_admin_token'])?>"><input type="hidden" name="action" value="csv"><input type="file" name="csv" accept=".csv,text/csv" required><button>Privat importieren</button></form></details></section>
<section><div class="panel"><div class="row"><h2>Deine Weltkarte</h2><small class="muted"><?=count($geo)?> Orte · <?=count($rows)?> Kontakte</small></div><div class="map-tools"><label><input type="checkbox" id="showLines" checked> Verbindungslinien ab Lübeck</label><button id="geocodeMissing" type="button">Fehlende Orte zuordnen</button></div><p id="geoProgress" class="muted" role="status"></p><div id="map" aria-label="Private Netzwerk-Weltkarte"></div><p class="muted">Die Punkte zeigen ungefähre Profilstandorte, nicht zwingend Wohnorte oder Orte der besprochenen Projekte.</p></div>
<div class="panel" style="margin-top:16px"><h2>Kontakte</h2><label>Kontakt suchen<input id="filter" type="search" placeholder="Name, Ort, Thema …"></label><div id="list"><?php foreach($rows as $r):?><div class="contact" data-search="<?=h(mb_strtolower($r['name'].' '.$r['place'].' '.$r['topic'],'UTF-8'))?>"><strong><?=h($r['name'])?></strong><small class="muted">Kontakt #<?=h($r['id'])?></small> · <span class="muted"><?=h($r['place'])?> · <?=h($r['topic'])?> · <?=h($r['comments_count'])?> Kommentare</span><?php if($r['linkedin_url']):?><p class="small"><a href="<?=h($r['linkedin_url'])?>" target="_blank" rel="noopener noreferrer">LinkedIn öffnen ↗</a></p><?php endif;?><div class="actions"><button type="button" class="edit" data-id="<?=h($r['id'])?>">Bearbeiten</button><form method="post" onsubmit="return confirm('Diesen Kontakt wirklich löschen?')"><input type="hidden" name="csrf" value="<?=h($_SESSION['ifis_admin_token'])?>"><input type="hidden" name="action" value="delete"><input type="hidden" name="confirm" value="yes"><input type="hidden" name="id" value="<?=h($r['id'])?>"><button>Löschen</button></form></div></div><?php endforeach;?></div></div>
</section></main>
<script src="https://unpkg.com/maplibre-gl@5.7.1/dist/maplibre-gl.js"></script><script src="profile-threads.js?v=2"></script><script>
const contacts=<?=json_encode($rows,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE|JSON_UNESCAPED_UNICODE)?>;
const geo=<?=json_encode($geo,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE|JSON_UNESCAPED_UNICODE)?>;
const form=document.getElementById('contactForm');
const ids=['name','linkedin_url','topic','relation','note'];
function edit(id){const c=contacts.find(x=>Number(x.id)===Number(id));if(!c)return;document.getElementById('contactId').value=c.id;ids.forEach(k=>document.getElementById(k).value=c[k]??'');
 const parts=(c.place||'').split(',').map(x=>x.trim());document.getElementById('city').value=parts.slice(0,-1).join(', ')|| (parts.length===1?parts[0]:'');document.getElementById('country').value=parts.length>1?parts[parts.length-1]:'';
 document.getElementById('place').value=c.place||'';document.getElementById('lat').value=c.lat??'';document.getElementById('lon').value=c.lon??'';form.scrollIntoView({behavior:'smooth'});}
document.querySelectorAll('.edit').forEach(b=>b.addEventListener('click',()=>edit(b.dataset.id)));
document.getElementById('reset').addEventListener('click',()=>{form.reset();document.getElementById('contactId').value='';});
document.getElementById('filter').addEventListener('input',e=>{const q=e.target.value.toLocaleLowerCase('de');document.querySelectorAll('.contact').forEach(c=>c.hidden=!c.dataset.search.includes(q));});
let map=null;
if(window.maplibregl){
 map=new maplibregl.Map({container:'map',style:'https://demotiles.maplibre.org/style.json',projection:'globe',center:[12,23],zoom:1.2});
 map.addControl(new maplibregl.NavigationControl(),'top-right');
 const home=[10.686,53.866];
 const sourcePin=document.createElement('div');sourcePin.className='homepin';sourcePin.title='Lübeck – Startpunkt deines Netzwerks';
 new maplibregl.Marker({element:sourcePin,occludedOpacity:0}).setLngLat(home).setPopup(new maplibregl.Popup({offset:15}).setText('Mein Netzwerk · Lübeck')).addTo(map);
 const degrees=Math.PI/180;
 const toVector=([lon,lat])=>[Math.cos(lat*degrees)*Math.cos(lon*degrees),Math.cos(lat*degrees)*Math.sin(lon*degrees),Math.sin(lat*degrees)];
 function arc(target){
  const a=toVector(home),b=toVector(target);const dot=Math.min(1,Math.max(-1,a.reduce((sum,v,i)=>sum+v*b[i],0)));const angle=Math.acos(dot);
  if(angle<1e-7)return [home,target];
  const positions=[];const sin=Math.sin(angle);
  for(let i=0;i<=48;i++){
   const t=i/48,x=Math.sin((1-t)*angle)/sin,y=Math.sin(t*angle)/sin;
   const v=a.map((val,k)=>val*x+b[k]*y);
   positions.push([Math.atan2(v[1],v[0])/degrees,Math.atan2(v[2],Math.hypot(v[0],v[1]))/degrees]);
  }
  return positions;
 }
 function drawLines(){
  const features=geo.map(c=>({type:'Feature',geometry:{type:'LineString',coordinates:arc([c.lon,c.lat])},properties:{name:c.name}}));
  const data={type:'FeatureCollection',features};
  if(!map.getSource('network-connections'))map.addSource('network-connections',{type:'geojson',data});
  else map.getSource('network-connections').setData(data);
  if(!map.getLayer('network-connections'))map.addLayer({id:'network-connections',type:'line',source:'network-connections',paint:{'line-color':'#2c8a67','line-opacity':0.58,'line-width':1.5}});
  map.setLayoutProperty('network-connections','visibility',document.getElementById('showLines').checked?'visible':'none');
 }
 map.on('style.load',drawLines);
 document.getElementById('showLines').addEventListener('change',()=>{if(map.getLayer('network-connections'))map.setLayoutProperty('network-connections','visibility',document.getElementById('showLines').checked?'visible':'none');});

 geo.forEach(c=>{
  const el=document.createElement('button');el.className='compass';el.type='button';el.setAttribute('aria-label',c.name);
  const box=document.createElement('div');const title=document.createElement('strong');title.textContent=c.name;box.append(title);
  const place=document.createElement('p');place.textContent=c.place||'Ungefährer Profilstandort';box.append(place);
  if(c.topic){const t=document.createElement('p');t.textContent=c.topic;box.append(t);}
  const interactions=document.createElement('p');interactions.textContent='Posts und meine Kommentare';box.append(interactions);
  if(c.url){const link=document.createElement('a');link.href=c.url;link.target='_blank';link.rel='noopener noreferrer';link.textContent='LinkedIn öffnen ↗';box.append(link);}
  const threads=document.createElement('div');box.append(threads);
  const profilePopup=new maplibregl.Popup({offset:18,maxWidth:'min(400px,calc(100vw - 40px))'}).setDOMContent(box);
  profilePopup.on('open',()=>window.loadProfileThreads?.(threads,{contactId:c.id,replace:true}));
  box.style.maxHeight='min(55vh,420px)';box.style.overflowY='auto';
  new maplibregl.Marker({element:el,occludedOpacity:0}).setLngLat([c.lon,c.lat]).setPopup(profilePopup).addTo(map);
 });
}
document.getElementById('geocodeMissing').addEventListener('click',async()=>{
 const pending=contacts.filter(c=>c.place&& (c.lat===null||c.lon===null));
 const status=document.getElementById('geoProgress'),button=document.getElementById('geocodeMissing');
 if(!pending.length){status.textContent='Alle bekannten Orte sind bereits zugeordnet.';return;}
 // Limit each manually initiated operation to 15 locations and respect Nominatim rate limits.
 const batch=pending.slice(0,15);const cache=new Map();let success=0,failed=0;
 button.disabled=true;
 for(const [i,c] of batch.entries()){
  status.textContent='Orte zuordnen: '+(i+1)+'/'+batch.length+' · '+c.place;
  try{
   let loc=cache.get(c.place.toLocaleLowerCase('de'));
   if(!loc){
    const response=await fetch('https://nominatim.openstreetmap.org/search?format=json&limit=1&q='+encodeURIComponent(c.place),{headers:{Accept:'application/json'}});
    if(!response.ok)throw Error('Suche fehlgeschlagen');
    const data=await response.json();
    if(!data.length)throw Error('Kein Ort gefunden');
    loc=[Number(data[0].lat),Number(data[0].lon)];
    cache.set(c.place.toLocaleLowerCase('de'),loc);
   }
   const body=new URLSearchParams({action:'geocode',csrf:<?=json_encode($_SESSION['ifis_admin_token'])?>,id:String(c.id),place:c.place,lat:String(loc[0]),lon:String(loc[1])});
   const saved=await fetch('admin-netzwerk.php',{method:'POST',credentials:'same-origin',body});
   const result=await saved.json();
   if(!result.ok)throw Error('Nicht gespeichert');
   success++;
  }catch(e){failed++;console.warn('Ortszuordnung',c.place,e);}
  // Never exceed 1 request/second to public Nominatim.
  if(i<batch.length-1)await new Promise(resolve=>setTimeout(resolve,1300));
 }
 status.textContent=success+' Orte zugeordnet, '+failed+' offen. Karte wird neu geladen …';
 location.reload();
});
let lastGeocode=0;
form.addEventListener('submit',async e=>{
 e.preventDefault();
 const city=document.getElementById('city').value.trim(),country=document.getElementById('country').value.trim();
 const place=[city,country].filter(Boolean).join(', ');
 document.getElementById('place').value=place;
 const lat=document.getElementById('lat'),lon=document.getElementById('lon');
 const status=document.getElementById('geoStatus'),submit=form.querySelector('button[type="submit"]');
 if(!place){lat.value='';lon.value='';form.submit();return;}
 // Keep existing coordinates when editing a contact without changing its location.
 const old=contacts.find(x=>String(x.id)===document.getElementById('contactId').value);
 if(old&&old.place===place&&old.lat!==null&&old.lon!==null){form.submit();return;}
 submit.disabled=true;status.textContent='Stadt und Land werden zugeordnet …';
 try{
  // Single, user-triggered lookup. No autocomplete or bulk requests.
  const delay=Math.max(0,1100-(Date.now()-lastGeocode));if(delay)await new Promise(resolve=>setTimeout(resolve,delay));
  lastGeocode=Date.now();
  const endpoint='https://nominatim.openstreetmap.org/search?format=json&limit=1&q='+encodeURIComponent(place);
  const res=await fetch(endpoint,{headers:{'Accept':'application/json'}});
  if(!res.ok)throw Error('Geodienst derzeit nicht erreichbar');
  const items=await res.json();
  if(!items.length)throw Error('Ort nicht gefunden');
  lat.value=Number(items[0].lat).toFixed(5);lon.value=Number(items[0].lon).toFixed(5);
  status.textContent='Ort zugeordnet: '+items[0].display_name;
  form.submit();
 }catch(err){
  lat.value='';lon.value='';
  status.textContent='Ort konnte nicht zugeordnet werden.';
  if(confirm('Die Ortsangabe konnte nicht automatisch auf der Karte platziert werden. Kontakt trotzdem ohne Kartenpunkt speichern?'))form.submit();
  else submit.disabled=false;
 }
});
</script></body></html>


