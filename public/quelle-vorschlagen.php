<?php
declare(strict_types=1);
session_start();
header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Cache-Control: no-store');
if(empty($_SESSION['ifis_source_csrf']))$_SESSION['ifis_source_csrf']=bin2hex(random_bytes(24));
$success=false;$error='';
$lang=(($_POST['lang']??$_GET['lang']??'de')==='en')?'en':'de';
function tr(string $de,string $en):string {global $lang;return $lang==='en'?$en:$de;}
function val(string $key,int $max):string {return mb_substr(trim((string)($_POST[$key]??'')),0,$max,'UTF-8');}
function feedUrlValid(string $value):bool {
 $p=parse_url($value);
 if(!is_array($p)||strtolower((string)($p['scheme']??''))!=='https'||empty($p['host'])||isset($p['user'])||isset($p['pass']))return false;
 $host=strtolower((string)$p['host']);
 if($host==='localhost'||str_ends_with($host,'.localhost')||filter_var($host,FILTER_VALIDATE_IP))return false;
 return (bool)preg_match('/^[a-z0-9.-]+\\.[a-z]{2,}$/i',$host);
}
function approvedUrl(string $value):bool {
 $p=parse_url($value);if(!is_array($p)||($p['scheme']??'')!=='https'||empty($p['host']))return false;
 $host=strtolower($p['host']);
 return $host==='linkedin.com'||str_ends_with($host,'.linkedin.com')||$host==='lnkd.in';
}
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  if(!hash_equals($_SESSION['ifis_source_csrf'],(string)($_POST['csrf']??'')))throw new RuntimeException(tr('Sitzung abgelaufen. Bitte erneut laden.','Session expired. Please reload and try again.'));
  if(!empty($_POST['website']))throw new RuntimeException(tr('Einreichung nicht möglich.','Submission could not be accepted.'));
  if(empty($_SESSION['ifis_admin_ok'])&&($_SESSION['ifis_source_last']??0)>time()-60)throw new RuntimeException(tr('Bitte vor einer weiteren Einreichung kurz warten.','Please wait a moment before submitting again.'));
  $kind=val('kind',20);if(!in_array($kind,['idea','offer','challenge'],true))$kind='idea';
  $topics=val('topics',250);$creator=val('creator',160);$privateNote=val('private_note',2000);
  $type=val('type',20);$url=val('url',500);$title=val('title',120);$place=implode(', ',array_filter([val('place',100),val('country',100)],static fn($value)=>$value!==''));$description=val('description',800);$feed=val('feed_url',500);
  if(!in_array($type,['linkedin','feed','website','network'],true)||!feedUrlValid($url)||$title===''||$description==='')throw new RuntimeException(tr('Bitte Art, Titel, Beschreibung und eine gültige öffentliche HTTPS-URL angeben.','Please provide a type, title, description and valid public HTTPS URL.'));
  if($type==='linkedin'&&!approvedUrl($url))throw new RuntimeException(tr('Bitte einen LinkedIn-Link oder lnkd.in-Kurzlink eingeben.','Please enter a LinkedIn link or lnkd.in short link.'));
  if($type==='feed'&&$feed==='')$feed=$url;
  if($type==='feed'&&empty($_POST['feed_permission']))throw new RuntimeException(tr('Bitte die Berechtigung für den Feed bestätigen.','Please confirm your authorization for this feed.'));

  if($feed!==''&&!feedUrlValid($feed))throw new RuntimeException(tr('Bitte eine gültige öffentliche HTTPS-Feedadresse eingeben.','Please enter a valid public HTTPS feed URL.'));
  if($feed!==''&&empty($_POST['feed_permission']))throw new RuntimeException(tr('Bitte bestätige, dass du diesen Feed selbst betreibst oder zur Einreichung berechtigt bist.','Please confirm that you operate this feed or are authorized to submit it.'));
  if(empty($_POST['permission']))throw new RuntimeException(tr('Bitte bestätige, dass du diese Angaben übermitteln darfst.','Please confirm that you are authorized to submit this information.'));
  $path=dirname(__DIR__).'/storage/source-submissions.sqlite';
  if(!extension_loaded('pdo_sqlite'))throw new RuntimeException(tr('Einreichungen sind vorübergehend nicht möglich.','Submissions are temporarily unavailable.'));
  $db=new PDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT=>5]);
  $db->exec('CREATE TABLE IF NOT EXISTS submissions (id INTEGER PRIMARY KEY AUTOINCREMENT, created_at TEXT NOT NULL, type TEXT NOT NULL, url TEXT NOT NULL, title TEXT NOT NULL, place TEXT NOT NULL, description TEXT NOT NULL, status TEXT NOT NULL DEFAULT "pending")');
  $columns=array_column($db->query('PRAGMA table_info(submissions)')->fetchAll(PDO::FETCH_ASSOC),'name');
  foreach(['kind'=>"TEXT NOT NULL DEFAULT 'idea'",'topics'=>"TEXT NOT NULL DEFAULT ''",'creator'=>"TEXT NOT NULL DEFAULT ''",'private_note'=>"TEXT NOT NULL DEFAULT ''"] as $column=>$definition){
   if(!in_array($column,$columns,true))$db->exec("ALTER TABLE submissions ADD COLUMN $column $definition");
  }
  if(!in_array('feed_url',$columns,true))$db->exec("ALTER TABLE submissions ADD COLUMN feed_url TEXT NOT NULL DEFAULT ''");
  $duplicate=$db->prepare("SELECT id FROM submissions WHERE url=? AND status IN ('pending','approved') LIMIT 1");
  $duplicate->execute([$url]);
  if($duplicate->fetchColumn())throw new RuntimeException(tr('Dieser Link ist bereits in der Sammlung oder wartet auf Prüfung.','This link is already saved or awaiting review.'));
  $stmt=$db->prepare('INSERT INTO submissions(created_at,type,url,title,place,description,feed_url,kind,topics,creator,private_note,status) VALUES(?,?,?,?,?,?,?,?,?,?,?,"pending")');
  $stmt->execute([gmdate('c'),$type,$url,$title,$place,$description,$feed,$kind,$topics,$creator,$privateNote]);
  $_SESSION['ifis_source_last']=time();$_SESSION['ifis_source_csrf']=bin2hex(random_bytes(24));$success=true;
 }catch(Throwable $e){$error=$e instanceof RuntimeException?$e->getMessage():tr('Speichern derzeit nicht möglich.','Unable to save your submission right now.');}
}
function out(string $s):string{return htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
?><!doctype html><html lang="<?=out($lang)?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?=out(tr("Quelle vorschlagen · IFIS News","Suggest a source · IFIS News"))?></title><style>
:root{color-scheme:light dark;font-family:system-ui,-apple-system,sans-serif}body{max-width:650px;margin:1.5rem auto;padding:0 18px;line-height:1.5}a{color:inherit}form,.fields{display:grid;gap:15px}.fields{padding-top:14px}summary{cursor:pointer;font-weight:600}small{opacity:.75}details{border-top:1px solid #9995;padding-top:14px}input,textarea,select{width:100%;box-sizing:border-box}input[type=checkbox]{width:auto}label{display:grid;gap:6px}input,select,textarea,button{font:inherit;padding:10px;border-radius:8px;border:1px solid #999}textarea{min-height:110px}button{background:#237e5c;color:#fff;border:0;cursor:pointer}.note{font-size:.91rem;opacity:.8}.error{color:#ad4444}.ok{color:#238056}.trap{position:absolute;left:-9999px}</style></head><body>
<p><a href="/">← IFIS News</a></p><h1><?=out(tr("Idee oder Projekt festhalten","Save an idea or project"))?></h1>
<p><?=out(tr('Bewahre den Gedanken hinter einem Beitrag: kurz in eigenen Worten, mit Link zum Original. Nur Link, Titel und Kerngedanke sind Pflicht.','Keep the idea behind a post, in your own words, with a link to the original. Only link, title and key idea are required.'))?></p>
<?php if($success):?><p><a href="?lang=<?=out($lang)?>"><?=out(tr("Weiteren Beitrag festhalten","Save another contribution"))?></a></p><p class="ok" role="status"><?=out(tr("Vielen Dank! Deine Einreichung wurde gespeichert und wartet auf Prüfung. Sie ist noch nicht öffentlich sichtbar.","Thank you! Your submission has been saved for review and is not publicly visible yet."))?></p><?php else:?>
<?php if($error!==''):?><p class="error" role="alert"><?=out($error)?></p><?php endif;?>
<form method="post" action=""><input type="hidden" name="lang" value="<?=out($lang)?>"><input type="hidden" name="csrf" value="<?=out($_SESSION['ifis_source_csrf'])?>"><label class="trap" aria-hidden="true">Website <input name="website" tabindex="-1" autocomplete="off"></label>
<label><?=out(tr('Link zum Original','Original link'))?><input type="url" name="url" maxlength="500" value="<?=out(val('url',500))?>" placeholder="https://…" required></label>
<label><?=out(tr('Eigener Titel – worum geht es?','Your title — what is it about?'))?><input name="title" maxlength="120" value="<?=out(val('title',120))?>" required></label>
<label><?=out(tr('Kerngedanke – was soll erhalten bleiben?','Key idea — what should be preserved?'))?><textarea name="description" maxlength="800" required placeholder="<?=out(tr('Idee, Vorgehen oder Wirkung in eigenen Worten zusammenfassen.','Summarize the idea, approach or effect in your own words.'))?>"><?=out(val('description',800))?></textarea><small><?=out(tr('Wird nach Freigabe als Beschreibung angezeigt.','Shown as the description after approval.'))?></small></label>
<details open><summary><?=out(tr('Einordnen und verbinden (optional)','Organize and connect (optional)'))?></summary><div class="fields">
<label><?=out(tr('Was wird beschrieben?','What does it describe?'))?><select name="kind"><?php foreach(['idea'=>tr('Idee / Projekt','Idea / project'),'offer'=>tr('Angebot / Lösungsansatz','Offer / solution'),'challenge'=>tr('Anfrage / Herausforderung','Request / challenge')] as $value=>$label):?><option value="<?=out($value)?>" <?=val('kind',20)===$value?'selected':''?>><?=out($label)?></option><?php endforeach;?></select></label>
<label><?=out(tr("Themen – durch Kommas getrennt","Topics — comma-separated"))?><input name="topics" maxlength="250" value="<?=out(val('topics',250))?>"></label>
<label><?=out(tr("Urheber / Organisation","Creator / organization"))?><input name="creator" maxlength="160" value="<?=out(val('creator',160))?>"></label>
<p class="note"><?=out(tr("Ort des Projekts oder der Herausforderung, falls bekannt. Keine Ortsangabe aus dem Profil ableiten.","Project or challenge location, if known. Do not infer it from the profile."))?></p><label><?=out(tr("Ort / Region","City / region"))?><input name="place" maxlength="100" value="<?=out(val('place',100))?>"></label>
<label><?=out(tr("Land","Country"))?><input name="country" maxlength="100" value="<?=out(val('country',100))?>"></label>
</div></details><label><?=out(tr("Meine Gedanken / mögliche Verbindung (nur im Adminbereich sichtbar)","My thoughts / possible connection (visible only to the admin)"))?><textarea name="private_note" maxlength="2000"><?=out(val('private_note',2000))?></textarea></label>
<details><summary><?=out(tr('Quelle und Feed (optional)','Source and feed (optional)'))?></summary><div class="fields">
<label><?=out(tr('Art der Quelle','Source type'))?><select name="type" id="sourceType"><?php foreach(['linkedin'=>'LinkedIn','website'=>tr('Webseite / Projekt','Website / project'),'network'=>tr('Anderes Netzwerk','Other network'),'feed'=>'RSS / Atom'] as $value=>$label):?><option value="<?=out($value)?>" <?=(val('type',20)?:'website')===$value?'selected':''?>><?=out($label)?></option><?php endforeach;?></select></label>
<label><?=out(tr("RSS-/Atom-Feed","RSS/Atom feed"))?><input name="feed_url" maxlength="500" value="<?=out(val('feed_url',500))?>"></label>

<label><span><input type="checkbox" name="feed_permission" value="1" <?=!empty($_POST['feed_permission'])?'checked':''?>> <?=out(tr('Ich betreibe den Feed oder habe die Erlaubnis zur Einreichung.','I operate the feed or am authorized to submit it.'))?></span></label>
<small><?=out(tr('Feeds werden nicht automatisch importiert.','Feeds are not imported automatically.'))?></small>
</div></details>
<label><span><input type="checkbox" name="permission" value="1" required> <?=out(tr("Ich darf diese selbst formulierten Angaben übermitteln und bin mit ihrer Prüfung und Speicherung einverstanden.","I am authorized to submit this original information and agree to its review and storage."))?></span></label>
<button type="submit"><?=out(tr("Zur Prüfung einreichen","Submit for review"))?></button></form>
<?php endif;?>
<p class="note"><?=out(tr("Nur zur manuellen Prüfung gespeichert; keine automatische Veröffentlichung. Bitte keine privaten Informationen Dritter oder kopierten Beitragstexte übermitteln. Für Rücknahme oder Auskunft:","Stored for manual review only; no automatic publication. Do not submit third-party private data or copied post text. For withdrawal or inquiries:"))?> <a href="https://ifis.net/impressum"><?=out(tr("IFIS Kontakt/Impressum","IFIS contact/legal notice"))?></a>. <?=out(tr("Eine öffentliche Einreichung gilt nicht als Berechtigung zum Scraping oder zur Veröffentlichung fremder Inhalte.","A public submission does not authorize scraping or republication of third-party content."))?></p>
<script>
const urlField=document.querySelector('input[name="url"]'),typeField=document.getElementById('sourceType');
if(urlField&&typeField){
 let manual=false;
 typeField.addEventListener('change',()=>{manual=true});
 urlField.addEventListener('input',()=>{
  if(manual)return;
  try{
   const host=new URL(urlField.value).hostname.toLowerCase();
   typeField.value=host==='linkedin.com'||host.endsWith('.linkedin.com')||host==='lnkd.in'?'linkedin':'website';
  }catch{}
 });
}
</script></body></html>