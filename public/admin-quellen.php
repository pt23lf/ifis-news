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
   if($hash===''||!password_verify((string)($_POST['password']??''),$hash))throw new RuntimeException('Anmeldung fehlgeschlagen.');
   session_regenerate_id(true);$_SESSION['ifis_admin_ok']=true;
  }elseif($action==='logout'){unset($_SESSION['ifis_admin_ok']);header('Location: admin-quellen.php');exit;}
  elseif($action==='save'&&!empty($_SESSION['ifis_admin_ok'])&&$hash!==''){
   $db=new PDO('sqlite:'.dirname(__DIR__).'/storage/source-submissions.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
   $id=filter_var($_POST['id']??null,FILTER_VALIDATE_INT);$status=(string)($_POST['status']??'pending');
   $lat=trim((string)($_POST['lat']??''));$lon=trim((string)($_POST['lon']??''));
   if(!$id||!in_array($status,['pending','approved','rejected'],true))throw new RuntimeException('Ungültige Auswahl.');
   if(($lat==='')!==($lon==='')||($lat!==''&&(!is_numeric($lat)||!is_numeric($lon)||abs((float)$lat)>90||abs((float)$lon)>180)))throw new RuntimeException('Koordinaten ungültig.');
   if($status==='approved'&&$lat==='')throw new RuntimeException('Vor Freigabe Koordinaten prüfen und eintragen.');
   $stmt=$db->prepare('UPDATE submissions SET status=?,lat=?,lon=? WHERE id=?');
   $stmt->execute([$status,$lat===''?null:(float)$lat,$lon===''?null:(float)$lon,$id]);
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
<?php if(!empty($row['feed_url'])):?><p>Feed: <?=e($row['feed_url'])?></p><?php endif;?>
<p><?=e($row['place'])?> · <?=e($row['description'])?></p>
<form method="post"><input type="hidden" name="csrf" value="<?=e($_SESSION['ifis_admin_token'])?>"><input type="hidden" name="action" value="save"><input type="hidden" name="id" value="<?=e($row['id'])?>">
<label>Status <select name="status"><?php foreach(['pending'=>'Offen','approved'=>'Freigeben','rejected'=>'Ablehnen'] as $value=>$label):?><option value="<?=e($value)?>" <?=$row['status']===$value?'selected':''?>><?=e($label)?></option><?php endforeach;?></select></label>
<label>Breite <input type="number" name="lat" step="any" min="-90" max="90" value="<?=e($row['lat']??'')?>"></label>
<label>Länge <input type="number" name="lon" step="any" min="-180" max="180" value="<?=e($row['lon']??'')?>"></label><button>Speichern</button></form></article>
<?php endforeach; endif;?>
<?php if($error):?><p role="alert"><?=e($error)?></p><?php endif;?><?php if($notice):?><p role="status"><?=e($notice)?></p><?php endif;?></html>