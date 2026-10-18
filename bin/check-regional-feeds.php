<?php
declare(strict_types=1);
if (PHP_SAPI !== 'cli') exit(1);
$base=dirname(__DIR__);
$data=json_decode(file_get_contents($base.'/config/regional-feed-candidates.json'),true,512,JSON_THROW_ON_ERROR);
foreach($data['candidates'] as $candidate){
 $url=$candidate['url'];
 $status='ERROR';$message='';
 if(!filter_var($url,FILTER_VALIDATE_URL)||parse_url($url,PHP_URL_SCHEME)!=='https'){$message='invalid HTTPS URL';}
 else {
  $ctx=stream_context_create(['http'=>['timeout'=>12,'follow_location'=>0,'user_agent'=>'IFIS-News/0.1 (+https://news.ifis.net)']]);
  $body=@file_get_contents($url,false,$ctx);
  $headers=$http_response_header??[];
  $code=0;
  foreach($headers as $header)if(preg_match('~^HTTP/\\S+\\s+(\\d+)~',$header,$m))$code=(int)$m[1];
  if($body===false){$message='HTTP '.$code.' or network error';}
  elseif(strlen($body)>5000000){$message='feed exceeds 5MB';}
  else {
   $old=libxml_use_internal_errors(true);
   $xml=simplexml_load_string($body,'SimpleXMLElement',LIBXML_NONET);
   libxml_clear_errors();libxml_use_internal_errors($old);
   if(!$xml){$message='invalid XML';}
   else {
    $root=strtolower($xml->getName());
    $entries=$root==='rss'?count($xml->channel->item):($root==='feed'?count($xml->entry):($root==='rdf'?count($xml->item):0));
    if($entries){$status='OK';$message=$root.' '.$entries.' entries';}
    else $message='no RSS/Atom items';
   }
  }
 }
 echo $status.' | '.$candidate['region'].' | '.$candidate['name'].' | '.$message.PHP_EOL;
}
