<?php
declare(strict_types=1);

/**
 * Optional, read-only source-location audit.
 * Run: php bin/check-source-imprints.php
 * Does not geolocate IP addresses, does not guess coordinates and does not
 * change the map: detected legal-address clues require editorial confirmation.
 */
if (PHP_SAPI !== 'cli') { http_response_code(403); exit(1); }
$base = dirname(__DIR__);
$feeds = json_decode((string)file_get_contents($base.'/config/feeds.json'), true, 512, JSON_THROW_ON_ERROR);
$targets = [
 'IFIS · Artikel'=>['site'=>'https://ifis.net','city'=>'Lübeck'],
 'The Guardian'=>['site'=>'https://www.theguardian.com','city'=>'London'],
 'BBC News'=>['site'=>'https://www.bbc.com','city'=>'London'],
 'Deutsche Welle'=>['site'=>'https://www.dw.com','city'=>'Bonn'],
 'SPIEGEL'=>['site'=>'https://www.spiegel.de','city'=>'Hamburg'],
 'Al Jazeera'=>['site'=>'https://www.aljazeera.com','city'=>'Doha'],
 'France 24'=>['site'=>'https://www.france24.com','city'=>'Issy-les-Moulineaux'],
 'Euronews'=>['site'=>'https://www.euronews.com','city'=>'Lyon'],
 'NPR'=>['site'=>'https://www.npr.org','city'=>'Washington'],
 'New York Times'=>['site'=>'https://www.nytimes.com','city'=>'New York'],
 'NASA'=>['site'=>'https://www.nasa.gov','city'=>'Washington'],
 'NOAA'=>['site'=>'https://www.noaa.gov','city'=>'Silver Spring'],
 'ScienceDaily'=>['site'=>'https://www.sciencedaily.com','city'=>'Rockville'],
 'Phys.org'=>['site'=>'https://phys.org','city'=>'Isle of Man'],
 'IEEE Spectrum'=>['site'=>'https://spectrum.ieee.org','city'=>'New York'],
];
$requested = $argv[1] ?? '';
$registry = [];
foreach ($feeds as $feed) {
 foreach ($targets as $name=>$target) {
  if (str_contains((string)$feed['name'], $name)) $registry[$name] = $target;
 }
}
if ($requested !== '' && !array_key_exists($requested, $registry)) {
 fwrite(STDERR, "Unbekannte Quelle. Mögliche Namen: ".implode(', ', array_keys($registry))."\n");
 exit(2);
}
if ($requested !== '') $registry = [$requested=>$registry[$requested]];
function downloadPage(string $url): ?string {
 if (parse_url($url, PHP_URL_SCHEME) !== 'https') return null;
 $ctx=stream_context_create(['http'=>[
  'timeout'=>7,'max_redirects'=>3,'follow_location'=>1,
  'user_agent'=>'IFIS-News source-imprint-audit/1.0 (+https://news.ifis.net)'
 ]]);
 $raw=@file_get_contents($url,false,$ctx,0,350000);
 return is_string($raw) && strlen($raw)>150 ? $raw : null;
}
function legalLinks(string $html, string $origin): array {
 $links=[];
 $host=parse_url($origin,PHP_URL_HOST);
 if(preg_match_all('~<a\b[^>]+href\s*=\s*["\x27]([^"\x27]+)["\x27][^>]*>(.*?)</a>~isu',$html,$matches,PREG_SET_ORDER)){
  foreach($matches as $m){
   $caption=mb_strtolower(strip_tags($m[2]),'UTF-8');
   $href=html_entity_decode($m[1],ENT_QUOTES|ENT_HTML5,'UTF-8');
   if(!preg_match('~impressum|legal notice|legal information|imprint|corporate information|contact|about us~iu',$caption.' '.$href))continue;
   if(str_starts_with($href,'/'))$href=$origin.$href;
   if(parse_url($href,PHP_URL_SCHEME)==='https' && parse_url($href,PHP_URL_HOST)===$host)$links[$href]=true;
  }
 }
 return array_slice(array_keys($links),0,3);
}
function addressCityEvidence(string $html,string $city): bool {
 // Require an address-like context rather than a city mentioned in a news story.
 $plain=html_entity_decode(strip_tags(preg_replace('~<(script|style)\b[^>]*>.*?</\1>~isu',' ',$html)),ENT_QUOTES|ENT_HTML5,'UTF-8');
 $plain=preg_replace('/\s+/u',' ',$plain);
 $c=preg_quote($city,'~');
 if(preg_match('~(?:postalAddress|addressLocality|headquarters|registered office|registered address|Anschrift|Sitz|Adresse|Impressum)[^.!?]{0,180}\b'.$c.'\b~iu',$html.' '.$plain))return true;
 if(preg_match('~(?:\b\d{5}\s+'.$c.'\b|\b'.$c.'\s*,?\s*[A-Z]{2}\s+\d{5}\b)~iu',$plain))return true;
 return false;
}
$existingPath=$base.'/storage/source-imprint-audit.json';
$results=is_file($existingPath)?json_decode((string)file_get_contents($existingPath),true):[];
if(!is_array($results))$results=[];
foreach($registry as $name=>$target){
 $origin=rtrim($target['site'],'/');
 $home=downloadPage($origin.'/');
 $urls=$home!==null?legalLinks($home,$origin):[];
 // These are only discovery candidates, never assumed to be valid legal notices.
 foreach(['/impressum','/legal','/about-us','/contact'] as $suffix){
  if(count($urls)>=4)break;
  $url=$origin.$suffix;
  if(!in_array($url,$urls,true))$urls[]=$url;
 }
 $result=['site'=>$origin,'expected_city'=>$target['city'],'checked_at'=>gmdate('c'),
  'status'=>'not_confirmed','evidence_url'=>null,'method'=>'official-site-address-evidence'];
 foreach(array_slice($urls,0,4) as $url){
  $body=downloadPage($url);
  if($body===null)continue;
  if(!addressCityEvidence($body,$target['city']))continue;
  $result['status']='address_city_found';
  $result['evidence_url']=$url;
  break;
 }
 $results[$name]=$result;
 echo $name.': '.$result['status'].($result['evidence_url']?' ('.$result['evidence_url'].')':'')."\n";
}
$dir=dirname($existingPath);
if(!is_dir($dir))mkdir($dir,0750,true);
$tmp=$existingPath.'.tmp.'.getmypid();
file_put_contents($tmp,json_encode($results,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES|JSON_THROW_ON_ERROR),LOCK_EX);
rename($tmp,$existingPath);
echo "Prüfergebnisse: ".$existingPath."\n";
