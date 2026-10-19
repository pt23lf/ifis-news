<?php
declare(strict_types=1);
// 1200 × 630 social-preview image. Built server-side because social crawlers do
// not execute MapLibre/JavaScript. This endpoint does not call external services.
if (!function_exists('imagecreatetruecolor')) {
 http_response_code(503);
 header('Content-Type: text/plain; charset=utf-8');
 echo 'PNG-Vorschau erfordert die PHP-GD-Erweiterung.';
 exit;
}
header('Content-Type: image/png');
header('Cache-Control: public, max-age=3600');
$w=1200; $h=630;
$im=imagecreatetruecolor($w,$h);
imagealphablending($im,true);
function rgb($im, int $r,int $g,int $b,int $a=0): int {
 return imagecolorallocatealpha($im,$r,$g,$b,$a);
}
$white=rgb($im,255,255,255);
$ink=rgb($im,20,36,56);
$blue=rgb($im,28,94,188);
$sea=rgb($im,203,232,244);
$land=rgb($im,158,203,164);
$landLight=rgb($im,218,229,160);
$border=rgb($im,125,180,197);
$line=rgb($im,225,150,46,38);
$source=rgb($im,238,158,48);
$event=rgb($im,19,158,144);
imagefill($im,0,0,$white);
$cx=323; $cy=308; $radius=260;
// Subtle horizon, oceans and latitude grid, the geographic content is stylised.
imagefilledellipse($im,$cx+6,$cy+9,$radius*2+15,$radius*2+15,rgb($im,219,227,230,28));
imagefilledellipse($im,$cx,$cy,$radius*2,$radius*2,$sea);
imageellipse($im,$cx,$cy,$radius*2,$radius*2,rgb($im,125,187,210));
$lon0=12*M_PI/180; $lat0=18*M_PI/180;
function projectPoint(float $lon,float $lat): ?array {
 global $cx,$cy,$radius,$lon0,$lat0;
 $l=$lon*M_PI/180; $p=$lat*M_PI/180;
 $visible=sin($lat0)*sin($p)+cos($lat0)*cos($p)*cos($l-$lon0);
 if($visible<=0.055)return null;
 return [
  (int)round($cx+$radius*cos($p)*sin($l-$lon0)),
  (int)round($cy-$radius*(cos($lat0)*sin($p)-sin($lat0)*cos($p)*cos($l-$lon0)))
 ];
}
for($lat=-60;$lat<=60;$lat+=30){
 $previous=null;
 for($lon=-180;$lon<=180;$lon+=2){
  $pt=projectPoint($lon,$lat);
  if($pt&&$previous)imageline($im,$previous[0],$previous[1],$pt[0],$pt[1],rgb($im,144,188,201,79));
  $previous=$pt;
 }
}
for($lon=-180;$lon<=180;$lon+=30){
 $previous=null;
 for($lat=-84;$lat<=84;$lat+=2){
  $pt=projectPoint($lon,$lat);
  if($pt&&$previous)imageline($im,$previous[0],$previous[1],$pt[0],$pt[1],rgb($im,144,188,201,83));
  $previous=$pt;
 }
}
// Deliberately simplified geographic outlines: visual preview, not authoritative map.
$continents=[
 [[-10,35],[0,43],[10,43],[20,45],[31,41],[40,43],[43,51],[31,60],[11,69],[-8,58],[-10,35]],
 [[-17,35],[1,36],[12,32],[30,32],[44,12],[50,-13],[33,-34],[18,-35],[9,-22],[2,-6],[-12,6],[-17,35]],
 [[30,41],[45,49],[53,58],[70,64],[95,59],[120,47],[127,30],[110,22],[99,10],[84,5],[71,11],[62,25],[43,31],[30,41]],
 [[-84,13],[-70,10],[-60,3],[-53,-3],[-48,-17],[-57,-32],[-70,-50],[-77,-24],[-84,13]],
 [[-130,45],[-118,49],[-105,60],[-87,57],[-80,46],[-63,42],[-67,30],[-81,20],[-98,17],[-109,33],[-130,45]],
 [[-53,82],[-40,82],[-32,68],[-45,59],[-54,65],[-53,82]],
];
foreach($continents as $index=>$polygon){
 $shape=[]; $allVisible=true;
 foreach($polygon as [$lon,$lat]){
  $p=projectPoint((float)$lon,(float)$lat);
  if(!$p){$allVisible=false;break;}
  array_push($shape,$p[0],$p[1]);
 }
 if($allVisible&&count($shape)>=6){
  imagefilledpolygon($im,$shape,$index%2===0?$land:$landLight);
  imagepolygon($im,$shape,$border);
 }
}
// Connections between editorial locations and reported places.
$points=[
 [-.1276,51.5072], [10.0007,53.5503],[7.0982,50.7374],
 [2.273,48.823],[51.531,25.2854],[-77.0369,38.9072],
 [30.52,50.45],[31.04,30.04],[10.68,53.86],
 [35.21,31.77],[32,15],[38.3,9.0],[-54,-12],[-60,-2],
 [11.6,2.6],[44,13],[64,28],[20,10],[-1,6]
];
$routes=[[0,6],[0,9],[1,7],[1,11],[1,13],[2,10],[2,8],[3,9],[3,14],[4,12],[4,18],[0,16],[1,15],[2,6],[5,8],[0,17],[1,10],[3,16],[4,7],[2,9],[1,18],[0,14],[3,15]];
foreach($routes as [$fromIndex,$toIndex]){
 [$flon,$flat]=$points[$fromIndex];[$tlon,$tlat]=$points[$toIndex];
 $a=projectPoint($flon,$flat);$b=projectPoint($tlon,$tlat);
 if(!$a||!$b)continue;
 $mx=($a[0]+$b[0])/2;$my=($a[1]+$b[1])/2;
 $dist=hypot($b[0]-$a[0],$b[1]-$a[1]);
 $lift=min(55,$dist*.18);
 $control=[$mx,$my-$lift];$old=$a;
 for($step=1;$step<=36;$step++){
  $t=$step/36;$u=1-$t;
  $current=[(int)round($u*$u*$a[0]+2*$u*$t*$control[0]+$t*$t*$b[0]),
    (int)round($u*$u*$a[1]+2*$u*$t*$control[1]+$t*$t*$b[1])];
  imageline($im,$old[0],$old[1],$current[0],$current[1],$line);
  $old=$current;
 }
}
foreach($points as $i=>[$lon,$lat]){
 $p=projectPoint((float)$lon,(float)$lat);if(!$p)continue;
 imagefilledellipse($im,$p[0],$p[1],10,10,$white);
 imagefilledellipse($im,$p[0],$p[1],7,7,$i<6?$source:$event);
}
// Copy panel.
imagefilledrectangle($im,670,0,1199,629,$white);
imageline($im,673,87,673,545,rgb($im,226,234,240));
$font=null;
foreach(['/usr/share/fonts/truetype/dejavu/DejaVuSans.ttf',
 '/usr/share/fonts/truetype/liberation2/LiberationSans-Regular.ttf',
 '/usr/share/fonts/truetype/freefont/FreeSans.ttf'] as $candidate){
 if(is_file($candidate)){$font=$candidate;break;}
}
function drawText($im,int $size,int $x,int $y,string $text,int $color): void {
 global $font;
 if($font&&function_exists('imagettftext')){
  imagettftext($im,$size,0,$x,$y,$color,$font,$text);
 }else imagestring($im,5,$x,$y-18,iconv('UTF-8','ASCII//TRANSLIT//IGNORE',$text)?:$text,$color);
}
drawText($im,45,715,185,'IFIS News',$ink);
drawText($im,23,715,243,'Nachrichten verbinden.',$blue);
drawText($im,17,715,298,'Ereignisorte · Quellen',$ink);
drawText($im,17,715,330,'Verbindungen auf einer Weltkarte',$ink);
$kind=(string)($_GET['collection']??'');
$name=trim((string)($_GET['name']??''));
if(in_array($kind,['event','source'],true)&&$name!==''){
 $name=mb_substr($name,0,43,'UTF-8');
 drawText($im,15,715,412,$kind==='event'?'EREIGNISORT':'QUELLENORT',$blue);
 drawText($im,24,715,453,$name,$ink);
}
drawText($im,18,715,545,'news.ifis.net',$blue);
imagepng($im,null,6);
imagedestroy($im);
