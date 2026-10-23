<?php
declare(strict_types=1);

function profileThreadUrl(string $url):string {
 $p=parse_url($url);$host=strtolower((string)($p['host']??''));
 return is_array($p)&&strtolower((string)($p['scheme']??''))==='https'&&!isset($p['user'])&&!isset($p['pass'])&&!isset($p['port'])&&($host==='linkedin.com'||str_ends_with($host,'.linkedin.com')||$host==='lnkd.in')?$url:'';
}
function profileThreadKey(string $url):string {
 if(profileThreadUrl($url)==='')return '';
 $path=rawurldecode((string)parse_url($url,PHP_URL_PATH));
 if(preg_match('~(?:urn:li:activity:|activity-)([0-9]{10,30})(?:[^0-9]|$)~',$path,$m))return 'activity:'.$m[1];
 $host=strtolower((string)parse_url($url,PHP_URL_HOST));
 return ($host==='lnkd.in'?'lnkd.in':'linkedin.com').rtrim($path,'/');
}
function profileThreadTables(PDO $db):array {
 return $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
}
function profileThreadAuthor(PDO $db,array $urls):?int {
 $tables=profileThreadTables($db);$keys=[];$ids=[];
 foreach($urls as $url){$key=profileThreadKey($url);if($key!=='')$keys[$key]=true;}
 if(!$keys)return null;
 if(in_array('contacts',$tables,true))foreach($db->query("SELECT id,linkedin_url FROM contacts WHERE linkedin_url<>''") as $r){
  $path=(string)parse_url($r['linkedin_url'],PHP_URL_PATH);
  if(preg_match('~^/(in|company)/[^/]+/?$~',$path)&&isset($keys[profileThreadKey($r['linkedin_url'])]))$ids[(int)$r['id']]=true;
 }
 if(in_array('capture_posts',$tables,true))foreach($db->query("SELECT author_id,url FROM capture_posts WHERE author_id IS NOT NULL AND url<>''") as $r){if(isset($keys[profileThreadKey($r['url'])]))$ids[(int)$r['author_id']]=true;}
 if(in_array('linkedin_comments',$tables,true))foreach($db->query("SELECT contact_id,post_url FROM linkedin_comments WHERE contact_id IS NOT NULL AND post_url<>''") as $r){if(isset($keys[profileThreadKey($r['post_url'])]))$ids[(int)$r['contact_id']]=true;}
 if(count($ids)>1)throw new RuntimeException('Mehrdeutige Autorenzuordnung.');
 return $ids?(int)array_key_first($ids):null;
}
function profileThreadData(PDO $db,int $contactId):?array {
 $tables=profileThreadTables($db);if(!in_array('contacts',$tables,true))return null;
 $q=$db->prepare('SELECT id,name,place FROM contacts WHERE id=?');$q->execute([$contactId]);$profile=$q->fetch(PDO::FETCH_ASSOC);
 if(!$profile)return null;
 $posts=[];$byExcerpt=[];$own=null;
 if(in_array('capture_profiles',$tables,true))$own=$db->query("SELECT contact_id FROM capture_profiles WHERE source_key='profile-lars-focke'")->fetchColumn();
 $normalize=static fn(string $s):string=>preg_replace('/\s+/u',' ',trim($s))??trim($s);
 $addComment=static function(array &$post,string $text,string $url,string $status)use($normalize):void {
  if(trim($text)==='')return;
  foreach($post['my_comments'] as &$c){if($normalize($c['text'])===$normalize($text)){if($c['url']==='')$c['url']=profileThreadUrl($url);return;}}unset($c);
  $post['my_comments'][]=['text'=>$text,'url'=>profileThreadUrl($url),'status'=>$status==='complete'?'complete':'excerpt'];
 };
 if(in_array('capture_posts',$tables,true)){
  $q=$db->prepare('SELECT * FROM capture_posts WHERE author_id=? ORDER BY id DESC');$q->execute([$contactId]);
  foreach($q as $r){
   $key=profileThreadKey($r['url'])?:'capture:'.$r['id'];
   if(!isset($posts[$key]))$posts[$key]=['title'=>$r['title'],'excerpt'=>$r['excerpt'],'url'=>profileThreadUrl($r['url']),'my_comments'=>[],'archive_url'=>'admin-erfassung.php#post-'.(int)$r['id']];
   $byExcerpt[$normalize($r['excerpt'])][$key]=true;
   if($own&&in_array('capture_comments',$tables,true)){
    $c=$db->prepare('SELECT text,url,text_status FROM capture_comments WHERE post_id=? AND author_id=? ORDER BY id');$c->execute([(int)$r['id'],(int)$own]);
    foreach($c as $comment)$addComment($posts[$key],$comment['text'],$comment['url'],$comment['text_status']);
   }
  }
 }
 if(in_array('linkedin_comments',$tables,true)){
  $q=$db->prepare('SELECT * FROM linkedin_comments WHERE contact_id=? ORDER BY id DESC');$q->execute([$contactId]);
  foreach($q as $r){
   $key=profileThreadKey($r['post_url']);$excerptKey=$normalize($r['post_excerpt']);
   if($key===''&&$excerptKey!==''&&count($byExcerpt[$excerptKey]??[])===1)$key=(string)array_key_first($byExcerpt[$excerptKey]);
   if($key==='')$key='csv:'.hash('sha256',$excerptKey!==''?$excerptKey:'row:'.$r['id']);
   if(!isset($posts[$key]))$posts[$key]=['title'=>mb_substr($r['post_excerpt'],0,140,'UTF-8')?:'Gespeicherter Beitrag','excerpt'=>$r['post_excerpt'],'url'=>profileThreadUrl($r['post_url']),'my_comments'=>[],'archive_url'=>'admin-kommentare.php'];
   $addComment($posts[$key],(string)$r['my_comment'],'','excerpt');
  }
 }
 return ['id'=>(int)$profile['id'],'name'=>$profile['name'],'place'=>$profile['place'],'posts'=>array_values($posts),'post_count'=>count($posts),'comment_count'=>array_sum(array_map(static fn($p)=>count($p['my_comments']),$posts))];
}
