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
  if(($_SESSION['ifis_source_last']??0)>time()-60)throw new RuntimeException(tr('Bitte vor einer weiteren Einreichung kurz warten.','Please wait a moment before submitting again.'));
  $type=val('type',20);$url=val('url',500);$title=val('title',120);$place=val('place',160);$description=val('description',800);$feed=val('feed_url',500);
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
  if(!in_array('feed_url',$columns,true))$db->exec("ALTER TABLE submissions ADD COLUMN feed_url TEXT NOT NULL DEFAULT ''");
  $stmt=$db->prepare('INSERT INTO submissions(created_at,type,url,title,place,description,feed_url,status) VALUES(?,?,?,?,?,?,?,"pending")');
  $stmt->execute([gmdate('c'),$type,$url,$title,$place,$description,$feed]);
  $_SESSION['ifis_source_last']=time();$_SESSION['ifis_source_csrf']=bin2hex(random_bytes(24));$success=true;
 }catch(Throwable $e){$error=$e instanceof RuntimeException?$e->getMessage():tr('Speichern derzeit nicht möglich.','Unable to save your submission right now.');}
}
function out(string $s):string{return htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
?><!doctype html><html lang="<?=out($lang)?>"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?=out(tr("Quelle vorschlagen · IFIS News","Suggest a source · IFIS News"))?></title><style>
:root{color-scheme:light dark;font-family:system-ui,-apple-system,sans-serif}body{max-width:650px;margin:4rem auto;padding:0 18px;line-height:1.5}a{color:inherit}form{display:grid;gap:15px}label{display:grid;gap:6px}input,select,textarea,button{font:inherit;padding:10px;border-radius:8px;border:1px solid #999}textarea{min-height:110px}button{background:#237e5c;color:#fff;border:0;cursor:pointer}.note{font-size:.91rem;opacity:.8}.error{color:#ad4444}.ok{color:#238056}.trap{position:absolute;left:-9999px}</style></head><body>
<p><a href="/">← IFIS News</a></p><h1><?=out(tr("Konstruktive Quelle vorschlagen","Suggest a constructive source"))?></h1>
<p><?=out(tr('Melde konstruktive Aktivitäten über einen RSS-/Atom-Feed, eine Webseite, LinkedIn oder ein anderes Netzwerk. Jeder Vorschlag wird vor einer möglichen Veröffentlichung geprüft.','Suggest constructive activities through an RSS/Atom feed, website, LinkedIn or another network. All sources are reviewed before any publication.'))?></p>
<?php if($success):?><p class="ok" role="status"><?=out(tr("Vielen Dank! Deine Einreichung wurde gespeichert und wartet auf Prüfung. Sie ist noch nicht öffentlich sichtbar.","Thank you! Your submission has been saved for review and is not publicly visible yet."))?></p><?php else:?>
<?php if($error!==''):?><p class="error" role="alert"><?=out($error)?></p><?php endif;?>
<form method="post" action=""><input type="hidden" name="lang" value="<?=out($lang)?>"><input type="hidden" name="csrf" value="<?=out($_SESSION['ifis_source_csrf'])?>"><label class="trap" aria-hidden="true">Website <input name="website" tabindex="-1" autocomplete="off"></label>
<label><?=out(tr('Art der Quelle','Source type'))?><select name="type" id="sourceType" required>
<option value="feed"><?=out(tr('RSS-/Atom-Feed','RSS/Atom feed'))?></option>
<option value="website"><?=out(tr('Webseite / Projekt','Website / project'))?></option>
<option value="linkedin">LinkedIn</option>
<option value="network"><?=out(tr('Anderes Netzwerk / Plattform','Other network / platform'))?></option>
</select></label>
<label><?=out(tr('Öffentlicher Quellenlink (HTTPS)','Public source link (HTTPS)'))?><input type="url" name="url" maxlength="500" placeholder="https://..." required></label>
<label><?=out(tr("Eigener Titel oder Name des Projekts","Your title or project name"))?><input name="title" maxlength="120" required></label>
<label><?=out(tr("Ereignisort / Region (optional)","Event location / region (optional)"))?><input name="place" maxlength="160" placeholder="<?=out(tr("z. B. Lübeck","e.g. Lübeck"))?>"></label>
<label><?=out(tr("Eigene Beschreibung: Was ist konstruktiv daran?","Your description: What makes it constructive?"))?><textarea name="description" maxlength="800" required></textarea></label>
<label><?=out(tr('Zusätzlicher RSS-/Atom-Feed (optional)','Additional RSS/Atom feed (optional)'))?><input type="url" name="feed_url" maxlength="500" placeholder="<?=out(tr("https://beispiel.de/feed.xml","https://example.org/feed.xml"))?>"><small><?=out(tr('Wird zunächst nur geprüft, nicht automatisch abgerufen.','Reviewed first; not fetched automatically.'))?></small></label>
<label><span><input type="checkbox" name="feed_permission" value="1"> <?=out(tr("Ich betreibe den Feed selbst oder habe die erforderliche Erlaubnis zur Einreichung und beantragten Nutzung.","I operate the feed or have permission for its submission and proposed use."))?></span></label>
<label><span><input type="checkbox" name="permission" value="1" required> <?=out(tr("Ich darf diese selbst formulierten Angaben übermitteln und bin mit ihrer Prüfung und Speicherung einverstanden.","I am authorized to submit this original information and agree to its review and storage."))?></span></label>
<button type="submit"><?=out(tr("Zur Prüfung einreichen","Submit for review"))?></button></form>
<?php endif;?>
<p class="note"><?=out(tr("Nur zur manuellen Prüfung gespeichert; keine automatische Veröffentlichung. Bitte keine privaten Informationen Dritter oder kopierten Beitragstexte übermitteln. Für Rücknahme oder Auskunft:","Stored for manual review only; no automatic publication. Do not submit third-party private data or copied post text. For withdrawal or inquiries:"))?> <a href="https://ifis.net/impressum"><?=out(tr("IFIS Kontakt/Impressum","IFIS contact/legal notice"))?></a>. <?=out(tr("Eine öffentliche Einreichung gilt nicht als Berechtigung zum Scraping oder zur Veröffentlichung fremder Inhalte.","A public submission does not authorize scraping or republication of third-party content."))?></p>
</body></html>