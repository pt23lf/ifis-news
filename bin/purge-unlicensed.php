<?php
declare(strict_types=1);
// Irreversible cleanup of third-party feeds and shared snapshots.
// Invoke manually after deploying the first-party-only public code.
// php bin/purge-unlicensed.php --confirm
if(PHP_SAPI!=='cli'){http_response_code(404);exit;}
if(!in_array('--confirm',$argv,true)){fwrite(STDERR,"Usage: php bin/purge-unlicensed.php --confirm\nDeletes all non-IFIS news rows and collections containing non-IFIS records.\n");exit(2);}
$root=dirname(__DIR__);
$dbFile=$root.'/storage/news.sqlite';
if(!is_file($dbFile)){fwrite(STDERR,"News database not found.\n");exit(1);}
$db=new PDO('sqlite:'.$dbFile,null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$db->exec('PRAGMA busy_timeout=15000');
$db->beginTransaction();
try{
 $count=(int)$db->query("SELECT COUNT(*) FROM articles WHERE source <> 'IFIS · Artikel'")->fetchColumn();
 $db->exec("DELETE FROM article_analysis WHERE article_id IN (SELECT id FROM articles WHERE source <> 'IFIS · Artikel')");
 $db->exec("DELETE FROM articles WHERE source <> 'IFIS · Artikel'");
 $db->commit();
 echo "Removed non-IFIS article records: $count\n";
}catch(Throwable $e){$db->rollBack();fwrite(STDERR,"Database cleanup failed.\n");exit(1);}
$dir=$root.'/storage/collections';$deleted=0;$kept=0;
foreach(glob($dir.'/*.json')?:[] as $file){
 $data=json_decode((string)@file_get_contents($file),true);
 if(!is_array($data)||!is_array($data['articles']??null)){continue;}
 $unsafe=false;
 foreach($data['articles'] as $article){
  if(!is_array($article)||($article['source']??'')!=='IFIS · Artikel'||!str_starts_with((string)($article['url']??''),'https://ifis.net/artikel/')){$unsafe=true;break;}
 }
 if($unsafe){if(@unlink($file))$deleted++;else fwrite(STDERR,"Could not remove collection: ".basename($file)."\n");}
 else $kept++;
}
echo "Removed collections containing third-party material: $deleted; kept IFIS-only: $kept\n";
echo "SQLite free pages can retain old bytes. Run VACUUM during a maintenance window if required; check backups separately.\n";
