<?php
declare(strict_types=1);
session_start();
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
function h($v):string{return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
$root=dirname(__DIR__);
$hashFile=$root.'/storage/admin-password.hash';
$hash=is_file($hashFile)?trim((string)file_get_contents($hashFile)):'';
if(empty($_SESSION['ifis_admin_token']))$_SESSION['ifis_admin_token']=bin2hex(random_bytes(24));
if($hash===''||empty($_SESSION['ifis_admin_ok'])){
 http_response_code(403);
 ?><!doctype html><html lang="de"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Privater Kommentarimport</title><p>Bitte zuerst in <a href="admin-netzwerk.php">Mein Netzwerk</a> anmelden.</p></html><?php exit;
}
$db=new PDO('sqlite:'.$root.'/storage/network.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_TIMEOUT=>10]);
$db->exec("CREATE TABLE IF NOT EXISTS contacts(id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT NOT NULL,linkedin_url TEXT NOT NULL DEFAULT '',place TEXT NOT NULL DEFAULT '',lat REAL,lon REAL,topic TEXT NOT NULL DEFAULT '',relation TEXT NOT NULL DEFAULT 'commented',note TEXT NOT NULL DEFAULT '',created_at TEXT NOT NULL,updated_at TEXT NOT NULL)");
$db->exec('CREATE UNIQUE INDEX IF NOT EXISTS contacts_linkedin_unique ON contacts(linkedin_url) WHERE linkedin_url <> ""');
$db->exec("CREATE TABLE IF NOT EXISTS linkedin_comments (
 id INTEGER PRIMARY KEY AUTOINCREMENT, fingerprint TEXT NOT NULL UNIQUE, contact_id INTEGER,
 author TEXT NOT NULL, post_date TEXT NOT NULL DEFAULT '', post_excerpt TEXT NOT NULL DEFAULT '',
 my_comment TEXT NOT NULL DEFAULT '', profile_url TEXT NOT NULL DEFAULT '', post_url TEXT NOT NULL DEFAULT '',
 city TEXT NOT NULL DEFAULT '', country TEXT NOT NULL DEFAULT '', pdf_page TEXT NOT NULL DEFAULT '',
 text_status TEXT NOT NULL DEFAULT '', comment_views INTEGER, post_views INTEGER,
 imported_at TEXT NOT NULL, FOREIGN KEY(contact_id) REFERENCES contacts(id))");
$db->exec('CREATE INDEX IF NOT EXISTS idx_ln_comment_contact ON linkedin_comments(contact_id)');
$error='';$notice='';
function csvUrl(string $s):string{
 $s=trim($s);
 if($s==='')return '';
 $parts=parse_url($s);
 if(!is_array($parts)||strtolower((string)($parts['scheme']??''))!=='https'||empty($parts['host'])||isset($parts['user'])||isset($parts['pass']))return '';
 $host=strtolower((string)$parts['host']);
 return in_array($host,['linkedin.com','www.linkedin.com','lnkd.in'],true)||str_ends_with($host,'.linkedin.com')?$s:'';
}
function clean($v,int $n=1000):string{return mb_substr(trim((string)$v),0,$n,'UTF-8');}
function views($v):?int{
 $v=trim((string)$v);if($v==='')return null;
 if(!preg_match('/^[0-9]{1,10}$/',$v))return null;
 return min(1000000000,(int)$v);
}
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  if(!hash_equals((string)$_SESSION['ifis_admin_token'],(string)($_POST['csrf']??'')))throw new RuntimeException('Sitzung abgelaufen. Seite neu laden.');
  if(($_POST['action']??'')==='import'){
   $file=$_FILES['file']??null;
   if(!$file||$file['error']!==UPLOAD_ERR_OK||$file['size']>2*1024*1024)throw new RuntimeException('Bitte CSV-Datei bis 2 MB auswählen.');
   $fp=fopen($file['tmp_name'],'rb');if(!$fp)throw new RuntimeException('CSV nicht lesbar.');
   $first=fgetcsv($fp,0,',','"','\\');
   if(!$first)throw new RuntimeException('CSV ist leer.');
   $columns=array_map(static fn($x)=>trim(strtolower((string)$x),"\xEF\xBB\xBF \t\n\r"),$first);
   foreach(['profilname','post_text_sichtbar','mein_kommentar_sichtbar'] as $column)
    if(!in_array($column,$columns,true))throw new RuntimeException('Die CSV benötigt die Spalten profilname, post_text_sichtbar und mein_kommentar_sichtbar.');
   $insert=$db->prepare('INSERT OR IGNORE INTO linkedin_comments(fingerprint,contact_id,author,post_date,post_excerpt,my_comment,profile_url,post_url,city,country,pdf_page,text_status,comment_views,post_views,imported_at) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?,?)');
   $byProfile=$db->prepare('SELECT id FROM contacts WHERE linkedin_url=? LIMIT 1');
   $byName=$db->prepare('SELECT id FROM contacts WHERE lower(name)=lower(?) AND (place=? OR place="") ORDER BY CASE WHEN place=? THEN 0 ELSE 1 END LIMIT 1');
   $contactInsert=$db->prepare('INSERT INTO contacts(name,linkedin_url,place,topic,relation,note,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?)');
   $contactUpdate=$db->prepare('UPDATE contacts SET place=CASE WHEN place="" THEN ? ELSE place END,linkedin_url=CASE WHEN linkedin_url="" THEN ? ELSE linkedin_url END,updated_at=? WHERE id=?');
   $count=0;$contactsAdded=0;$duplicates=0;$line=0;
   $db->beginTransaction();
   try{
    while(($values=fgetcsv($fp,0,',','"','\\'))!==false){
     if(++$line>600)throw new RuntimeException('Maximal 600 Kommentare pro Datei.');
     if(count($values)!==count($columns)){continue;}
     $row=array_combine($columns,$values);
     $author=clean($row['profilname']??'',140);
     $post=clean($row['post_text_sichtbar']??'',4500);
     $comment=clean($row['mein_kommentar_sichtbar']??'',4500);
     if($author===''||($post===''&&$comment===''))continue;
     $date=clean($row['post_datum']??'',40);
     $page=clean($row['quelle_pdf_seite']??'',40);
     $profile=csvUrl(clean($row['profil_url']??'',600));
     $postUrl=csvUrl(clean($row['beitrag_url']??'',600));
     $city=clean($row['stadt_region']??'',120);$country=clean($row['land']??'',120);
     if(strtolower($country)==='unbekannt')$country='';
     $place=implode(', ',array_filter([$city,$country],static fn($x)=>$x!==''));
     // The source CSV records comments on one's own posts too; do not generate a new external contact for those.
     $contactId=null;
     if(mb_strtolower($author,'UTF-8')!=='lars focke'){
      if($profile!==''){$byProfile->execute([$profile]);$contactId=$byProfile->fetchColumn()?:null;}
      if(!$contactId){$byName->execute([$author,$place,$place]);$contactId=$byName->fetchColumn()?:null;}
      if(!$contactId){
       $now=gmdate('c');
       $contactInsert->execute([$author,$profile,$place,'','commented','Aus eigenem LinkedIn-Kommentarimport',$now,$now]);
       $contactId=(int)$db->lastInsertId();$contactsAdded++;
      }else{
       $existing=$db->prepare('SELECT linkedin_url FROM contacts WHERE id=?');$existing->execute([$contactId]);
       $oldUrl=(string)$existing->fetchColumn();
       // Never overwrite an existing contact's different LinkedIn identity.
       $contactUpdate->execute([$place,$oldUrl===''?$profile:'',gmdate('c'),$contactId]);
      }
     }
     // Fingerprint is independent of place and view counts, so re-imports cannot duplicate entries.
     $fingerprint=hash('sha256',implode("\x1F",[$author,$date,$post,$comment,$page]));
     $insert->execute([$fingerprint,$contactId,$author,$date,$post,$comment,$profile,$postUrl,$city,$country,$page,clean($row['textstatus']??'',160),views($row['kommentar_views']??''),views($row['post_views']??''),gmdate('c')]);
     if($insert->rowCount())$count++;else $duplicates++;
    }
    $db->commit();
   }catch(Throwable $e){$db->rollBack();throw $e;}finally{fclose($fp);}
   $notice="$count Kommentare importiert, $contactsAdded Kontakte neu angelegt, $duplicates Dubletten übersprungen.";
  }
 }catch(Throwable $e){$error=$e instanceof PDOException?'Datenbankfehler beim Import. Bitte CSV prüfen.':$e->getMessage();}
}
$captureRows=[];
$hasCapture=(bool)$db->query("SELECT 1 FROM sqlite_master WHERE type='table' AND name='capture_comments'")->fetchColumn();
if($hasCapture){
 $captureRows=$db->query("SELECT k.source_key,k.text,k.text_status,k.url AS comment_url,k.evidence,p.id AS post_id,p.title,p.excerpt,p.url AS post_url,p.tags,p.place AS project_place,COALESCE(c.name,'Noch nicht zugeordnet') AS author,COALESCE(c.place,'') AS profile_place,COALESCE(c.linkedin_url,'') AS profile_url
 FROM capture_comments k JOIN capture_posts p ON p.id=k.post_id LEFT JOIN contacts c ON c.id=p.author_id
 WHERE EXISTS(SELECT 1 FROM capture_profiles own WHERE own.source_key='profile-lars-focke' AND own.contact_id=k.author_id)
 ORDER BY k.source_key ASC LIMIT 2000")->fetchAll(PDO::FETCH_ASSOC);
}
$stats=$db->query('SELECT COUNT(*) AS c,COUNT(DISTINCT author) AS authors FROM linkedin_comments')->fetch(PDO::FETCH_ASSOC);
$rows=$db->query('SELECT author,post_date,post_excerpt,my_comment,profile_url,post_url,city,country,comment_views,post_views,pdf_page,text_status FROM linkedin_comments ORDER BY COALESCE(comment_views,-1) DESC,id DESC LIMIT 250')->fetchAll(PDO::FETCH_ASSOC);
?><!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>LinkedIn-Kommentare · IFIS News</title>
<style>body{font:15px/1.5 system-ui,-apple-system,sans-serif;max-width:980px;margin:0 auto;padding:20px;color:CanvasText;background:Canvas}a{color:inherit}header{display:flex;justify-content:space-between;align-items:center;gap:12px;flex-wrap:wrap}h1{font-size:1.6rem}h2{font-size:1.1rem;margin:0}.muted{opacity:.7}.panel{padding:20px;border:1px solid #aaa6;border-radius:14px;margin:15px 0}.entry{border-top:1px solid #aaa6;padding:15px 0}.entry:first-child{border-top:0}input,button{font:inherit;padding:9px;border:1px solid #999;border-radius:8px}button{cursor:pointer}.num{font-weight:700;font-variant-numeric:tabular-nums}p{overflow-wrap:anywhere}.meta{font-size:.88rem;opacity:.78}.notice{border-left:3px solid #298e64;padding:10px;background:#298e6414}.filter{width:100%;box-sizing:border-box}.links{display:flex;gap:12px;flex-wrap:wrap}@media(min-width:740px){.entry{display:grid;grid-template-columns:195px 1fr;gap:18px}}</style></head><body>
<header><a href="admin-netzwerk.php">← Mein Netzwerk</a><span class="muted">Privat · Nur mit Adminanmeldung</span></header>
<h1>Meine LinkedIn-Kommentare</h1><p><?=count($captureRows)?> eigene Kommentare aus der Screenshot-Erfassung · <?=h($stats['c'])?> Einträge im bisherigen CSV-Bestand. Privat gespeichert.</p><p><a href="admin-erfassung.php">Profil- und Gesprächsnetz öffnen →</a></p>
<section class="panel"><h2>Meine Kommentare aus Screenshots</h2><p class="muted">Alle gespeicherten Absätze werden angezeigt. „Sichtbarer Text“ beschreibt den im PDF erfassten Inhalt; nicht sichtbare Fortsetzungen werden nicht ergänzt.</p><input type="search" id="captureSearch" class="filter" placeholder="Beitragsautor, Thema oder Kommentar suchen" aria-label="Screenshot-Kommentare durchsuchen">
<?php if(!$captureRows):?><p>Noch keine eigenen Screenshot-Kommentare übernommen. Bitte den privaten Datenimport auf dem Server ausführen.</p><?php endif;?>
<?php foreach($captureRows as $r):?><article class="entry capture-entry" id="<?=h($r['source_key'])?>" data-search="<?=h(mb_strtolower($r['author'].' '.$r['title'].' '.$r['text'].' '.$r['tags'].' '.$r['profile_place'].' '.$r['project_place'],'UTF-8'))?>"><div><strong><?=h($r['author'])?></strong><div class="meta"><?=h($r['profile_place'])?></div><p class="meta"><?=h($r['tags'])?></p><a href="admin-erfassung.php#post-<?=h($r['post_id'])?>">Gespräch im Netzwerk →</a></div><div><h3><?=h($r['title'])?></h3><details><summary>Beitragskontext anzeigen</summary><p><?=nl2br(h($r['excerpt']))?></p><?php if($r['project_place']):?><p>Projektort: <?=h($r['project_place'])?></p><?php endif;?></details><p><strong>Mein Kommentar</strong> <small class="muted"><?=h($r['text_status']==='complete'?'Vollständig sichtbar':'Sichtbarer Text')?></small></p><p><?=nl2br(h($r['text']))?></p><details><summary>Quellenbeleg</summary><p class="meta"><?=h($r['evidence'])?></p></details><div class="links"><?php if($r['profile_url']):?><a href="<?=h($r['profile_url'])?>" rel="noopener noreferrer" target="_blank">Profil des Beitragsautors ↗</a><?php endif;?><?php if($r['post_url']):?><a href="<?=h($r['post_url'])?>" rel="noopener noreferrer" target="_blank">Originalbeitrag ↗</a><?php endif;?><?php if($r['comment_url']):?><a href="<?=h($r['comment_url'])?>" rel="noopener noreferrer" target="_blank">Originalkommentar ↗</a><?php endif;?></div></div></article><?php endforeach;?></section>
<details class="panel"><summary>Bisheriger CSV-Import und frühere Gespräche</summary><div class="panel"><h2>Kommentar-CSV importieren</h2><p class="muted">Unterstützt die IFIS-Kommentar-CSV mit Orten und Views. Bestehende Kontakte werden anhand des Profillinks oder von Name und Ort verbunden. Wiederholte Importe überspringen Dubletten. Die Datei wird nicht dauerhaft als Upload gespeichert.</p>
<?php if($notice):?><p class="notice" role="status"><?=h($notice)?></p><?php endif;?><?php if($error):?><p role="alert"><?=h($error)?></p><?php endif;?>
<form method="post" enctype="multipart/form-data"><input type="hidden" name="csrf" value="<?=h($_SESSION['ifis_admin_token'])?>"><input type="hidden" name="action" value="import"><input type="file" name="file" accept=".csv,text/csv" required><button>Privat importieren</button></form></div>
<div class="panel"><h2>Importierte Gespräche</h2><p class="muted">Sortiert nach sichtbaren Kommentar-Aufrufen. Leere Post-Views bedeuten unbekannt, nicht null. Aus Screenshots abgeschnittene Texte bleiben Ausschnitte.</p><input type="search" id="search" class="filter" placeholder="Autoren, Themen oder Kommentare suchen">
<div id="entries"><?php foreach($rows as $r):?><article class="entry" data-search="<?=h(mb_strtolower($r['author'].' '.$r['post_excerpt'].' '.$r['my_comment'].' '.$r['city'].' '.$r['country'],'UTF-8'))?>"><div><strong><?=h($r['author'])?></strong><div class="meta"><?=h($r['city'])?><?=($r['city']&&$r['country'])?', ':''?><?=h($r['country'])?></div><div class="meta"><?=h($r['post_date'])?> · PDF S. <?=h($r['pdf_page'])?></div><div>Kommentar-Views: <span class="num"><?=h($r['comment_views']??'–')?></span></div><div class="meta">Post-Views: <?=h($r['post_views']??'–')?></div></div><div><strong>Beitrag (sichtbarer Ausschnitt)</strong><p><?=nl2br(h($r['post_excerpt']))?></p><strong>Mein Kommentar (sichtbarer Ausschnitt)</strong><p><?=nl2br(h($r['my_comment']))?></p><div class="links"><?php if($r['profile_url']):?><a href="<?=h($r['profile_url'])?>" rel="noopener noreferrer" target="_blank">LinkedIn-Profil ↗</a><?php endif;?><?php if($r['post_url']):?><a href="<?=h($r['post_url'])?>" rel="noopener noreferrer" target="_blank">Originalbeitrag ↗</a><?php endif;?></div></div></article><?php endforeach;?></div></div>
</details><script>document.getElementById('search').addEventListener('input',e=>{const q=e.target.value.toLocaleLowerCase('de');document.querySelectorAll('#entries .entry').forEach(el=>el.hidden=!el.dataset.search.includes(q));});document.getElementById('captureSearch').addEventListener('input',e=>{const q=e.target.value.toLocaleLowerCase('de');document.querySelectorAll('.capture-entry').forEach(el=>el.hidden=!el.dataset.search.includes(q));});</script></body></html>