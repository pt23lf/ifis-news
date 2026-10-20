<?php
declare(strict_types=1);
session_start();
header('Content-Type: text/html; charset=utf-8');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: strict-origin-when-cross-origin');
header('Cache-Control: no-store');
if(empty($_SESSION['ifis_source_csrf']))$_SESSION['ifis_source_csrf']=bin2hex(random_bytes(24));
$success=false;$error='';
function val(string $key,int $max):string {return mb_substr(trim((string)($_POST[$key]??'')),0,$max,'UTF-8');}
function approvedUrl(string $value):bool {
 $p=parse_url($value);if(!is_array($p)||($p['scheme']??'')!=='https'||empty($p['host']))return false;
 $host=strtolower($p['host']);
 return $host==='linkedin.com'||str_ends_with($host,'.linkedin.com')||$host==='www.linkedin.com';
}
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  if(!hash_equals($_SESSION['ifis_source_csrf'],(string)($_POST['csrf']??'')))throw new RuntimeException('Sitzung abgelaufen. Bitte erneut laden.');
  if(!empty($_POST['website']))throw new RuntimeException('Einreichung nicht möglich.');
  if(($_SESSION['ifis_source_last']??0)>time()-60)throw new RuntimeException('Bitte vor einer weiteren Einreichung kurz warten.');
  $type=val('type',20);$url=val('url',500);$title=val('title',120);$place=val('place',160);$description=val('description',800);
  if(!in_array($type,['profile','post'],true)||!approvedUrl($url)||$title===''||$description==='')throw new RuntimeException('Bitte Titel, Beschreibung und eine gültige LinkedIn-URL angeben.');
  if(empty($_POST['permission']))throw new RuntimeException('Bitte bestätige, dass du diese Angaben übermitteln darfst.');
  $path=dirname(__DIR__).'/storage/source-submissions.sqlite';
  if(!extension_loaded('pdo_sqlite'))throw new RuntimeException('Einreichungen sind vorübergehend nicht möglich.');
  $db=new PDO('sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT=>5]);
  $db->exec('CREATE TABLE IF NOT EXISTS submissions (id INTEGER PRIMARY KEY AUTOINCREMENT, created_at TEXT NOT NULL, type TEXT NOT NULL, url TEXT NOT NULL, title TEXT NOT NULL, place TEXT NOT NULL, description TEXT NOT NULL, status TEXT NOT NULL DEFAULT "pending")');
  $stmt=$db->prepare('INSERT INTO submissions(created_at,type,url,title,place,description,status) VALUES(?,?,?,?,?,?,"pending")');
  $stmt->execute([gmdate('c'),$type,$url,$title,$place,$description]);
  $_SESSION['ifis_source_last']=time();$_SESSION['ifis_source_csrf']=bin2hex(random_bytes(24));$success=true;
 }catch(Throwable $e){$error=$e instanceof RuntimeException?$e->getMessage():'Speichern derzeit nicht möglich.';}
}
function out(string $s):string{return htmlspecialchars($s,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
?><!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Quelle vorschlagen · IFIS News</title><style>
:root{color-scheme:light dark;font-family:system-ui,-apple-system,sans-serif}body{max-width:650px;margin:4rem auto;padding:0 18px;line-height:1.5}a{color:inherit}form{display:grid;gap:15px}label{display:grid;gap:6px}input,select,textarea,button{font:inherit;padding:10px;border-radius:8px;border:1px solid #999}textarea{min-height:110px}button{background:#237e5c;color:#fff;border:0;cursor:pointer}.note{font-size:.91rem;opacity:.8}.error{color:#ad4444}.ok{color:#238056}.trap{position:absolute;left:-9999px}</style></head><body>
<p><a href="/">← IFIS News</a></p><h1>Konstruktive Quelle vorschlagen</h1>
<p>Du kannst ein öffentliches LinkedIn-Profil oder einen einzelnen Beitrag zur Prüfung einreichen. IFIS News übernimmt keine Beitragstexte oder Bilder und liest LinkedIn nicht automatisch aus.</p>
<?php if($success):?><p class="ok" role="status">Vielen Dank! Deine Einreichung wurde gespeichert und wartet auf Prüfung. Sie ist noch nicht öffentlich sichtbar.</p><?php else:?>
<?php if($error!==''):?><p class="error" role="alert"><?=out($error)?></p><?php endif;?>
<form method="post" action=""><input type="hidden" name="csrf" value="<?=out($_SESSION['ifis_source_csrf'])?>"><label class="trap" aria-hidden="true">Website <input name="website" tabindex="-1" autocomplete="off"></label>
<label>Art der Quelle<select name="type" required><option value="post">Einzelner LinkedIn-Beitrag</option><option value="profile">LinkedIn-Profil oder Unternehmensseite</option></select></label>
<label>Öffentliche LinkedIn-URL<input type="url" name="url" maxlength="500" placeholder="https://www.linkedin.com/..." required></label>
<label>Eigener Titel oder Name des Projekts<input name="title" maxlength="120" required></label>
<label>Ereignisort / Region (optional)<input name="place" maxlength="160" placeholder="z. B. Lübeck"></label>
<label>Eigene Beschreibung: Was ist konstruktiv daran?<textarea name="description" maxlength="800" required></textarea></label>
<label><span><input type="checkbox" name="permission" value="1" required> Ich darf diese selbst formulierten Angaben übermitteln und bin mit ihrer Prüfung und Speicherung einverstanden.</span></label>
<button type="submit">Zur Prüfung einreichen</button></form>
<?php endif;?>
<p class="note">Nur zur manuellen Prüfung gespeichert; keine automatische Veröffentlichung. Bitte keine privaten Informationen Dritter oder kopierten Beitragstexte übermitteln. Für Rücknahme oder Auskunft: <a href="https://ifis.net/impressum">IFIS Kontakt/Impressum</a>. Eine öffentliche Einreichung gilt nicht als Berechtigung zum Scraping oder zur Veröffentlichung fremder Inhalte.</p>
</body></html>