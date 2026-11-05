<?php
declare(strict_types=1);
/**
 * Personal contributor network (separate from the legacy admin tool).
 * Requires the editorial-users migration and network-owners migration.
 * Fail closed while those are absent. Not yet deployed on production.
 */
$root = dirname(__DIR__);
require_once $root . '/lib/EditorialAuth.php';
require_once $root . '/lib/PersonalNetwork.php';
require_once $root . '/lib/I18n.php';

header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: no-store, private');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');
header('Referrer-Policy: no-referrer');
header('Content-Security-Policy: default-src \'self\'; form-action \'self\'; base-uri \'none\'; frame-ancestors \'none\'; style-src \'self\' \'unsafe-inline\'');
ini_set('session.use_strict_mode', '1');
ini_set('session.use_only_cookies', '1');
session_name('ifis_editorial');
session_set_cookie_params(['lifetime'=>0,'path'=>'/','secure'=>!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off','httponly'=>true,'samesite'=>'Lax']);
session_start();
function e(?string $v): string { return htmlspecialchars($v ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function csrfValid(): bool { return isset($_SESSION['csrf']) && is_string($_POST['csrf'] ?? null) && hash_equals($_SESSION['csrf'], $_POST['csrf']); }
if (!isset($_SESSION['csrf'])) $_SESSION['csrf'] = bin2hex(random_bytes(32));
$language = I18n::normalize((string)($_SESSION['language'] ?? 'de'));
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='language' && csrfValid()) {
    $language = I18n::normalize((string)($_POST['language'] ?? 'de'));
    $_SESSION['language'] = $language;
    header('Location: mein-netzwerk.php');exit;
}
$i18n = new I18n($language);
$t = static fn(string $key): string => e($i18n->text($key));
$labels = $language === 'en'
 ? ['email'=>'Email','password'=>'Password','sign_in'=>'Sign in','sign_out'=>'Sign out','title'=>'Personal network','save'=>'Save contact','name'=>'Name or organization','url'=>'LinkedIn URL','place'=>'City / country','topic'=>'Topics','relation'=>'Connection','note'=>'Private note','add'=>'Add contact','edit'=>'Edit','search'=>'Find contacts','no_contacts'=>'No contacts yet.','login_error'=>'Sign-in failed.','blocked'=>'Too many attempts. Please try again later.','not_ready'=>'Personal networks have not been activated yet.','saved'=>'Contact saved.','invalid'=>'Please check the entered data.','security'=>'Session expired. Please reload.','back'=>'Back to IFIS News','cancel'=>'Cancel','contact'=>'Connection','commented'=>'I commented','interesting'=>'Interesting profile','lang'=>'Language']
 : ['email'=>'E-Mail','password'=>'Passwort','sign_in'=>'Anmelden','sign_out'=>'Abmelden','title'=>'Persönliches Netzwerk','save'=>'Kontakt speichern','name'=>'Name oder Organisation','url'=>'LinkedIn-Link','place'=>'Stadt / Land','topic'=>'Themen','relation'=>'Verbindung','note'=>'Private Notiz','add'=>'Kontakt hinzufügen','edit'=>'Bearbeiten','search'=>'Kontakte suchen','no_contacts'=>'Noch keine Kontakte vorhanden.','login_error'=>'Anmeldung fehlgeschlagen.','blocked'=>'Zu viele Versuche. Bitte später erneut versuchen.','not_ready'=>'Persönliche Netzwerke sind noch nicht aktiviert.','saved'=>'Kontakt gespeichert.','invalid'=>'Bitte Eingaben prüfen.','security'=>'Sitzung abgelaufen. Bitte neu laden.','back'=>'Zurück zu IFIS News','cancel'=>'Abbrechen','contact'=>'Kontakt','commented'=>'Ich habe kommentiert','interesting'=>'Interessantes Profil','lang'=>'Sprache'];
$error = ''; $notice = '';
$auth = null; $network = null; $user = null; $contacts = [];
try {
    $newsPath = $root . '/storage/news.sqlite';
    $networkPath = $root . '/storage/network.sqlite';
    $auth = EditorialAuth::connect($newsPath);
    if (!is_file($networkPath)) throw new RuntimeException('network storage unavailable');
    $netDb = new PDO('sqlite:' . $networkPath,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $columns = array_column($netDb->query('PRAGMA table_info(contacts)')->fetchAll(PDO::FETCH_ASSOC), 'name');
    if (!in_array('owner_user_id', $columns, true)) throw new RuntimeException('owner migration not applied');
    $usersDb = new PDO('sqlite:' . $newsPath,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $network = new PersonalNetwork($netDb, $usersDb);
} catch (Throwable $ex) {
    error_log('IFIS personal network unavailable: ' . $ex->getMessage());
    http_response_code(503);
    echo '<!doctype html><html lang="'.e($language).'"><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>IFIS News</title><body style="font:16px system-ui;max-width:650px;margin:10vh auto;padding:20px"><h1>IFIS News</h1><p>'.e($labels['not_ready']).'</p><p><a href="/">'.e($labels['back']).'</a></p></body></html>';
    exit;
}
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='logout') {
    if (!csrfValid()) { $error=$labels['security']; }
    else {
        $_SESSION = [];
        session_regenerate_id(true);
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
        header('Location: mein-netzwerk.php');exit;
    }
}
if (isset($_SESSION['editorial_user_id'])) {
    try {
        $user = $auth->requireRole((int)$_SESSION['editorial_user_id'],['admin','editor','contributor','reader']);
    } catch (Throwable $ex) {
        unset($_SESSION['editorial_user_id']);
    }
}
if ($_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='login' && !$user) {
    if (!csrfValid()) $error=$labels['security'];
    elseif ((int)($_SESSION['login_tries'] ?? 0) >= 5 && time() - (int)($_SESSION['login_last'] ?? 0) < 900) $error=$labels['blocked'];
    else {
        try {
            $candidate = $auth->authenticate((string)($_POST['email'] ?? ''),(string)($_POST['password'] ?? ''));
            if ($candidate) {
                session_regenerate_id(true);
                $_SESSION['editorial_user_id']=(int)$candidate['id'];
                $_SESSION['login_tries']=0;
                $_SESSION['csrf']=bin2hex(random_bytes(32));
                header('Location: mein-netzwerk.php');exit;
            }
        } catch (Throwable $ex) { error_log('Editorial login error: '.$ex->getMessage()); }
        $_SESSION['login_tries']=(int)($_SESSION['login_tries'] ?? 0)+1;
        $_SESSION['login_last']=time();
        $error=$labels['login_error'];
    }
}
if ($user && $_SERVER['REQUEST_METHOD']==='POST' && ($_POST['action'] ?? '')==='save' && $user['role'] !== 'reader') {
    if (!csrfValid()) $error=$labels['security'];
    else {
        try {
            $idRaw=(string)($_POST['id']??'');
            $id=$idRaw!=='' ? filter_var($idRaw,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]) : null;
            if ($idRaw!=='' && $id===false) throw new InvalidArgumentException('Invalid contact id');
            $payload=[];
            foreach (['name','linkedin_url','place','topic','relation','note'] as $key) $payload[$key]=trim((string)($_POST[$key]??''));
            // Coordinates deliberately omitted: never trust client supplied coordinates.
            $network->save((int)$user['id'],$payload,$id ?: null);
            $notice=$labels['saved'];
        } catch (Throwable $ex) {
            error_log('Personal network save failed: '.$ex->getMessage());
            $error=$labels['invalid'];
        }
    }
}
if ($user) $contacts=$network->contacts((int)$user['id'],1000);
$editing = null;
if ($user && isset($_GET['edit']) && ctype_digit((string)$_GET['edit'])) {
    $editing=$network->contact((int)$user['id'],(int)$_GET['edit']);
}
?><!doctype html>
<html lang="<?=e($language)?>">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title><?=e($labels['title'])?> · IFIS News</title>
<style>
:root{font:16px system-ui,-apple-system,sans-serif;color-scheme:light dark}*{box-sizing:border-box}body{margin:0;background:Canvas;color:CanvasText}a{color:inherit}header{border-bottom:1px solid #aaa6;padding:14px clamp(16px,4vw,50px);display:flex;align-items:center;justify-content:space-between;gap:12px;flex-wrap:wrap}header form{display:inline-flex;gap:8px;align-items:center}main{max-width:1150px;margin:25px auto;padding:0 16px}h1{font-size:1.5rem}h2{font-size:1.16rem}.columns{display:grid;grid-template-columns:minmax(260px,400px) minmax(300px,1fr);gap:18px}.panel{border:1px solid #aaa6;border-radius:12px;padding:18px}label{display:grid;gap:5px;margin:12px 0;font-size:.9rem}input,select,textarea,button{font:inherit;padding:10px;border-radius:8px;border:1px solid #aaa9;background:Canvas;color:CanvasText;max-width:100%}input,textarea{width:100%}textarea{min-height:80px}button{cursor:pointer}.primary{background:#277b63;color:white;border-color:#277b63}.status{padding:10px;border:1px solid #aaa6;border-radius:8px;margin-bottom:16px}.contact{border-top:1px solid #aaa6;padding:13px 0}.contact strong{display:block}.muted{opacity:.72;font-size:.87rem}#search{margin:8px 0 16px}@media(max-width:750px){.columns{grid-template-columns:1fr}}
</style></head>
<body>
<header><a href="/"><?=e($labels['back'])?></a><div>
<form method="post"><input type="hidden" name="csrf" value="<?=e($_SESSION['csrf'])?>"><input type="hidden" name="action" value="language"><label style="margin:0"><span><?=e($labels['lang'])?></span><select name="language" id="personal-language"><option value="de" <?=$language==='de'?'selected':''?>>Deutsch</option><option value="en" <?=$language==='en'?'selected':''?>>English</option></select></label></form>
<?php if ($user): ?><form method="post"><input type="hidden" name="csrf" value="<?=e($_SESSION['csrf'])?>"><input type="hidden" name="action" value="logout"><button><?=e($labels['sign_out'])?></button></form><?php endif;?>
</div></header>
<main><h1><?=e($labels['title'])?></h1>
<?php if ($error): ?><p class="status" role="alert"><?=e($error)?></p><?php endif;?>
<?php if ($notice): ?><p class="status" role="status"><?=e($notice)?></p><?php endif;?>
<?php if (!$user): ?>
<section class="panel" style="max-width:440px"><h2><?=e($labels['sign_in'])?></h2><form method="post">
<input type="hidden" name="action" value="login"><input type="hidden" name="csrf" value="<?=e($_SESSION['csrf'])?>">
<label><?=e($labels['email'])?><input type="email" name="email" autocomplete="username" required></label>
<label><?=e($labels['password'])?><input type="password" name="password" autocomplete="current-password" required></label>
<button class="primary"><?=e($labels['sign_in'])?></button></form></section>
<?php else: ?>
<p class="muted"><?=e($user['display_name'])?> · <?=e($i18n->text('network.not_public'))?></p>
<div class="columns"><section class="panel"><h2><?=e($editing ? $labels['edit'] : $labels['add'])?></h2>
<?php if ($user['role'] !== 'reader'): ?><form method="post"><input type="hidden" name="action" value="save"><input type="hidden" name="csrf" value="<?=e($_SESSION['csrf'])?>">
<input type="hidden" name="id" value="<?=e($editing ? (string)$editing['id'] : '')?>">
<label><?=e($labels['name'])?><input name="name" maxlength="140" value="<?=e($editing['name']??'')?>" required></label>
<label><?=e($labels['url'])?><input type="url" name="linkedin_url" maxlength="500" value="<?=e($editing['linkedin_url']??'')?>"></label>
<label><?=e($labels['place'])?><input name="place" maxlength="160" value="<?=e($editing['place']??'')?>"></label>
<label><?=e($labels['topic'])?><input name="topic" maxlength="220" value="<?=e($editing['topic']??'')?>"></label>
<label><?=e($labels['relation'])?><select name="relation">
<?php foreach (['contact','commented','interesting'] as $key): ?><option value="<?=e($key)?>" <?=($editing['relation']??'interesting')===$key?'selected':''?>><?=e($labels[$key])?></option><?php endforeach;?>
</select></label>
<label><?=e($labels['note'])?><textarea name="note" maxlength="1200"><?=e($editing['note']??'')?></textarea></label>
<button class="primary"><?=e($labels['save'])?></button> <a href="mein-netzwerk.php"><?=e($labels['cancel'])?></a>
</form><?php endif; ?></section>
<section class="panel"><h2><?=e($i18n->text('network.contacts'))?> (<?=count($contacts)?>)</h2>
<label><?=e($labels['search'])?><input id="search" type="search" autocomplete="off"></label>
<div id="contacts"><?php foreach ($contacts as $c): ?>
<article class="contact" data-filter="<?=e(mb_strtolower($c['name'].' '.$c['place'].' '.$c['topic'],'UTF-8'))?>">
<strong><?=e($c['name'])?></strong><span class="muted"><?=e($c['place'])?> · <?=e($c['topic'])?></span>
<?php if ($c['linkedin_url']): ?><p><a href="<?=e($c['linkedin_url'])?>" target="_blank" rel="noopener noreferrer">LinkedIn</a></p><?php endif; ?>
<?php if ($user['role'] !== 'reader'): ?><p><a href="?edit=<?=(int)$c['id']?>"><?=e($labels['edit'])?></a></p><?php endif; ?>
</article><?php endforeach; ?></div>
<?php if (!$contacts): ?><p class="muted"><?=e($labels['no_contacts'])?></p><?php endif;?>
</section></div>
<?php endif;?></main>
<script src="/personal-network.js" defer></script>
</body></html>
