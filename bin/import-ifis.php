<?php
declare(strict_types=1);
if(PHP_SAPI!=='cli'){http_response_code(403);exit;}
$root=dirname(__DIR__);
$target=$root.'/storage/ifis-articles.json';
if(is_file($target)&&filemtime($target)>time()-86400&&!in_array('--force',$argv,true)){echo "IFIS reference index current\n";exit;}
function fetch(string $url):string{
 $ctx=stream_context_create(['http'=>['timeout'=>15,'user_agent'=>'IFIS-News/1.0 (+https://news.ifis.net)','follow_location'=>0]]);
 return (string)(@file_get_contents($url,false,$ctx)?:'');
}
$xml=fetch('https://ifis.net/sitemap.xml');
if(!$xml){fwrite(STDERR,"IFIS sitemap unavailable; retaining previous index\n");exit(1);}
libxml_use_internal_errors(true);
$map=simplexml_load_string($xml,'SimpleXMLElement',LIBXML_NONET);
if(!$map){fwrite(STDERR,"Invalid sitemap\n");exit(1);}
$urls=[];
foreach($map->url as $node){
 $url=(string)$node->loc;
 if(preg_match('~^https://ifis\.net/artikel/[a-z0-9-]+/?$~i',$url))$urls[]=$url;
}
$urls=array_values(array_unique($urls));
$records=[];
foreach(array_slice($urls,0,350) as $url){
 $html=fetch($url);
 if(!$html)continue;
 $doc=new DOMDocument();
 if(!@$doc->loadHTML('<?xml encoding="utf-8" ?>'.$html,LIBXML_NONET|LIBXML_NOERROR|LIBXML_NOWARNING))continue;
 $xp=new DOMXPath($doc);
 $title=trim((string)$xp->evaluate('string(//meta[@property="og:title"]/@content)'));
 if(!$title)$title=trim((string)$xp->evaluate('string(//h1[1])'));
 $description=trim((string)$xp->evaluate('string(//meta[@name="description"]/@content)'));
 if(!$description)$description=trim((string)$xp->evaluate('string(//meta[@property="og:description"]/@content)'));
 if(!$title)continue;
 $records[]=['url'=>$url,'title'=>mb_substr($title,0,180),'description'=>mb_substr($description,0,400)];
}
if(count($records)<2){fwrite(STDERR,"Too few IFIS articles; retaining previous index\n");exit(1);}
$tmp=$target.'.tmp';
file_put_contents($tmp,json_encode(['updated_at'=>gmdate('c'),'articles'=>$records],JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_PRETTY_PRINT),LOCK_EX);
rename($tmp,$target);
echo "Indexed ".count($records)." IFIS articles\n";
