<?php
declare(strict_types=1);

function captureSchema(PDO $db):void {
 $db->exec("CREATE TABLE IF NOT EXISTS contacts(id INTEGER PRIMARY KEY AUTOINCREMENT,name TEXT NOT NULL,linkedin_url TEXT NOT NULL DEFAULT '',place TEXT NOT NULL DEFAULT '',lat REAL,lon REAL,topic TEXT NOT NULL DEFAULT '',relation TEXT NOT NULL DEFAULT 'commented',note TEXT NOT NULL DEFAULT '',created_at TEXT NOT NULL,updated_at TEXT NOT NULL)");
 $db->exec('CREATE UNIQUE INDEX IF NOT EXISTS contacts_linkedin_unique ON contacts(linkedin_url) WHERE linkedin_url <> ""');
 // Separate tables preserve the existing CSV imports and their original interpretation.
 $db->exec("CREATE TABLE IF NOT EXISTS capture_profiles(source_key TEXT PRIMARY KEY,contact_id INTEGER NOT NULL,homepage_url TEXT NOT NULL DEFAULT '',headline TEXT NOT NULL DEFAULT '',evidence TEXT NOT NULL DEFAULT '',FOREIGN KEY(contact_id) REFERENCES contacts(id))");
 $db->exec("CREATE TABLE IF NOT EXISTS capture_posts(id INTEGER PRIMARY KEY AUTOINCREMENT,source_key TEXT NOT NULL UNIQUE,url TEXT NOT NULL DEFAULT '',title TEXT NOT NULL,excerpt TEXT NOT NULL DEFAULT '',author_id INTEGER,place TEXT NOT NULL DEFAULT '',tags TEXT NOT NULL DEFAULT '',evidence TEXT NOT NULL DEFAULT '',FOREIGN KEY(author_id) REFERENCES contacts(id))");
 $db->exec('CREATE UNIQUE INDEX IF NOT EXISTS capture_post_url ON capture_posts(url) WHERE url <> ""');
 $db->exec("CREATE TABLE IF NOT EXISTS capture_comments(id INTEGER PRIMARY KEY AUTOINCREMENT,source_key TEXT NOT NULL UNIQUE,post_id INTEGER NOT NULL,author_id INTEGER NOT NULL,text TEXT NOT NULL,text_status TEXT NOT NULL DEFAULT 'excerpt',url TEXT NOT NULL DEFAULT '',evidence TEXT NOT NULL DEFAULT '',FOREIGN KEY(post_id) REFERENCES capture_posts(id),FOREIGN KEY(author_id) REFERENCES contacts(id))");
}
function captureText(array $r,string $key,int $max=500,bool $required=false):string {
 $v=$r[$key]??'';
 if(!is_string($v))throw new RuntimeException("$key muss Text sein.");
 $v=trim($v);
 if(($required&&$v==='')||mb_strlen($v,'UTF-8')>$max)throw new RuntimeException("$key fehlt oder ist zu lang (max. $max Zeichen).");
 return $v;
}
function captureUrl(string $v,bool $profile=false,bool $homepage=false):string {
 if($v==='')return '';
 $p=parse_url($v);
 if(!filter_var($v,FILTER_VALIDATE_URL)||!is_array($p)||strtolower((string)($p['scheme']??''))!=='https'||empty($p['host'])||isset($p['user'])||isset($p['pass']))throw new RuntimeException('Ungültiger HTTPS-Link.');
 $host=strtolower($p['host']);
 if($homepage){
  if($host==='localhost'||str_ends_with($host,'.localhost')||filter_var($host,FILTER_VALIDATE_IP)||!str_contains($host,'.'))throw new RuntimeException('Ungültige Homepage.');
  return $v;
 }
 if($host!=='lnkd.in'&&$host!=='linkedin.com'&&!str_ends_with($host,'.linkedin.com'))throw new RuntimeException('LinkedIn-Link erwartet.');
 $path=rtrim((string)($p['path']??''),'/');
 if($profile&&!preg_match('~^/(in|company)/[^/]+$~',$path))throw new RuntimeException('Profil benötigt einen eindeutigen /in/… oder /company/… Link; sonst leer lassen.');
 // Strip tracking; retain comment identity in LinkedIn query parameters.
 $query=[];parse_str((string)($p['query']??''),$query);
 $query=array_intersect_key($query,array_flip(['commentUrn','replyUrn']));
 return 'https://'.($host==='lnkd.in'?'lnkd.in':'www.linkedin.com').$path.(!$profile&&$query?'?'.http_build_query($query):'');
}
function captureBatch(string $json):array {
 if(strlen($json)>1048576)throw new RuntimeException('Maximal 1 MB pro Import.');
 $b=json_decode($json,true,32,JSON_THROW_ON_ERROR);
 if(!is_array($b)||($b['schema_version']??null)!==1)throw new RuntimeException('schema_version: 1 wird benötigt.');
 $total=0;
 foreach(['profiles','posts','comments'] as $type){
  $b[$type]=$b[$type]??[];
  if(!is_array($b[$type])||!array_is_list($b[$type]))throw new RuntimeException("$type muss eine Liste sein.");
  $keys=[];$total+=count($b[$type]);
  foreach($b[$type] as $r){
   if(!is_array($r))throw new RuntimeException('Eintrag muss ein Objekt sein.');
   $key=captureText($r,'key',180,true);
   if(isset($keys[$key]))throw new RuntimeException("Doppelter Schlüssel in $type: $key");
   $keys[$key]=true;
  }
 }
 if($total<1||$total>200)throw new RuntimeException('Bitte 1 bis 200 Einträge pro Paket importieren.');
 return $b;
}
function captureFind(PDO $db,string $sql,array $args) {
 $q=$db->prepare($sql);$q->execute($args);return $q->fetch(PDO::FETCH_ASSOC)?:null;
}
function captureProfileId(PDO $db,string $key):?int {
 if($key==='')return null;
 $r=captureFind($db,'SELECT c.id FROM capture_profiles p JOIN contacts c ON c.id=p.contact_id WHERE p.source_key=?',[$key]);
 if(!$r)throw new RuntimeException("Profilzuordnung fehlt: $key");
 return (int)$r['id'];
}
function captureImport(PDO $db,array $b,bool $save):array {
 $counts=['profiles'=>0,'posts'=>0,'comments'=>0,'existing'=>0];
 $db->beginTransaction();
 try {
  foreach($b['profiles'] as $r){
   $key=captureText($r,'key',180,true);$name=captureText($r,'name',140,true);
   $url=captureUrl(captureText($r,'linkedin_url',600),true);
   $homepage=captureUrl(captureText($r,'homepage_url',600),false,true);
   $headline=captureText($r,'headline',500);$evidence=captureText($r,'evidence',1000,true);
   $place=implode(', ',array_filter([captureText($r,'city',75),captureText($r,'country',75)],static fn($v)=>$v!==''));
   $tags=captureText($r,'tags',220);
   $linked=captureFind($db,'SELECT c.* FROM capture_profiles p JOIN contacts c ON c.id=p.contact_id WHERE p.source_key=?',[$key]);
   $byUrl=null;
   if($url!==''){
    // Existing imports may have stored a trailing slash, regional host or tracking.
    foreach($db->query('SELECT * FROM contacts WHERE linkedin_url<>""') as $candidate){
     try{$same=captureUrl((string)$candidate['linkedin_url'],true)===$url;}catch(Throwable $e){$same=false;}
     if($same){if($byUrl&&(int)$byUrl['id']!==(int)$candidate['id'])throw new RuntimeException('Mehrere bestehende Kontakte mit diesem Profillink; bitte zuerst bereinigen.');$byUrl=$candidate;}
    }
   }
   $explicit=null;
   if(isset($r['contact_id'])){
    if(!is_int($r['contact_id'])||$r['contact_id']<1)throw new RuntimeException('contact_id muss eine positive Zahl sein.');
    $explicit=captureFind($db,'SELECT * FROM contacts WHERE id=?',[$r['contact_id']]);
    if(!$explicit)throw new RuntimeException('Bestehender Kontakt fehlt.');
   }
   $matches=array_values(array_filter([$linked,$byUrl,$explicit]));
   if(count(array_unique(array_column($matches,'id')))>1)throw new RuntimeException("Widersprüchliche Profilzuordnung: $name");
   $old=$matches[0]??null;
   if($old&&$url!==''&&$old['linkedin_url']!==''&&captureUrl($old['linkedin_url'],true)!==$url)throw new RuntimeException("Abweichender Profillink: $name");
   if(!$old){
    $q=$db->prepare('INSERT INTO contacts(name,linkedin_url,place,topic,relation,note,created_at,updated_at) VALUES(?,?,?,?,?,?,?,?)');
    $q->execute([$name,$url,$place,$tags,'interesting','Screenshot: '.$evidence,gmdate('c'),gmdate('c')]);$id=(int)$db->lastInsertId();$counts['profiles']++;
   }else{
    $id=(int)$old['id'];$counts['existing']++;
    // Enrich missing fields only; never replace a manually verified location.
    $q=$db->prepare('UPDATE contacts SET linkedin_url=CASE WHEN linkedin_url="" THEN ? ELSE linkedin_url END,place=CASE WHEN place="" THEN ? ELSE place END,topic=CASE WHEN topic="" THEN ? ELSE topic END,updated_at=? WHERE id=?');
    $q->execute([$url,$place,$tags,gmdate('c'),$id]);
   }
   $q=$db->prepare('INSERT INTO capture_profiles(source_key,contact_id,homepage_url,headline,evidence) VALUES(?,?,?,?,?) ON CONFLICT(source_key) DO UPDATE SET homepage_url=CASE WHEN excluded.homepage_url<>"" THEN excluded.homepage_url ELSE capture_profiles.homepage_url END,headline=CASE WHEN excluded.headline<>"" THEN excluded.headline ELSE capture_profiles.headline END');
   $q->execute([$key,$id,$homepage,$headline,$evidence]);
  }
  foreach($b['posts'] as $r){
   $key=captureText($r,'key',180,true);$url=captureUrl(captureText($r,'url',600));
   $author=captureProfileId($db,captureText($r,'author_key',180));
   $title=captureText($r,'title',200,true);$excerpt=captureText($r,'excerpt',6000);$place=captureText($r,'place',160);$tags=captureText($r,'tags',500);$evidence=captureText($r,'evidence',1000,true);
   $old=captureFind($db,'SELECT * FROM capture_posts WHERE source_key=?',[$key]);
   $byUrl=$url!==''?captureFind($db,'SELECT * FROM capture_posts WHERE url=?',[$url]):null;
   if($byUrl&&(!$old||(int)$old['id']!==(int)$byUrl['id']))throw new RuntimeException('Beitrag bereits unter Schlüssel '.$byUrl['source_key'].' erfasst. Bitte diesen Schlüssel verwenden.');
   if($old){
    if(($url!==''&&$old['url']!==''&&$old['url']!==$url)||($author!==null&&$old['author_id']!==null&&(int)$old['author_id']!==$author))throw new RuntimeException('Widersprüchlicher Beitrag: '.$key);
    $q=$db->prepare('UPDATE capture_posts SET url=CASE WHEN url="" THEN ? ELSE url END,author_id=COALESCE(author_id,?) WHERE id=?');$q->execute([$url,$author,$old['id']]);$counts['existing']++;
   }else{
    $q=$db->prepare('INSERT INTO capture_posts(source_key,url,title,excerpt,author_id,place,tags,evidence) VALUES(?,?,?,?,?,?,?,?)');$q->execute([$key,$url,$title,$excerpt,$author,$place,$tags,$evidence]);$counts['posts']++;
   }
  }
  foreach($b['comments'] as $r){
   $key=captureText($r,'key',180,true);$postKey=captureText($r,'post_key',180,true);$author=captureProfileId($db,captureText($r,'author_key',180,true));
   $post=captureFind($db,'SELECT id FROM capture_posts WHERE source_key=?',[$postKey]);if(!$post)throw new RuntimeException('Beitragszuordnung fehlt: '.$postKey);
   $text=captureText($r,'text',6000,true);$status=captureText($r,'text_status',20)?:'excerpt';
   if(!in_array($status,['excerpt','complete'],true))throw new RuntimeException('text_status muss excerpt oder complete sein.');
   $url=captureUrl(captureText($r,'url',600));$evidence=captureText($r,'evidence',1000,true);
   $old=captureFind($db,'SELECT * FROM capture_comments WHERE source_key=?',[$key]);
   if($old){
    if((int)$old['post_id']!==(int)$post['id']||(int)$old['author_id']!==$author||$old['text']!==$text)throw new RuntimeException('Kommentar-Schlüssel bereits mit anderen Daten belegt: '.$key);
    $counts['existing']++;continue;
   }
   $q=$db->prepare('INSERT INTO capture_comments(source_key,post_id,author_id,text,text_status,url,evidence) VALUES(?,?,?,?,?,?,?)');$q->execute([$key,$post['id'],$author,$text,$status,$url,$evidence]);$counts['comments']++;
  }
  if($save)$db->commit();else $db->rollBack();
 }catch(Throwable $e){if($db->inTransaction())$db->rollBack();throw $e;}
 return $counts;
}
