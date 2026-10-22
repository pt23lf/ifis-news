<?php
declare(strict_types=1);
session_start();
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store');
header('X-Frame-Options: DENY');
function e($s){return htmlspecialchars((string)$s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
$hashFile=dirname(__DIR__).'/storage/admin-password.hash';
$hash=is_file($hashFile)?trim((string)file_get_contents($hashFile)):'';
if(empty($_SESSION['ifis_admin_token']))$_SESSION['ifis_admin_token']=bin2hex(random_bytes(24));
$error='';$notice='';
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  if(!hash_equals($_SESSION['ifis_admin_token'],(string)($_POST['csrf']??'')))throw new RuntimeException('Sitzung abgelaufen.');
  $action=(string)($_POST['action']??'');
  if($action==='login'){
   if(($_SESSION['ifis_admin_tries']??0)>=5&&time()-($_SESSION['ifis_admin_last']??0)<900)throw new RuntimeException('Zu viele Versuche. Später erneut versuchen.');
   if($hash===''||!password_verify((string)($_POST['password']??''),$hash)){
    $_SESSION['ifis_admin_tries']=($_SESSION['ifis_admin_tries']??0)+1;$_SESSION['ifis_admin_last']=time();
    throw new RuntimeException('Anmeldung fehlgeschlagen.');
   }
   $_SESSION['ifis_admin_tries']=0;
   session_regenerate_id(true);$_SESSION['ifis_admin_ok']=true;
  }elseif($action==='logout'){unset($_SESSION['ifis_admin_ok']);header('Location: admin-quellen.php');exit;}
  elseif($action==='save'&&!empty($_SESSION['ifis_admin_ok'])&&$hash!==''){
   $db=new PDO('sqlite:'.dirname(__DIR__).'/storage/source-submissions.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
   $id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT);$status=(string)($_POST['status']??'pending');
   $city=mb_substr(trim((string)($_POST['city']??'')),0,100,'UTF-8');
   $country=mb_substr(trim((string)($_POST['country']??'')),0,100,'UTF-8');
   $place=implode(', ',array_filter([$city,$country],static fn($value)=>$value!==''));
   $lat=trim((string)($_POST['lat']??''));$lon=trim((string)($_POST['lon']??''));
   if(!$id||!in_array($status,['pending','approved','rejected'],true))throw new RuntimeException('Ungültige Auswahl.');
   if(($lat==='')!==($lon==='')||($lat!==''&&(!is_numeric($lat)||!is_numeric($lon)||abs((float)$lat)>90||abs((float)$lon)>180)))throw new RuntimeException('Koordinaten ungültig.');
   if($status==='approved'&&($city===''||$country===''||$lat===''))throw new RuntimeException('Bitte Ort und Land angeben und automatisch zuordnen lassen.');
   $cols=array_column($db->query('PRAGMA table_info(submissions)')->fetchAll(PDO::FETCH_ASSOC),'name');
   foreach(['lat','lon'] as $col)if(!in_array($col,$cols,true))$db->exec("ALTER TABLE submissions ADD COLUMN $col REAL");
   $stmt=$db->prepare('UPDATE submissions SET status=?,place=?,lat=?,lon=? WHERE id=?');
   $stmt->execute([$status,$place,$lat===''?null:(float)$lat,$lon===''?null:(float)$lon,$id]);
   $notice='Gespeichert.';
  }
 }catch(Throwable $ex){$error=$ex->getMessage();}
}
$logged=$hash!==''&&!empty($_SESSION['ifis_admin_ok']);
$rows=[];
if($logged){
 $path=dirname(__DIR__).'/storage/source-submissions.sqlite';
 if(is_file($path)){
  $db=new PDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  $cols=array_column($db->query('PRAGMA table_info(submissions)')->fetchAll(PDO::FETCH_ASSOC),'name');
  foreach(['lat'=>'REAL','lon'=>'REAL','feed_url'=>'TEXT'] as $col=>$type)if(!in_array($col,$cols,true))$db->exec("ALTER TABLE submissions ADD COLUMN $col $type");
  $rows=$db->query('SELECT * FROM submissions ORDER BY id DESC LIMIT 300')->fetchAll(PDO::FETCH_ASSOC);
 }
}
?><!doctype html><html lang="de"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>IFIS Quellenprüfung</title>
<style>body{font:16px system-ui;margin:30px auto;padding:0 18px;max-width:870px;line-height:1.5}article{border:1px solid #aaa;border-radius:10px;padding:15px;margin:15px 0}input,select,button{font:inherit;padding:7px;margin:5px}p{overflow-wrap:anywhere}label{display:inline-block}button{cursor:pointer}</style>
<h1>IFIS News · Quellenprüfung</h1>
<?php if($hash===''):?><p>Adminzugang noch nicht eingerichtet. Erstelle auf dem Server die Datei <code>storage/admin-password.hash</code> mit einem PHP-Passworthash.</p>
<?php elseif(!$logged):?><form method="post"><input type="hidden" name="csrf" value="<?=e($_SESSION['ifis_admin_token'])?>"><input type="hidden" name="action" value="login"><input type="password" name="password" required autocomplete="current-password"><button>Anmelden</button></form>
<?php else:?><form method="post"><input type="hidden" name="csrf" value="<?=e($_SESSION['ifis_admin_token'])?>"><input type="hidden" name="action" value="logout"><button>Abmelden</button></form>
<p>Freigabe nur nach Prüfung von Quelle, Berechtigung und geografischer Zuordnung. Feeds werden nicht automatisch importiert.</p>
<?php foreach($rows as $row):?><article><strong>#<?=e($row['id'])?> · <?=e($row['title'])?></strong><p><?=e($row['type'])?> · <?=e($row['status'])?></p>
<p><a href="<?=e($row['url'])?>" target="_blank" rel="noopener noreferrer"><?=e($row['url'])?></a></p>
<?php if(!empty($row['homepage_url'])&&filter_var($row['homepage_url'],FILTER_VALIDATE_URL)&&strtolower((string)parse_url($row['homepage_url'],PHP_URL_SCHEME))==='https'):?><p>Homepage: <a href="<?=e($row['homepage_url'])?>" target="_blank" rel="noopener noreferrer"><?=e($row['homepage_url'])?></a></p><?php endif;?>
<?php if(!empty($row['feed_url'])):?><p>Feed: <?=e($row['feed_url'])?></p><?php endif;?>
<p><?=e($row['place'])?> · <?=e($row['description'])?></p>
<?php if(!empty($row['kind'])):?><p><?=e(['idea'=>'Idee / Projekt','offer'=>'Angebot / Lösungsansatz','challenge'=>'Anfrage / Herausforderung'][$row['kind']]??'Idee / Projekt')?></p><?php endif;?>
<?php if(!empty($row['topics'])):?><p>Tags / Suchbegriffe: <?=e($row['topics'])?></p><?php endif;?>
<?php if(!empty($row['creator'])):?><p>Urheber: <?=e($row['creator'])?></p><?php endif;?>
<?php if(!empty($row['private_note'])):?><details><summary>Persönliche Notiz · nur Admin</summary><p style="white-space:pre-wrap"><?=e($row['private_note'])?></p></details><?php endif;?>

<form method="post"><input type="hidden" name="csrf" value="<?=e($_SESSION['ifis_admin_token'])?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=e($row['id'])?>">
<label>Status <select name="status"><?php foreach(['pending'=>'Offen','approved'=>'Freigeben','rejected'=>'Ablehnen'] as $value=>$label):?><option value="<?=e($value)?>" <?=$row['status']===$value?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></label>
<?php
$placeParts=array_map('trim',explode(',',(string)$row['place']));
$country=count($placeParts)>1?array_pop($placeParts):'';
$city=implode(', ',$placeParts);
?>
<label>Ort <input name="city" maxlength="100" value="<?=e($city)?>" placeholder="z. B. Lübeck"></label>
<label>Land <input name="country" maxlength="100" value="<?=e($country)?>" placeholder="z. B. Deutschland"></label>
<input type="hidden" name="lat" value="<?=e($row['lat']??'')?>">
<input type="hidden" name="lon" value="<?=e($row['lon']??'')?>">
<button>Speichern</button><p class="location-status" role="status"></p></form></article>
<?php endforeach; endif;?>
<?php if($error):?><p role="alert"><?=e($error)?></p><?php endif;?><?php if($notice):?><p role="status"><?=e($notice)?></p><?php endif;?><script>
document.querySelectorAll('form:has(input[name="city"])').forEach(form=>{
 const city=form.elements.city,country=form.elements.country;
 const original=city.value.trim()+', '+country.value.trim();
 let ready=false;
 form.addEventListener('submit',async event=>{
  if(ready)return;
  event.preventDefault();
  const status=form.querySelector('.location-status'),button=form.querySelector('button');
  const place=city.value.trim()+', '+country.value.trim();
  const approved=form.elements.status.value==='approved';
  if(approved&&(!city.value.trim()||!country.value.trim())){status.textContent='Bitte Ort und Land angeben.';return;}
  if(place===original&&form.elements.lat.value!==''&&form.elements.lon.value!==''){ready=true;form.requestSubmit();return;}
  form.elements.lat.value='';form.elements.lon.value='';
  if(!city.value.trim()||!country.value.trim()){ready=true;form.requestSubmit();return;}
  button.disabled=true;status.textContent='Ort wird zugeordnet …';
  try{
   const params=new URLSearchParams({format:'json',limit:'1',city:city.value.trim(),country:country.value.trim()});
   const response=await fetch('https://nominatim.openstreetmap.org/search?'+params,{headers:{Accept:'application/json'}});
   if(!response.ok)throw Error();
   const matches=await response.json(),match=matches[0];
   if(!match||!Number.isFinite(Number(match.lat))||!Number.isFinite(Number(match.lon)))throw Error();
   form.elements.lat.value=match.lat;form.elements.lon.value=match.lon;
   ready=true;form.requestSubmit();
  }catch{
   status.textContent='Ort konnte nicht zugeordnet werden. Bitte Ort und Land genauer angeben oder später erneut versuchen.';
   if(!approved){ready=true;form.requestSubmit();}
  }finally{button.disabled=false;}
 });
});
</script></html>