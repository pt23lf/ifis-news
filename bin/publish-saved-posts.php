<?php
declare(strict_types=1);

// Explicitly selected public link cards only. Never publish private capture records.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}

function savedPostActivity(string $url):string {
 $parts=parse_url($url);
 $host=strtolower((string)($parts['host']??''));
 if(!is_array($parts)||strtolower((string)($parts['scheme']??''))!=='https'
  ||($host!=='linkedin.com'&&!str_ends_with($host,'.linkedin.com'))
  ||isset($parts['user'])||isset($parts['pass'])||isset($parts['port']))return '';
 $path=rawurldecode((string)($parts['path']??''));
 return preg_match('~(?:urn:li:activity:|activity-)([0-9]{10,30})(?:[^0-9]|$)~',$path,$match)?$match[1]:'';
}

function savedPostManifest(string $json):array {
 if(strlen($json)>1048576)throw new RuntimeException('Beitragsdatei zu groß.');
 $data=json_decode($json,true,32,JSON_THROW_ON_ERROR);
 if(!is_array($data)||($data['schema_version']??null)!==1||!is_array($data['posts']??null)
  ||!array_is_list($data['posts'])||count($data['posts'])<1||count($data['posts'])>50){
  throw new RuntimeException('Ungültiges Beitragsformat.');
 }
 $ids=[];
 foreach($data['posts'] as $post){
  if(!is_array($post))throw new RuntimeException('Ungültiger Beitrag.');
  foreach(['url'=>500,'title'=>120,'description'=>800,'creator'=>160,'topics'=>250] as $key=>$limit){
   if(!isset($post[$key])||!is_string($post[$key])||trim($post[$key])===''||mb_strlen($post[$key],'UTF-8')>$limit){
    throw new RuntimeException('Beitragsfeld fehlt oder ist zu lang: '.$key);
   }
  }
  if(isset($post['source_place'])&&(!is_string($post['source_place'])||trim($post['source_place'])===''||mb_strlen($post['source_place'],'UTF-8')>160
   ||!is_numeric($post['source_lat']??null)||!is_numeric($post['source_lon']??null)
   ||!is_finite((float)$post['source_lat'])||!is_finite((float)$post['source_lon'])
   ||abs((float)$post['source_lat'])>90||abs((float)$post['source_lon'])>180))throw new RuntimeException('Ungültiger Profilstandort.');
  $activity=savedPostActivity($post['url']);
  if($activity===''||isset($ids[$activity])||!in_array($post['kind']??'',['idea','offer','challenge'],true)){
   throw new RuntimeException('Ungültiger oder doppelter LinkedIn-Beitrag.');
  }
  $ids[$activity]=true;
 }
 return $data['posts'];
}

function publishSavedPosts(PDO $db,array $posts,bool $save):array {
 $db->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
 $db->exec('PRAGMA busy_timeout=5000');
 $db->exec('BEGIN IMMEDIATE');
 try{
  $db->exec('CREATE TABLE IF NOT EXISTS submissions (id INTEGER PRIMARY KEY AUTOINCREMENT,created_at TEXT NOT NULL,type TEXT NOT NULL,url TEXT NOT NULL,title TEXT NOT NULL,place TEXT NOT NULL,description TEXT NOT NULL,status TEXT NOT NULL DEFAULT "pending")');
  $columns=array_column($db->query('PRAGMA table_info(submissions)')->fetchAll(PDO::FETCH_ASSOC),'name');
  foreach(['homepage_url'=>"TEXT NOT NULL DEFAULT ''",'feed_url'=>"TEXT NOT NULL DEFAULT ''",'kind'=>"TEXT NOT NULL DEFAULT 'idea'",'topics'=>"TEXT NOT NULL DEFAULT ''",'creator'=>"TEXT NOT NULL DEFAULT ''",'private_note'=>"TEXT NOT NULL DEFAULT ''",'lat'=>'REAL','lon'=>'REAL','source_place'=>"TEXT NOT NULL DEFAULT ''",'source_lat'=>'REAL','source_lon'=>'REAL'] as $column=>$definition){
   if(!in_array($column,$columns,true))$db->exec("ALTER TABLE submissions ADD COLUMN $column $definition");
  }
  $byActivity=[];
  foreach($db->query('SELECT id,url,status FROM submissions') as $row){
   $id=savedPostActivity((string)$row['url']);
   if($id!=='')$byActivity[$id][]=$row;
  }
  $counts=['new'=>0,'approved'=>0,'existing'=>0,'located'=>0];
  $insert=$db->prepare("INSERT INTO submissions(created_at,type,url,title,place,description,status,homepage_url,feed_url,kind,topics,creator,private_note,lat,lon) VALUES(?,'linkedin',?,?,'',?,'approved','','',?,?,?,'',NULL,NULL)");
  $approve=$db->prepare("UPDATE submissions SET status='approved' WHERE id=? AND status='pending'");
  $locate=$db->prepare("UPDATE submissions SET source_place=?,source_lat=?,source_lon=? WHERE id=? AND COALESCE(source_place,'')='' AND source_lat IS NULL AND source_lon IS NULL");
  foreach($posts as $post){
   $id=savedPostActivity($post['url']);$existing=$byActivity[$id]??[];
   if(count($existing)>1)throw new RuntimeException('Beitrag mehrfach vorhanden; bitte zuerst prüfen: '.$post['title']);
   if($existing){
    $row=$existing[0];
    if($row['status']==='approved'){
     if(isset($post['source_place'])){$locate->execute([$post['source_place'],$post['source_lat'],$post['source_lon'],$row['id']]);$counts['located']+=$locate->rowCount();}
     $counts['existing']++;continue;
    }
    if($row['status']!=='pending')throw new RuntimeException('Beitrag wurde bereits abgelehnt; bitte in der Quellenprüfung prüfen: '.$post['title']);
    $approve->execute([$row['id']]);
    if(isset($post['source_place'])){$locate->execute([$post['source_place'],$post['source_lat'],$post['source_lon'],$row['id']]);$counts['located']+=$locate->rowCount();}
    $counts['approved']++;continue;
   }
   // No inferred event location, copied media or private contact details.
   $insert->execute([gmdate('c'),$post['url'],$post['title'],$post['description'],$post['kind'],$post['topics'],$post['creator']]);
   if(isset($post['source_place'])){$locate->execute([$post['source_place'],$post['source_lat'],$post['source_lon'],$db->lastInsertId()]);$counts['located']+=$locate->rowCount();}
   $counts['new']++;
  }
  $db->exec($save?'COMMIT':'ROLLBACK');
  return $counts;
 }catch(Throwable $error){
  // BEGIN IMMEDIATE was issued explicitly; PDO transaction bookkeeping can vary.
  try{$db->exec('ROLLBACK');}catch(Throwable $ignored){}
  throw $error;
 }
}

if(realpath((string)($_SERVER['SCRIPT_FILENAME']??''))!==__FILE__)return;
try{
 $args=array_slice($argv,1);
 if($args!==[]&&$args!==['--dry-run'])throw new RuntimeException('Aufruf: php bin/publish-saved-posts.php [--dry-run]');
 $root=dirname(__DIR__);$file=$root.'/config/curated-posts-2026-10-23.json';
 $posts=savedPostManifest((string)file_get_contents($file));
 $dryRun=in_array('--dry-run',$args,true);
 $path=$root.'/storage/source-submissions.sqlite';
 $db=new PDO($dryRun&&!is_file($path)?'sqlite::memory:':'sqlite:'.$path,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION,PDO::ATTR_TIMEOUT=>5]);
 $result=publishSavedPosts($db,$posts,!$dryRun);
 echo ($dryRun?'Vorschau: ':'Gespeichert: ').$result['new'].' neue Beiträge, '.$result['approved'].' bisher offene freigegeben, '.$result['existing']." bereits veröffentlicht, ".$result['located']." Profilstandorte ergänzt.\n";
 if(!$dryRun)echo "Öffentliche Linkübersicht: https://news.ifis.net/?view=world&map=0#stories\n";
}catch(Throwable $error){fwrite(STDERR,'Beitragsübernahme abgebrochen: '.$error->getMessage()."\n");exit(1);}

