<?php
declare(strict_types=1);
session_start();
header('Content-Type: text/html; charset=utf-8');
header('Cache-Control: private, no-store');
header('X-Frame-Options: DENY');
header('X-Content-Type-Options: nosniff');
header('Referrer-Policy: no-referrer');
function h($v):string{return htmlspecialchars((string)$v,ENT_QUOTES|ENT_SUBSTITUTE,'UTF-8');}
$root=dirname(__DIR__);$hashFile=$root.'/storage/admin-password.hash';
if(!is_file($hashFile)||trim((string)file_get_contents($hashFile))===''||empty($_SESSION['ifis_admin_ok'])){
 http_response_code(403);echo '<!doctype html><html lang="de"><meta charset="utf-8"><p>Bitte zuerst in <a href="admin-netzwerk.php">Mein Netzwerk</a> anmelden.</p></html>';exit;
}
if(empty($_SESSION['ifis_admin_token']))$_SESSION['ifis_admin_token']=bin2hex(random_bytes(24));
require $root.'/lib/screenshot-network.php';
$db=new PDO('sqlite:'.$root.'/storage/network.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_TIMEOUT=>10]);
captureSchema($db);
$json='';$error='';$notice='';$preview=null;
if($_SERVER['REQUEST_METHOD']==='POST'){
 try{
  if(!hash_equals((string)$_SESSION['ifis_admin_token'],(string)($_POST['csrf']??'')))throw new RuntimeException('Sitzung abgelaufen. Bitte neu laden.');
  $json=(string)($_POST['payload']??'');
  $b=captureBatch($json);$action=(string)($_POST['action']??'preview');
  if(!in_array($action,['preview','save'],true))throw new RuntimeException('Ungültige Aktion.');
  $digest=hash('sha256',$json);
  if($action==='save'&&!hash_equals((string)($_SESSION['capture_reviewed']??''),$digest))throw new RuntimeException('Bitte dieses Paket zuerst in der Vorschau prüfen.');
  $result=captureImport($db,$b,$action==='save');
  if($action==='save'){
   unset($_SESSION['capture_reviewed']);
   $notice='Privat gespeichert: '.$result['profiles'].' neue Profile, '.$result['posts'].' Beiträge, '.$result['comments'].' Kommentare. '.$result['existing'].' bestehende Einträge erkannt.';
  }else{$preview=$b;$_SESSION['capture_reviewed']=$digest;}
 }catch(Throwable $e){$error=$e instanceof PDOException?'Datenbankfehler. Das Paket wurde nicht gespeichert.':$e->getMessage();}
}
$posts=$db->query('SELECT p.*,c.name AS author_name FROM capture_posts p LEFT JOIN contacts c ON c.id=p.author_id ORDER BY p.id DESC LIMIT 100')->fetchAll(PDO::FETCH_ASSOC);
$comments=$db->query('SELECT k.*,c.name AS author_name,c.linkedin_url FROM capture_comments k JOIN contacts c ON c.id=k.author_id ORDER BY k.id')->fetchAll(PDO::FETCH_ASSOC);
$profiles=$db->query('SELECT p.*,c.name,c.linkedin_url,c.place,c.topic FROM capture_profiles p JOIN contacts c ON c.id=p.contact_id ORDER BY c.name')->fetchAll(PDO::FETCH_ASSOC);
$example=['schema_version'=>1,'profiles'=>[['key'=>'profil-eindeutiger-schluessel','name'=>'Sichtbarer Name','linkedin_url'=>'','homepage_url'=>'','headline'=>'','city'=>'','country'=>'','tags'=>'','evidence'=>'Screenshot 1 · Profilkopf']],'posts'=>[['key'=>'beitrag-eindeutiger-schluessel','author_key'=>'profil-eindeutiger-schluessel','url'=>'','title'=>'Kurzer eigener Titel','excerpt'=>'Nur sichtbarer Beitragsausschnitt','place'=>'','tags'=>'','evidence'=>'Screenshot 2 · Beitrag']],'comments'=>[['key'=>'kommentar-eindeutiger-schluessel','post_key'=>'beitrag-eindeutiger-schluessel','author_key'=>'profil-eindeutiger-schluessel','text'=>'Sichtbarer Kommentar','text_status'=>'excerpt','url'=>'','evidence'=>'Screenshot 2 · Kommentar']]];
?><!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>Screenshot-Erfassung · Mein Netzwerk</title>
<style>:root{font:16px/1.5 system-ui;color-scheme:light dark}body{max-width:1100px;margin:auto;padding:20px;background:Canvas;color:CanvasText}a{color:inherit}h1{font-size:1.7rem}h2{font-size:1.2rem}.panel{border:1px solid #8886;border-radius:14px;padding:18px;margin:18px 0}textarea{width:100%;box-sizing:border-box;min-height:180px;font:13px/1.5 monospace;padding:12px;background:Canvas;color:CanvasText;border:1px solid #888;border-radius:9px}button,input{font:inherit;padding:10px;border:1px solid #888;border-radius:8px}button{cursor:pointer;background:#21744f;color:white}.muted{opacity:.7;font-size:.9rem}.error{color:#bd4d4d}.ok{border-left:3px solid #23835b;padding:12px}.entry{border-top:1px solid #8885;padding:14px 0;overflow-wrap:anywhere}.comment{border-left:3px solid #448c73;margin:12px 0;padding:0 14px;white-space:pre-wrap}.excerpt{white-space:pre-wrap}.links{display:flex;gap:16px;flex-wrap:wrap}pre{white-space:pre-wrap;overflow-wrap:anywhere;font-size:12px}#graph{width:100%;height:auto;min-height:280px;border-radius:10px;background:#819c8e0c}#graph text{fill:CanvasText;font:13px system-ui}#graph line{stroke:#5a927d;stroke-width:1.5;opacity:.6}#graph .person{fill:#318765}#graph .post{fill:#507cad}#graph g{cursor:pointer}#graph g:focus{outline:2px solid #318765}input[type=search]{width:100%;box-sizing:border-box}.preview-row{padding:10px 0;border-top:1px solid #8885}small{overflow-wrap:anywhere}@media(max-width:600px){body{padding:12px}.panel{padding:12px}}</style></head><body>
<nav class="links"><a href="admin-netzwerk.php">← Mein Netzwerk</a><a href="admin-kommentare.php">Bisheriger Kommentarimport</a></nav>
<h1>Aus Screenshots wird dein Gesprächsnetz</h1><p>Profile, Beiträge und Kommentare gemeinsam festhalten. Alle Daten bleiben im geschützten Netzwerkbereich.</p>
<section class="panel"><h2>Erfassung aus dem Chat</h2><p>Gib die Screenshots im Chat mit dem Hinweis „Für mein Netzwerk erfassen“ weiter. Ergänze nach Möglichkeit den Profil- oder Beitragslink. Das daraus erstellte Importpaket kannst du hier einfügen und prüfen.</p><p class="muted">Es werden nur lesbare Angaben übernommen. Profilstandort und Projektort bleiben getrennt. Gleiche Namen werden nicht automatisch zusammengeführt. Eine Verbindung bedeutet „hat an diesem Beitrag mitgewirkt“, keine bestätigte persönliche Beziehung.</p>
<?php if($error):?><p class="error" role="alert"><?=h($error)?></p><?php endif;?><?php if($notice):?><p class="ok" role="status"><?=h($notice)?></p><?php endif;?>
<form method="post"><input type="hidden" name="csrf" value="<?=h($_SESSION['ifis_admin_token'])?>"><label for="payload">Importpaket aus dem Chat (JSON)</label><textarea name="payload" id="payload" maxlength="1048576" required spellcheck="false"><?=h($json)?></textarea><button name="action" value="preview">Vorschau prüfen</button>
<?php if($preview!==null):?><div class="panel"><h2>Vorschau · noch nicht gespeichert</h2><p><?=h($result['profiles'])?> neue Profile · <?=h($result['posts'])?> neue Beiträge · <?=h($result['comments'])?> neue Kommentare · <?=h($result['existing'])?> bestehende Einträge</p>
<?php foreach($preview['profiles'] as $r):?><div class="preview-row"><strong>Profil: <?=h($r['name'])?></strong><p><?=h($r['headline']??'')?> · <?=h($r['city']??'')?> <?=h($r['country']??'')?></p><small>Schlüssel: <?=h($r['key'])?> · <?=h($r['linkedin_url']??'')?> · <?=h($r['homepage_url']??'')?> · Tags: <?=h($r['tags']??'')?><?php if(isset($r['contact_id'])):?> · Bestehender Kontakt #<?=h($r['contact_id'])?><?php endif;?></small><?php if(($r['about']??'')!==''):?><details><summary>Info / About</summary><div class="excerpt"><?=h($r['about'])?></div></details><?php endif;?><p class="muted">Beleg: <?=h($r['evidence'])?></p></div><?php endforeach;?>
<?php foreach($preview['posts'] as $r):?><div class="preview-row"><strong>Beitrag: <?=h($r['title'])?></strong><p class="excerpt"><?=h($r['excerpt']??'')?></p><small>Schlüssel: <?=h($r['key'])?> · Autor: <?=h($r['author_key']??'')?> · Ort: <?=h($r['place']??'')?> · Tags: <?=h($r['tags']??'')?> · <?=h($r['url']??'')?></small><?php if(($r['about']??'')!==''):?><details><summary>Info / About</summary><div class="excerpt"><?=h($r['about'])?></div></details><?php endif;?><p class="muted">Beleg: <?=h($r['evidence'])?></p></div><?php endforeach;?>
<?php foreach($preview['comments'] as $r):?><div class="preview-row"><strong>Kommentar von <?=h($r['author_key'])?> zu <?=h($r['post_key'])?></strong><p class="excerpt"><?=h($r['text'])?></p><small><?=h(($r['text_status']??'excerpt')==='complete'?'Vollständig':'Sichtbarer Ausschnitt')?> · <?=h($r['url']??'')?></small><?php if(($r['about']??'')!==''):?><details><summary>Info / About</summary><div class="excerpt"><?=h($r['about'])?></div></details><?php endif;?><p class="muted">Beleg: <?=h($r['evidence'])?></p></div><?php endforeach;?>
<p>Bei Änderungen am Paket bitte erneut die Vorschau öffnen.</p><button name="action" value="save">Paket privat speichern</button></div><?php endif;?></form>
<details><summary>Format und bestehende Zuordnungen</summary><p class="muted">Schlüssel bei Folgeimporten wiederverwenden. Ohne sichtbaren Link keine URL erfinden. Mit contact_id kann ein Profil ausdrücklich einem bestehenden Kontakt aus „Mein Netzwerk“ zugeordnet werden. Kommentare benötigen einen bekannten Autor und Beitrag.</p><pre><?=h(json_encode($example,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES))?></pre><p>Bestehende Profil-Schlüssel:</p><?php foreach($profiles as $p):?><p><code><?=h($p['source_key'])?></code> → <?=h($p['name'])?> (Kontakt #<?=h($p['contact_id'])?>)</p><?php endforeach;?></details></section>
<section class="panel"><h2>Dein Profil- und Gesprächsnetz</h2><p class="muted">Grüne Kreise: Profile · blaue Kästchen: Beiträge. Linien zeigen Autorenschaft oder einen erfassten Kommentar. Dargestellt werden bis zu 12 aktuelle Beiträge und 40 beteiligte Profile. Knoten öffnen die zugehörigen Einträge.</p><svg id="graph" viewBox="0 0 1000 650" role="group" aria-label="Profile und ihre Beiträge und Kommentare"></svg><p id="graphEmpty" hidden>Noch keine zugeordneten Beiträge. Nach dem ersten Import erscheint hier dein Netz.</p></section>
<section class="panel"><h2>Beiträge und Gespräche</h2><input type="search" id="search" aria-label="Gespräche durchsuchen" placeholder="Name, Thema, Ort oder Kommentar suchen …">
<?php foreach($posts as $p):$thread=array_values(array_filter($comments,static fn($c)=>(int)$c['post_id']===(int)$p['id']));$search=$p['title'].' '.$p['excerpt'].' '.$p['tags'].' '.$p['place'].' '.$p['author_name'];foreach($thread as $c)$search.=' '.$c['author_name'].' '.$c['text'];?><article class="entry" id="post-<?=h($p['id'])?>" data-search="<?=h(mb_strtolower($search,'UTF-8'))?>"><h3><?=h($p['title'])?></h3><p class="muted">Autor: <?=h($p['author_name']?:'Noch nicht zugeordnet')?> · <?=h($p['place'])?> · <?=h($p['tags'])?></p><p class="excerpt"><?=h($p['excerpt'])?></p><?php if($p['url']):?><a href="<?=h($p['url'])?>" target="_blank" rel="noopener noreferrer">Originalbeitrag ↗</a><?php endif;?><p class="muted">Beleg: <?=h($p['evidence'])?> · Schlüssel: <?=h($p['source_key'])?></p>
<?php foreach($thread as $c):?><div class="comment"><strong><?=h($c['author_name'])?></strong> <small><?=h($c['text_status']==='complete'?'Vollständig':'Sichtbarer Ausschnitt')?></small><p><?=h($c['text'])?></p><?php if($c['url']):?><a href="<?=h($c['url'])?>" target="_blank" rel="noopener noreferrer">Kommentar ↗</a><?php endif;?><small class="muted">Beleg: <?=h($c['evidence'])?></small></div><?php endforeach;?></article><?php endforeach;?></section>
<section class="panel"><h2>Erfasste Profile</h2><?php $seen=[];foreach($profiles as $p):if(isset($seen[$p['contact_id']]))continue;$seen[$p['contact_id']]=true;?><article class="entry" id="profile-<?=h($p['contact_id'])?>" data-search="<?=h(mb_strtolower($p['name'].' '.$p['headline'].' '.$p['place'].' '.$p['topic'].' '.($p['about']??''),'UTF-8'))?>"><h3><?=h($p['name'])?></h3><p><?=h($p['headline'])?></p><?php if(($p['about']??'')!==''):?><details><summary>Info / About</summary><div class="excerpt"><?=h($p['about'])?></div></details><?php endif;?><p class="muted"><?=h($p['place'])?> · <?=h($p['topic'])?></p><div class="links"><?php if($p['linkedin_url']):?><a href="<?=h($p['linkedin_url'])?>" target="_blank" rel="noopener noreferrer">LinkedIn-Profil ↗</a><?php endif;?><?php if($p['homepage_url']):?><a href="<?=h($p['homepage_url'])?>" target="_blank" rel="noopener noreferrer">Homepage ↗</a><?php endif;?></div><details data-profile-threads="<?=h($p['contact_id'])?>"><summary>Posts und meine Kommentare</summary><div class="profile-thread-body"></div></details><small class="muted">Beleg: <?=h($p['evidence'])?></small></article><?php endforeach;?></section>
<script src="profile-threads.js?v=2"></script><script>
const posts=<?=json_encode(array_slice($posts,0,12),JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE)?>;
const comments=<?=json_encode($comments,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE)?>;
const profiles=<?=json_encode($profiles,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_INVALID_UTF8_SUBSTITUTE)?>;
const svg=document.getElementById('graph'),ns='http://www.w3.org/2000/svg',nodes=new Map(),edges=new Map();
const add=(tag,attrs,parent=svg)=>{const el=document.createElementNS(ns,tag);for(const [k,v] of Object.entries(attrs))el.setAttribute(k,v);parent.append(el);return el;};
function edge(person,post){if(person)edges.set(person+'-'+post,[String(person),String(post)]);}
for(const p of posts){edge(p.author_id,p.id);for(const c of comments.filter(c=>Number(c.post_id)===Number(p.id)))edge(c.author_id,p.id);}
const people=[...new Set([...edges.values()].map(e=>e[0]))].slice(0,40);
const height=Math.max(500,people.length*46+60,posts.length*76+60);svg.setAttribute('viewBox','0 0 1000 '+height);
people.forEach((id,i)=>nodes.set('person-'+id,{x:240,y:40+i*(height-80)/Math.max(1,people.length-1)}));
posts.forEach((p,i)=>nodes.set('post-'+p.id,{x:680,y:40+i*(height-80)/Math.max(1,posts.length-1)}));
for(const [person,post] of edges.values()){const a=nodes.get('person-'+person),b=nodes.get('post-'+post);if(a&&b)add('line',{x1:a.x,y1:a.y,x2:b.x,y2:b.y});}
function node(kind,id,label){const pos=nodes.get(kind+'-'+id);if(!pos)return;const g=add('g',{tabindex:0,role:'link','aria-label':label});
 if(kind==='person')add('circle',{cx:pos.x,cy:pos.y,r:10,class:'person'},g);else add('rect',{x:pos.x-10,y:pos.y-10,width:20,height:20,rx:4,class:'post'},g);
 const text=add('text',{x:pos.x+(kind==='person'?-18:18),y:pos.y+5,'text-anchor':kind==='person'?'end':'start'},g);text.textContent=label.length>30?label.slice(0,28)+'…':label;
 const title=add('title',{},g);title.textContent=label;
 const open=()=>{const el=document.getElementById((kind==='person'?'profile-':'post-')+id);if(el){el.hidden=false;el.scrollIntoView({behavior:'smooth',block:'center'});}};
 g.addEventListener('click',open);g.addEventListener('keydown',e=>{if(e.key==='Enter'||e.key===' '){e.preventDefault();open();}});
}
people.forEach(id=>{const p=profiles.find(p=>String(p.contact_id)===id);if(p)node('person',id,p.name);});posts.forEach(p=>node('post',p.id,p.title));
document.getElementById('graphEmpty').hidden=posts.length>0;
document.getElementById('search').addEventListener('input',e=>{const q=e.target.value.toLocaleLowerCase('de');document.querySelectorAll('[data-search]').forEach(el=>el.hidden=!el.dataset.search.includes(q));});
</script></body></html>



