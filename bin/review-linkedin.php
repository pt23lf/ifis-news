<?php
declare(strict_types=1);
// Private CLI moderation. Never available over HTTP.
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
$dbPath=dirname(__DIR__).'/storage/source-submissions.sqlite';
if(!is_file($dbPath)){fwrite(STDERR,"No submissions database.\n");exit(1);}
$db=new PDO('sqlite:'.$dbPath,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('PRAGMA busy_timeout=5000');
$columns=array_column($db->query('PRAGMA table_info(submissions)')->fetchAll(PDO::FETCH_ASSOC),'name');
foreach(['lat'=>'REAL','lon'=>'REAL'] as $key=>$type)if(!in_array($key,$columns,true))$db->exec("ALTER TABLE submissions ADD COLUMN $key $type");
$command=$argv[1]??'list';
if($command==='list'){
 foreach($db->query("SELECT id,status,type,title,url,place,feed_url,lat,lon FROM submissions ORDER BY id DESC LIMIT 100") as $r){
  echo "#".$r['id']." [".$r['status']."] ".$r['type']." | ".$r['title']." | ".$r['url']." | ".$r['place']." | feed: ".($r['feed_url']?:'-')." | coordinates: ".($r['lat']??'-').", ".($r['lon']??'-')."\n";
 }
 exit;
}
$id=filter_var($argv[2]??'',FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
if(!$id||!in_array($command,['approve','reject'],true)){fwrite(STDERR,"Usage: php bin/review-linkedin.php list|approve ID [LAT LON]|reject ID\n");exit(1);}
if($command==='approve'){
 $lat=null;$lon=null;
 if(isset($argv[3],$argv[4])){
  if(!is_numeric($argv[3])||!is_numeric($argv[4])||abs((float)$argv[3])>90||abs((float)$argv[4])>180){fwrite(STDERR,"Invalid coordinates.\n");exit(1);}
  $lat=(float)$argv[3];$lon=(float)$argv[4];
 }
 $st=$db->prepare("UPDATE submissions SET status='approved',lat=?,lon=? WHERE id=?");
 $st->execute([$lat,$lon,$id]);
}else{
 $st=$db->prepare("UPDATE submissions SET status='rejected',lat=NULL,lon=NULL WHERE id=?");
 $st->execute([$id]);
}
if($st->rowCount()!==1){fwrite(STDERR,"ID not found or no change.\n");exit(1);}
echo "Submission #$id: $command\n";
