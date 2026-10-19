<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
$view = ($_GET['view'] ?? 'ifis') === 'world' ? 'world' : 'ifis';
$shareCollectionType=in_array((string)($_GET['collection']??''),['event','source'],true)?(string)$_GET['collection']:'';
$shareCollectionName=trim((string)($_GET['name']??''));
if(mb_strlen($shareCollectionName,'UTF-8')>100)$shareCollectionName=mb_substr($shareCollectionName,0,100,'UTF-8');
if($shareCollectionName===''||preg_match('/[\x00-\x1f\x7f]/u',$shareCollectionName))$shareCollectionType='';
$ogPageTitle=$shareCollectionType!==''?$shareCollectionName.' · '.($shareCollectionType==='event'?'Ereignisort':'Quellenort').' · IFIS News':($view==='ifis'?'Potenzial':'Weltlage').' · IFIS News';
$ogDescription=$shareCollectionType!==''?
 'Aktuelle Artikelsammlung zum '.($shareCollectionType==='event'?'Ereignisort ':'Quellenort ').$shareCollectionName.'. Quellen, Meldungen und Verbindungen auf der interaktiven IFIS-News-Karte entdecken.':
 'Nachrichten aus unterschiedlichen Quellen, Ereignisorte und ihre Verbindungen auf der interaktiven Weltkarte von IFIS News.';
$ogQuery=(string)($_SERVER['QUERY_STRING']??'');
$ogCanonical='https://news.ifis.net/'.($ogQuery!==''?'?'.$ogQuery:'');
$ogImage='https://news.ifis.net/share-image.php'.($shareCollectionType!==''?'?'.http_build_query(['collection'=>$shareCollectionType,'name'=>$shareCollectionName]):'');

$items = [];
$sourceBatches=[];
$dbPath = dirname(__DIR__) . '/storage/news.sqlite';
if (is_file($dbPath) && extension_loaded('pdo_sqlite')) {
 try {
  $db = new PDO('sqlite:' . $dbPath);
  $query = $db->prepare('SELECT title,url,source,published_at,summary,image_url FROM articles WHERE section=:section ORDER BY published_at DESC LIMIT 400');
  $query->execute(['section'=>$view]);
  $items = $query->fetchAll(PDO::FETCH_ASSOC);
  // Keep IFIS articles selectable even if the general 400-item news window moves on.
  if($view==='ifis'){
   $ifisQuery=$db->prepare("SELECT title,url,source,published_at,summary,image_url FROM articles WHERE section='ifis' AND source=:source ORDER BY published_at DESC LIMIT 250");
   $ifisQuery->execute(['source'=>'IFIS · Artikel']);
   $extra=$ifisQuery->fetchAll(PDO::FETCH_ASSOC);
   $byUrl=[];
   foreach(array_merge($items,$extra) as $article)$byUrl[$article['url']]=$article;
   $items=array_values($byUrl);
   usort($items,static fn($a,$b)=>strcmp((string)$b['published_at'],(string)$a['published_at']));
  }
  $cols=$db->query('PRAGMA table_info(articles)')->fetchAll(PDO::FETCH_ASSOC);
  $recent=$db->prepare("SELECT source,published_at,COUNT(*) AS count FROM articles WHERE section=:section AND published_at >= :since AND published_at <= :until GROUP BY source, strftime('%Y-%m-%d %H:',published_at), CAST(CAST(strftime('%M',published_at) AS INTEGER)/30 AS INTEGER) ORDER BY published_at DESC");
  $recent->execute(['section'=>$view,'since'=>gmdate('Y-m-d H:i:s',time()-172800),'until'=>gmdate('Y-m-d H:i:s')]);
  $sourceBatches=$recent->fetchAll(PDO::FETCH_ASSOC);

 } catch (Throwable $e) { error_log($e->getMessage()); }
}
$lastImportFile=dirname(__DIR__).'/storage/news-last-import.txt';
$lastImportLabel='Noch keine Aktualisierung protokolliert';
if(is_file($lastImportFile)){
 $lastImportTime=strtotime(trim((string)file_get_contents($lastImportFile)));
 if($lastImportTime!==false)$lastImportLabel='Letzter Import: '.date('d.m.Y H:i',$lastImportTime).' Uhr (Serverzeit)';
}
function esc(?string $value): string { return htmlspecialchars($value ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8'); }
function category(array $item): string {
 $s = mb_strtolower(($item['title'] ?? '').' '.($item['summary'] ?? ''),'UTF-8');
 $groups = [
 'Politik'=>['politik','regierung','parlament','wahl','diplomat','minister','konflikt','krieg'],
 'Gesellschaft'=>['gesellschaft','stadt','wohnen','migration','sozial','demokratie'],
 'Wirtschaft'=>['wirtschaft','markt','handel','unternehmen','inflation','energiepreis'],
 'Technologie'=>['technologie','ki ','künstliche intelligenz','digital','software','forschung'],
 'Umwelt'=>['klima','umwelt','natur','wald','biodivers','wasser','erneuerbar'],
 'Gesundheit'=>['gesundheit','medizin','pflege','krankenhaus','patient','bewegung'],
 'Bildung'=>['bildung','schule','lehrer','unterricht','universität','lernen'],
 'Wissenschaft'=>['wissenschaft','studie','forscher','experiment']
 ];
 foreach($groups as $name=>$words){foreach($words as $word){if(str_contains($s,$word))return $name;}}
 return 'Weitere';
}
foreach($items as &$item){$item['category']=category($item);} unset($item);
function headlineWords(string $title):array{
 $title=mb_strtolower($title,'UTF-8');
 $title=preg_replace('/[^\\p{L}\\p{N}]+/u',' ',$title);
 $stop=['the','and','for','with','from','nach','über','eine','einer','einem','einen','dass','sich','auch','mehr','news','live','update','aktuell'];
 return array_values(array_unique(array_filter(explode(' ',trim($title)),static fn($w)=>mb_strlen($w,'UTF-8')>=4&&!in_array($w,$stop,true))));
}
function originAgency(array $item):string{
 $sample=(string)($item['summary']??'');
 // Only attribute an agency when the feed explicitly credits it.
 if(preg_match('/(?:^|[\\s(\\[—–-])(?:Quelle:|Agentur:|Von:)?\\s*(dpa|Deutsche Presse-Agentur|Reuters|Associated Press|Agence France-Presse|AFP|AP)(?=[\\s)\\].,:;—–-]|$)/iu',$sample,$m)){
  $name=mb_strtolower($m[1],'UTF-8');
  return str_contains($name,'presse-agentur')||$name==='dpa'?'dpa':(str_contains($name,'associated')||$name==='ap'?'AP':(str_contains($name,'france')||$name==='afp'?'AFP':'Reuters'));
 }
 return '';
}
$deduplicated=[];
foreach($items as $item){
 $item['agency']=originAgency($item);
 $item['other_versions']=[];
 $words=headlineWords((string)$item['title']);
 $date=strtotime((string)$item['published_at'])?:0;
 $matched=false;
 if(count($words)>=5){
  foreach($deduplicated as &$existing){
   $other=headlineWords((string)$existing['title']);
   $otherDate=strtotime((string)$existing['published_at'])?:0;
   if(count($other)<5||!$date||!$otherDate||abs($date-$otherDate)>172800)continue;
   $overlap=count(array_intersect($words,$other));
   $similarity=$overlap/max(count($words),count($other));
   if($similarity<0.84)continue;
   $existing['other_versions'][]=['source'=>$item['source'],'url'=>$item['url'],'agency'=>$item['agency']];
   if(!$existing['agency']&&$item['agency'])$existing['agency']=$item['agency'];
   $matched=true;break;
  }
  unset($existing);
 }
 if(!$matched)$deduplicated[]=$item;
}
$items=$deduplicated;

// The CLI source-audit stores reviewable hints only; it never overrides editorial coordinates.
$sourceImprintAudit=[];
$auditFile=dirname(__DIR__).'/storage/source-imprint-audit.json';
if(is_file($auditFile)){
 $data=json_decode((string)file_get_contents($auditFile),true);
 if(is_array($data))$sourceImprintAudit=$data;
}
$ifisIndexFile=dirname(__DIR__).'/storage/ifis-articles.json';
$ifisIndex=[];
if(is_file($ifisIndexFile)){
 $raw=json_decode((string)file_get_contents($ifisIndexFile),true);
 if(is_array($raw)&&isset($raw['articles'])&&is_array($raw['articles']))$ifisIndex=$raw['articles'];
}

?><!doctype html>
<html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#ffffff">
<meta name="description" content="<?=esc($ogDescription)?>">
<meta property="og:type" content="website">
<meta property="og:site_name" content="IFIS News">
<meta property="og:locale" content="de_DE">
<meta property="og:title" content="<?=esc($ogPageTitle)?>">
<meta property="og:description" content="<?=esc($ogDescription)?>">
<meta property="og:url" content="<?=esc($ogCanonical)?>">
<meta property="og:image" content="<?=esc($ogImage)?>">
<meta property="og:image:secure_url" content="<?=esc($ogImage)?>">
<meta property="og:image:type" content="image/png">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta property="og:image:alt" content="IFIS News – Weltkarte mit Quellen, Ereignisorten und Verbindungslinien">
<meta name="twitter:card" content="summary_large_image">
<meta name="twitter:title" content="<?=esc($ogPageTitle)?>">
<meta name="twitter:description" content="<?=esc($ogDescription)?>">
<meta name="twitter:image" content="<?=esc($ogImage)?>">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 64 64%27%3E%3Crect width=%2764%27 height=%2764%27 rx=%2714%27 fill=%27%232468d5%27/%3E%3Ctext x=%2732%27 y=%2743%27 text-anchor=%27middle%27 font-family=%27Arial%27 font-weight=%27bold%27 font-size=%2733%27 fill=%27white%27%3EI%3C/text%3E%3C/svg%3E"><title><?=esc($ogPageTitle)?></title>
<style>
:root{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Inter,Arial,sans-serif;color-scheme:light;--bg:#fff;--text:#17212e;--muted:#697587;--line:#e7eaf0;--soft:#f5f7fa;--accent:#2468d5;--hover:#eef4ff}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font-size:15px;line-height:1.48}a{color:inherit;text-decoration:none}button,input{font:inherit}button{cursor:pointer}button:focus-visible,a:focus-visible,input:focus-visible{outline:2px solid var(--accent);outline-offset:2px}.topbar{height:65px;border-bottom:1px solid var(--line);display:flex;align-items:center;gap:32px;padding:0 30px;position:sticky;top:0;background:var(--bg);z-index:5}.brand{font-size:25px;letter-spacing:-1px;white-space:nowrap}.brand strong{font-weight:780}.topnav{display:flex;height:100%;gap:18px}.topnav a{padding:19px 12px 16px;border-bottom:3px solid transparent;white-space:nowrap}.topnav a.active{border-color:var(--accent);font-weight:650}.top-actions{margin-left:auto;display:flex;gap:15px;align-items:center}.search{border:1px solid var(--line);background:var(--soft);border-radius:24px;padding:9px 15px;min-width:270px;color:var(--text)}.search::placeholder{color:var(--muted)}.iconbtn{border:0;background:transparent;color:var(--muted);padding:7px 9px;border-radius:8px}.iconbtn:hover,.side button:hover,.chip:hover{background:var(--hover)}.layout{display:grid;grid-template-columns:minmax(0,1fr);gap:28px;max-width:1100px;margin:auto;padding:25px 24px 70px}.side{border-right:1px solid var(--line);padding-right:15px}.side h3{font-size:12px;margin:23px 10px 8px;color:var(--muted);font-weight:650;text-transform:uppercase;letter-spacing:.06em}.side button{display:block;width:100%;text-align:left;border:0;background:transparent;padding:10px 12px;border-radius:9px;color:var(--muted)}.side button.active{background:var(--hover);color:var(--accent);font-weight:650}.feedhead{display:flex;align-items:baseline;justify-content:space-between;border-bottom:1px solid var(--line);padding:6px 0 13px;gap:12px}.feedhead h1{font-size:23px;letter-spacing:-.5px;margin:0}.muted{color:var(--muted)}.feedhead small{color:var(--muted);white-space:nowrap}.chips{display:flex;gap:8px;overflow:auto;padding:14px 0 16px;scrollbar-width:none}.chips::-webkit-scrollbar{display:none}.chip{border:1px solid var(--line);border-radius:18px;background:var(--bg);padding:6px 12px;white-space:nowrap;font-size:13px;color:var(--muted)}.chip.active{background:var(--text);color:var(--bg);border-color:var(--text)}.story{display:grid;grid-template-columns:120px minmax(0,1fr) auto;gap:17px;padding:17px 0;border-bottom:1px solid var(--line);align-items:start}.story.feature{grid-template-columns:minmax(0,1fr) auto;padding-top:8px}.story h2{font-size:17px;line-height:1.3;letter-spacing:-.25px;margin:3px 0 5px}.story.feature h2{font-size:23px}.story h2 a:hover{text-decoration:underline}.summary{color:var(--muted);font-size:14px;margin:0 0 7px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}.meta{font-size:12px;color:var(--muted)}.cat{font-size:11px;font-weight:700;color:var(--accent)}.thumb{background:var(--soft);width:120px;height:82px;border-radius:9px;display:flex;align-items:center;justify-content:center;color:#a3afbd;font-size:24px}.story.feature .thumb{display:none}.story:not(.feature):not(:has(.thumb)){grid-template-columns:minmax(0,1fr) auto}.story>div:nth-child(2){min-width:0}.thumb{object-fit:cover}.story-actions{display:flex;gap:0}.story-actions button{border:0;background:transparent;padding:5px;color:var(--muted);font-size:20px}.story-actions button:hover,.story-actions button.saved{color:var(--accent)}.panel{border:1px solid var(--line);border-radius:12px;padding:16px;margin-bottom:15px}.panel h2{font-size:15px;margin:0 0 12px}.panel p{color:var(--muted);font-size:13px}.panel a{color:var(--accent)}.panel button{border:1px solid var(--line);border-radius:18px;padding:6px 10px;background:var(--bg);margin:0 5px 8px 0;color:var(--muted)}.empty{text-align:center;padding:75px 15px;color:var(--muted)}.empty .symbol{font-size:36px;color:#a6b1bf}.empty h2{color:var(--text);font-size:20px}.empty p{max-width:420px;margin:auto}.bottomnav{display:none}.hide{display:none!important}.subtle{font-size:12px;color:var(--muted)}.sectiontitle{font-size:14px;font-weight:650;margin:10px 0 0}.sidefooter{font-size:12px;color:var(--muted);padding:15px 10px}
@media(max-width:1150px){.layout{grid-template-columns:minmax(0,1fr);gap:22px}.right{display:none}}
@media(max-width:720px){.topbar{height:60px;padding:0 15px;gap:8px}.brand{font-size:23px}.topnav{position:absolute;top:60px;left:0;right:0;height:47px;justify-content:center;border-bottom:1px solid var(--line);background:var(--bg)}.topnav a{padding:11px 25px 9px}.top-actions{gap:0}.search{position:absolute;left:12px;right:12px;top:112px;width:calc(100% - 24px);min-width:0;display:none;z-index:4;background:var(--bg)}.search.open{display:block}.search-toggle{display:block}.layout{display:block;padding:61px 15px 90px}.side{display:none}.feedhead h1{font-size:20px}.feedhead{padding-top:5px}.feedhead small{font-size:11px}.story{grid-template-columns:83px minmax(0,1fr) auto;gap:10px;padding:14px 0}.thumb{width:83px;height:75px}.story h2{font-size:15px}.story.feature{grid-template-columns:minmax(0,1fr) auto}.story.feature h2{font-size:20px}.story:not(.feature):not(:has(.thumb)){grid-template-columns:minmax(0,1fr) auto}.summary{font-size:13px;-webkit-line-clamp:2}.story-actions{flex-direction:column}.story-actions button{font-size:18px;padding:2px}.bottomnav{position:fixed;bottom:0;left:0;right:0;display:flex;justify-content:space-around;background:var(--bg);border-top:1px solid var(--line);padding:7px 0 max(8px,env(safe-area-inset-bottom));z-index:6}.bottomnav button,.bottomnav a{border:0;background:none;text-align:center;font-size:11px;color:var(--muted);min-width:60px}.bottomnav .ico{display:block;font-size:21px}.bottomnav .active{color:var(--accent)}.empty{padding:65px 15px}}
@media(prefers-color-scheme:dark){:root{color-scheme:dark;--bg:#15191f;--text:#e9edf3;--muted:#a0acba;--line:#313945;--soft:#202731;--accent:#8ab4ff;--hover:#26354a}.chip.active{background:#e9edf3;color:#15191f;border-color:#e9edf3}}
.source-controls{margin:12px 0;font-size:13px}.source-controls button{border:1px solid var(--line);border-radius:8px;background:var(--bg);color:var(--text);padding:6px 10px;cursor:pointer}.source-controls #sourcePanel{border:1px solid var(--line);border-radius:10px;padding:12px;margin-top:8px}.source-controls #sourceList{display:flex;flex-wrap:wrap;gap:12px;margin-bottom:12px}.source-controls #sourceList label{display:flex;gap:5px;align-items:center}.quality-controls{display:flex;align-items:center;gap:12px;flex-wrap:wrap;margin:8px 0;font-size:13px}.quality-controls select,.quality-controls button{border:1px solid var(--line);border-radius:8px;background:var(--bg);color:var(--text);padding:5px}.quality-controls label{display:flex;align-items:center;gap:5px}.quality-tag{font-size:11px;color:var(--muted)}.side .direction-shortcut{font-weight:700;color:#177245;margin-bottom:8px}.side .direction-shortcut.active{background:#e7f5ee;color:#17643d}.direction-nav{padding:16px 0 6px}.direction-label{display:block;font-size:12px;font-weight:650;color:var(--muted);margin-bottom:10px}.direction-buttons{display:flex;gap:7px;flex-wrap:wrap}.direction-buttons button{border:1px solid var(--line);border-radius:20px;padding:7px 12px;background:var(--bg);color:var(--muted);font-size:13px;cursor:pointer}.direction-buttons button.active{background:var(--text);color:var(--bg)}.direction-buttons button[data-direction="constructive"].active{background:#0d7666;color:white;border-color:#0d7666}.direction-buttons button[data-direction="harmful"].active{background:#a54b40;color:white;border-color:#a54b40}.direction-buttons button[data-direction="mixed"].active{background:#74649a;color:white;border-color:#74649a}.direction-note{font-size:11px;color:var(--muted)}.refine{margin:8px 0 18px;border-bottom:1px solid var(--line);padding-bottom:12px}.refine>summary{cursor:pointer;color:var(--muted);font-size:13px;list-style:none}.refine>summary::-webkit-details-marker{display:none}.refine>summary:before{content:'⚙ ';font-size:13px}.refine[open]>summary{margin-bottom:12px}.refine .subtle{margin-bottom:8px}.assessment{margin-top:9px;color:var(--muted);font-size:12px}.assessment>summary{cursor:pointer;width:max-content}.assessment .trust-tools{margin-top:7px}.editorial-note{border-top:1px solid var(--line);padding:15px 0;color:var(--muted);font-size:12px}.editorial-note strong{color:var(--text);font-size:13px}.editorial-note p{line-height:1.6}.editorial-note a{color:var(--accent)}.quality-tag[hidden]{display:none}.story .cat{color:var(--muted);font-weight:600}.story.feature h2{font-size:21px}.story h2{font-weight:680}.story{padding:20px 0}.story-actions button{font-size:18px}.filter-toolbar{display:flex;gap:5px;align-items:center;justify-content:space-around;border-block:1px solid var(--line);padding:8px 0;margin:12px 0}.filter-toolbar button{font:inherit;border:0;background:transparent;color:var(--muted);border-radius:10px;padding:8px 9px;display:flex;align-items:center;gap:6px;cursor:pointer}.filter-toolbar button.active,.filter-toolbar button:hover{background:var(--hover);color:var(--accent)}.filter-section:not(.filter-expanded){display:none!important}@media(max-width:720px){.filter-toolbar{gap:1px}.filter-toolbar button{font-size:18px;flex-direction:column;gap:0;min-width:0;padding:6px 3px;flex:1}.filter-toolbar button span{font-size:10px}.layout{padding-bottom:35px}.bottomnav{display:none!important}}.topnav{align-items:center}.topnav .globe-toggle{font-size:13px;margin-left:8px;padding:6px 11px}.feedhead h1{display:none}.filter-dock{position:sticky;top:66px;z-index:4;background:var(--bg);border-radius:12px;box-shadow:0 3px 15px #0000000d}.filter-dock-toggle{display:none}.filter-toolbar{margin:0}.filter-section{scroll-margin-top:145px}@media(max-width:720px){.topnav .globe-toggle{font-size:12px;padding:4px 8px;margin-left:0}.topnav{gap:5px}.topnav a{padding-inline:12px}.feedhead{justify-content:flex-end}.filter-dock{position:fixed;right:10px;top:52%;transform:translateY(-50%);z-index:8;background:var(--bg);border:1px solid var(--line);box-shadow:0 4px 18px #0003;border-radius:18px;max-width:68px}.filter-dock-toggle{display:block;width:46px;height:44px;border:0;background:transparent;color:var(--accent);font-size:23px;margin:auto}.filter-toolbar{display:none;flex-direction:column;border:0;padding:3px 2px 9px;gap:0}.filter-dock.expanded .filter-toolbar{display:flex}.filter-dock .filter-toolbar button{width:58px;flex:none;font-size:20px;padding:7px 2px}.filter-dock .filter-toolbar button span{font-size:9px}.filter-section{scroll-margin-top:120px}}.dock-language{display:flex;align-items:center;gap:4px;font-size:13px;color:var(--muted);padding:5px}.dock-language select{font-size:12px!important;padding:4px!important;max-width:62px}.topnav .globe-toggle{border:0;border-radius:0;border-bottom:3px solid transparent;height:100%;padding:19px 12px 16px;font-size:15px;margin:0;color:var(--text)}.topnav .globe-toggle.active{border-bottom-color:var(--accent);background:transparent;color:var(--text)}@media(max-width:720px){.topnav .globe-toggle{height:47px;padding:11px 12px 9px;font-size:15px}.dock-language{flex-direction:column;gap:1px;font-size:16px;padding:3px}.dock-language select{width:52px;font-size:11px!important}}.story-menu{position:relative;align-self:start}.story-menu>summary{list-style:none;cursor:pointer;color:var(--muted);font-size:24px;line-height:1;padding:2px 7px;border-radius:8px}.story-menu>summary::-webkit-details-marker{display:none}.story-menu[open]>summary{background:var(--hover)}.story-menu .story-actions{position:absolute;right:0;top:30px;z-index:3;display:flex;flex-direction:column;background:var(--bg);border:1px solid var(--line);box-shadow:0 5px 20px #0002;border-radius:12px;min-width:180px;padding:7px}.story-menu .story-actions button{font-size:13px;text-align:left;white-space:nowrap;padding:9px 12px;color:var(--text)}.story-menu .story-actions button:hover{background:var(--hover)}@media(max-width:720px){.story,.story.feature,.story:not(.feature):not(:has(.thumb)){display:grid;grid-template-columns:minmax(0,1fr) 36px;gap:4px 10px;padding:20px 0 23px}.story .thumb{grid-column:1/-1;grid-row:1;width:100%;height:auto;aspect-ratio:1.85;object-fit:cover;border-radius:14px;margin-bottom:12px}.story.feature .thumb{display:block}.story>div:not(.story-actions){grid-column:1;min-width:0}.story h2,.story.feature h2{font-size:20px;line-height:1.3;letter-spacing:-.25px;font-weight:560;margin:6px 0 12px}.story .summary{display:none}.story .cat{display:none}.story .meta{font-size:13px;line-height:1.4}.story .assessment{margin-top:10px}.story .story-menu{grid-column:2;grid-row:2}.story .story-menu>summary{font-size:23px;text-align:center}.story .story-menu .story-actions{right:0}.story .quality-tag{font-size:10px;margin-top:4px}}.story{scroll-margin-top:110px}.filter-dock{position:fixed;right:20px;top:50%;transform:translateY(-50%);z-index:8;width:65px;border:1px solid var(--line);border-radius:18px;box-shadow:0 4px 18px #0002}.filter-dock-toggle{display:block;width:100%;height:44px;border:0;background:transparent;color:var(--accent);font-size:22px}.filter-toolbar{display:none;flex-direction:column;gap:0;border:0;padding:4px}.filter-dock.expanded .filter-toolbar{display:flex}.filter-dock .filter-toolbar button{width:100%;flex-direction:column;font-size:19px;padding:7px 2px}.filter-dock .filter-toolbar button span{font-size:9px}.filter-dock .dock-language{flex-direction:column}.filter-dock .dock-language select{width:52px}@media(min-width:721px){.filter-dock{top:50%;right:18px}.layout{padding-right:90px}}@media(max-width:720px){.story{scroll-margin-top:125px}}.globe-view{scroll-margin-top:115px}.story-map-link{border:0;background:transparent;color:var(--muted);font-size:12px;padding:6px 0;cursor:pointer}.story-map-link:hover{color:var(--accent)}.filter-dock .dock-language{padding:4px 0}.filter-dock .dock-language select{width:54px}@media(max-width:720px){.feedhead{display:none}}.story .assessment{display:none}.story .assessment[open]{display:block}.story .assessment>summary{display:none}@media(max-width:720px){.topbar{gap:8px;padding:0 10px}.brand{font-size:21px}.topnav{position:static;height:60px;flex:1;min-width:0;gap:1px;border-bottom:0;justify-content:center;background:transparent}.topnav a{font-size:13px;padding:20px 7px 16px;white-space:nowrap}.topnav .globe-toggle{height:60px;font-size:13px;padding:18px 7px 14px;white-space:nowrap}.top-actions{margin-left:0}.search-toggle{font-size:20px;padding:4px}.search{top:61px}.layout{padding-top:12px}.globe-view{scroll-margin-top:68px}}@media(max-width:390px){.brand{font-size:19px}.topnav a,.topnav .globe-toggle{font-size:12px;padding-left:5px;padding-right:5px}}.topbar{padding-left:0;padding-right:0}.topbar-inner{width:100%;max-width:1100px;margin:0 auto;padding:0 24px;display:flex;align-items:center;gap:32px;height:100%}@media(max-width:720px){.topbar-inner{padding:0 15px;gap:8px}.story .summary{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;font-size:14px;line-height:1.45;color:var(--muted);margin:0 0 10px}.story h2,.story.feature h2{margin-bottom:7px}}@media(min-width:721px){.topbar-inner .top-actions{margin-left:auto}}.view-switch{display:flex;justify-content:flex-end;gap:5px;margin:5px 0 12px}.view-switch button{border:1px solid var(--line);border-radius:9px;background:var(--bg);color:var(--muted);padding:7px 11px;font-size:18px;cursor:pointer}.view-switch button.active{color:var(--accent);background:var(--soft)}#stories.stories-grid{display:grid;grid-template-columns:repeat(auto-fit,minmax(min(100%,285px),1fr));gap:20px;align-items:start}#stories.stories-grid .story,#stories.stories-grid .story.feature,#stories.stories-grid .story:not(.feature):not(:has(.thumb)){display:grid;grid-template-columns:minmax(0,1fr) 30px;gap:5px 8px;align-content:start;padding:0 0 18px;border:1px solid var(--line);border-radius:14px;overflow:visible;background:var(--bg)}#stories.stories-grid .story .thumb,#stories.stories-grid .story.feature .thumb{display:block;grid-column:1/-1;grid-row:1;width:100%;height:auto;aspect-ratio:1.7;object-fit:cover;border-radius:13px 13px 0 0;margin:0 0 9px}#stories.stories-grid .story>div:not(.story-actions){grid-column:1;min-width:0;padding-left:14px}#stories.stories-grid .story h2,#stories.stories-grid .story.feature h2{font-size:18px;line-height:1.35;margin:5px 0 8px}#stories.stories-grid .story .summary{display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden;font-size:13px}#stories.stories-grid .story .story-menu{grid-column:2;grid-row:2;margin-right:8px}#stories.stories-grid .story .cat{display:none}#stories.stories-grid .story .meta{font-size:12px}#stories.stories-grid .story .assessment[open]{display:block}@media(max-width:720px){#stories.stories-grid{grid-template-columns:repeat(auto-fit,minmax(min(100%,220px),1fr));gap:12px}#stories.stories-grid .story h2{font-size:17px}}@media(min-width:1050px){#stories.stories-grid{grid-template-columns:repeat(3,minmax(0,1fr))}}.topbar-inner{box-sizing:border-box}.topbar-inner .view-switch{margin:0 0 0 auto;flex-shrink:0}.topbar-inner .view-switch button{padding:5px 9px;font-size:17px}.topbar-inner .top-actions{margin-left:0;flex-shrink:0}.topbar-inner{max-width:1100px}@media(max-width:720px){.topbar-inner .view-switch{gap:2px}.topbar-inner .view-switch button{padding:5px 6px;font-size:15px}.topbar-inner .top-actions{margin-left:0}.topbar-inner{padding-left:15px;padding-right:15px}.topnav{flex:0 1 auto}}@media(max-width:410px){.topbar-inner{gap:3px}.topbar-inner .view-switch button{padding:4px;font-size:14px}.topbar-inner .topnav a,.topbar-inner .topnav .globe-toggle{padding-left:4px;padding-right:4px}}.topbar-inner{max-width:1100px;width:100%;box-sizing:border-box}.topbar-inner .view-switch{margin-left:auto}.topbar-inner .top-actions{margin-left:0}.topbar-inner .search-toggle{margin-right:0}@media(min-width:721px){.topbar-inner{padding-right:24px}.topbar-inner .top-actions{margin-left:0}}@media(max-width:720px){.topbar-inner .top-actions{margin-left:0}}@media(max-width:720px){.topbar-inner{gap:9px}.topbar-inner .brand{margin-right:9px;flex-shrink:0}.topbar-inner .topnav{justify-content:flex-start;gap:3px}.story:not(:has(.thumb)) {padding-top:12px;padding-bottom:14px;row-gap:0}.story:not(:has(.thumb)) h2{margin-top:2px;margin-bottom:6px}.story:not(:has(.thumb)) .summary{margin-bottom:6px}.story:not(:has(.thumb)) .story-map-link{padding:3px 0}.story:not(:has(.thumb)) .meta{line-height:1.3}}@media(max-width:390px){.topbar-inner .brand{margin-right:5px}.topbar-inner{gap:5px}}#stories.stories-grid .story:not(:has(.thumb)){padding-top:12px}@media(max-width:720px){#stories.stories-grid .story:not(:has(.thumb)){padding-top:10px}}@media(max-width:720px){#stories.stories-grid{grid-template-columns:repeat(2,minmax(0,1fr));gap:10px}#stories.stories-grid .story h2,#stories.stories-grid .story.feature h2{font-size:15px;line-height:1.32}#stories.stories-grid .story .summary{font-size:12px;line-height:1.4}#stories.stories-grid .story .meta{font-size:11px}#stories.stories-grid .story>div:not(.story-actions){padding-left:9px}#stories.stories-grid .story .story-menu{margin-right:3px}#stories.stories-grid .story .thumb{aspect-ratio:1.35}}@media(max-width:350px){#stories.stories-grid{gap:7px}#stories.stories-grid .story h2{font-size:14px}}.news-stats{max-width:660px;margin:12px 0 18px;padding:18px;border:1px solid var(--line);border-radius:16px;background:var(--bg);box-shadow:0 4px 16px #0000000a}.news-stats[hidden]{display:none}.news-stats-head{display:flex;justify-content:space-between;align-items:center}.news-stats-head button{border:0;background:transparent;color:var(--muted);font-size:24px}.news-stats p{font-size:12px;color:var(--muted);line-height:1.5}.stats-group{margin:15px 0}.stats-group>strong{font-size:14px}.stats-row{margin-top:8px}.stats-row span{font-size:12px;color:var(--muted)}.stats-track{height:6px;background:var(--line);border-radius:10px;margin-top:4px;overflow:hidden}.stats-fill{height:100%;background:var(--accent);border-radius:10px}@media(max-width:720px){.news-stats{margin:10px 0;padding:14px}}.feedhead{display:flex;align-items:center;justify-content:flex-end}.grid-toggle{border:1px solid var(--line);background:var(--bg);color:var(--muted);border-radius:10px;padding:6px 10px;cursor:pointer;display:inline-flex;align-items:center;gap:6px;font-size:16px}.grid-toggle span{font-size:12px}#stories.grid-2,#stories.grid-3{display:grid;gap:0 18px;align-items:start}#stories.grid-2{grid-template-columns:repeat(2,minmax(0,1fr))}#stories.grid-3{grid-template-columns:repeat(3,minmax(0,1fr))}#stories.grid-2 .story,#stories.grid-3 .story{display:grid;grid-template-columns:minmax(0,1fr) 28px;gap:8px;padding:18px 0}#stories.grid-2 .story .thumb,#stories.grid-3 .story .thumb{grid-column:1/-1;grid-row:1;width:100%;height:auto;aspect-ratio:1.75;object-fit:cover;display:block;border-radius:12px}#stories.grid-2 .story.feature .thumb,#stories.grid-3 .story.feature .thumb{display:block}#stories.grid-2 .story>div:not(.story-actions),#stories.grid-3 .story>div:not(.story-actions){grid-column:1;min-width:0}#stories.grid-2 .story .story-menu,#stories.grid-3 .story .story-menu{grid-column:2;grid-row:2}#stories.grid-2 .story h2,#stories.grid-3 .story h2{font-size:17px;line-height:1.35}#stories.grid-2 .story .summary,#stories.grid-3 .story .summary{display:none}#stories.grid-2 .story .cat,#stories.grid-3 .story .cat{display:none}#stories.grid-2 .story .meta,#stories.grid-3 .story .meta{font-size:11px}@media(max-width:720px){#stories.grid-2{grid-template-columns:repeat(2,minmax(0,1fr));gap:0 12px}#stories.grid-3{grid-template-columns:repeat(3,minmax(0,1fr));gap:0 8px}#stories.grid-2 .story h2{font-size:15px}#stories.grid-3 .story h2{font-size:12px}#stories.grid-2 .story .meta{font-size:10px}#stories.grid-3 .story .meta{font-size:9px}#stories.grid-2 .story .thumb,#stories.grid-3 .story .thumb{aspect-ratio:1.35}#stories.grid-2 .story,#stories.grid-3 .story{grid-template-columns:minmax(0,1fr);gap:4px}#stories.grid-2 .story .story-menu,#stories.grid-3 .story .story-menu{grid-column:1;grid-row:auto;justify-self:end}#stories.grid-3 .story .story-menu .story-actions{right:0}}.topbar .grid-toggle{flex-shrink:0;margin-left:auto}.topbar .grid-toggle span{font-size:11px}@media(max-width:720px){.topbar .grid-toggle{padding:4px 6px;font-size:15px;margin-left:auto}}/* Final header and floating toolbar alignment */
@media(min-width:721px){.topbar-inner{max-width:1100px;width:100%;margin:0 auto;padding:0 24px;box-sizing:border-box}.topbar-inner .top-actions{margin-left:0;flex:0 1 280px;min-width:0}.topbar-inner .search{display:block;position:static;width:100%;min-width:0;max-width:280px;box-sizing:border-box}.topbar-inner .grid-toggle{margin-left:auto}}
.filter-dock{position:fixed!important;top:auto!important;bottom:18px!important;right:18px!important;left:auto!important;transform:none!important;z-index:30!important;width:auto!important;max-width:68px;display:flex;flex-direction:column-reverse}
.filter-dock.expanded .filter-toolbar{display:flex;flex-direction:column}
@media(max-width:720px){.filter-dock{bottom:calc(14px + env(safe-area-inset-bottom,0px))!important;right:14px!important}.topbar-inner .search{display:block;position:absolute;top:60px;left:15px;right:15px;width:calc(100% - 30px);min-width:0;box-sizing:border-box}}
/* Shared content edge and symmetric floating control */
@media(min-width:721px){.layout{width:100%;max-width:1100px;margin:0 auto;padding:12px 24px 70px!important;box-sizing:border-box}.topbar-inner{max-width:1100px;padding-left:24px!important;padding-right:24px!important}.topbar-inner .top-actions{margin-right:0!important}.topbar-inner .search{max-width:280px}.filter-dock{right:24px!important}}
.filter-dock{box-sizing:border-box!important;width:56px!important;max-width:56px!important;min-width:56px!important;align-items:center!important;padding:5px!important;border-radius:20px!important;overflow:visible!important}.filter-dock-toggle{width:44px!important;height:44px!important;min-width:44px!important;display:flex!important;align-items:center!important;justify-content:center!important;margin:0!important;padding:0!important;line-height:1!important;border-radius:15px!important}.filter-dock .filter-toolbar{width:100%}.filter-dock .filter-toolbar button{box-sizing:border-box}
@media(max-width:720px){.layout{padding-left:15px!important;padding-right:15px!important}.filter-dock{right:15px!important}}
.topbar .grid-toggle{border:0!important;background:transparent!important;box-shadow:none!important;border-radius:0!important;display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:5px 7px!important;color:var(--muted)}.topbar .grid-toggle .grid-toggle-symbol{display:block;flex-shrink:0}.topbar .grid-toggle #gridCount{font-size:13px;font-weight:500;line-height:1}
/* Preview text in every column layout, including mobile */
#stories.grid-1 .story .summary,#stories.grid-2 .story .summary,#stories.grid-3 .story .summary{display:-webkit-box!important;-webkit-box-orient:vertical;overflow:hidden;-webkit-line-clamp:3;font-size:14px;line-height:1.45;margin:4px 0 9px;color:var(--muted)}
@media(max-width:720px){#stories.grid-2 .story .summary{font-size:12px;-webkit-line-clamp:3}#stories.grid-3 .story .summary{font-size:11px;-webkit-line-clamp:3}}
/* Floating filter options appear beside the trigger, not above it */
.filter-dock{display:flex!important;flex-direction:row-reverse!important;align-items:center!important;gap:8px!important;width:auto!important;max-width:none!important;min-width:0!important;background:transparent!important;border:0!important;box-shadow:none!important;padding:0!important;overflow:visible!important}
.filter-dock-toggle{flex:0 0 52px!important;width:52px!important;height:52px!important;background:var(--bg)!important;border:1px solid var(--line)!important;border-radius:17px!important;box-shadow:0 4px 18px #0002!important}
.filter-dock .filter-toolbar{display:none!important;position:static!important;width:auto!important;max-width:min(520px,calc(100vw - 90px))!important;flex-direction:row!important;flex-wrap:wrap!important;justify-content:flex-end!important;align-items:center!important;gap:3px!important;margin:0!important;padding:5px 8px!important;background:var(--bg)!important;border:1px solid var(--line)!important;border-radius:16px!important;box-shadow:0 4px 18px #0002!important}
.filter-dock.expanded .filter-toolbar{display:flex!important}
.filter-dock .filter-toolbar button{flex:0 0 auto!important}
@media(max-width:720px){.filter-dock .filter-toolbar{max-width:calc(100vw - 86px)!important;padding:5px!important}.filter-dock .filter-toolbar button span{display:none!important}.filter-dock .filter-toolbar button{padding:7px!important}}
/* Two-level floating menu: vertical dock, contextual panel to its left */
.filter-dock{flex-direction:column-reverse!important;align-items:center!important;gap:0!important;width:56px!important;min-width:56px!important;max-width:56px!important;padding:5px!important;background:var(--bg)!important;border:1px solid var(--line)!important;box-shadow:0 4px 18px #0002!important;border-radius:20px!important}
.filter-dock-toggle{flex:0 0 44px!important;width:44px!important;height:44px!important;min-width:44px!important;border:0!important;box-shadow:none!important;background:transparent!important}
.filter-dock .filter-toolbar{position:static!important;display:none!important;flex-direction:column!important;flex-wrap:nowrap!important;align-items:stretch!important;justify-content:flex-start!important;width:100%!important;max-width:100%!important;padding:4px 2px 9px!important;margin:0!important;border:0!important;box-shadow:none!important;background:transparent!important}
.filter-dock.expanded .filter-toolbar{display:flex!important;flex-direction:column!important}
.filter-dock .filter-toolbar button{width:100%!important}
@media(max-width:720px){.filter-dock .filter-toolbar button span{display:none!important}}
/* Selected filter appears in its own overlay beside the vertical dock */
.filter-section.filter-expanded{position:fixed!important;z-index:31!important;right:calc(15px + 56px + 12px)!important;bottom:18px!important;left:auto!important;top:auto!important;width:min(390px,calc(100vw - 105px))!important;max-height:min(75vh,650px)!important;overflow-y:auto!important;box-sizing:border-box!important;display:block!important;padding:16px!important;margin:0!important;border:1px solid var(--line)!important;border-radius:18px!important;background:var(--bg)!important;box-shadow:0 8px 30px #0002!important}
@media(min-width:721px){.filter-section.filter-expanded{right:calc(24px + 56px + 12px)!important;bottom:18px!important}}
/* Position is calculated from the clicked icon; keep panels compact. */
.filter-section.filter-expanded{max-height:calc(100vh - 24px)!important}
#newsStats:not([hidden]){position:fixed!important;z-index:31!important;width:min(390px,calc(100vw - 105px))!important;max-height:calc(100vh - 24px)!important;overflow:auto!important;box-sizing:border-box!important;margin:0!important;background:var(--bg)!important;box-shadow:0 8px 30px #0002!important}
/* Keep the dock truly narrow, with centered menu glyph and short labels. */
.filter-dock{width:76px!important;min-width:76px!important;max-width:76px!important;padding:5px!important}
.filter-dock .filter-toolbar{box-sizing:border-box!important;width:100%!important;max-width:100%!important;padding:4px 0 8px!important}
.filter-dock .filter-toolbar button{box-sizing:border-box!important;width:100%!important;min-width:0!important;padding:7px 1px!important;font-size:11px!important;overflow-wrap:anywhere!important;text-align:center!important}
.filter-dock .filter-toolbar button span{display:block!important;font-size:10px!important;white-space:nowrap!important}
.filter-dock-toggle{box-sizing:border-box!important;flex:0 0 44px!important;min-width:44px!important;width:44px!important;height:44px!important;margin:0 auto!important;padding:0!important;align-self:center!important;display:grid!important;place-items:center!important;border-radius:15px!important;font-size:23px!important;line-height:1!important}
.filter-dock .dock-language{max-width:100%!important}
.filter-dock .dock-language select{max-width:100%!important;width:100%!important;box-sizing:border-box!important;font-size:12px!important;padding:5px 2px!important}
@media(max-width:720px){.filter-dock{width:58px!important;min-width:58px!important;max-width:58px!important}.filter-dock .filter-toolbar button span{display:none!important}}
/* Mobile search icon and collapsible search field */
.mobile-search-toggle{display:none}
@media(max-width:720px){
.topbar-inner .mobile-search-toggle{display:grid;place-items:center;border:0;background:transparent;color:var(--text);width:40px;height:40px;padding:0}
.topbar-inner .search{display:none!important;position:absolute!important;top:60px!important;left:15px!important;right:15px!important;width:calc(100% - 30px)!important;z-index:15!important}
.topbar-inner .search.open{display:block!important}
}
.topbar .brand{font-size:22px!important;letter-spacing:.025em!important;font-weight:400!important}.topbar .brand strong{font-weight:400!important}
.site-footer{max-width:1100px;margin:32px auto 0;padding:22px 24px 35px;border-top:1px solid var(--line);display:flex;flex-wrap:wrap;gap:12px 22px;font-size:12px;color:var(--muted)}.site-footer a:hover{text-decoration:underline}
</style><link href="https://unpkg.com/maplibre-gl@5.7.1/dist/maplibre-gl.css" rel="stylesheet"><style>.globe-view{display:none;position:relative;height:min(72vh,680px);min-height:380px;border:1px solid var(--line);border-radius:14px;overflow:hidden;margin-top:16px}.globe-view.open{display:block}.globe-view #newsGlobe{width:100%;height:100%}.globe-info{position:absolute;left:12px;bottom:12px;background:var(--bg);padding:12px;border-radius:10px;max-width:320px;box-shadow:0 2px 12px #0002;font-size:13px}.globe-toggle{border:1px solid var(--line);background:var(--bg);color:var(--text);border-radius:20px;padding:6px 12px;white-space:nowrap}.globe-toggle.active{background:var(--hover);color:var(--accent)}.geo-link{font-size:12px;color:var(--accent);cursor:pointer;border:0;background:none;padding:3px 0}.trust-tools{display:flex;gap:12px;flex-wrap:wrap;align-items:center;margin-top:9px;font-size:12px;color:var(--muted)}.trust{border:1px solid var(--line);border-radius:8px;background:var(--bg);color:var(--text);padding:4px}.ifis-related a{color:var(--accent);text-decoration:underline;text-underline-offset:2px}.globe-marker{background:#0d9488;color:white;border:2px solid white;border-radius:50%;width:29px;height:29px;display:grid;place-items:center;font-size:11px;box-shadow:0 1px 7px #0005;cursor:pointer}.globe-marker .constructive-count{position:absolute;right:-10px;top:-10px;background:#177245;color:white;border:1px solid white;border-radius:10px;min-width:20px;height:19px;padding:0 3px;display:grid;place-items:center;font-size:10px;font-weight:700;line-height:1}.globe-source{background:#e59a35;color:#17212e;border:2px solid white;border-radius:50%;width:25px;height:25px;display:grid;place-items:center;font-size:13px;cursor:pointer;box-shadow:0 1px 7px #0005}.globe-legend{position:absolute;right:12px;top:12px;background:var(--bg);padding:8px 10px;border-radius:9px;font-size:12px;z-index:2}.globe-legend span{margin-left:9px}.globe-view .globe-legend{left:auto;right:12px;top:12px;width:max-content;max-width:min(265px,calc(100% - 24px));padding:12px 14px;display:flex;flex-direction:column;align-items:stretch;gap:8px;background:var(--bg);border:1px solid var(--line);border-radius:12px;box-shadow:0 3px 12px #0002;font-size:12px;line-height:1.35}
.globe-view .globe-legend-line{display:flex;align-items:center;gap:9px;margin:0;white-space:normal}
.globe-view .globe-legend-line input{margin:0 2px 0 0;flex-shrink:0}
.globe-view .globe-legend .legend-dot{width:11px;height:11px;border-radius:50%;flex-shrink:0;margin:0}
.globe-view .globe-legend .legend-arrow{font-size:17px;color:#177245;width:11px;line-height:1;flex-shrink:0;margin:0}
.globe-view .globe-legend-update{border-top:1px solid var(--line);padding-top:8px;color:var(--muted);font-size:11px}
@media(max-width:600px){.globe-view .globe-legend{right:8px;top:8px;padding:9px 10px;font-size:11px;max-width:210px}}
.globe-view details.globe-legend{display:block;width:max-content;max-width:min(265px,calc(100% - 24px));min-width:0}
.globe-view details.globe-legend:not([open]){width:auto;padding:9px 12px}
.globe-view .globe-legend-heading{cursor:pointer;display:list-item;font-weight:600;list-style:revert;list-style-position:inside;white-space:nowrap;user-select:none}
.globe-view .globe-legend-heading:focus-visible{outline:2px solid var(--accent);outline-offset:3px}
.globe-view details.globe-legend[open] .globe-legend-heading{margin-bottom:10px}
.globe-view details.globe-legend .globe-legend-line{margin-top:8px}
.globe-view .globe-info.has-selection{border:1px solid #0d9488;box-shadow:0 4px 20px #0004;z-index:3;max-height:calc(100% - 28px);overflow-y:auto}
.globe-view .globe-info .globe-story-link{display:block;padding:9px 0;border-top:1px solid var(--line);line-height:1.4}
.globe-view .globe-info .globe-story-link:first-of-type{margin-top:9px}
.globe-view .globe-info .globe-story-link small{display:block;color:var(--muted);font-size:11px;margin-top:3px}
.globe-view .globe-connector{position:absolute;inset:0;width:100%;height:100%;pointer-events:none;z-index:2;display:none;overflow:visible}
.globe-view .globe-connector path{fill:none;stroke:#0d9488;stroke-width:2.5;stroke-dasharray:6 5;filter:drop-shadow(0 1px 2px #fff)}
.globe-view .globe-connector circle{fill:#0d9488;stroke:white;stroke-width:2}
.news-duplicates{font-size:12px;color:var(--muted);margin:5px 0}.news-duplicates summary{cursor:pointer}.news-duplicates a{display:inline-block;margin:5px 12px 0 0;text-decoration:underline;text-underline-offset:2px}
.topnav [data-subtitle-de]{position:relative}
.topnav [data-subtitle-de]::after{content:attr(data-subtitle-de);position:absolute;left:50%;top:calc(100% + 9px);transform:translate(-50%,-5px);opacity:0;pointer-events:none;white-space:nowrap;background:#17212e;color:#fff;border-radius:9px;padding:8px 12px;font-size:12px;font-weight:400;line-height:1.4;box-shadow:0 5px 18px #0003;z-index:20;transition:opacity .15s,transform .15s}
.topnav [data-subtitle-de]:hover::after,.topnav [data-subtitle-de]:focus-visible::after{opacity:1;transform:translate(-50%,0)}
.topnav [data-subtitle-de].subtitle-dismissed::after{opacity:0!important;pointer-events:none!important}
html[lang="en"] .topnav [data-subtitle-de]::after{content:attr(data-subtitle-en)}
@media(max-width:720px){.topnav [data-subtitle-de]::after{white-space:normal;min-width:155px;max-width:210px;text-align:center}.topnav [data-subtitle-de]:first-child::after{left:0;transform:translate(0,-5px)}.topnav [data-subtitle-de]:first-child:hover::after,.topnav [data-subtitle-de]:first-child:focus-visible::after{transform:translate(0,0)}}
.globe-source-batch{border:2px solid white;border-radius:50%;width:31px;height:31px;display:grid;place-items:center;color:#17212e;font-size:12px;font-weight:700;box-shadow:0 2px 9px #0005;cursor:pointer}
.globe-legend #mapTimeRange{max-width:190px;min-width:0;border:1px solid var(--line);border-radius:7px;background:var(--bg);color:var(--text);padding:5px;font-size:12px}
.globe-legend #mapTimeRange{width:135px;accent-color:#e59a35;vertical-align:middle}.globe-legend #mapTimeValue{min-width:42px;display:inline-block;font-size:12px;font-variant-numeric:tabular-nums}
.globe-source-cluster{display:flex;gap:5px;align-items:center;justify-content:center;position:relative;white-space:nowrap;transform:translateY(-6px);pointer-events:auto}
.globe-source-cluster::after{content:'';position:absolute;left:50%;bottom:-7px;width:7px;height:7px;background:#e59a35;border:1px solid white;border-radius:50%;transform:translateX(-50%);pointer-events:none}
.map-projection-toggle{background:var(--bg);color:var(--text);border:1px solid var(--line);border-radius:7px;padding:5px 8px;font-size:12px;cursor:pointer;text-align:left}
.globe-view .globe-legend{max-width:min(320px,calc(100% - 24px))}
.globe-view .globe-legend-line:has(#mapTimeRange){display:grid;grid-template-columns:auto minmax(100px,1fr) auto;align-items:center;gap:9px;white-space:nowrap}
.globe-legend #mapTimeRange{width:100%;max-width:none;min-width:100px;box-sizing:border-box}
.globe-legend #mapTimeValue{min-width:45px;text-align:right}
.globe-view .globe-legend-heading{display:flex;align-items:center;justify-content:space-between;gap:14px}
.globe-view .globe-legend-heading::before{content:'▾';font-size:12px}
.globe-view details:not([open])>.globe-legend-heading::before{content:'▸'}
.globe-view .globe-legend-heading{list-style:none}
.globe-view .globe-legend-heading::-webkit-details-marker{display:none}
.globe-view .map-projection-toggle{padding:4px 8px;line-height:1;font-size:20px;min-width:35px;text-align:center;margin-left:auto}
.globe-view .globe-legend-line:has(#mapTimeRange){grid-template-columns:auto 110px auto;justify-content:start;gap:8px}
.globe-legend #mapTimeRange{width:110px;min-width:0;max-width:110px}
.globe-event-jump{display:block;width:100%;border:0;background:transparent;color:inherit;text-align:left;cursor:pointer;padding:8px 4px;font:inherit}
.globe-event-jump:hover{text-decoration:underline}
.globe-view .globe-legend-line:has(#mapTimeRange){display:flex;align-items:center;justify-content:flex-start;gap:9px;flex-wrap:nowrap}
.globe-view .globe-legend-line #mapTimeRange{flex:0 0 82px;width:82px;min-width:82px;max-width:82px;margin:0}
.globe-view .globe-legend-line #mapTimeValue{flex:0 0 auto;min-width:42px;text-align:right;margin-left:0}
.globe-legend #mapColorMode{background:var(--bg);color:var(--text);border:1px solid var(--line);border-radius:7px;font-size:12px;padding:4px;max-width:185px}

/* Mobile floating filter menu: larger, readable action icons. */
@media(max-width:720px){
 .filter-dock{max-width:82px}
 .filter-dock .filter-toolbar button{width:72px;min-height:60px;font-size:30px;line-height:1.1;padding:9px 4px;justify-content:center}
 .filter-dock .filter-toolbar button span{font-size:11px;line-height:1.25}
 .filter-dock .filter-dock-toggle{width:60px;height:56px;font-size:31px}
 .filter-dock .dock-language{justify-content:center;padding:8px 3px}
 .filter-dock .dock-language select{font-size:16px!important;max-width:72px;padding:5px!important}
}
.globe-view{isolation:isolate;overflow:hidden}
.globe-view .maplibregl-marker{z-index:1!important}
.globe-view .globe-legend{z-index:8}
.globe-view .globe-info{z-index:7}
.globe-view .globe-source-cluster{transform-origin:center center}
.globe-view .globe-source-cluster::after{display:none}
.topbar,.topbar-inner,.topnav{z-index:30}
.globe-view #newsGlobe{background:#142b3a}
.globe-view .globe-connector{pointer-events:none;z-index:2}
.globe-view .globe-connector g path{stroke:#e59a35;stroke-dasharray:none;filter:none;vector-effect:non-scaling-stroke}
.globe-view .globe-marker{width:calc(29px * var(--marker-scale,1));height:calc(29px * var(--marker-scale,1));font-size:calc(11px * var(--marker-scale,1))}
.globe-view .globe-source{width:calc(25px * var(--marker-scale,1));height:calc(25px * var(--marker-scale,1));font-size:calc(13px * var(--marker-scale,1))}
.globe-view .globe-source-batch{width:calc(31px * var(--marker-scale,1));height:calc(31px * var(--marker-scale,1));font-size:calc(12px * var(--marker-scale,1))}
.globe-view .globe-info-toggle{position:absolute;left:12px;bottom:12px;z-index:9;border:1px solid var(--line);background:var(--bg);color:var(--text);border-radius:8px;padding:6px 10px;cursor:pointer;font-size:12px;box-shadow:0 2px 8px #0002}
.globe-view .globe-info{bottom:49px;max-height:min(55vh,370px);overflow-y:auto;z-index:8}
.globe-view .globe-info.is-collapsed{display:none}

/* Article lists attach beneath their map marker, independent of age/color. */
.globe-view .maplibregl-popup.marker-article-popup{z-index:12}
.globe-view .marker-article-popup .maplibregl-popup-content{background:var(--bg);color:var(--text);border:1px solid var(--line);border-radius:12px;padding:12px;box-shadow:0 6px 24px #0005}
.globe-view .marker-article-overlay{width:min(320px,76vw)}
.globe-view .marker-article-overlay summary{font-weight:700;cursor:pointer;padding:3px 24px 8px 0;line-height:1.4}
.globe-view .marker-article-list{max-height:min(34vh,250px);overflow-y:auto;overscroll-behavior:contain}
.globe-view .marker-article-list .globe-story-link{display:block;padding:8px 3px;border-top:1px solid var(--line)}
.globe-view .marker-article-popup .maplibregl-popup-close-button{font-size:21px;color:var(--text);padding:2px 7px}

/* Compact, grouped map controls. Overrides historical legend rules. */
.globe-view details.globe-legend{
 display:block;position:absolute;right:12px;top:12px;left:auto;
 width:min(292px,calc(100% - 24px));max-width:calc(100% - 24px);
 margin:0;padding:0;border:1px solid var(--line);border-radius:12px;
 background:var(--bg);color:var(--text);box-shadow:0 4px 20px #0003;
 font-size:13px;line-height:1.4;z-index:8;overflow:hidden
}
.globe-view details.globe-legend:not([open]){width:auto;max-width:calc(100% - 24px);padding:0}
.globe-view .globe-legend-heading{
 display:flex;align-items:center;justify-content:space-between;gap:16px;
 list-style:none;white-space:nowrap;margin:0!important;
 cursor:pointer;padding:10px 13px;font-weight:650;font-size:13px;
}
.globe-view .globe-legend-heading::-webkit-details-marker{display:none}
.globe-view .globe-legend-heading::before{content:none!important}
.globe-view .globe-legend-heading::after{content:'⌄';font-size:18px;font-weight:400;line-height:1;opacity:.75}
.globe-view details.globe-legend[open] .globe-legend-heading::after{transform:rotate(180deg)}
.globe-view details.globe-legend[open] .globe-legend-heading{border-bottom:1px solid var(--line)}
.globe-view .map-legend-content{padding:0 13px 10px}
.globe-view .map-legend-section{padding:11px 0}
.globe-view .map-legend-section + .map-legend-section{border-top:1px solid var(--line)}
.globe-view .map-legend-section-title{
 display:flex;align-items:center;justify-content:space-between;
 font-size:11px;font-weight:750;letter-spacing:.035em;color:var(--muted);
 text-transform:uppercase;margin-bottom:8px
}
.globe-view .map-legend-section-title output{
 color:var(--text);font-weight:700;font-size:13px;letter-spacing:0;text-transform:none
}
.globe-view #mapTimeRange{
 display:block;width:100%;min-width:0;max-width:none;
 margin:5px 0 0;accent-color:#e59a35
}
.globe-view .map-range-labels{
 display:flex;justify-content:space-between;color:var(--muted);font-size:10px;margin-top:2px
}
.globe-view .map-legend-check{
 display:flex;align-items:center;gap:9px;min-height:34px;cursor:pointer;
 margin:0;white-space:normal
}
.globe-view .map-legend-check input{margin:0;width:17px;height:17px;accent-color:var(--accent);flex-shrink:0}
.globe-view .map-legend-swatch{width:14px;height:14px;flex:none;display:inline-block}
.globe-view .map-legend-event{background:#0d9488;border-radius:50%}
.globe-view .map-legend-source{background:#e59a35;border-radius:50%}
.globe-view .map-legend-connection{height:0;border-top:2px dashed #e59a35}
.globe-view .map-legend-night{background:linear-gradient(90deg,#f1c65b 50%,#293b60 50%);border-radius:50%}
.globe-view .map-legend-select,.globe-view .map-legend-projection{
 display:flex;align-items:center;justify-content:space-between;gap:9px;
 min-height:36px;margin:0
}
.globe-view .map-legend-select select{
 min-width:0;max-width:66%;width:auto;
 color:var(--text);background:var(--bg);border:1px solid var(--line);
 border-radius:7px;padding:5px 8px;font:inherit;font-size:12px
}
.globe-view .map-legend-projection button{
 min-width:36px;width:36px;height:33px;padding:4px;
 border:1px solid var(--line);border-radius:7px;background:var(--bg);color:var(--text)
}
.globe-view .globe-legend-update{
 font-size:10px;line-height:1.45;padding:10px 0 0;
 margin:0;border-top:1px solid var(--line);color:var(--muted)
}
@media(max-width:600px){
 .globe-view details.globe-legend{right:8px;top:8px;width:min(270px,calc(100% - 16px));max-width:calc(100% - 16px)}
 .globe-view details.globe-legend:not([open]){width:auto;max-width:calc(100% - 16px)}
 .globe-view .globe-legend-heading{padding:9px 12px;font-size:12px}
 .globe-view .map-legend-content{padding:0 12px 10px}
}


/* The icon glyph is independent of the small caption and old font-size rules. */
.filter-dock .filter-toolbar button .dock-symbol{
 display:block!important;font-size:29px!important;font-weight:500!important;
 line-height:1.15!important;min-height:33px;letter-spacing:0!important;
}
.filter-dock .filter-toolbar button > span:not(.dock-symbol){
 display:block!important;font-size:11px!important;line-height:1.25!important;
 white-space:nowrap!important;
}
.filter-dock .filter-toolbar button{
 display:flex!important;flex-direction:column!important;align-items:center!important;
 justify-content:center!important;min-height:63px!important;gap:1px!important;
}
@media(max-width:720px){
 .filter-dock .filter-toolbar button .dock-symbol{font-size:31px!important;min-height:36px}
}

/* Base-map mode also styles map controls and article popups in dark mode. */
.globe-view.map-dark{--bg:#161e29;--text:#ecf1f7;--muted:#aab9ca;--line:#344358;--hover:#27354a;color:var(--text)}
.globe-view.map-dark #newsGlobe{background:#111720}
.globe-view.map-dark #mapLegend,.globe-view.map-dark .marker-article-popup .maplibregl-popup-content{background:var(--bg);color:var(--text);border-color:var(--line)}
.globe-view.map-dark .map-legend-select select,.globe-view.map-dark .map-legend-projection button{color:var(--text);background:var(--bg);border-color:var(--line)}
.globe-view.map-dark .maplibregl-popup-tip{border-top-color:var(--bg)}
.globe-view .map-legend-content{max-height:min(75vh,680px);overflow-y:auto;overscroll-behavior:contain}
.globe-view .map-legend-section-title{margin-bottom:7px}
@media(max-width:600px){.globe-view .map-legend-content{max-height:min(65vh,540px)}}

.info-pages-panel{position:fixed!important;right:calc(18px + 76px + 12px);bottom:18px;z-index:31!important;width:min(280px,calc(100vw - 115px));max-height:70vh;overflow:auto;padding:8px!important;border:1px solid var(--line);border-radius:16px;background:var(--bg);color:var(--text);box-shadow:0 8px 30px #0003}
.info-pages-panel[hidden]{display:none!important}
.info-pages-panel a{display:block;padding:11px 13px;border-radius:9px;font-size:14px;line-height:1.3}
.info-pages-panel a:hover,.info-pages-panel a:focus-visible{background:var(--hover);color:var(--accent)}
@media(max-width:720px){.info-pages-panel{right:calc(14px + 58px + 12px);bottom:calc(14px + env(safe-area-inset-bottom,0px))}}
.story{scroll-margin-top:115px}

/* The selected background concerns the space around the globe, not the map tiles. */
.globe-view.map-light #newsGlobe,.globe-view.map-light .maplibregl-canvas-container{background:#fff!important}
.globe-view.map-dark #newsGlobe,.globe-view.map-dark .maplibregl-canvas-container{background:#111720!important}
.feedhead #feedTitle.feed-title-saved{font-size:27px!important;line-height:1.2!important;letter-spacing:0!important}


/* Flight connections run underneath marker circles and their readable counts. */
.globe-view #newsGlobe>.globe-connector{position:absolute;inset:0;z-index:2!important;pointer-events:none}
.globe-view #newsGlobe>.maplibregl-marker{z-index:4!important}
.globe-view #newsGlobe>.maplibregl-popup{z-index:8!important}
/* One seamless selection underline: active section alone, or section + map. */
.topbar-inner .topnav{position:relative!important;--selection-left:0px;--selection-width:0px}
.topbar-inner .topnav::after{
 content:"";position:absolute;bottom:0;left:var(--selection-left);
 width:var(--selection-width);height:3px;background:var(--accent);
 border-radius:0;pointer-events:none;z-index:1;
 transition:left .18s ease,width .18s ease
}
.topbar-inner .topnav a.active,
.topbar-inner .topnav .globe-toggle.active{
 border-bottom-color:transparent!important;
}
/* On hover show compact location details, not the browser's slow native title. */
.map-marker-hover{position:fixed;z-index:1000;pointer-events:none;width:max-content;max-width:min(275px,calc(100vw - 20px));padding:10px 12px;background:var(--bg,#fff);color:var(--text,#17212e);border:1px solid var(--line,#ccd3dc);border-radius:10px;box-shadow:0 5px 24px #0004;font-size:12px;line-height:1.4;display:flex;flex-direction:column;gap:3px}
.map-marker-hover[hidden]{display:none!important}
.map-marker-hover strong{font-size:14px;line-height:1.25;color:inherit}
.map-marker-hover-type{font-size:10px;letter-spacing:.05em;text-transform:uppercase;font-weight:700;color:var(--accent,#2468d5)}
.map-marker-hover small{font-size:11px;color:var(--muted,#697587);max-width:240px}
@media(prefers-color-scheme:dark){.map-marker-hover{--bg:#15191f;--text:#e9edf3;--muted:#a0acba;--line:#313945;--accent:#8ab4ff}}

/* Globe dots stay at their geographic positions; numbered icons remain on the flat map. */
.globe-view.is-globe .globe-marker,.globe-view.is-globe .globe-source{
 width:10px!important;height:10px!important;min-width:10px!important;min-height:10px!important;
 padding:0!important;border:1.5px solid #fff!important;border-radius:50%!important;
 font-size:0!important;line-height:0!important;box-shadow:0 0 0 1px #0003!important;
 overflow:visible!important;cursor:pointer
}
.globe-view.is-globe .globe-marker::after,.globe-view.is-globe .globe-source::after{
 content:'';position:absolute;width:24px;height:24px;left:50%;top:50%;
 transform:translate(-50%,-50%);border-radius:50%;background:transparent
}
.globe-view.is-globe .globe-marker:hover,.globe-view.is-globe .globe-source:hover,
.globe-view.is-globe .globe-marker:focus-visible,.globe-view.is-globe .globe-source:focus-visible{
 box-shadow:0 0 0 3px #ffffffa8,0 0 0 5px #2468d5a8!important
}

/* Readable editorial hierarchy inside marker article overlays. */
.globe-view .marker-article-list .globe-story-link{
 display:flex;flex-direction:column;align-items:flex-start;gap:0;
 padding:14px 3px 15px;border-top:1px solid var(--line);text-decoration:none
}
.globe-view .marker-article-list .globe-story-category{
 display:inline-block;margin-bottom:6px;font-size:10px;line-height:1.35;
 color:var(--accent);font-weight:700;text-transform:uppercase;letter-spacing:.04em
}
.globe-view .marker-article-list .globe-story-title{
 display:block;font-size:14px;line-height:1.48;font-weight:570;overflow-wrap:anywhere
}
.globe-view .marker-article-list .globe-story-meta{
 display:flex;flex-wrap:wrap;align-items:center;gap:4px 10px;
 margin-top:10px;font-size:11px;line-height:1.4;color:var(--muted)
}
.globe-view .marker-article-list .globe-story-source{font-weight:650;color:var(--text)}
.globe-view .marker-article-list .globe-story-places{
 display:flex;flex-wrap:wrap;gap:5px 12px;margin-top:7px;
 font-size:11px;line-height:1.45;color:var(--muted)
}
.globe-view .marker-article-list .globe-story-places span{white-space:normal}

/* Keep the three main tabs in the same mobile header row as the brand. */
@media(max-width:720px){
 .topbar{height:60px!important;min-height:60px!important;padding-top:0!important;padding-bottom:0!important}
 .topbar .topbar-inner{display:flex!important;flex-wrap:nowrap!important;align-items:center!important;justify-content:space-between!important;height:60px!important;min-height:60px!important;padding:0 11px!important;gap:4px!important}
 .topbar .brand{align-self:center!important;flex:0 0 auto!important;margin:0 5px 0 0!important;line-height:1.2!important}
 .topbar-inner .topnav{position:relative!important;top:auto!important;left:auto!important;right:auto!important;align-self:stretch!important;display:flex!important;flex:0 1 auto!important;min-width:0!important;height:60px!important;margin:0!important;padding:0!important;gap:0!important;align-items:stretch!important;justify-content:center!important;border-bottom:0!important}
 .topbar-inner .topnav a,.topbar-inner .topnav .globe-toggle{display:flex!important;align-items:center!important;justify-content:center!important;align-self:stretch!important;height:60px!important;min-height:60px!important;margin:0!important;padding:0 7px!important;font-size:13px!important;line-height:1.2!important;white-space:nowrap!important}
 .topbar-inner .top-actions{flex:0 0 auto!important;align-self:center!important;margin:0!important}
 .topbar-inner .grid-toggle{flex:0 0 auto!important;align-self:center!important;margin:0!important;padding:4px!important}
 .topbar-inner .mobile-search-toggle{width:30px!important;height:36px!important}
}
@media(max-width:395px){
 .topbar .topbar-inner{padding:0 6px!important;gap:2px!important}
 .topbar .brand{margin-right:2px!important;font-size:19px!important}
 .topbar-inner .topnav a,.topbar-inner .topnav .globe-toggle{padding:0 4px!important;font-size:12px!important}
 .topbar-inner .grid-toggle{padding:2px!important}
 .topbar-inner .mobile-search-toggle{width:25px!important}
}

/* Two clear, accessible geographic markers immediately after each card's map link. */
.story-location-flags{display:inline-flex;vertical-align:middle;align-items:center;gap:5px;
 margin-left:7px;font-size:15px;line-height:1.15;white-space:nowrap}
.story-location-flag{display:inline-flex;min-width:18px;align-items:center;justify-content:center;
 cursor:help;font-family:system-ui,-apple-system,sans-serif}
@media(max-width:720px){
 .story-location-flags{gap:3px;margin-left:5px;font-size:14px}
 /* Keep the selected point free and constrain popups to the map/visible viewport. */
 .globe-view .maplibregl-popup.marker-article-popup{
  max-width:calc(100vw - 24px)!important;z-index:18!important
 }
 .globe-view .marker-article-popup .maplibregl-popup-content{
  max-height:min(57dvh,390px)!important;overflow:hidden!important;
  padding:9px 10px!important
 }
 .globe-view .marker-article-overlay{width:min(300px,calc(100vw - 52px))!important;
  max-height:calc(min(57dvh,390px) - 20px);display:flex;flex-direction:column}
 .globe-view .marker-article-overlay details{min-height:0;display:flex;flex-direction:column}
 .globe-view .marker-article-list{max-height:min(43dvh,270px)!important;overflow-y:auto!important;
  overscroll-behavior:contain;-webkit-overflow-scrolling:touch}
 .globe-view .marker-article-overlay summary{font-size:13px;padding-right:28px}
}

.globe-view .marker-collection-share{display:flex;flex-direction:column;gap:4px;margin:8px 0 10px}
.globe-view .marker-collection-share-actions{display:grid;grid-template-columns:repeat(2,minmax(0,1fr));gap:7px}
.globe-view .marker-collection-share-actions button{min-width:0;padding:9px 5px;font-size:11px;white-space:nowrap}
.globe-view .marker-collection-share-actions button:disabled{opacity:.55;cursor:wait}
.globe-view .collection-share-button{border:1px solid var(--line);border-radius:9px;background:var(--soft);color:var(--text);font-size:12px;font-weight:650;padding:9px 12px;text-align:center;cursor:pointer}
.globe-view .collection-share-button:hover,.globe-view .collection-share-button:focus-visible{border-color:var(--accent);color:var(--accent)}
.globe-view .marker-collection-share small{font-size:11px;overflow-wrap:anywhere;color:var(--muted)}

/* Keep the selected location and its complete popup together in all projections. */
.globe-view .maplibregl-popup.marker-article-popup{max-width:calc(100% - 24px);z-index:18}
.globe-view .marker-article-popup .maplibregl-popup-content{overflow:auto;overscroll-behavior:contain}
.globe-view .marker-article-overlay{display:flex;flex-direction:column;min-height:0}
.globe-view .marker-article-overlay details{min-height:0}
.globe-view .marker-article-overlay .marker-article-list{overflow-y:auto;overscroll-behavior:contain}

/* Compact map settings: shorter rows, restrained spacing and viewport-safe scrolling.
   Keep native controls and full tap targets rather than hiding options. */
.globe-view details.globe-legend{
 width:min(276px,calc(100% - 20px));
 max-width:calc(100% - 20px);
 max-height:min(80dvh,640px);
 overflow:hidden;
 display:flex;
 flex-direction:column;
}
.globe-view details.globe-legend:not([open]){display:block;width:auto;max-height:none;overflow:visible}
.globe-view details.globe-legend[open] .globe-legend-heading{
 flex:0 0 auto;
 min-height:42px;
 padding:8px 12px;
 font-size:14px;
}
.globe-view details.globe-legend[open] .map-legend-content{
 overflow-y:auto;
 overscroll-behavior:contain;
 scrollbar-width:thin;
 min-height:0;
 padding:0 12px 7px;
}
.globe-view .map-legend-section{padding:8px 0}
.globe-view .map-legend-section + .map-legend-section{border-top:1px solid var(--line)}
.globe-view .map-legend-section-title{
 margin:0 0 4px;
 font-size:10px;
 letter-spacing:.045em;
}
.globe-view .map-legend-section-title output{font-size:12px}
.globe-view #mapTimeRange{margin:2px 0 0}
.globe-view .map-range-labels{font-size:10px;margin-top:0}
.globe-view .map-legend-check{
 min-height:29px;
 gap:7px;
 font-size:12px;
 line-height:1.2;
}
.globe-view .map-legend-check input{height:16px;width:16px}
.globe-view .map-legend-swatch{width:11px;height:11px}
.globe-view .map-legend-select,.globe-view .map-legend-projection{
 min-height:32px;
 margin:0;
 gap:8px;
 font-size:12px;
}
.globe-view .map-legend-select select{
 max-width:65%;
 padding:4px 7px;
 font-size:12px;
 min-height:27px;
}
.globe-view .map-legend-projection button{height:28px;min-width:32px;width:32px}
.globe-view .globe-legend-update{font-size:10px;padding:7px 0 1px}
@media(max-width:720px){
 .globe-view details.globe-legend{
  right:8px;top:8px;
  width:min(258px,calc(100% - 16px));
  max-width:calc(100% - 16px);
  max-height:min(74dvh,550px);
 }
 .globe-view details.globe-legend[open] .globe-legend-heading{
  min-height:39px;
  padding:7px 11px;
  font-size:13px
 }
 .globe-view details.globe-legend[open] .map-legend-content{padding:0 11px 6px}
 .globe-view .map-legend-section{padding:7px 0}
 .globe-view .map-legend-check{min-height:29px}
}
@media(max-height:600px){
 .globe-view details.globe-legend{max-height:calc(100dvh - 90px)}
}
/* The icon popup and the filtered feed below the map replace the redundant bottom-left map list. */
.globe-view #globeInfo{display:none!important}
</style></head><body>
<header class="topbar"><div class="topbar-inner"><a class="brand" href="?view=world"><strong>IFIS</strong></a><nav class="topnav" aria-label="Bereiche"><a href="?view=ifis&amp;map=0" class="<?= $view==='ifis'?'active':'' ?>" data-subtitle-de="Nachrichten mit Veränderungspotenzial" data-subtitle-en="News with potential for change">Potenzial</a><button class="globe-toggle" id="globeToggle" type="button" aria-pressed="false" data-subtitle-de="Nachrichten geografisch entdecken" data-subtitle-en="Explore news geographically">Karte</button><a href="?view=world&amp;map=0" class="<?= $view==='world'?'active':'' ?>" data-subtitle-de="Nachrichten aus aller Welt" data-subtitle-en="News from around the world">Weltlage</a></nav><button type="button" id="gridToggle" class="grid-toggle" title="Spaltenzahl ändern" aria-label="Spaltenzahl ändern"><svg class="grid-toggle-symbol" viewBox="0 0 24 24" width="22" height="22" fill="none" stroke="currentColor" stroke-width="1.6" aria-hidden="true"><rect x="2.5" y="2.5" width="8" height="8" rx="1.3"/><rect x="13.5" y="2.5" width="8" height="8" rx="1.3"/><rect x="2.5" y="13.5" width="8" height="8" rx="1.3"/><rect x="13.5" y="13.5" width="8" height="8" rx="1.3"/></svg><span id="gridCount">1</span></button><div class="top-actions"><button type="button" id="mobileSearchToggle" class="mobile-search-toggle" aria-label="Suche öffnen" aria-expanded="false" title="Nachrichten suchen"><svg viewBox="0 0 24 24" width="21" height="21" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" aria-hidden="true"><circle cx="10.8" cy="10.8" r="6.8"/><path d="m16 16 5 5"/></svg></button><input class="search" id="search" type="search" placeholder="Nachrichten durchsuchen …" aria-label="Nachrichten durchsuchen"></div></div></header>
<div class="layout">

<main id="main"><div class="feedhead"><h1 id="feedTitle"><?= $view==='ifis'?'Potenzial':'Weltlage' ?></h1></div>
<div class="globe-view" id="globeView"><div id="newsGlobe"></div><svg id="globeConnector" class="globe-connector" aria-hidden="true"><path id="globeConnectorPath"></path><circle id="globeConnectorEnd" r="4"></circle></svg><details class="globe-legend" id="mapLegend"><summary class="globe-legend-heading">Karte einstellen</summary>
<div class="map-legend-content">
 <section class="map-legend-section" aria-label="Zeitraum">
  <div class="map-legend-section-title">Zeitraum <output id="mapTimeValue" for="mapTimeRange">24 h</output></div>
  <input id="mapTimeRange" type="range" min="0.5" max="48" step="0.5" value="24" aria-label="Nachrichten der letzten Stunden">
  <div class="map-range-labels"><span>30 Min.</span><span>48 Std.</span></div>
 </section>
 <section class="map-legend-section" aria-label="Kartenelemente">
  <div class="map-legend-section-title">Anzeigen</div>
  <label class="map-legend-check"><input id="showEventMarkers" type="checkbox" checked><span class="map-legend-swatch map-legend-event" aria-hidden="true"></span><span>Ereignisorte</span></label>
  <label class="map-legend-check"><input id="showSourceMarkers" type="checkbox" checked><span class="map-legend-swatch map-legend-source" aria-hidden="true"></span><span>Quellenorte</span></label>
  <label class="map-legend-check"><input id="showSourceLinks" type="checkbox" checked><span class="map-legend-swatch map-legend-connection" aria-hidden="true"></span><span>Verbindungslinien</span></label>
  <label class="map-legend-check"><input id="nightToggle" type="checkbox"><span class="map-legend-swatch map-legend-night" aria-hidden="true"></span><span>Tag/Nacht</span></label>
 </section>
 <section class="map-legend-section" aria-label="Darstellung">
  <div class="map-legend-section-title">Darstellung</div>
  <label class="map-legend-select">Kartentyp <select id="mapBasemap" aria-label="Kartentyp"><option value="geographic">Geografisch</option><option value="political">Politisch</option><option value="satellite">Satellit</option></select></label>
  <label class="map-legend-select">Hintergrund <select id="mapBrightness" aria-label="Kartenhintergrund"><option value="auto">System</option><option value="light">Weiß</option><option value="dark">Dunkel</option></select></label>
  <label class="map-legend-select">Globus dreht <select id="mapAutoRotate" aria-label="Geschwindigkeit der Globusrotation"><option value="0">Aus</option><option value="1.5">Langsam</option><option value="4">Normal</option><option value="9">Schnell</option></select></label>
  <label class="map-legend-select">Hervorheben <select id="mapFocusMode" aria-label="Kartenobjekte hervorheben"><option value="event">Ereignisorte</option><option value="source">Quellenorte</option></select></label>
  <label class="map-legend-select">Farbmodus <select id="mapColorMode" aria-label="Farbmodus der Karte"><option value="age">Veröffentlichungszeit</option><option value="unseen">Noch nicht gesehen</option></select></label>
  <div class="map-legend-projection"><span>Kartenform</span><button id="mapProjectionToggle" type="button" class="map-projection-toggle" title="Flache Karte anzeigen" aria-label="Flache Karte anzeigen" aria-pressed="false">▤</button></div>
 </section>
 <div class="globe-legend-update"><?=esc($lastImportLabel)?></div>
</div></details><div class="globe-info" id="globeInfo">Nachrichten geografisch entdecken. Punkte zeigen erkannte Ereignisorte, nicht den Sitz der Redaktion. Die Zuordnung ist vorläufig.</div></div><div class="filter-dock" id="filterDock"><button class="filter-dock-toggle" id="filterDockToggle" type="button" aria-label="Filterleiste ein- oder ausklappen" aria-expanded="false">☷</button><div class="filter-toolbar" aria-label="Nachrichtenfilter"><button type="button" data-open-filter="direction" aria-expanded="false" title="Entwicklungsrichtung"><span class="dock-symbol" aria-hidden="true">↗</span><span>Richtung</span></button><button type="button" data-open-filter="topics" aria-expanded="false" title="Themen"><span class="dock-symbol" aria-hidden="true">▦</span><span>Themen</span></button><button type="button" data-open-filter="quality" aria-expanded="false" title="Tonlage und Quellen"><span class="dock-symbol" aria-hidden="true">⚑</span><span>Qualität</span></button><button type="button" id="statsToggle" title="Nachrichtenbilanz" aria-expanded="false"><span class="dock-symbol" aria-hidden="true">▥</span><span>Bilanz</span></button><button type="button" data-open-filter="saved" title="Gemerkt"><span class="dock-symbol" aria-hidden="true">☆</span><span>Gemerkt</span></button><button type="button" data-open-filter="hidden" title="Ausgeblendet"><span class="dock-symbol" aria-hidden="true">⊘</span><span>Verborgen</span></button><button type="button" id="infoPagesToggle" title="Infoseiten" aria-expanded="false" aria-controls="infoPagesPanel"><span class="dock-symbol" aria-hidden="true">☷</span><span>Seiten</span></button><label class="dock-language" title="Sprache auswählen"><select id="language" aria-label="Sprache auswählen" title="Sprache" style="border:1px solid var(--line);border-radius:16px;background:var(--bg);color:var(--text);padding:6px"><option value="de">DE</option><option value="en">EN</option></select></label></div></div><nav id="infoPagesPanel" class="info-pages-panel" aria-label="Informationsseiten" hidden>
<a href="info.php?page=about">Über IFIS News</a><a href="info.php?page=method">Quellen &amp; Methodik</a>
<a href="info.php?page=imprint">Impressum</a><a href="info.php?page=privacy">Datenschutz</a><a href="https://ifis.net/">IFIS.net ↗</a>
</nav><section id="newsStats" class="news-stats" hidden><div class="news-stats-head"><strong>Nachrichtenbilanz</strong><button type="button" id="statsClose" aria-label="Schließen">×</button></div><p id="statsScope"></p><div id="statsContent"></div><p class="stats-disclaimer">Automatische Stichwort-Schätzungen aus Titel und Kurztext. Clickbait-Verdacht ist kein Qualitätsurteil; Tonlage und Entwicklungsrichtung sind unterschiedliche Dimensionen. „Nicht bestimmbar“ wird mitgezählt.</p></section><div class="direction-nav filter-section" data-filter-section="direction"><span class="direction-label">Planetare Entwicklungsrichtung <span title="Vorläufige Zuordnung aus Titel und Kurztext; keine Wirkungsanalyse">ⓘ</span></span><div class="direction-buttons"><button data-direction="all" class="active">Alle</button><button data-direction="constructive">↗ Konstruktiv</button><button data-direction="harmful">↘ Problematisch</button><button data-direction="mixed">↔ Ambivalent</button><button data-direction="unknown">? Offen</button></div></div><div class="chips filter-section" data-filter-section="topics" id="chips" aria-label="Themenfilter"><button class="chip active" data-cat="Alle">Alle</button><?php foreach(['Politik','Gesellschaft','Wirtschaft','Technologie','Umwelt','Gesundheit','Bildung','Wissenschaft','Weitere'] as $c): ?><button class="chip" data-cat="<?=esc($c)?>"><?=esc($c)?></button><?php endforeach; ?></div>
<details class="refine filter-section" data-filter-section="quality"><summary>Filter & Quellen <span class="muted">· optional</span></summary><div class="source-controls"><button id="sourcesToggle" type="button">⚙ Quellen auswählen</button><div id="sourcePanel" hidden><div id="sourceList"></div><button id="sourcesAll" type="button">Alle</button><button id="sourcesNone" type="button">Keine</button><p class="subtle">Auswahl wird nur in diesem Browser gespeichert. Eigene RSS-Adressen folgen mit einer sicheren serverseitigen Importfunktion.</p></div></div><div class="quality-controls"><label>Tonlage <select id="toneFilter"><option value="all">Alle</option><option value="positive">Hoffnungsvoll</option><option value="balanced">Sachlich / gemischt</option><option value="negative">Belastend</option><option value="unknown">Unbestimmt</option></select></label><label><input type="checkbox" id="filterClickbait" checked> Clickbait-Verdacht ausblenden</label><button id="showFiltered" type="button">Ausgefilterte anzeigen</button></div><p class="subtle">Tonlage beschreibt die Wortwahl, nicht den Wert einer Nachricht. Entwicklungsrichtung und Gestaltbarkeit sind davon zu unterscheiden. Automatische Schlagwort-Einschätzung aus Titel und Kurztext, keine Inhaltsprüfung.</p></details><div id="stories"><?php foreach($items as $i=>$item): ?><article id="news-<?=esc(substr(hash('sha256',$item['url']),0,16))?>" class="story <?= $i===0?'feature':'' ?>" data-source="<?=esc($item['source'])?>" data-url="<?=esc($item['url'])?>" data-cat="<?=esc($item['category'])?>" data-search="<?=esc(mb_strtolower(($item['title']??'').' '.($item['summary']??'').' '.($item['source']??''),'UTF-8'))?>"><?php if(!empty($item["image_url"])): ?><img class="thumb" src="<?=esc($item["image_url"])?>" loading="lazy" decoding="async" referrerpolicy="no-referrer" alt="" onerror="this.style.display='none'"><?php endif; ?><div><div class="cat"><?=esc($item['category'])?></div><h2><a href="<?=esc($item['url'])?>" target="_blank" rel="noopener noreferrer"><?=esc($item['title'])?></a></h2><?php if(!empty($item['summary'])): ?><p class="summary"><?=esc($item['summary'])?></p><?php endif; ?><div class="meta"><?=esc($item['source'])?><?php if($item['agency']): ?> · Agentur: <?=esc($item['agency'])?><?php endif; ?> · <time datetime="<?=esc(str_replace(' ','T',$item['published_at']))?>"><?=esc($item['published_at'])?></time> ↗</div><?php if(!empty($item['other_versions'])): ?><details class="news-duplicates"><summary><?=count($item['other_versions'])?> weitere Veröffentlichung<?=count($item['other_versions'])===1?'':'en'?> anzeigen</summary><?php foreach($item['other_versions'] as $other): ?><a href="<?=esc($other['url'])?>" target="_blank" rel="noopener noreferrer"><?=esc($other['source'])?> ↗</a><?php endforeach; ?></details><?php endif; ?><button class="story-map-link" type="button" title="Auf dem Globus anzeigen" aria-label="Auf dem Globus anzeigen">◎ Karte</button><details class="assessment"><summary>Persönlich einordnen</summary><div class="trust-tools"><label>Eigene Einschätzung <select class="trust" aria-label="Glaubwürdigkeit persönlich einschätzen"><option value="">Ungeprüft</option><option value="plausible">Plausibel</option><option value="questionable">Fraglich</option></select></label><span class="ifis-related"></span></div></details></div><details class="story-menu"><summary aria-label="Weitere Optionen" title="Weitere Optionen">···</summary><div class="story-actions"><button class="save" aria-label="Meldung merken" title="Merken">☆ Merken</button><button class="dismiss" aria-label="Meldung ausblenden" title="Ausblenden">× Ausblenden</button><button class="assess-open" type="button">Persönlich einordnen</button></div></details></article><?php endforeach; ?></div>
<div class="empty <?= $items?'hide':'' ?>" id="empty"><div class="symbol">▤</div><h2>Noch keine Nachrichten importiert.</h2><p>Sobald die ersten Quellen eingebunden sind, erscheinen hier die aktuellen Meldungen – übersichtlich und ohne Ablenkung.</p></div><div class="empty hide" id="noMatches"><h2>Keine passenden Meldungen</h2><p>Versuche einen anderen Suchbegriff oder Filter.</p></div></main>

</div>
<footer class="site-footer" aria-label="Rechtliches und Informationen"><a href="info.php?page=about">Über IFIS News</a><a href="info.php?page=method">Quellen & Methodik</a><a href="info.php?page=imprint">Impressum</a><a href="info.php?page=privacy">Datenschutz</a><a href="https://ifis.net/">IFIS.net</a><span>IFIS News · Externe Nachrichtenquellen; automatische Einordnungen sind vorläufig.</span></footer>

<script src="https://unpkg.com/maplibre-gl@5.7.1/dist/maplibre-gl.js"></script><script>
(()=>{'use strict';
window.ifisReferenceArticles=<?=json_encode($ifisIndex,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE|JSON_INVALID_UTF8_SUBSTITUTE)?>;
const prefix='ifis-news-v1-',load=(k)=>{try{return JSON.parse(localStorage.getItem(prefix+k)||'[]')}catch{return []}},store=(k,v)=>{try{localStorage.setItem(prefix+k,JSON.stringify(v))}catch{}};
let saved=load('saved'),hidden=load('hidden'),mode='all',cat='Alle';
const stories=[...document.querySelectorAll('.story')],search=document.getElementById('search'),empty=document.getElementById('empty'),noMatches=document.getElementById('noMatches'),title=document.getElementById('feedTitle');
const base=title.textContent;
const gridToggle=document.getElementById('gridToggle'),gridCount=document.getElementById('gridCount'),storyContainer=document.getElementById('stories');let gridCols=Number(localStorage.getItem(prefix+'grid-cols'))||1;if(![1,2,3].includes(gridCols))gridCols=1;function setGrid(n){gridCols=n;storyContainer.classList.remove('grid-1','grid-2','grid-3');storyContainer.classList.add('grid-'+n);gridCount.textContent=String(n);gridToggle.setAttribute('aria-label',n+' Spalten, zur nächsten Ansicht wechseln');localStorage.setItem(prefix+'grid-cols',String(n))}gridToggle.addEventListener('click',()=>setGrid(gridCols===3?1:gridCols+1));setGrid(gridCols);

let mapSelectedStoryIds=null;let direction='all';const constructive=['restoration','restores','restored','regenerat','reforestation','reforest','clean energy','renewable energy','conservation','cooperation','peace agreement','recovery','reduces emissions','protects biodiversity','recycling','renaturier','wiederaufforst','erneuerbare energie','entsiegel','kooperation','friedensabkommen','emissionen senken','nachhaltig'];
const harmful=['deforestation','pollution','habitat loss','mass extinction','war crimes','oil spill','toxic waste','biodiversity loss','environmental destruction','illegal logging','entwald','verschmutzung','artensterben','lebensraumverlust','kriegsverbrechen','ölkatastrophe','umweltzerstörung'];
const hasTerms=(s,terms)=>terms.some(term=>s.includes(term));
stories.forEach(el=>{const s=el.dataset.search.toLowerCase(),yes=hasTerms(s,constructive),no=hasTerms(s,harmful);el.dataset.direction=yes&&no?'mixed':yes?'constructive':no?'harmful':'unknown';});
document.querySelectorAll('[data-direction]').forEach(button=>button.addEventListener('click',()=>{direction=button.dataset.direction;document.querySelectorAll('[data-direction]').forEach(b=>b.classList.toggle('active',b===button));refresh()}));
const sourceKey=prefix+'sources-'+(location.search.includes('view=world')?'world':'ifis');
const sourceNames=[...new Set(stories.map(el=>el.dataset.source).filter(Boolean))].sort();
if(!location.search.includes('view=world')&&!sourceNames.includes('IFIS · Artikel'))sourceNames.push('IFIS · Artikel');
sourceNames.sort();
let enabledSources;
try{enabledSources=JSON.parse(localStorage.getItem(sourceKey)||'null')}catch{}
if(!Array.isArray(enabledSources))enabledSources=[...sourceNames];
else{
 const migrationKey=sourceKey+'-source-names-v2';
 const valid=new Set(sourceNames);
 enabledSources=enabledSources.map(name=>valid.has(name)?name:sourceNames.find(src=>name.startsWith(src+' · '))).filter(Boolean);
 if(!localStorage.getItem(migrationKey)){
  if(valid.has('IFIS · Artikel')&&!enabledSources.includes('IFIS · Artikel'))enabledSources.push('IFIS · Artikel');
  try{localStorage.setItem(migrationKey,'1')}catch{}
 }
 enabledSources=[...new Set(enabledSources)];
}
const sourcePanel=document.getElementById('sourcePanel'),sourceList=document.getElementById('sourceList');
function renderSources(){sourceList.replaceChildren();sourceNames.forEach(name=>{const label=document.createElement('label'),input=document.createElement('input');input.type='checkbox';input.checked=enabledSources.includes(name);input.addEventListener('change',()=>{enabledSources=input.checked?[...enabledSources,name]:enabledSources.filter(x=>x!==name);localStorage.setItem(sourceKey,JSON.stringify(enabledSources));refresh()});label.append(input,document.createTextNode(' '+name));sourceList.append(label)});}
document.getElementById('sourcesToggle').addEventListener('click',()=>{sourcePanel.hidden=!sourcePanel.hidden;renderSources()});
document.getElementById('sourcesAll').addEventListener('click',()=>{enabledSources=[...sourceNames];localStorage.setItem(sourceKey,JSON.stringify(enabledSources));renderSources();refresh()});
document.getElementById('sourcesNone').addEventListener('click',()=>{enabledSources=[];localStorage.setItem(sourceKey,JSON.stringify(enabledSources));renderSources();refresh()});
renderSources();
const toneFilter=document.getElementById('toneFilter'),baitFilter=document.getElementById('filterClickbait'),baitButton=document.getElementById('showFiltered');let showBait=false;
const positive=['breakthrough','improve','success','recovery','restored','solution','progress','benefit','hope','reverses','reduces','erfolg','fortschritt','verbessert','lösung','hilft'];
const negative=['war','killed','death','crisis','disaster','collapse','attack','famine','violence','threat','krieg','getötet','krise','katastrophe','angriff','gewalt'];
const bait=['you won’t believe','shocking','mind-blowing','what happened next','doctors hate','one weird','must see','schockierend','geheimtrick','das glaubst du nicht'];
const hits=(s,words)=>words.filter(w=>s.includes(w)).length;
stories.forEach(el=>{const t=el.querySelector('h2').textContent.toLowerCase(),s=el.dataset.search.toLowerCase(),p=hits(s,positive),n=hits(s,negative);el.dataset.tone=p>n?'positive':n>p?'negative':p&&n?'balanced':'unknown';el.dataset.bait=bait.some(w=>t.includes(w))?'yes':'no';const label=document.createElement('div');label.className='quality-tag';label.hidden=true;label.textContent='Automatische Tonlage: '+({positive:'hoffnungsvoll',negative:'belastend',balanced:'sachlich / gemischt',unknown:'unbestimmt'}[el.dataset.tone])+(el.dataset.bait==='yes'?' · Clickbait-Verdacht':'');el.querySelector('.meta').after(label)});
toneFilter.addEventListener('change',refresh);baitFilter.addEventListener('change',refresh);baitButton.addEventListener('click',()=>{showBait=!showBait;baitButton.textContent=showBait?'Normale Ansicht':'Ausgefilterte anzeigen';refresh()});
const statsPanel=document.getElementById('newsStats'),statsToggle=document.getElementById('statsToggle');
const infoPagesToggle=document.getElementById('infoPagesToggle'),infoPagesPanel=document.getElementById('infoPagesPanel');
function hideInfoPages(){infoPagesPanel.hidden=true;infoPagesToggle.setAttribute('aria-expanded','false');infoPagesToggle.classList.remove('active')}
infoPagesToggle.addEventListener('click',()=>{const opening=infoPagesPanel.hidden;closeDockOverlays();infoPagesPanel.hidden=!opening;infoPagesToggle.setAttribute('aria-expanded',String(opening));infoPagesToggle.classList.toggle('active',opening);if(opening)positionDockPanel(infoPagesPanel,infoPagesToggle)});
function closeDockOverlays(){
 hideInfoPages();
 document.querySelectorAll('[data-filter-section].filter-expanded').forEach(panel=>panel.classList.remove('filter-expanded'));
 document.querySelectorAll('[data-open-filter].active').forEach(button=>{button.classList.remove('active');button.setAttribute('aria-expanded','false')});
 statsPanel.hidden=true;
 statsToggle.setAttribute('aria-expanded','false');
}
statsToggle.addEventListener('click',()=>{const opening=statsPanel.hidden;closeDockOverlays();statsPanel.hidden=!opening;statsToggle.setAttribute('aria-expanded',String(!statsPanel.hidden));renderNewsStats();if(!statsPanel.hidden)positionDockPanel(statsPanel,statsToggle)});
document.getElementById('statsClose').addEventListener('click',()=>{statsPanel.hidden=true;statsToggle.setAttribute('aria-expanded','false')});
function renderNewsStats(){if(statsPanel.hidden)return;const available=stories.filter(el=>enabledSources.includes(el.dataset.source));const visible=available.filter(el=>!el.classList.contains('hide'));document.getElementById('statsScope').textContent=available.length+' Meldungen aus aktivierten Quellen · '+visible.length+' aktuell angezeigt';const categories=[['Clickbait-Verdacht','bait',[['Verdacht','yes'],['Kein Treffer','no']]],['Tonlage','tone',[['Hoffnungsvoll','positive'],['Sachlich / gemischt','balanced'],['Belastend','negative'],['Nicht bestimmbar','unknown']]],['Entwicklungsrichtung','direction',[['Konstruktiv','constructive'],['Problemverstärkend','harmful'],['Gemischt','mixed'],['Nicht bestimmbar','unknown']]]];const root=document.getElementById('statsContent');root.replaceChildren();for(const [name,key,groups] of categories){const section=document.createElement('div');section.className='stats-group';const heading=document.createElement('strong');heading.textContent=name;section.append(heading);for(const [label,value] of groups){const n=available.filter(el=>el.dataset[key]===value).length;const row=document.createElement('div');row.className='stats-row';const caption=document.createElement('span');caption.textContent=label+' · '+n+' ('+(available.length?Math.round(n/available.length*100):0)+' %)';const bar=document.createElement('div');bar.className='stats-track';const fill=document.createElement('div');fill.className='stats-fill';fill.style.width=(available.length?n/available.length*100:0)+'%';bar.append(fill);row.append(caption,bar);section.append(row)}root.append(section)}}
function refresh(){let count=0;for(const el of stories){const url=el.dataset.url;const show=mapSelectedStoryIds?mapSelectedStoryIds.has(el.id):(mode==='saved'?saved.includes(url):mode==='hidden'?hidden.includes(url):!hidden.includes(url))&&(cat==='Alle'||el.dataset.cat===cat)&&(direction==='all'||el.dataset.direction===direction)&&enabledSources.includes(el.dataset.source)&&el.dataset.search.includes(search.value.trim().toLocaleLowerCase())&&(toneFilter.value==='all'||el.dataset.tone===toneFilter.value)&&(showBait?el.dataset.bait==='yes':(!baitFilter.checked||el.dataset.bait!=='yes'));el.classList.toggle('hide',!show);el.querySelector('.save').textContent=saved.includes(url)?'★':'☆';el.querySelector('.save').classList.toggle('saved',saved.includes(url));el.querySelector('.dismiss').textContent=mode==='hidden'?'↶':'×';el.querySelector('.dismiss').title=mode==='hidden'?'Wieder anzeigen':'Ausblenden';if(show)count++}
empty.classList.toggle('hide',stories.length>0||mode!=='all'||cat!=='Alle'||!!search.value);
noMatches.classList.toggle('hide',count>0||(!stories.length&&mode==='all'&&cat==='Alle'&&!search.value));
title.textContent=mode==='saved'?'☆':mode==='hidden'?'Ausgeblendet':cat==='Alle'?base:cat;
title.classList.toggle('feed-title-saved',mode==='saved');
title.setAttribute('aria-label',mode==='saved'?'Gemerkt':title.textContent);
document.querySelectorAll('[data-cat]').forEach(b=>b.classList.toggle('active',mode==='all'&&b.dataset.cat===cat));
renderNewsStats();document.querySelector('[data-open-filter="saved"]').classList.toggle('active',mode==='saved');document.querySelector('[data-open-filter="hidden"]').classList.toggle('active',mode==='hidden');
}
function setCat(v){cat=v;mode='all';refresh()}
document.querySelectorAll('[data-cat]').forEach(b=>b.addEventListener('click',()=>setCat(b.dataset.cat)));
document.querySelectorAll('.assess-open').forEach(b=>b.addEventListener('click',()=>{const story=b.closest('.story');story.querySelector('.assessment').open=true;story.querySelector('.story-menu').open=false;story.querySelector('.assessment').scrollIntoView({block:'nearest',behavior:'smooth'})}));
document.querySelectorAll('.story-menu button').forEach(b=>b.addEventListener('click',()=>{const menu=b.closest('.story-menu');if(menu)menu.open=false}));
document.querySelectorAll('.story time').forEach(t=>{const dt=new Date(t.dateTime);if(!Number.isFinite(dt.getTime()))return;const delta=Math.max(0,Date.now()-dt.getTime()),hours=Math.floor(delta/3600000),days=Math.floor(hours/24);t.textContent=days?days+' T':hours?hours+' h':Math.max(1,Math.floor(delta/60000))+' min'});
document.querySelectorAll('.save').forEach(b=>b.addEventListener('click',()=>{const u=b.closest('.story').dataset.url;saved=saved.includes(u)?saved.filter(x=>x!==u):[...saved,u];store('saved',saved);refresh()}));
document.querySelectorAll('.dismiss').forEach(b=>b.addEventListener('click',()=>{const u=b.closest('.story').dataset.url;hidden=hidden.includes(u)?hidden.filter(x=>x!==u):[...hidden,u];store('hidden',hidden);refresh()}));
function showSaved(){window.ifisNewsFeed.clearMapStories();mode='saved';cat='Alle';search.value='';toneFilter.value='all';baitFilter.checked=false;showBait=false;direction='all';refresh();document.getElementById('stories').scrollIntoView({block:'start',behavior:'smooth'})}



 const mobileSearchToggle=document.getElementById('mobileSearchToggle');
mobileSearchToggle.addEventListener('click',()=>{const field=document.getElementById('search');const open=field.classList.toggle('open');mobileSearchToggle.setAttribute('aria-expanded',String(open));mobileSearchToggle.setAttribute('aria-label',open?'Suche schließen':'Suche öffnen');if(open)field.focus()});
document.getElementById('filterDockToggle').addEventListener('click',()=>{const dock=document.getElementById('filterDock');const expanded=dock.classList.toggle('expanded');if(!expanded)closeDockOverlays();document.getElementById('filterDockToggle').setAttribute('aria-expanded',String(expanded))});
function positionDockPanel(panel,trigger){
 if(!panel||!trigger)return;
 const rect=trigger.getBoundingClientRect();
 const gap=12,margin=12;
 panel.style.setProperty('position','fixed','important');
 panel.style.setProperty('right',Math.max(margin,window.innerWidth-rect.left+gap)+'px','important');
 panel.style.setProperty('left','auto','important');
 panel.style.setProperty('bottom','auto','important');
 panel.style.setProperty('top','0px','important');
 const height=panel.getBoundingClientRect().height;
 const top=Math.max(margin,Math.min(rect.top,window.innerHeight-height-margin));
 panel.style.setProperty('top',top+'px','important');
}
window.addEventListener('resize',()=>{
 const active=document.querySelector('[data-filter-section].filter-expanded');
 if(active)positionDockPanel(active,document.querySelector('[data-open-filter].active'));
 const stats=document.getElementById('newsStats');
 if(stats&&!stats.hidden)positionDockPanel(stats,document.getElementById('statsToggle'));
});
document.querySelectorAll('[data-open-filter]').forEach(button=>button.addEventListener('click',()=>{const type=button.dataset.openFilter;if(type==='saved'){closeDockOverlays();showSaved();return}if(type==='hidden'){closeDockOverlays();window.ifisNewsFeed.clearMapStories();mode='hidden';cat='Alle';refresh();document.getElementById('stories').scrollIntoView({block:'start',behavior:'smooth'});return}const panel=document.querySelector('[data-filter-section="'+type+'"]');const opening=!panel.classList.contains('filter-expanded');closeDockOverlays();if(opening){panel.classList.add('filter-expanded');button.classList.add('active');button.setAttribute('aria-expanded','true');if(type==='quality')panel.open=true;positionDockPanel(panel,button)}}));
document.addEventListener('pointerdown',event=>{
 const dock=document.getElementById('filterDock');
 if(dock.contains(event.target)||event.target.closest('.filter-section.filter-expanded, #newsStats:not([hidden]), #infoPagesPanel:not([hidden])'))return;
 closeDockOverlays();
});
document.addEventListener('keydown',event=>{if(event.key==='Escape')closeDockOverlays()});
// Menu subtitles are transient: dismiss on the next action, including touch and keyboard.
const subtitleLinks=[...document.querySelectorAll('.topnav [data-subtitle-de]')];
function dismissSubtitles(){subtitleLinks.forEach(link=>link.classList.add('subtitle-dismissed'))}
subtitleLinks.forEach(link=>{
 link.addEventListener('pointerenter',()=>link.classList.remove('subtitle-dismissed'));
 link.addEventListener('focus',()=>link.classList.remove('subtitle-dismissed'));
});
document.addEventListener('pointerdown',dismissSubtitles,true);
document.addEventListener('keydown',event=>{if(event.key==='Escape'||event.key==='Enter'||event.key===' ')dismissSubtitles()},true);
document.addEventListener('scroll',dismissSubtitles,{passive:true,capture:true});
const trustKey=prefix+'trust';
let ratings={};try{ratings=JSON.parse(localStorage.getItem(trustKey)||'{}')||{}}catch{}
document.querySelectorAll('.story').forEach(story=>{
const url=story.dataset.url,sel=story.querySelector('.trust');sel.value=ratings[url]||'';
sel.addEventListener('change',()=>{if(sel.value)ratings[url]=sel.value;else delete ratings[url];try{localStorage.setItem(trustKey,JSON.stringify(ratings))}catch{}});
const words=(story.querySelector('h2').textContent+' '+(story.querySelector('.summary')?.textContent||'')).toLowerCase();
const referenceArticles=window.ifisReferenceArticles||[];
const stop=new Set(['und','oder','aber','eine','einer','eines','einem','einen','sind','wird','werden','über','auch','mehr','nicht','sich','durch','the','and','for','from','with','that','this','into','about','after','their','have','what','when','where','news','ifis','artikel','menschen','leben','system','systeme','struktur','strukturen']);
const tokens=(text)=>new Set((text.toLocaleLowerCase('de').match(/[\\p{L}]{4,}/gu)||[]).filter(w=>!stop.has(w)));
const storyTokens=tokens(words);
const matches=referenceArticles.map(ref=>{
 const titleTokens=tokens(ref.title||'');
 const descTokens=tokens(ref.description||'');
 let score=0;
 for(const token of storyTokens){if(titleTokens.has(token))score+=3;else if(descTokens.has(token))score+=1}
 return {ref,score};
}).filter(x=>x.score>=6).sort((a,b)=>b.score-a.score).slice(0,2);
const related=story.querySelector('.ifis-related');
for(const {ref} of matches){
 const a=document.createElement('a');a.href=ref.url;a.target='_blank';a.rel='noopener noreferrer';a.textContent='↗ IFIS: '+ref.title;a.title='Automatisch ermittelter thematischer Lesehinweis; keine Inhalts- oder Faktenprüfung';related.append(a);
}

});
const language=document.getElementById('language');
const dictionary={de:{world:'Weltlage',ifis:'Potenzial',all:'Alle',saved:'Gemerkt',hidden:'Ausgeblendet',search:'Nachrichten durchsuchen …',globe:'🌍 Globus',rating:'Eigene Einschätzung',unverified:'Ungeprüft',plausible:'Plausibel',questionable:'Fraglich'},en:{world:'World news',ifis:'Potential',all:'All',saved:'Saved',hidden:'Hidden',search:'Search news …',globe:'🌍 Globe',rating:'My assessment',unverified:'Not checked',plausible:'Plausible',questionable:'Questionable'}};
function translate(lang){const d=dictionary[lang]||dictionary.de;document.documentElement.lang=lang;language.value=lang;try{localStorage.setItem(prefix+'lang',lang)}catch{}
document.querySelector('.topnav a[href^="?view=ifis"]').textContent=lang==='en'?'Potential':'Potenzial';document.querySelector('.topnav a[href^="?view=world"]').textContent=lang==='en'?'World':'Weltlage';document.getElementById('globeToggle').textContent=lang==='en'?'Map':'Karte';search.placeholder=d.search;document.querySelector('.chips [data-cat="Alle"]').textContent=d.all;document.querySelectorAll('.trust-tools label').forEach(el=>{el.firstChild.textContent=d.rating+' ';const opts=el.querySelector('select').options;opts[0].textContent=d.unverified;opts[1].textContent=d.plausible;opts[2].textContent=d.questionable;});document.querySelector('[data-open-filter="saved"] span:not(.dock-symbol)').textContent=d.saved;}
language.addEventListener('change',()=>translate(language.value));translate(localStorage.getItem(prefix+'lang')||'de');
// Feed and map are separate closures: expose an explicit selection interface.
window.ifisNewsFeed={
 selectMapStories(ids){
  mapSelectedStoryIds=new Set(ids);
  mode='all';cat='Alle';direction='all';search.value='';
  toneFilter.value='all';baitFilter.checked=false;showBait=false;
  refresh();
 },
 clearMapStories(){mapSelectedStoryIds=null;refresh();}
};
search.addEventListener('input',refresh);refresh();
})();
</script>
<script>
(()=>{
const cityLocations=[
['Berlin',52.52,13.405,['berlin']],['Hamburg',53.55,10.0,['hamburg']],['München',48.137,11.575,['munich','münchen']],['Lübeck',53.866,10.686,['lübeck','luebeck']],['Frankfurt',50.11,8.68,['frankfurt']],['Köln',50.94,6.96,['cologne','köln']],['London',51.507,-0.128,['london']],['Paris',48.857,2.352,['paris']],['New York',40.713,-74.006,['new york']],['Washington',38.907,-77.037,['washington dc','washington d.c.','white house']],['Los Angeles',34.05,-118.24,['los angeles']],['Chicago',41.878,-87.63,['chicago']],['Kyjiw',50.45,30.52,['kyiv','kiev','kyjiw']],['Moskau',55.75,37.62,['moscow','moskau']],['Peking',39.90,116.40,['beijing','peking']],['Shanghai',31.23,121.47,['shanghai']],['Tokio',35.68,139.69,['tokyo','tokio']],['Neu-Delhi',28.61,77.21,['new delhi','neu-delhi']],['Mumbai',19.08,72.88,['mumbai']],['Teheran',35.69,51.39,['tehran','teheran']],['Jerusalem',31.77,35.21,['jerusalem']],['Tel Aviv',32.09,34.78,['tel aviv']],['Gaza-Stadt',31.50,34.47,['gaza city','gaza-stadt']],['Istanbul',41.01,28.98,['istanbul']],['Ankara',39.93,32.86,['ankara']],['Brüssel',50.85,4.35,['brussels','brüssel']],['Seoul',37.57,126.98,['seoul']],['Taipeh',25.03,121.57,['taipei','taipeh']],['Kairo',30.04,31.24,['cairo','kairo']],['Sydney',-33.87,151.21,['sydney']],['Toronto',43.65,-79.38,['toronto']],['Mexiko-Stadt',19.43,-99.13,['mexico city','mexiko-stadt']],['São Paulo',-23.55,-46.63,['são paulo','sao paulo']],['Rio de Janeiro',-22.91,-43.20,['rio de janeiro']],['Nairobi',-1.29,36.82,['nairobi']],['Kapstadt',-33.93,18.42,['cape town','kapstadt']]
];
const locations=[
['Deutschland',51.16,10.45,['germany','deutschland','german','berlin','hamburg','munich','münchen']],
['Ukraine',49,32,['ukraine','kyiv','kiev','ukrainian']],
['Russland',61,100,['russia','russian','moscow','kremlin','russland']],
['USA',39,-98,['united states','u.s.',' us ','america','american','washington','new york','trump']],
['China',35,104,['china','chinese','beijing','peking']],
['Indien',22,79,['india','indian','delhi','mumbai']],
['Israel',31.5,35,['israel','israeli','jerusalem','tel aviv']],
['Gaza',31.42,34.38,['gaza','palestinian','palestine']],
['Iran',32,53,['iran','iranian','tehran']],
['Großbritannien',54,-2,['britain','british','uk ','london','england']],
['Frankreich',46.6,2.2,['france','french','paris','frankreich']],
['Brasilien',-10,-52,['brazil','brazilian','brasilien']],
['Kenia',0.2,37.9,['kenya','kenyan','kenia']],
['Südafrika',-29,24,['south africa','südafrika']],
['Japan',36,138,['japan','japanese','tokyo']],
['Australien',-25,134,['australia','australian','sydney']],
['Kanada',56,-106,['canada','canadian','kanada']],
['Mexiko',23,-102,['mexico','mexican','mexiko']],
['EU',50.85,4.35,['european union','european commission','eu parliament','europäische union','brussels','brüssel']],
['Türkei',39,35,['turkey','türkei','turkish','ankara']],
['Südkorea',36,128,['south korea','südkorea','seoul']],
['Taiwan',24,121,['taiwan','taipei']],
['Ägypten',27,30,['egypt','ägypten','cairo']],
['Sudan',15,30,['sudan','khartoum']],
['Nigeria',9,8,['nigeria','nigerian']],
['Indonesien',-2,118,['indonesia','indonesien','jakarta']]
];
const stories=[...document.querySelectorAll('.story')],btn=document.getElementById('globeToggle'),panel=document.getElementById('globeView'),info=document.getElementById('globeInfo');let map=null,markers=[];const nightToggle=document.getElementById('nightToggle');
const normalize=t=>' '+t.toLocaleLowerCase()+' ';
const freshness=(group)=>{
 const timestamps=group.stories.map(story=>{
  const raw=story.querySelector('time')?.getAttribute('datetime')||'';
  const parsed=Date.parse(raw.replace(' ','T').replace(/^(\\d{4}-\\d{2}-\\d{2}T\\d{2}:\\d{2}:\\d{2})$/,'$1Z'));
  return Number.isFinite(parsed)?parsed:0;
 }).filter(Boolean);
 if(!timestamps.length)return {color:'hsl(174 18% 66%)',label:'Alter unbekannt'};
 const age=Math.max(0,(Date.now()-Math.max(...timestamps))/3600000);
 const intensity=Math.max(0,1-Math.log2(1+age/6)/Math.log2(1+168/6));
 return {color:`hsl(174 ${Math.round(20+60*intensity)}% ${Math.round(68-34*intensity)}%)`,label:age<24?'Neueste Meldung vor '+Math.max(1,Math.round(age))+' Std.':'Neueste Meldung vor '+Math.round(age/24)+' Tagen'};
};
const groups=new Map();const constructiveCount=g=>g.stories.filter(story=>story.dataset.direction==='constructive').length;
stories.forEach(story=>{const title=story.querySelector('h2').textContent,txt=normalize(title+' '+(story.querySelector('.summary')?.textContent||''));const hit=cityLocations.find(loc=>loc[3].some(word=>txt.includes(word)))||locations.find(loc=>loc[3].some(word=>txt.includes(word)));if(!hit)return;story.dataset.place=hit[0];if(!groups.has(hit[0]))groups.set(hit[0],{loc:hit,stories:[]});groups.get(hit[0]).stories.push(story);});
document.querySelectorAll('.story-map-link').forEach(link=>link.addEventListener('click',()=>{const story=link.closest('.story');document.getElementById('globeToggle').click();const place=story.dataset.place;if(place&&groups.has(place)){const select=()=>choose(place);if(map?.getSource('news-source-links'))select();else if(map)map.once('load',select);}else{const source=sourceFor(story);const info=document.getElementById('globeInfo');if(info)info.textContent=source?source.name+' · Ereignisort nicht eindeutig zugeordnet':'Quelle und Ereignisort derzeit nicht eindeutig zugeordnet';if(source)setTimeout(()=>map?.flyTo({center:source.coordinates,zoom:3,essential:true}),400)}}));
const sourceBatches=<?=json_encode($sourceBatches,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE)?>;
const countryCenters={
 'Vereinigtes Königreich':[-0.1276,51.5072],
 'Deutschland':[10.0007,53.5503],
 'USA':[-77.0369,38.9072],
 'Katar':[51.531,25.2854],
 'Frankreich':[2.273,48.823]
};
function sourceCountry(name){
 if(/Guardian|BBC News/.test(name))return 'Vereinigtes Königreich';
 if(/Deutsche Welle|SPIEGEL/.test(name))return 'Deutschland';
 if(/Al Jazeera/.test(name))return 'Katar';
 if(/France 24/.test(name))return 'Frankreich';
 if(/Euronews/.test(name))return 'Frankreich';
 if(/ScienceDaily|NASA|Phys.org|NPR|New York Times|NOAA|IEEE Spectrum/.test(name))return 'USA';
 return null;
}
const incomingShare=new URLSearchParams(location.search);
const sharedSelection=['event','source'].includes(incomingShare.get('collection'))&&incomingShare.get('name')
 ?{kind:incomingShare.get('collection'),name:incomingShare.get('name')}:null;
const requestedHours=Number(incomingShare.get('hours'));
if(sharedSelection&&Number.isFinite(requestedHours)&&requestedHours>=.5&&requestedHours<=48){
 document.getElementById('mapTimeRange').value=String(Math.round(requestedHours*2)/2);
}
const seenStorageKey='ifis-news-seen-v1';
let seenUrls=new Set();
try{seenUrls=new Set(JSON.parse(localStorage.getItem(seenStorageKey)||'[]'))}catch(e){}
const colorMode=document.getElementById('mapColorMode');
const focusMode=document.getElementById('mapFocusMode');
focusMode.addEventListener('change',()=>{if(map){hideFarSide();updateConnector();}});
function filterMapStories(articles){
 window.ifisNewsFeed.selectMapStories(articles.map(story=>story.id));
}
function openMapStory(story){
 if(!story)return;
 // Close the popup without clearing the icon's article selection.
 if(activeMapPopup){const popup=activeMapPopup;activeMapPopup=null;popup.remove();}
 setHoveredMapStory(null);
 markStorySeen(story);
 requestAnimationFrame(()=>{story.scrollIntoView({behavior:'smooth',block:'center'});history.replaceState(null,'','#'+story.id)});
}
// The flags indicate country, never a supposed geographic origin of the news itself.
// Unknown or politically ambiguous locations deliberately get a neutral location marker.
const countryFlags={
 'Deutschland':'🇩🇪','Ukraine':'🇺🇦','Russland':'🇷🇺','USA':'🇺🇸','China':'🇨🇳','Indien':'🇮🇳',
 'Israel':'🇮🇱','Iran':'🇮🇷','Großbritannien':'🇬🇧','Frankreich':'🇫🇷','Brasilien':'🇧🇷',
 'Kenia':'🇰🇪','Südafrika':'🇿🇦','Japan':'🇯🇵','Australien':'🇦🇺','Kanada':'🇨🇦',
 'Mexiko':'🇲🇽','Türkei':'🇹🇷','Südkorea':'🇰🇷','Taiwan':'🇹🇼','Ägypten':'🇪🇬',
 'Sudan':'🇸🇩','Nigeria':'🇳🇬',
 'Berlin':'🇩🇪','Hamburg':'🇩🇪','München':'🇩🇪','Lübeck':'🇩🇪','Frankfurt':'🇩🇪','Köln':'🇩🇪',
 'London':'🇬🇧','Paris':'🇫🇷','New York':'🇺🇸','Washington':'🇺🇸','Los Angeles':'🇺🇸','Chicago':'🇺🇸',
 'Kyjiw':'🇺🇦','Moskau':'🇷🇺','Peking':'🇨🇳','Shanghai':'🇨🇳','Tokio':'🇯🇵','Neu-Delhi':'🇮🇳','Mumbai':'🇮🇳',
 'Teheran':'🇮🇷','Jerusalem':'','Tel Aviv':'🇮🇱','Gaza-Stadt':'','Istanbul':'🇹🇷','Ankara':'🇹🇷',
 'Brüssel':'🇧🇪','Seoul':'🇰🇷','Taipeh':'🇹🇼','Kairo':'🇪🇬','Sydney':'🇦🇺','Toronto':'🇨🇦',
 'Mexiko-Stadt':'🇲🇽','São Paulo':'🇧🇷','Rio de Janeiro':'🇧🇷','Nairobi':'🇰🇪'
};
function newsPlaceFlag(place){return countryFlags[place]||'📍'}
function newsSourceFlag(source){
 const name=source?.name||'';
 if(/Lübeck|Bonn|Hamburg/.test(name))return '🇩🇪';
 if(/London/.test(name))return '🇬🇧';
 if(/Doha/.test(name))return '🇶🇦';
 if(/Issy|Lyon/.test(name))return '🇫🇷';
 if(/Isle of Man/.test(name))return '🇮🇲';
 if(/Washington|New York|Silver Spring|Rockville/.test(name))return '🇺🇸';
 return '📍';
}
function appendMapArticle(info,story){
 const a=document.createElement('a');a.className='globe-story-link';a.href='#'+story.id;
 const title=document.createElement('span');title.className='globe-story-title';
 title.textContent=story.querySelector('h2')?.textContent||'Artikel öffnen';
 const category=document.createElement('span');category.className='globe-story-category';
 category.textContent=story.querySelector('.cat')?.textContent?.trim()||'Weitere';
 const meta=document.createElement('small');meta.className='globe-story-meta';
 const sourceName=story.dataset.source||'Quelle unbekannt';
 const source=sourceFor(story);
 const place=story.dataset.place||'Nicht eindeutig erkannt';
 const sourceLine=document.createElement('span');sourceLine.className='globe-story-source';
 sourceLine.textContent=sourceName;
 const age=story.querySelector('time')?.textContent?.trim()||'';
 const places=document.createElement('span');places.className='globe-story-places';
 // Keep both endpoints explicit: a publisher's registered location is not where the event happened.
 const sourcePlace=document.createElement('span');
 sourcePlace.textContent=newsSourceFlag(source)+' Quelle: '+(source?.name?.split(' · ').pop()||'unbekannt');
 const eventPlace=document.createElement('span');
 eventPlace.textContent=newsPlaceFlag(place)+' Ereignis: '+place;
 places.append(sourcePlace,eventPlace);
 meta.append(sourceLine);
 if(age){const date=document.createElement('span');date.textContent=age;meta.append(date)}
 a.append(category,title,meta,places);
 a.addEventListener('pointerenter',event=>{if(event.pointerType!=='touch')setHoveredMapStory(story)});
 a.addEventListener('pointerleave',()=>{if(hoveredMapStory===story)setHoveredMapStory(null)});
 a.addEventListener('focus',()=>setHoveredMapStory(story));
 a.addEventListener('blur',()=>{if(hoveredMapStory===story)setHoveredMapStory(null)});
 a.addEventListener('click',e=>{e.preventDefault();setHoveredMapStory(null);openMapStory(story)});
 info.append(a);
}
function markStorySeen(story){
 if(!story||!story.dataset.url||seenUrls.has(story.dataset.url))return;
 seenUrls.add(story.dataset.url);
 try{localStorage.setItem(seenStorageKey,JSON.stringify([...seenUrls].slice(-3000)))}catch(e){}
 updateMapReadState();
}
function storyAge(story){const raw=story.querySelector('time')?.getAttribute('datetime')||'';const parsed=Date.parse(raw.endsWith('Z')?raw:raw.replace(' ','T')+'Z');return Number.isFinite(parsed)?(Date.now()-parsed)/3600000:Infinity}
function mapTimeStories(items){const hours=Number(document.getElementById('mapTimeRange').value)||24;return items.filter(story=>{const age=storyAge(story);return age>=0&&age<=hours;});}
function updateMapReadState(){
 const hours=Number(document.getElementById('mapTimeRange').value)||24;
 document.getElementById('mapTimeValue').textContent=hours.toLocaleString('de-DE')+' h';
 if(!map)return;
 markers.forEach(marker=>{
  const el=marker.getElement();
  if(!el._ifisStories)return;
  const matching=mapTimeStories(el._ifisStories);
  el._ifisTimeStories=matching;
  const source=el.classList.contains('globe-source');
  const events=new Set(matching.map(story=>story.dataset.place).filter(Boolean));
  const unread=matching.filter(story=>!seenUrls.has(story.dataset.url)).length;
  el.textContent=flatProjection?String(source?events.size:matching.length):'';
  const description=(el._ifisLabel||'')+' · '+matching.length+' Artikel · '+(source?events.size+' Ereignisorte':new Set(matching.map(story=>story.dataset.source).filter(Boolean)).size+' Quellen')+' · '+hours.toLocaleString('de-DE')+' h'+(colorMode.value==='unseen'?' · '+unread+' ungesehen':'');
  el.setAttribute('aria-label',description);
  if(hoveredMarkerButton===el)showMarkerHover(el);
  el.style.backgroundColor=colorMode.value==='unseen'&&!unread?'#718096':'';
 });
 hideFarSide();
}
colorMode.addEventListener('change',updateMapReadState);
stories.forEach(story=>story.querySelectorAll('h2 a').forEach(link=>link.addEventListener('click',()=>markStorySeen(story))));
let activeMapPopup=null;
let hoveredMapStory=null;
function setHoveredMapStory(story){if(hoveredMapStory===story)return;hoveredMapStory=story;renderFlightArcs();}
// Clicking beside an article popup dismisses it. Keep marker changes and links
// into the filtered article feed usable without premature selection resets.
document.addEventListener('pointerdown',event=>{
 if(!activeMapPopup)return;
 const target=event.target;
 if(!(target instanceof Element))return;
 if(target.closest('.marker-article-popup,.globe-marker,.globe-source,#mapLegend,#stories'))return;
 activeMapPopup.remove();
});
function collectionShareUrl(selection){
 const url=new URL(location.href);
 url.searchParams.set('map','1');
 url.searchParams.set('collection',selection.kind);
 url.searchParams.set('name',selection.name);
 url.searchParams.set('hours',document.getElementById('mapTimeRange').value);
 url.hash='';
 return url.href;
}
async function shareNewsLink(link,title,feedback){
 if(navigator.share){
  try{await navigator.share({title,text:'Artikelsammlung auf IFIS News',url:link});return}
  catch(error){if(error?.name==='AbortError')return}
 }
 try{
  if(navigator.clipboard?.writeText)await navigator.clipboard.writeText(link);
  else{
   const field=document.createElement('textarea');field.value=link;field.style.position='fixed';field.style.opacity='0';
   document.body.append(field);field.select();const success=document.execCommand('copy');field.remove();
   if(!success)throw Error('Kopieren nicht unterstützt');
  }
  feedback.textContent='Link kopiert';
 }catch(error){
  feedback.textContent='Link zum Kopieren: '+link;
 }
}
async function shareNewsCollection(selection,articles,mode,feedback,button){
 const title=selection.name+' · '+(selection.kind==='event'?'Ereignisort':'Quellenort')+' · IFIS News';
 if(mode==='dynamic'){
  await shareNewsLink(collectionShareUrl(selection),title,feedback);
  return;
 }
 if(button)button.disabled=true;
 feedback.textContent='Feste Auswahl wird gespeichert …';
 try{
  const response=await fetch('collection.php',{
   method:'POST',headers:{'Content-Type':'application/json'},
   body:JSON.stringify({
    kind:selection.kind,name:selection.name,
    view:new URLSearchParams(location.search).get('view')==='world'?'world':'ifis',
    urls:articles.slice(0,40).map(story=>story.dataset.url).filter(Boolean)
   })
  });
  const result=await response.json();
  if(!response.ok||!result.url)throw Error(result.error||'Speichern nicht möglich');
  feedback.textContent='Feste Sammlung gespeichert ('+result.count+' Artikel)';
  await shareNewsLink(result.url,title+' · fest',feedback);
 }catch(error){
  feedback.textContent='Feste Sammlung konnte nicht gespeichert werden: '+error.message;
 }finally{if(button)button.disabled=false}
}
function showMarkerArticles(coordinates,title,articles,routePoints=[],description='',shareSelection=null){
 articles=[...articles].sort((a,b)=>(Date.parse(b.querySelector('time')?.getAttribute('datetime')||'')||0)-(Date.parse(a.querySelector('time')?.getAttribute('datetime')||'')||0));
 setHoveredMapStory(null);
 if(activeMapPopup){const previous=activeMapPopup;activeMapPopup=null;previous.remove();}
 mapOverlayOpen=true;
 filterMapStories(articles);
 info.classList.add('has-selection');info.classList.remove('is-collapsed');
 info.replaceChildren();

 const wrapper=document.createElement('div');wrapper.className='marker-article-overlay';
 const details=document.createElement('details');details.open=true;
 const summary=document.createElement('summary');summary.textContent=title+' · '+articles.length+' Artikel'+(description?' · '+description:'');
 details.append(summary);
 const list=document.createElement('div');list.className='marker-article-list';
 if(articles.length)articles.slice(0,40).forEach(story=>appendMapArticle(list,story));
 else{const empty=document.createElement('p');empty.textContent='Keine zugeordneten Artikel in der aktuell geladenen Liste. Bitte Zeitraum oder Nachrichtenansicht erweitern.';list.append(empty);}
 details.append(list);
 if(shareSelection){
  const controls=document.createElement('div');controls.className='marker-collection-share';
  const fixed=document.createElement('button');fixed.type='button';fixed.className='collection-share-button';
  fixed.textContent='↗ Fest teilen';
  fixed.title='Diese Artikelauswahl unveränderlich speichern und teilen';
  const dynamic=document.createElement('button');dynamic.type='button';dynamic.className='collection-share-button';
  dynamic.textContent='↻ Dynamisch teilen';
  dynamic.title='Die Artikel ändern sich entsprechend der aktuellen Nachrichtenlage';
  const feedback=document.createElement('small');feedback.setAttribute('role','status');
  const actions=document.createElement('div');actions.className='marker-collection-share-actions';
  fixed.addEventListener('click',event=>{event.preventDefault();event.stopPropagation();shareNewsCollection(shareSelection,articles,'fixed',feedback,fixed)});
  dynamic.addEventListener('click',event=>{event.preventDefault();event.stopPropagation();shareNewsCollection(shareSelection,articles,'dynamic',feedback,dynamic)});
  actions.append(fixed,dynamic);controls.append(actions,feedback);wrapper.append(controls);
 }
 wrapper.append(details);
 // The whole unit (popup + selected location) is centered together.
 // Keeping the popup above its point guarantees the chosen point stays visible.
 const mapContainer=map.getContainer();
 const mapHeight=mapContainer.clientHeight;
 const mapWidth=mapContainer.clientWidth;
 const compact=window.matchMedia('(max-width:720px)').matches;
 const gap=compact?24:23;
 // Reserve space for the marker beneath the card and comfortable exterior margins.
 const maxPopupHeight=Math.max(120,Math.min(compact?410:440,Math.floor(mapHeight*.58)));
 const cardList=list;
 cardList.style.setProperty('max-height',Math.max(64,Math.min(compact?220:260,maxPopupHeight-125))+'px','important');
 wrapper.style.setProperty('max-height',Math.max(80,maxPopupHeight-20)+'px','important');
 wrapper.style.overflowY='auto';
 wrapper.style.overscrollBehavior='contain';
 const popupOptions={
  closeButton:true,closeOnClick:false,
  anchor:'bottom',offset:gap,
  maxWidth:compact?'min(340px,calc(100vw - 28px))':'min(360px,85vw)',
  className:'marker-article-popup'
 };
 activeMapPopup=new maplibregl.Popup(popupOptions).setLngLat(coordinates).setDOMContent(wrapper).addTo(map);
 const selectedPopup=activeMapPopup;
 // Find the actual marker, including a flat-map ring offset if one exists.
 // The geographical point is unchanged; only the popup receives the same offset.
 const selectedMarker=markers.find(candidate=>{
  const el=candidate.getElement(),p=candidate.getLngLat();
  if(Math.abs(p.lng-coordinates[0])>.0001||Math.abs(p.lat-coordinates[1])>.0001)return false;
  if(!shareSelection)return true;
  return el._ifisLabel===shareSelection.name &&
   el.classList.contains(shareSelection.kind==='source'?'globe-source':'globe-marker');
 });
 const markerOffset=selectedMarker?selectedMarker._ifisScreenOffset||[0,0]:[0,0];
 if(markerOffset[0]||markerOffset[1])
  selectedPopup.setOffset({bottom:[markerOffset[0],markerOffset[1]-gap]});
 // First let the popup get its actual height; then center the complete unit.
 requestAnimationFrame(()=>requestAnimationFrame(()=>{
  if(activeMapPopup!==selectedPopup)return;
  const popupEl=selectedPopup.getElement();
  if(!popupEl)return;
  const mapBox=mapContainer.getBoundingClientRect();
  // Use the viewport-visible part of the map (important on mobile browsers).
  const visibleTop=Math.max(8,Math.min(mapHeight-90,Math.max(0,64-mapBox.top)));
  const visibleBottom=Math.min(mapHeight-8,Math.max(visibleTop+90,window.innerHeight-mapBox.top-12));
  const freeHeight=visibleBottom-visibleTop;
  // Apply the height limit before measuring, so centering uses final dimensions.
  const availableCardHeight=Math.max(95,Math.floor(freeHeight-gap-28));
  popupEl.style.setProperty('max-height',availableCardHeight+'px','important');
  const popupContent=popupEl.querySelector('.maplibregl-popup-content');
  if(popupContent){
   popupContent.style.setProperty('max-height',availableCardHeight+'px','important');
   popupContent.style.overflow='auto';
  }
  const popupHeight=Math.min(popupEl.getBoundingClientRect().height,availableCardHeight);
  const targetX=mapWidth/2;
  // At tiny viewport sizes, always keep the icon beneath the card.
  const desiredY=visibleTop+Math.max(0,(freeHeight-popupHeight-gap)/2)+popupHeight+gap;
  const point=map.project(coordinates);
  const currentX=point.x+markerOffset[0],currentY=point.y+markerOffset[1];
  // panBy moves screen features opposite to its delta.
  const shiftX=currentX-targetX,shiftY=currentY-desiredY;
  if(Math.abs(shiftX)>1||Math.abs(shiftY)>1)
   map.panBy([shiftX,shiftY],{duration:300,essential:true});
 }));
 const popupForSelection=activeMapPopup;
 popupForSelection.on('close',()=>{if(activeMapPopup!==popupForSelection)return;setHoveredMapStory(null);activeMapPopup=null;mapOverlayOpen=false;selectedPlace=null;selectedSource=null;window.ifisNewsFeed.clearMapStories();info.replaceChildren();info.textContent='Nachrichten geografisch entdecken. Wähle einen Ort auf der Karte.';info.classList.remove('has-selection');updateSourceLinks()});
 // Keep the clicked marker and its popup in view. Fitting to every reported place
 // moved the popup offscreen whenever a source covered distant events.
 requestAnimationFrame(()=>{hideFarSide();renderFlightArcs()});
}
let batchMarkers=[];
function addCountryBatchMarkers(){
 batchMarkers.forEach(marker=>{marker.remove();const index=markers.indexOf(marker);if(index>=0)markers.splice(index,1)});
 batchMarkers=[];
 const selectedHours=Number(document.getElementById('mapTimeRange').value)||24;
 document.getElementById('mapTimeValue').textContent=selectedHours.toLocaleString('de-DE')+' h';
 const now=Date.now();
 const bands=[{from:0,to:.5,label:'0–30 Min.'},{from:.5,to:2.5,label:'30 Min.–2,5 Std.'},{from:2.5,to:6,label:'2,5–6 Std.'},{from:6,to:12,label:'6–12 Std.'},{from:12,to:48,label:'12–48 Std.'}];
 const countries=new Map();
 if(colorMode.value==='unseen'){
  stories.forEach(story=>{
   const country=sourceCountry(story.querySelector('.meta')?.textContent||'');if(!country)return;
   const age=storyAge(story);if(age<0||age>48)return;
   const index=bands.findIndex(b=>age>=b.from&&age<b.to);if(index<0)return;
   if(!countries.has(country))countries.set(country,Array.from({length:5},()=>({total:0,unread:0})));
   const bucket=countries.get(country)[index];bucket.total++;if(!seenUrls.has(story.dataset.url))bucket.unread++;
  });
 }else for(const item of sourceBatches){
  const country=sourceCountry(item.source);if(!country)continue;
  const parsed=Date.parse(item.published_at.replace(' ','T')+'Z');if(!Number.isFinite(parsed))continue;
  const age=(now-parsed)/3600000;if(age<0||age>48)continue;
  const index=bands.findIndex(b=>age>=b.from&&age<b.to);if(index<0)continue;
  if(!countries.has(country))countries.set(country,[0,0,0,0,0]);
  countries.get(country)[index]+=Number(item.count||0);
 }
 countries.forEach((counts,country)=>{
  const center=countryCenters[country];if(!center)return;
  // One geographical anchor per country: buttons are arranged inside it, never as floating map offsets.
  const cluster=document.createElement('div');cluster.className='globe-source-cluster';
  counts.forEach((bucket,index)=>{
   const count=colorMode.value==='unseen'?bucket.total:bucket;
   if(!count)return;
   const band=bands[index],isSelected=band.from<selectedHours;
   const unread=colorMode.value==='unseen'?bucket.unread:count;
   if(colorMode.value==='unseen'&&!isSelected)return;
   const dot=document.createElement('button');dot.type='button';dot.className='globe-source-batch';
   dot.textContent=String(colorMode.value==='unseen'?unread:count);
   dot.style.backgroundColor=colorMode.value==='unseen'&&!unread?'#718096':isSelected?`hsl(34 80% ${46+index*7}%)`:'#718096';
   dot.style.color=colorMode.value==='unseen'&&!unread?'#fff':isSelected?'#17212e':'#fff';
   dot.style.opacity=isSelected?'1':'.65';
   dot.title=country+' · '+count+' veröffentlichte Artikel'+(colorMode.value==='unseen'?' · '+unread+' ungesehen':'')+' · '+band.label+(isSelected?' · im gewählten Zeitraum':' · älter als die Auswahl');
   dot.addEventListener('click',()=>{
    const matches=stories.filter(story=>sourceCountry(story.querySelector('.meta')?.textContent||'')===country&&storyAge(story)>=band.from&&storyAge(story)<band.to);
    const routePoints=matches.map(story=>groups.get(story.dataset.place)?.loc).filter(Boolean).map(loc=>[loc[2],loc[1]]);
    selectedPlace=null;selectedSource=null;updateSourceLinks();
    showMarkerArticles(center,country+' · '+band.label,matches,routePoints);
   });
   cluster.append(dot);
  });
  if(!cluster.childElementCount)return;
  const marker=new maplibregl.Marker({element:cluster,anchor:'center'}).setLngLat(center).addTo(map);
  markers.push(marker);batchMarkers.push(marker);
 });
 hideFarSide();
}
['showEventMarkers','showSourceMarkers','showSourceLinks'].forEach(id=>document.getElementById(id).addEventListener('change',()=>{if(map){hideFarSide();updateConnector()}}));
document.getElementById('mapTimeRange').addEventListener('input',()=>{if(map){if(mapOverlayOpen)closeMapOverlay();updateMapReadState();}else document.getElementById('mapTimeValue').textContent=Number(document.getElementById('mapTimeRange').value).toLocaleString('de-DE')+' h'});
const mapLegend=document.getElementById('mapLegend');
const mapBasemap=document.getElementById('mapBasemap');
const mapBrightness=document.getElementById('mapBrightness');
const mapAutoRotate=document.getElementById('mapAutoRotate');
const prefersDark=window.matchMedia('(prefers-color-scheme: dark)');
const storedMapPreference=key=>{try{return localStorage.getItem('ifis-news-map-'+key)}catch{return null}};
const rememberMapPreference=(key,value)=>{try{localStorage.setItem('ifis-news-map-'+key,value)}catch{}};
if(['political','geographic','satellite'].includes(storedMapPreference('type')))mapBasemap.value=storedMapPreference('type');
if(['auto','light','dark'].includes(storedMapPreference('brightness')))mapBrightness.value=storedMapPreference('brightness');
if(['0','1.5','4','9'].includes(storedMapPreference('rotation')))mapAutoRotate.value=storedMapPreference('rotation');
function isDarkMap(){return mapBrightness.value==='dark'||mapBrightness.value==='auto'&&prefersDark.matches}
function selectedMapStyle(){
 const dark=isDarkMap();
 panel.classList.toggle('map-dark',dark);
 panel.classList.toggle('map-light',!dark);
 if(mapBasemap.value==='satellite'){
  return {version:8,name:'Satellite',sources:{imagery:{type:'raster',tiles:['https://server.arcgisonline.com/ArcGIS/rest/services/World_Imagery/MapServer/tile/{z}/{y}/{x}'],tileSize:256,attribution:'Tiles © Esri, Maxar, Earthstar Geographics'}},layers:[{id:'satellite-imagery',type:'raster',source:'imagery',paint:{}}]};
 }
 if(mapBasemap.value==='political')return 'https://demotiles.maplibre.org/style.json';
 return {version:8,name:'Geografisch · Relief',sources:{topography:{type:'raster',tiles:['https://a.tile.opentopomap.org/{z}/{x}/{y}.png','https://b.tile.opentopomap.org/{z}/{x}/{y}.png','https://c.tile.opentopomap.org/{z}/{x}/{y}.png'],tileSize:256,maxzoom:17,attribution:'© OpenStreetMap-Mitwirkende · SRTM · OpenTopoMap (CC BY-SA)'}},layers:[{id:'geographic-terrain',type:'raster',source:'topography',paint:{}}]};
}
let styleSwitchSerial=0;
function applyMapStyle(){
 const style=selectedMapStyle();
 if(!map)return;
 map.getContainer().style.backgroundColor=isDarkMap()?'#111720':'#ffffff';
 const serial=++styleSwitchSerial;
 map.once('style.load',()=>{
  if(serial!==styleSwitchSerial)return;
  try{
   map.setProjection({type:flatProjection?'mercator':'globe'});
   addNightLayer();restoreSourceLineLayer();updateMapReadState();updateConnector();
  }catch(err){console.warn('Kartenebenen konnten nicht vollständig wiederhergestellt werden',err)}
 });
 map.setStyle(style);
}
mapBasemap.addEventListener('change',()=>{rememberMapPreference('type',mapBasemap.value);applyMapStyle()});
mapBrightness.addEventListener('change',()=>{rememberMapPreference('brightness',mapBrightness.value);applyMapStyle()});
if(prefersDark.addEventListener)prefersDark.addEventListener('change',()=>{if(mapBrightness.value==='auto')applyMapStyle()});
document.addEventListener('pointerdown',event=>{if(mapLegend.open&&!mapLegend.contains(event.target))mapLegend.open=false});
document.addEventListener('keydown',event=>{if(event.key==='Escape')mapLegend.open=false});
let rotationLast=0,rotationHoldUntil=0;
function rotateMap(timestamp){
 if(map&&mapAutoRotate.value!=='0'&&!flatProjection&&document.visibilityState==='visible'){
  if(rotationLast&&timestamp>=rotationHoldUntil&&map.isStyleLoaded()){
   const delta=Math.min(0.07,(timestamp-rotationLast)/1000);
   if(delta>0)map.setCenter([(map.getCenter().lng-Number(mapAutoRotate.value)*delta+540)%360-180,map.getCenter().lat]);
  }
 }
 rotationLast=timestamp;
 window.requestAnimationFrame(rotateMap);
}
mapAutoRotate.addEventListener('change',()=>rememberMapPreference('rotation',mapAutoRotate.value));
window.requestAnimationFrame(rotateMap);
let flatProjection=false;
document.getElementById('mapProjectionToggle').addEventListener('click',event=>{event.preventDefault();event.stopPropagation();
 flatProjection=!flatProjection;
 const control=document.getElementById('mapProjectionToggle');
 control.setAttribute('aria-pressed',String(flatProjection));
 control.title=flatProjection?'Globus anzeigen':'Flache Karte anzeigen';
 control.setAttribute('aria-label',flatProjection?'Globus anzeigen':'Flache Karte anzeigen');
 control.firstChild.textContent=flatProjection?'◉ ':'▤ ';
 if(map){try{map.setProjection({type:flatProjection?'mercator':'globe'});}catch(e){console.warn('Kartenprojektion konnte nicht gewechselt werden',e)}updateMapReadState();updateConnector();}
});
const sourceImprintAudit=<?=json_encode($sourceImprintAudit,JSON_HEX_TAG|JSON_HEX_AMP|JSON_HEX_APOS|JSON_HEX_QUOT|JSON_UNESCAPED_UNICODE)?>;
const sourceLocations={
 'IFIS · Artikel':{name:'IFIS · Lübeck',coordinates:[10.686,53.866]},
 'The Guardian':{name:'The Guardian · London',coordinates:[-0.1276,51.5072]},
 'Guardian World':{name:'The Guardian · London',coordinates:[-0.1276,51.5072]},
 'Guardian Global Development':{name:'The Guardian · London',coordinates:[-0.1276,51.5072]},
 'BBC News':{name:'BBC · London',coordinates:[-0.1276,51.5072]},
 'Deutsche Welle':{name:'Deutsche Welle · Bonn',coordinates:[7.0982,50.7374]},
 'SPIEGEL':{name:'DER SPIEGEL · Hamburg',coordinates:[10.0007,53.5503]},
 'Al Jazeera':{name:'Al Jazeera · Doha',coordinates:[51.531,25.2854]},
 'France 24':{name:'France 24 · Issy-les-Moulineaux',coordinates:[2.273,48.823]},
 'Euronews':{name:'Euronews · Lyon',coordinates:[4.8357,45.764]},
 'NPR':{name:'NPR · Washington',coordinates:[-77.0369,38.9072]},
 'New York Times':{name:'The New York Times · New York',coordinates:[-73.9857,40.7484]},
 'NASA':{name:'NASA · Washington',coordinates:[-77.0369,38.9072]},
 'NOAA':{name:'NOAA · Silver Spring',coordinates:[-77.0261,38.9907]},
 'ScienceDaily':{name:'ScienceDaily · Rockville',coordinates:[-77.1528,39.084]},
 'Phys.org':{name:'Phys.org · Isle of Man',coordinates:[-4.4818,54.1523]},
 'IEEE Spectrum':{name:'IEEE Spectrum · New York',coordinates:[-73.9857,40.7484]}
};
function sourceFor(story){const name=story.dataset.source||story.querySelector('.meta')?.textContent||'';return Object.entries(sourceLocations).find(([key])=>name.includes(key))?.[1]||null}
function storiesForSource(source){return stories.filter(story=>sourceFor(story)?.name===source.name)}
// A small source/event flag pair directly after "Karte" on every news card.
stories.forEach(story=>{
 const button=story.querySelector('.story-map-link');
 if(!button||story.querySelector('.story-location-flags'))return;
 const source=sourceFor(story);
 const place=story.dataset.place||'Nicht eindeutig erkannt';
 const pair=document.createElement('span');pair.className='story-location-flags';
 const publisher=document.createElement('span');publisher.className='story-location-flag';
 publisher.textContent=newsSourceFlag(source);
 publisher.title='Quellenstandort: '+(source?.name||'nicht bekannt');
 publisher.setAttribute('aria-label',publisher.title);
 const event=document.createElement('span');event.className='story-location-flag';
 event.textContent=newsPlaceFlag(place);
 event.title='Ereignisort: '+place;
 event.setAttribute('aria-label',event.title);
 pair.append(publisher,event);
 button.after(pair);
});

// Lay out different event/source markers around their projected shared location.
// Offsets are screen pixels, so the underlying geographic coordinates stay exact.
function spreadNearbyMarkers(){
 if(!map)return;
 if(!flatProjection){
  // Zero pixel offsets in globe mode: every dot stays on its geographic coordinate.
  markers.forEach(marker=>{
   const el=marker.getElement();
   if(!el.classList.contains('globe-marker')&&!el.classList.contains('globe-source'))return;
   marker._ifisScreenOffset=[0,0];
   const previous=marker.getOffset();
   if(previous.x!==0||previous.y!==0)marker.setOffset([0,0]);
  });
  return;
 }
 const visible=markers.filter(marker=>{
  const el=marker.getElement();
  return (el.classList.contains('globe-marker')||el.classList.contains('globe-source'))
   &&el.style.visibility!=='hidden';
 });
 const zoomScale=Math.max(.65,Math.min(1.65,.78+map.getZoom()*.14));
 const iconRadius=18*zoomScale;
 const origins=visible.map(marker=>({marker,point:map.project(marker.getLngLat())}));
 const clusters=[];
 const captureRadius=Math.max(22,iconRadius*2+6);
 for(const item of origins){
  const found=clusters.find(cluster=>Math.hypot(item.point.x-cluster.center.x,item.point.y-cluster.center.y)<captureRadius);
  if(!found){clusters.push({members:[item],center:{...item.point}});continue;}
  found.members.push(item);
  const n=found.members.length;
  found.center.x+=(item.point.x-found.center.x)/n;
  found.center.y+=(item.point.y-found.center.y)/n;
 }
 // Stable member order avoids icons exchanging places while the globe rotates.
 for(const cluster of clusters){
  const items=cluster.members;
  const count=items.length;
  const ringRadius=count>1?Math.max(iconRadius*2.1,(iconRadius*2+8)*count/(2*Math.PI)):0;
  items.forEach(({marker,point},i)=>{
   let x=point.x,y=point.y;
   if(count>1){
    const angle=-Math.PI/2+2*Math.PI*i/count;
    x=cluster.center.x+Math.cos(angle)*ringRadius;
    y=cluster.center.y+Math.sin(angle)*ringRadius;
   }
   const offset=[Math.round(x-point.x),Math.round(y-point.y)];
   marker._ifisScreenOffset=offset;
   const previous=marker.getOffset();
   if(Math.abs(previous.x-offset[0])>.5||Math.abs(previous.y-offset[1])>.5)marker.setOffset(offset);
  });
 }
}
function displayPointForMarker(coordinates,sourceName=null){
 const candidates=markers.filter(marker=>{
  const p=marker.getLngLat();
  const matched=Math.abs(p.lng-coordinates[0])<.0001&&Math.abs(p.lat-coordinates[1])<.0001;
  if(!matched)return false;
  const el=marker.getElement();
  return sourceName?el.classList.contains('globe-source')&&el._ifisLabel===sourceName:el.classList.contains('globe-marker');
 });
 const marker=candidates[0];
 const point=map.project(coordinates),offset=marker?marker._ifisScreenOffset||[0,0]:[0,0];
 return {x:point.x+offset[0],y:point.y+offset[1]};
}
function hideFarSide(){if(!map)return;
 panel.classList.toggle('is-globe',!flatProjection);
 panel.classList.toggle('is-flat-map',flatProjection);const center=map.getCenter(),rad=Math.PI/180,a=center.lat*rad,b=center.lng*rad;markers.forEach(marker=>{const el=marker.getElement(),p=marker.getLngLat(),lat=p.lat*rad,lon=p.lng*rad;const facing=flatProjection||Math.sin(a)*Math.sin(lat)+Math.cos(a)*Math.cos(lat)*Math.cos(lon-b)>0.025;const source=el.classList.contains('globe-source')||el.classList.contains('globe-source-cluster');const enabled=source?document.getElementById('showSourceMarkers').checked:document.getElementById('showEventMarkers').checked;
 const ageVisible=!el._ifisStories||(el._ifisTimeStories||mapTimeStories(el._ifisStories)).length>0;const screen=map.project(p);const bounds=map.getContainer().getBoundingClientRect();const inside=screen.x>=18&&screen.y>=18&&screen.x<=bounds.width-18&&screen.y<=bounds.height-18;el.style.visibility=facing&&enabled&&inside&&ageVisible?'visible':'hidden';el.style.opacity=source?(focusMode.value==='source'?'1':'.72'):(focusMode.value==='event'?'1':'.55');const zoomScale=Math.max(.65,Math.min(1.65,.78+map.getZoom()*.14));el.style.setProperty('--marker-scale',String(zoomScale));el.style.zIndex=source?(focusMode.value==='source'?'5':'2'):(focusMode.value==='event'?'5':'2')});spreadNearbyMarkers();updateSourceLinks();connector.style.display=(hoveredMapStory||document.getElementById('showSourceLinks').checked||selectedPlace||selectedSource)?'block':'none';}
function nightGeoJSON(){const now=new Date(),jd=now.getTime()/86400000+2440587.5,n=jd-2451545,mean=(280.46+0.9856474*n)*Math.PI/180,anomaly=(357.528+0.9856003*n)*Math.PI/180,lambda=mean+(1.915*Math.sin(anomaly)+0.020*Math.sin(2*anomaly))*Math.PI/180,epsilon=(23.439-0.0000004*n)*Math.PI/180,decl=Math.asin(Math.sin(epsilon)*Math.sin(lambda)),ra=Math.atan2(Math.cos(epsilon)*Math.sin(lambda),Math.cos(lambda)),gmst=(280.46061837+360.98564736629*(jd-2451545))*Math.PI/180,subLon=((ra-gmst)*180/Math.PI+540)%360-180;const features=[],rad=Math.PI/180;for(let lat=-90;lat<90;lat+=5){for(let lon=-180;lon<180;lon+=5){const phi=(lat+2.5)*rad,h=(lon+2.5-subLon)*rad,elevation=Math.asin(Math.sin(phi)*Math.sin(decl)+Math.cos(phi)*Math.cos(decl)*Math.cos(h))*180/Math.PI;if(elevation>=0)continue;features.push({type:'Feature',properties:{opacity:Math.min(.68,Math.max(.08,-elevation/22*.30))},geometry:{type:'Polygon',coordinates:[[[lon,lat],[lon+5,lat],[lon+5,lat+5],[lon,lat+5],[lon,lat]]]}})}}return {type:'FeatureCollection',features}}
function addNightLayer(){if(map.getSource('night'))return;map.addSource('night',{type:'geojson',data:nightGeoJSON()});map.addLayer({id:'night',type:'fill',source:'night',layout:{visibility:nightToggle.checked?'visible':'none'},paint:{'fill-color':'#071326','fill-opacity':['get','opacity']}})}
nightToggle.addEventListener('change',()=>{if(map?.getLayer('night'))map.setLayoutProperty('night','visibility',nightToggle.checked?'visible':'none')});
let flightLayer=null;
function visibleHemisphere(coordinates){if(flatProjection)return true;const c=map.getCenter(),r=Math.PI/180;return Math.sin(c.lat*r)*Math.sin(coordinates[1]*r)+Math.cos(c.lat*r)*Math.cos(coordinates[1]*r)*Math.cos((coordinates[0]-c.lng)*r)>0.025}
function renderFlightArcs(){
 if(!map||!flightLayer)return;
 flightLayer.replaceChildren();
 if(!(hoveredMapStory||document.getElementById('showSourceLinks').checked||selectedPlace||selectedSource)||!document.getElementById('showEventMarkers').checked||!document.getElementById('showSourceMarkers').checked)return;
 const size=map.getContainer();flightLayer.setAttribute('viewBox','0 0 '+size.clientWidth+' '+size.clientHeight);
 const unique=new Set();
 groups.forEach((group,place)=>{
  if(hoveredMapStory&&hoveredMapStory.dataset.place!==place)return;
  if(!hoveredMapStory&&selectedPlace&&place!==selectedPlace)return;
  group.stories.forEach(story=>{
   if(hoveredMapStory&&hoveredMapStory!==story)return;
   const source=sourceFor(story);if(!source||(!hoveredMapStory&&selectedSource&&source.name!==selectedSource))return;
   const key=source.name+'|'+place;if(unique.has(key))return;unique.add(key);
   const from=source.coordinates,to=[group.loc[2],group.loc[1]];
   if(!visibleHemisphere(from)||!visibleHemisphere(to))return;
   const a=displayPointForMarker(from,source.name),b=displayPointForMarker(to),dx=b.x-a.x,dy=b.y-a.y,distance=Math.hypot(dx,dy);
   if(distance<3)return;
   const lift=Math.min(110,Math.max(10,distance*.16));
   const mx=(a.x+b.x)/2,my=(a.y+b.y)/2;
   const nx=-dy/distance,ny=dx/distance;
   const upward=ny>0?-1:1;
   // Crop both ends against each marker's outer border, not its centre:
   // no line should cross a marker's count, even on the hovered route.
   const control={x:mx+nx*lift*upward,y:my+ny*lift*upward-lift*.3};
   const radiusScale=Math.max(.65,Math.min(1.65,.78+map.getZoom()*.14));
   const sourceRadius=flatProjection?14*radiusScale+2:5.5,eventRadius=flatProjection?16*radiusScale+2:5.5;
   const startLength=Math.hypot(control.x-a.x,control.y-a.y)||1;
   const endLength=Math.hypot(control.x-b.x,control.y-b.y)||1;
   if(distance<=sourceRadius+eventRadius+2)return;
   const startX=a.x+(control.x-a.x)/startLength*sourceRadius;
   const startY=a.y+(control.y-a.y)/startLength*sourceRadius;
   const endX=b.x+(control.x-b.x)/endLength*eventRadius;
   const endY=b.y+(control.y-b.y)/endLength*eventRadius;
   const curve=document.createElementNS('http://www.w3.org/2000/svg','path');
   curve.setAttribute('d','M '+startX+' '+startY+' Q '+control.x+' '+control.y+' '+endX+' '+endY);
   curve.setAttribute('fill','none');curve.setAttribute('stroke','#e59a35');curve.setAttribute('stroke-width',flatProjection?(hoveredMapStory?'3.1':'1.7'):(hoveredMapStory?'1.8':'0.9'));curve.setAttribute('stroke-opacity',flatProjection?(hoveredMapStory?'.96':'.82'):(hoveredMapStory?'.82':'.48'));curve.setAttribute('stroke-linecap','round');
   flightLayer.append(curve);
  });
 });
}
function updateSourceLinks(){
 if(!map||!map.getLayer('news-source-links'))return;
 map.setLayoutProperty('news-source-links','visibility','none');
 renderFlightArcs();
}
let mapSourceLines={type:'FeatureCollection',features:[]};
function restoreSourceLineLayer(){if(!map.getSource('news-source-links'))map.addSource('news-source-links',{type:'geojson',data:mapSourceLines});if(!map.getLayer('news-source-links'))map.addLayer({id:'news-source-links',type:'line',source:'news-source-links',layout:{visibility:'none'},paint:{'line-color':'#e59a35','line-width':2,'line-opacity':0.85,'line-dasharray':[2,2]}});}
// Shared accessible hover card. Keep it outside the MapLibre marker transforms
// so zooming and circular marker spreading do not distort the text.
const markerHoverCard=document.createElement('div');
markerHoverCard.className='map-marker-hover';
markerHoverCard.setAttribute('role','status');
markerHoverCard.hidden=true;
document.body.append(markerHoverCard);
let hoveredMarkerButton=null;
function hideMarkerHover(){hoveredMarkerButton=null;markerHoverCard.hidden=true;}
function positionMarkerHover(button,clientX,clientY){
 const rect=button.getBoundingClientRect();
 const x=Number.isFinite(clientX)?clientX:rect.left+rect.width/2;
 const y=Number.isFinite(clientY)?clientY:rect.top+rect.height/2;
 markerHoverCard.style.left=Math.max(9,Math.min(window.innerWidth-markerHoverCard.offsetWidth-9,x+16))+'px';
 markerHoverCard.style.top=Math.max(9,Math.min(window.innerHeight-markerHoverCard.offsetHeight-9,y+18))+'px';
}
function showMarkerHover(button,event){
 if(button.style.visibility==='hidden')return;
 hoveredMarkerButton=button;
 const items=button._ifisTimeStories||mapTimeStories(button._ifisStories||[]);
 const source=button.classList.contains('globe-source');
 const count=new Set(items.map(story=>source?story.dataset.place:story.dataset.source).filter(Boolean)).size;
 markerHoverCard.replaceChildren();
 const heading=document.createElement('strong');heading.textContent=button._ifisLabel||'Unbekannter Ort';
 const type=document.createElement('span');type.className='map-marker-hover-type';type.textContent=source?'Quellenstandort':'Ereignisort';
 const details=document.createElement('span');details.textContent=items.length+' Artikel · '+count+(source?' Ereignisorte':' Quellen');
 const note=document.createElement('small');
 note.textContent=source?(button._ifisAuditNote||'Redaktionsstandort vorläufig zugeordnet'):'Ortszuordnung aus Meldungstitel und Kurztext';
 markerHoverCard.append(type,heading,details,note);
 markerHoverCard.hidden=false;
 positionMarkerHover(button,event?.clientX,event?.clientY);
}
function attachMarkerHover(button){
 button.removeAttribute('title');
 button.addEventListener('pointerenter',event=>{if(event.pointerType!=='touch')showMarkerHover(button,event)});
 button.addEventListener('pointermove',event=>{if(hoveredMarkerButton===button)positionMarkerHover(button,event.clientX,event.clientY)});
 button.addEventListener('pointerleave',()=>{if(hoveredMarkerButton===button)hideMarkerHover()});
 button.addEventListener('focus',()=>showMarkerHover(button));
 button.addEventListener('blur',()=>{if(hoveredMarkerButton===button)hideMarkerHover()});
 button.addEventListener('click',hideMarkerHover);
}
function addSourceLinks(){const features=[],sources=new Map();groups.forEach(g=>g.stories.forEach(story=>{const source=sourceFor(story);if(!source)return;const destination=[g.loc[2],g.loc[1]];if(Math.abs(destination[0]-source.coordinates[0])+Math.abs(destination[1]-source.coordinates[1])<0.1)return;sources.set(source.name,source);features.push({type:'Feature',geometry:{type:'LineString',coordinates:[source.coordinates,destination]},properties:{source:source.name,event:g.loc[0]}})}));
stories.forEach(story=>{const source=sourceFor(story);if(source)sources.set(source.name,source);});
mapSourceLines={type:'FeatureCollection',features};
restoreSourceLineLayer();
groups.forEach((group,place)=>{
 const reportingSources=new Set(group.stories.map(story=>story.querySelector('.meta')?.textContent?.split(' · ')[0]?.trim()).filter(Boolean));
 const dot=document.createElement('button');dot.type='button';dot.className='globe-marker';
 dot._ifisStories=group.stories;dot._ifisLabel=place;
 dot.textContent=String(group.stories.length);
 dot.title=place+' · '+reportingSources.size+' berichtende Quellen · '+group.stories.length+' Meldungen';
 dot.setAttribute('aria-label',dot.title);
 attachMarkerHover(dot);
 dot.addEventListener('click',()=>{
  const selected=dot._ifisTimeStories||mapTimeStories(group.stories);if(!selected.length)return;
  const selectedSources=new Set(selected.map(story=>story.dataset.source).filter(Boolean));
  selectedPlace=place;selectedSource=null;updateSourceLinks();
  const routePoints=[...new Map(selected.map(story=>sourceFor(story)).filter(Boolean).map(src=>[src.name,src.coordinates])).values()];
  showMarkerArticles([group.loc[2],group.loc[1]],place,selected,routePoints,selectedSources.size+' Quellen',{kind:'event',name:place});
 });
 markers.push(new maplibregl.Marker({element:dot,anchor:'center'}).setLngLat([group.loc[2],group.loc[1]]).addTo(map));
});

sources.forEach(source=>{
 const related=storiesForSource(source);
 const places=new Set(related.map(story=>story.dataset.place).filter(place=>place&&groups.has(place)));
 const dot=document.createElement('button');dot.type='button';dot.className='globe-source';
 dot._ifisStories=related;dot._ifisLabel=source.name;
 dot.textContent=String(places.size);
 const audits=Object.entries(sourceLocations).filter(([,loc])=>loc===source).map(([key])=>sourceImprintAudit[key]).filter(Boolean);
 const audit=audits.find(item=>item.status==='address_city_found')||audits[0];
 dot.title=source.name+' · '+places.size+' Ereignisorte · '+related.length+' Artikel · Standorthinweis: '+(audit?.status==='address_city_found'?'Adresshinweis auf offizieller Seite gefunden (noch redaktionell zu bestätigen)':audit?'Impressum ohne eindeutigen Standortnachweis':'redaktionell hinterlegt, noch nicht geprüft')+(audit?.evidence_url?' · '+audit.evidence_url:'');
 dot._ifisAuditNote=audit?.status==='address_city_found'?'Adresshinweis auf offizieller Website gefunden · nicht abschließend geprüft':'Standort redaktionell zugeordnet · noch nicht bestätigt';
 attachMarkerHover(dot);
 dot.addEventListener('click',()=>{
  const selected=dot._ifisTimeStories||mapTimeStories(related);if(!selected.length)return;
  const visitedPlaces=new Set(selected.map(story=>story.dataset.place).filter(Boolean));
  selectedPlace=null;selectedSource=source.name;updateSourceLinks();
  const points=selected.map(story=>groups.get(story.dataset.place)?.loc).filter(Boolean).map(loc=>[loc[2],loc[1]]);
  showMarkerArticles(source.coordinates,source.name,selected,points,visitedPlaces.size+' Ereignisorte',{kind:'source',name:source.name});
 });
 markers.push(new maplibregl.Marker({element:dot,anchor:'center'}).setLngLat(source.coordinates).addTo(map));
});
}
let selectedPlace=null;
let selectedSource=null;
let mapOverlayOpen=false;
function closeMapOverlay(){setHoveredMapStory(null);window.ifisNewsFeed.clearMapStories();if(activeMapPopup){const popup=activeMapPopup;activeMapPopup=null;popup.remove();}if(!mapOverlayOpen)return;mapOverlayOpen=false;selectedPlace=null;selectedSource=null;info.replaceChildren();updateSourceLinks();info.classList.remove('has-selection');info.textContent='Nachrichten geografisch entdecken. Wähle einen Ort auf der Karte.';connector.style.display='block';}
const connector=document.getElementById('globeConnector'),connectorPath=document.getElementById('globeConnectorPath'),connectorEnd=document.getElementById('globeConnectorEnd');
function updateConnector(){connector.style.display=(hoveredMapStory||document.getElementById('showSourceLinks').checked||selectedPlace||selectedSource)?'block':'none';renderFlightArcs();return;
 if(!map||!selectedPlace||!groups.has(selectedPlace) ){connector.style.display='none';return;}
 const g=groups.get(selectedPlace),pt=map.project([g.loc[2],g.loc[1]]);
 const box=info.getBoundingClientRect(),container=panel.getBoundingClientRect();
 const x=box.right-container.left,y=Math.max(box.top-container.top+20,Math.min(pt.y,box.bottom-container.top-20));
 if(pt.x<0||pt.x>container.width||pt.y<0||pt.y>container.height){connector.style.display='none';return;}
 connector.style.display='block';connector.setAttribute('viewBox',`0 0 ${container.width} ${container.height}`);
 connectorPath.setAttribute('d',`M ${x} ${y} C ${x+55} ${y}, ${pt.x-55} ${pt.y}, ${pt.x} ${pt.y}`);
 connectorEnd.setAttribute('cx',pt.x);connectorEnd.setAttribute('cy',pt.y);
}
function choose(place){
 const group=groups.get(place);if(!group||!map)return;
 selectedPlace=place;selectedSource=null;updateSourceLinks();
 const reportingSources=new Set(group.stories.map(story=>story.dataset.source).filter(Boolean));
 showMarkerArticles([group.loc[2],group.loc[1]],place,group.stories,[...new Map(group.stories.map(story=>sourceFor(story)).filter(Boolean).map(src=>[src.name,src.coordinates])).values()],reportingSources.size+' Quellen',{kind:'event',name:place});
}
// The selected routes remain visible until the same marker is clicked again.
// Route selection is controlled by its origin marker.
// Clicking outside the map does not reset the route selection.
function updateHeaderUnderline(){
 const nav=document.querySelector('.topbar-inner .topnav');
 if(!nav)return;
 const section=nav.querySelector('a.active');
 const selected=section?[section]:[];
 if(panel.classList.contains('open'))selected.push(btn);
 if(!selected.length)return;
 const navBounds=nav.getBoundingClientRect();
 const bounds=selected.map(el=>el.getBoundingClientRect());
 const start=Math.min(...bounds.map(rect=>rect.left))-navBounds.left;
 const end=Math.max(...bounds.map(rect=>rect.right))-navBounds.left;
 nav.style.setProperty('--selection-left',Math.round(start)+'px');
 nav.style.setProperty('--selection-width',Math.round(end-start)+'px');
}
window.addEventListener('resize',updateHeaderUnderline);
if(document.fonts?.ready)document.fonts.ready.then(updateHeaderUnderline);
function restoreSharedCollection(){
 if(!sharedSelection||!map)return;
 let marker=markers.find(candidate=>{
  const button=candidate.getElement();
  return button._ifisLabel===sharedSelection.name&&button.classList.contains(sharedSelection.kind==='source'?'globe-source':'globe-marker');
 });
 if(!marker){
  const note=document.createElement('p');note.className='shared-collection-notice';
  note.textContent='Diese Artikelsammlung ist in der aktuellen Nachrichtenansicht nicht mehr verfügbar.';
  panel.after(note);
  return;
 }
 const button=marker.getElement();
 const matches=button._ifisTimeStories||mapTimeStories(button._ifisStories||[]);
 if(!matches.length){
  document.getElementById('mapTimeRange').value='48';
  updateMapReadState();
  // Preserve usefulness of older shared collections instead of opening nothing.
  if(!(button._ifisTimeStories||[]).length&&button._ifisStories?.length)
   button._ifisTimeStories=button._ifisStories;
 }
 // A restored marker may be on the far hemisphere. Face it before opening.
 map.jumpTo({center:marker.getLngLat(),zoom:Math.max(1.8,map.getZoom())});
 requestAnimationFrame(()=>button.click());
}
function openNewsMap(scrollToMap=true){panel.classList.add('open');btn.classList.add('active');btn.setAttribute('aria-pressed','true');updateHeaderUnderline();requestAnimationFrame(updateHeaderUnderline);if(scrollToMap)panel.scrollIntoView({behavior:'smooth',block:'start'});if(map){map.resize();return;}if(!window.maplibregl){info.textContent='Die Kartenbibliothek konnte nicht geladen werden.';return;}map=new maplibregl.Map({container:'newsGlobe',style:selectedMapStyle(),center:[10,20],zoom:1.15,attributionControl:true});
map.getContainer().style.backgroundColor=isDarkMap()?'#111720':'#ffffff';
map.getCanvas().addEventListener('pointerdown',()=>{rotationHoldUntil=performance.now()+4500});
map.getCanvas().addEventListener('wheel',()=>{rotationHoldUntil=performance.now()+4500},{passive:true});
map.on('dragstart',()=>{rotationHoldUntil=performance.now()+4500});map.on('load',()=>{try{map.setProjection({type:flatProjection?'mercator':'globe'});}catch(e){};addNightLayer();addSourceLinks();flightLayer=document.createElementNS('http://www.w3.org/2000/svg','g');connector.append(flightLayer);
// The flight-line SVG belongs inside MapLibre: above tiles but BELOW marker buttons.
map.getContainer().append(connector);updateMapReadState();map.on('move',()=>{hideFarSide();updateConnector()});map.on('resize',()=>{updateConnector();renderFlightArcs();});hideFarSide();if(!groups.size)info.textContent='Noch keine eindeutigen Ereignisorte erkannt.';restoreSharedCollection();});}
btn.addEventListener('click',()=>openNewsMap(true));
// Direct visits begin with the map; choosing Potential or World explicitly
// requests an article-only view with ?map=0. Map then opens that same section.
if(new URLSearchParams(location.search).get('map')!=='0')openNewsMap(false);
requestAnimationFrame(updateHeaderUnderline);
})();
</script></body></html>