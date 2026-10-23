<?php
declare(strict_types=1);

function commentMapCoordinate($lat,$lon):?array {
 if($lat===null||$lon===null||!is_numeric($lat)||!is_numeric($lon))return null;
 $lat=(float)$lat;$lon=(float)$lon;
 return is_finite($lat)&&is_finite($lon)&&abs($lat)<=90&&abs($lon)<=180?[$lon,$lat]:null;
}
function commentMapUrl(string $url):string {
 $p=parse_url($url);$host=strtolower((string)($p['host']??''));
 return is_array($p)&&strtolower((string)($p['scheme']??''))==='https'
  &&!isset($p['user'])&&!isset($p['pass'])&&!isset($p['port'])
  &&($host==='lnkd.in'||$host==='linkedin.com'||str_ends_with($host,'.linkedin.com'))?$url:'';
}
function commentMapData(PDO $db):array {
 $tables=$db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
 $out=['origin'=>['name'=>'Lars Focke · Lübeck','coordinates'=>[10.686,53.866]],'connections'=>[],'missing'=>[]];
 if(!in_array('contacts',$tables,true))return $out;
 $own=null;
 if(in_array('capture_profiles',$tables,true)){
  $own=$db->query("SELECT c.* FROM capture_profiles p JOIN contacts c ON c.id=p.contact_id WHERE p.source_key='profile-lars-focke'")->fetch(PDO::FETCH_ASSOC)?:null;
 }
 if($own&&($point=commentMapCoordinate($own['lat'],$own['lon'])))$out['origin']=['name'=>'Lars Focke · '.$own['place'],'coordinates'=>$point];
 $groups=[];$seen=[];
 $add=static function(array $r)use(&$groups,&$seen,$own):void {
  if(trim((string)$r['text'])===''||($own&&(int)$r['contact_id']===(int)$own['id'])||mb_strtolower(trim($r['name']),'UTF-8')==='lars focke')return;
  $id=(int)$r['contact_id'];
  $url=commentMapUrl((string)$r['post_url']);
  $fingerprint=hash('sha256',$id."\x1f".($url?:$r['excerpt'])."\x1f".$r['text']);
  if(isset($seen[$fingerprint]))return;$seen[$fingerprint]=true;
  if(!isset($groups[$id]))$groups[$id]=['id'=>$id,'name'=>$r['name'],'place'=>$r['place'],'coordinates'=>commentMapCoordinate($r['lat'],$r['lon']),'posts'=>[]];
  $groups[$id]['posts'][]=['title'=>$r['title']?:'Kommentierter Beitrag','excerpt'=>$r['excerpt'],'url'=>$url,'comment'=>$r['text'],'comment_url'=>commentMapUrl((string)$r['comment_url']),'detail_url'=>$r['detail_url']];
 };
 if($own&&in_array('capture_posts',$tables,true)&&in_array('capture_comments',$tables,true)){
  $q=$db->prepare('SELECT c.id AS contact_id,c.name,c.place,c.lat,c.lon,p.title,p.excerpt,p.url AS post_url,k.text,k.url AS comment_url,k.source_key FROM capture_comments k JOIN capture_posts p ON p.id=k.post_id JOIN contacts c ON c.id=p.author_id WHERE k.author_id=? ORDER BY k.id');
  $q->execute([(int)$own['id']]);
  foreach($q as $r){$r['detail_url']='admin-kommentare.php#'.rawurlencode($r['source_key']);$add($r);}
 }
 if(in_array('linkedin_comments',$tables,true)){
  $q=$db->query("SELECT c.id AS contact_id,c.name,c.place,c.lat,c.lon,k.post_excerpt AS excerpt,k.post_url,k.my_comment AS text FROM linkedin_comments k JOIN contacts c ON c.id=k.contact_id WHERE trim(k.my_comment)<>'' ORDER BY k.id");
  foreach($q as $r){$r['title']=mb_substr($r['excerpt'],0,140,'UTF-8');$r['comment_url']='';$r['detail_url']='admin-kommentare.php';$add($r);}
 }
 foreach($groups as $g){
  if($g['coordinates']===null)$out['missing'][]=['id'=>$g['id'],'name'=>$g['name'],'place'=>$g['place'],'comments'=>count($g['posts'])];
  else $out['connections'][]=$g;
 }
 return $out;
}
