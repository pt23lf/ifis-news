<?php
declare(strict_types=1);

// Only content already approved for the public page is eligible for sharing.
// Private network.sqlite is deliberately never opened here.
function publicCollectionRecords(string $root,string $section,array $urls):array {
 $records=[];if(!$urls)return $records;
 $marks=implode(',',array_fill(0,count($urls),'?'));
 if(is_file($root.'/storage/news.sqlite')){
  $db=new PDO('sqlite:'.$root.'/storage/news.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  $q=$db->prepare("SELECT title,url,source,published_at,summary FROM articles WHERE source='IFIS · Artikel' AND url IN ($marks)");
  $q->execute($urls);
  foreach($q->fetchAll(PDO::FETCH_ASSOC) as $r)$records[$r['url']]=$r;
 }
 if(is_file($root.'/storage/source-submissions.sqlite')){
  $db=new PDO('sqlite:'.$root.'/storage/source-submissions.sqlite',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
  $q=$db->prepare("SELECT title,url,description,created_at FROM submissions WHERE status='approved' AND url IN ($marks) ORDER BY id DESC");$q->execute($urls);
  foreach($q as $r){
   $p=parse_url($r['url']);
   if(!is_array($p)||strtolower((string)($p['scheme']??''))!=='https'||empty($p['host'])||isset($p['user'])||isset($p['pass']))continue;
   if(!isset($records[$r['url']]))$records[$r['url']]=['title'=>$r['title'],'url'=>$r['url'],'source'=>'Quelle · Freigegeben','published_at'=>$r['created_at'],'summary'=>$r['description'],'publication'=>'approved-submission-v1'];
  }
 }
 return $records;
}
function publicCollectionSnapshotAllowed(array $item,array $approved):bool {
 if(($item['source']??'')==='IFIS · Artikel'&&str_starts_with((string)($item['url']??''),'https://ifis.net/artikel/'))return true;
 $url=(string)($item['url']??'');
 return ($item['publication']??'')==='approved-submission-v1'&&isset($approved[$url])&&($approved[$url]['publication']??'')==='approved-submission-v1';
}
