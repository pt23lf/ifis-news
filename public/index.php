<?php
declare(strict_types=1);
header('Content-Type: text/html; charset=utf-8');
$view = ($_GET['view'] ?? 'world') === 'ifis' ? 'ifis' : 'world';
$items = [];
$dbPath = dirname(__DIR__) . '/storage/news.sqlite';
if (is_file($dbPath) && extension_loaded('pdo_sqlite')) {
 try {
  $db = new PDO('sqlite:' . $dbPath);
  $query = $db->prepare('SELECT title,url,source,published_at,summary,image_url FROM articles WHERE section=:section ORDER BY published_at DESC LIMIT 120');
  $query->execute(['section'=>$view]);
  $items = $query->fetchAll(PDO::FETCH_ASSOC);
 } catch (Throwable $e) { error_log($e->getMessage()); }
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
?><!doctype html>
<html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><meta name="theme-color" content="#ffffff">
<link rel="icon" href="data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 64 64%27%3E%3Crect width=%2764%27 height=%2764%27 rx=%2714%27 fill=%27%232468d5%27/%3E%3Ctext x=%2732%27 y=%2743%27 text-anchor=%27middle%27 font-family=%27Arial%27 font-weight=%27bold%27 font-size=%2733%27 fill=%27white%27%3EI%3C/text%3E%3C/svg%3E"><title><?= $view==='ifis'?'IFIS-Radar':'Weltlage' ?> · IFIS News</title>
<style>
:root{font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",Inter,Arial,sans-serif;color-scheme:light;--bg:#fff;--text:#17212e;--muted:#697587;--line:#e7eaf0;--soft:#f5f7fa;--accent:#2468d5;--hover:#eef4ff}
*{box-sizing:border-box}body{margin:0;background:var(--bg);color:var(--text);font-size:15px;line-height:1.48}a{color:inherit;text-decoration:none}button,input{font:inherit}button{cursor:pointer}button:focus-visible,a:focus-visible,input:focus-visible{outline:2px solid var(--accent);outline-offset:2px}.topbar{height:65px;border-bottom:1px solid var(--line);display:flex;align-items:center;gap:32px;padding:0 30px;position:sticky;top:0;background:var(--bg);z-index:5}.brand{font-size:25px;letter-spacing:-1px;white-space:nowrap}.brand strong{font-weight:780}.topnav{display:flex;height:100%;gap:18px}.topnav a{padding:19px 12px 16px;border-bottom:3px solid transparent;white-space:nowrap}.topnav a.active{border-color:var(--accent);font-weight:650}.top-actions{margin-left:auto;display:flex;gap:15px;align-items:center}.search{border:1px solid var(--line);background:var(--soft);border-radius:24px;padding:9px 15px;min-width:270px;color:var(--text)}.search::placeholder{color:var(--muted)}.iconbtn{border:0;background:transparent;color:var(--muted);padding:7px 9px;border-radius:8px}.iconbtn:hover,.side button:hover,.chip:hover{background:var(--hover)}.layout{display:grid;grid-template-columns:210px minmax(0,1fr) 270px;gap:28px;max-width:1520px;margin:auto;padding:25px 24px 100px}.side{border-right:1px solid var(--line);padding-right:15px}.side h3{font-size:12px;margin:23px 10px 8px;color:var(--muted);font-weight:650;text-transform:uppercase;letter-spacing:.06em}.side button{display:block;width:100%;text-align:left;border:0;background:transparent;padding:10px 12px;border-radius:9px;color:var(--muted)}.side button.active{background:var(--hover);color:var(--accent);font-weight:650}.feedhead{display:flex;align-items:baseline;justify-content:space-between;border-bottom:1px solid var(--line);padding:6px 0 13px;gap:12px}.feedhead h1{font-size:23px;letter-spacing:-.5px;margin:0}.muted{color:var(--muted)}.feedhead small{color:var(--muted);white-space:nowrap}.chips{display:flex;gap:8px;overflow:auto;padding:14px 0 16px;scrollbar-width:none}.chips::-webkit-scrollbar{display:none}.chip{border:1px solid var(--line);border-radius:18px;background:var(--bg);padding:6px 12px;white-space:nowrap;font-size:13px;color:var(--muted)}.chip.active{background:var(--text);color:var(--bg);border-color:var(--text)}.story{display:grid;grid-template-columns:120px minmax(0,1fr) auto;gap:17px;padding:17px 0;border-bottom:1px solid var(--line);align-items:start}.story.feature{grid-template-columns:minmax(0,1fr) auto;padding-top:8px}.story h2{font-size:17px;line-height:1.3;letter-spacing:-.25px;margin:3px 0 5px}.story.feature h2{font-size:23px}.story h2 a:hover{text-decoration:underline}.summary{color:var(--muted);font-size:14px;margin:0 0 7px;display:-webkit-box;-webkit-line-clamp:2;-webkit-box-orient:vertical;overflow:hidden}.meta{font-size:12px;color:var(--muted)}.cat{font-size:11px;font-weight:700;color:var(--accent)}.thumb{background:var(--soft);width:120px;height:82px;border-radius:9px;display:flex;align-items:center;justify-content:center;color:#a3afbd;font-size:24px}.story.feature .thumb{display:none}.story:not(.feature):not(:has(.thumb)){grid-template-columns:minmax(0,1fr) auto}.story>div:nth-child(2){min-width:0}.thumb{object-fit:cover}.story-actions{display:flex;gap:0}.story-actions button{border:0;background:transparent;padding:5px;color:var(--muted);font-size:20px}.story-actions button:hover,.story-actions button.saved{color:var(--accent)}.panel{border:1px solid var(--line);border-radius:12px;padding:16px;margin-bottom:15px}.panel h2{font-size:15px;margin:0 0 12px}.panel p{color:var(--muted);font-size:13px}.panel a{color:var(--accent)}.panel button{border:1px solid var(--line);border-radius:18px;padding:6px 10px;background:var(--bg);margin:0 5px 8px 0;color:var(--muted)}.empty{text-align:center;padding:75px 15px;color:var(--muted)}.empty .symbol{font-size:36px;color:#a6b1bf}.empty h2{color:var(--text);font-size:20px}.empty p{max-width:420px;margin:auto}.bottomnav{display:none}.hide{display:none!important}.subtle{font-size:12px;color:var(--muted)}.sectiontitle{font-size:14px;font-weight:650;margin:10px 0 0}.sidefooter{font-size:12px;color:var(--muted);padding:15px 10px}
@media(max-width:1150px){.layout{grid-template-columns:180px minmax(0,1fr);gap:22px}.right{display:none}}
@media(max-width:720px){.topbar{height:60px;padding:0 15px;gap:8px}.brand{font-size:23px}.topnav{position:absolute;top:60px;left:0;right:0;height:47px;justify-content:center;border-bottom:1px solid var(--line);background:var(--bg)}.topnav a{padding:11px 25px 9px}.top-actions{gap:0}.search{position:absolute;left:12px;right:12px;top:112px;width:calc(100% - 24px);min-width:0;display:none;z-index:4;background:var(--bg)}.search.open{display:block}.search-toggle{display:block}.layout{display:block;padding:61px 15px 90px}.side{display:none}.feedhead h1{font-size:20px}.feedhead{padding-top:5px}.feedhead small{font-size:11px}.story{grid-template-columns:83px minmax(0,1fr) auto;gap:10px;padding:14px 0}.thumb{width:83px;height:75px}.story h2{font-size:15px}.story.feature{grid-template-columns:minmax(0,1fr) auto}.story.feature h2{font-size:20px}.story:not(.feature):not(:has(.thumb)){grid-template-columns:minmax(0,1fr) auto}.summary{font-size:13px;-webkit-line-clamp:2}.story-actions{flex-direction:column}.story-actions button{font-size:18px;padding:2px}.bottomnav{position:fixed;bottom:0;left:0;right:0;display:flex;justify-content:space-around;background:var(--bg);border-top:1px solid var(--line);padding:7px 0 max(8px,env(safe-area-inset-bottom));z-index:6}.bottomnav button,.bottomnav a{border:0;background:none;text-align:center;font-size:11px;color:var(--muted);min-width:60px}.bottomnav .ico{display:block;font-size:21px}.bottomnav .active{color:var(--accent)}.empty{padding:65px 15px}}
@media(prefers-color-scheme:dark){:root{color-scheme:dark;--bg:#15191f;--text:#e9edf3;--muted:#a0acba;--line:#313945;--soft:#202731;--accent:#8ab4ff;--hover:#26354a}.chip.active{background:#e9edf3;color:#15191f;border-color:#e9edf3}}
</style><link href="https://unpkg.com/maplibre-gl@5.7.1/dist/maplibre-gl.css" rel="stylesheet"><style>.globe-view{display:none;position:relative;height:min(72vh,680px);min-height:380px;border:1px solid var(--line);border-radius:14px;overflow:hidden;margin-top:16px}.globe-view.open{display:block}.globe-view #newsGlobe{width:100%;height:100%}.globe-info{position:absolute;left:12px;bottom:12px;background:var(--bg);padding:12px;border-radius:10px;max-width:320px;box-shadow:0 2px 12px #0002;font-size:13px}.globe-toggle{border:1px solid var(--line);background:var(--bg);color:var(--text);border-radius:20px;padding:6px 12px;white-space:nowrap}.globe-toggle.active{background:var(--hover);color:var(--accent)}.geo-link{font-size:12px;color:var(--accent);cursor:pointer;border:0;background:none;padding:3px 0}.trust-tools{display:flex;gap:12px;flex-wrap:wrap;align-items:center;margin-top:9px;font-size:12px;color:var(--muted)}.trust{border:1px solid var(--line);border-radius:8px;background:var(--bg);color:var(--text);padding:4px}.ifis-related a{color:var(--accent);text-decoration:underline;text-underline-offset:2px}.globe-marker{background:#2468d5;color:white;border:2px solid white;border-radius:50%;width:29px;height:29px;display:grid;place-items:center;font-size:11px;box-shadow:0 1px 7px #0005;cursor:pointer}</style></head><body>
<header class="topbar"><a class="brand" href="?view=world"><strong>IFIS</strong> News</a><nav class="topnav" aria-label="Bereiche"><a href="?view=world" class="<?= $view==='world'?'active':'' ?>">Weltlage</a><a href="?view=ifis" class="<?= $view==='ifis'?'active':'' ?>">IFIS-Radar</a></nav><div class="top-actions"><select id="language" aria-label="Sprache auswählen" title="Sprache" style="border:1px solid var(--line);border-radius:16px;background:var(--bg);color:var(--text);padding:6px"><option value="de">DE</option><option value="en">EN</option></select><input class="search" id="search" type="search" placeholder="Nachrichten durchsuchen …" aria-label="Nachrichten durchsuchen"><button class="iconbtn search-toggle" id="searchToggle" title="Suche" aria-label="Suche">⌕</button><button class="iconbtn" id="savedTop" title="Gemerkt" aria-label="Gemerkt">♧ <span class="desktoplabel">Gemerkt</span></button></div></header>
<div class="layout">
<aside class="side" aria-label="Themen"><button data-cat="Alle" class="active">◉ &nbsp; Alle Meldungen</button><?php foreach(['Politik','Gesellschaft','Wirtschaft','Technologie','Umwelt','Gesundheit','Bildung','Wissenschaft','Weitere'] as $c): ?><button data-cat="<?=esc($c)?>"><?=esc($c)?></button><?php endforeach; ?><h3>Ansicht</h3><button id="savedSide">☆ &nbsp; Gemerkt</button><button id="hiddenSide">Ausgeblendete Meldungen</button><div class="sidefooter">Weniger Ablenkung. Mehr geografischer Überblick.<br><a href="https://ifis.net">Über IFIS ↗</a></div></aside>
<main id="main"><div class="feedhead"><h1 id="feedTitle"><?= $view==='ifis'?'IFIS-Radar':'Weltlage' ?></h1><button class="globe-toggle" id="globeToggle" type="button" aria-pressed="false">🌍 Globus</button><small><?=count($items)?> Meldungen</small></div>
<div class="globe-view" id="globeView"><div id="newsGlobe"></div><div class="globe-info" id="globeInfo">Nachrichten geografisch entdecken. Punkte zeigen erkannte Ereignisorte, nicht den Sitz der Redaktion. Die Zuordnung ist vorläufig.</div></div><div class="chips" id="chips" aria-label="Themenfilter"><button class="chip active" data-cat="Alle">Alle</button><?php foreach(['Politik','Gesellschaft','Wirtschaft','Technologie','Umwelt','Gesundheit','Bildung','Wissenschaft','Weitere'] as $c): ?><button class="chip" data-cat="<?=esc($c)?>"><?=esc($c)?></button><?php endforeach; ?></div>
<div id="stories"><?php foreach($items as $i=>$item): ?><article class="story <?= $i===0?'feature':'' ?>" data-url="<?=esc($item['url'])?>" data-cat="<?=esc($item['category'])?>" data-search="<?=esc(mb_strtolower(($item['title']??'').' '.($item['summary']??'').' '.($item['source']??''),'UTF-8'))?>"><?php if(!empty($item["image_url"])): ?><img class="thumb" src="<?=esc($item["image_url"])?>" loading="lazy" referrerpolicy="no-referrer" alt="" onerror="this.style.display='none'"><?php endif; ?><div><div class="cat"><?=esc($item['category'])?></div><h2><a href="<?=esc($item['url'])?>" target="_blank" rel="noopener noreferrer"><?=esc($item['title'])?></a></h2><?php if(!empty($item['summary'])): ?><p class="summary"><?=esc($item['summary'])?></p><?php endif; ?><div class="meta"><?=esc($item['source'])?> · <?=esc($item['published_at'])?> ↗</div><div class="trust-tools"><label>Eigene Einschätzung <select class="trust" aria-label="Glaubwürdigkeit persönlich einschätzen"><option value="">Ungeprüft</option><option value="plausible">Plausibel</option><option value="questionable">Fraglich</option></select></label><span class="ifis-related"></span></div></div><div class="story-actions"><button class="save" aria-label="Meldung merken" title="Merken">☆</button><button class="dismiss" aria-label="Meldung ausblenden" title="Ausblenden">×</button></div></article><?php endforeach; ?></div>
<div class="empty <?= $items?'hide':'' ?>" id="empty"><div class="symbol">▤</div><h2>Noch keine Nachrichten importiert.</h2><p>Sobald die ersten Quellen eingebunden sind, erscheinen hier die aktuellen Meldungen – übersichtlich und ohne Ablenkung.</p></div><div class="empty hide" id="noMatches"><h2>Keine passenden Meldungen</h2><p>Versuche einen anderen Suchbegriff oder Filter.</p></div></main>
<aside class="right"><div class="panel"><h2>Auf einen Blick</h2><p>Die wichtigsten Meldungen erscheinen hier, sobald Nachrichten importiert wurden.</p></div><div class="panel"><h2>Themen im Fokus</h2><div id="focusTopics"><?php foreach(['Umwelt','Gesundheit','Bildung','Technologie','Gesellschaft'] as $c): ?><button data-cat="<?=esc($c)?>"><?=esc($c)?></button><?php endforeach; ?></div></div><div class="panel"><h2>IFIS-Perspektive</h2><p>Weltlage und IFIS-Radar bleiben bewusst getrennt: erst informieren, dann Zusammenhänge entdecken.</p><a href="https://ifis.net">IFIS entdecken ↗</a></div></aside>
</div>
<nav class="bottomnav" aria-label="Mobile Navigation"><a href="?view=world" class="<?= $view==='world'?'active':'' ?>"><span class="ico">▤</span>News</a><a href="?view=ifis" class="<?= $view==='ifis'?'active':'' ?>"><span class="ico">◎</span>Radar</a><button id="savedBottom"><span class="ico">☆</span>Gemerkt</button><button id="moreBottom"><span class="ico">☷</span>Themen</button></nav>
<script src="https://unpkg.com/maplibre-gl@5.7.1/dist/maplibre-gl.js"></script><script>
(()=>{'use strict';
const prefix='ifis-news-v1-',load=(k)=>{try{return JSON.parse(localStorage.getItem(prefix+k)||'[]')}catch{return []}},store=(k,v)=>{try{localStorage.setItem(prefix+k,JSON.stringify(v))}catch{}};
let saved=load('saved'),hidden=load('hidden'),mode='all',cat='Alle';
const stories=[...document.querySelectorAll('.story')],search=document.getElementById('search'),empty=document.getElementById('empty'),noMatches=document.getElementById('noMatches'),title=document.getElementById('feedTitle');
const base=title.textContent;
function refresh(){let count=0;for(const el of stories){const url=el.dataset.url;const show=(mode==='saved'?saved.includes(url):mode==='hidden'?hidden.includes(url):!hidden.includes(url))&&(cat==='Alle'||el.dataset.cat===cat)&&el.dataset.search.includes(search.value.trim().toLocaleLowerCase());el.classList.toggle('hide',!show);el.querySelector('.save').textContent=saved.includes(url)?'★':'☆';el.querySelector('.save').classList.toggle('saved',saved.includes(url));el.querySelector('.dismiss').textContent=mode==='hidden'?'↶':'×';el.querySelector('.dismiss').title=mode==='hidden'?'Wieder anzeigen':'Ausblenden';if(show)count++}
empty.classList.toggle('hide',stories.length>0||mode!=='all'||cat!=='Alle'||!!search.value);
noMatches.classList.toggle('hide',count>0||(!stories.length&&mode==='all'&&cat==='Alle'&&!search.value));
title.textContent=mode==='saved'?'Gemerkt':mode==='hidden'?'Ausgeblendet':cat==='Alle'?base:cat;
document.querySelectorAll('[data-cat]').forEach(b=>b.classList.toggle('active',mode==='all'&&b.dataset.cat===cat));
document.getElementById('savedBottom').classList.toggle('active',mode==='saved');
}
function setCat(v){cat=v;mode='all';refresh()}
document.querySelectorAll('[data-cat]').forEach(b=>b.addEventListener('click',()=>setCat(b.dataset.cat)));
document.querySelectorAll('.save').forEach(b=>b.addEventListener('click',()=>{const u=b.closest('.story').dataset.url;saved=saved.includes(u)?saved.filter(x=>x!==u):[...saved,u];store('saved',saved);refresh()}));
document.querySelectorAll('.dismiss').forEach(b=>b.addEventListener('click',()=>{const u=b.closest('.story').dataset.url;hidden=hidden.includes(u)?hidden.filter(x=>x!==u):[...hidden,u];store('hidden',hidden);refresh()}));
function showSaved(){mode='saved';cat='Alle';refresh();window.scrollTo({top:0,behavior:'smooth'})}
document.getElementById('savedTop').onclick=showSaved;document.getElementById('savedSide').onclick=showSaved;document.getElementById('savedBottom').onclick=showSaved;
document.getElementById('hiddenSide').onclick=()=>{mode='hidden';cat='Alle';refresh()};
document.getElementById('searchToggle').onclick=()=>{search.classList.toggle('open');search.focus()};
document.getElementById('moreBottom').onclick=()=>{document.getElementById('chips').scrollIntoView({behavior:'smooth',block:'center'});};
const trustKey=prefix+'trust';
let ratings={};try{ratings=JSON.parse(localStorage.getItem(trustKey)||'{}')||{}}catch{}
document.querySelectorAll('.story').forEach(story=>{
const url=story.dataset.url,sel=story.querySelector('.trust');sel.value=ratings[url]||'';
sel.addEventListener('change',()=>{if(sel.value)ratings[url]=sel.value;else delete ratings[url];try{localStorage.setItem(trustKey,JSON.stringify(ratings))}catch{}});
const words=(story.querySelector('h2').textContent+' '+(story.querySelector('.summary')?.textContent||'')).toLowerCase();
const links=[
{terms:['agriculture','farming','farmland','landwirtschaft','soil','ackerbau'],label:'IFIS: Landwirtschaft neu erfinden',url:'https://ifis.net/artikel/landwirtschaft-neu-erfinden'},
{terms:['overtraining','overload','überlastung','excessive exercise','too much training'],label:'IFIS: Wenn mehr nicht mehr besser wird',url:'https://ifis.net/artikel/wenn-mehr-nicht-mehr-besser-wird'}
];const match=links.find(x=>x.terms.some(t=>words.includes(t)));
if(match){const a=document.createElement('a');a.href=match.url;a.target='_blank';a.rel='noopener noreferrer';a.textContent='↗ '+match.label; a.title='Automatischer thematischer Lesehinweis, keine Faktenprüfung';story.querySelector('.ifis-related').append(a);}
});
const language=document.getElementById('language');
const dictionary={de:{world:'Weltlage',ifis:'IFIS-Radar',all:'Alle',saved:'Gemerkt',hidden:'Ausgeblendet',search:'Nachrichten durchsuchen …',globe:'🌍 Globus',rating:'Eigene Einschätzung',unverified:'Ungeprüft',plausible:'Plausibel',questionable:'Fraglich'},en:{world:'World news',ifis:'IFIS Radar',all:'All',saved:'Saved',hidden:'Hidden',search:'Search news …',globe:'🌍 Globe',rating:'My assessment',unverified:'Not checked',plausible:'Plausible',questionable:'Questionable'}};
function translate(lang){const d=dictionary[lang]||dictionary.de;document.documentElement.lang=lang;language.value=lang;try{localStorage.setItem(prefix+'lang',lang)}catch{}
document.querySelectorAll('.topnav a')[0].textContent=d.world;document.querySelectorAll('.topnav a')[1].textContent=d.ifis;document.getElementById('globeToggle').textContent=d.globe;search.placeholder=d.search;document.querySelector('.chips [data-cat="Alle"]').textContent=d.all;document.querySelectorAll('.trust-tools label').forEach(el=>{el.firstChild.textContent=d.rating+' ';const opts=el.querySelector('select').options;opts[0].textContent=d.unverified;opts[1].textContent=d.plausible;opts[2].textContent=d.questionable;});document.querySelectorAll('.bottomnav a')[0].lastChild.textContent=lang==='en'?'News':'News';document.querySelectorAll('.bottomnav button')[0].lastChild.textContent=d.saved;document.querySelectorAll('.bottomnav button')[1].lastChild.textContent=lang==='en'?'Topics':'Themen';}
language.addEventListener('change',()=>translate(language.value));translate(localStorage.getItem(prefix+'lang')||'de');
search.addEventListener('input',refresh);refresh();
})();
</script>
<script>
(()=>{
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
const stories=[...document.querySelectorAll('.story')],btn=document.getElementById('globeToggle'),panel=document.getElementById('globeView'),info=document.getElementById('globeInfo');let map=null,markers=[];
const normalize=t=>' '+t.toLocaleLowerCase()+' ';
const groups=new Map();
stories.forEach(story=>{const title=story.querySelector('h2').textContent,txt=normalize(title+' '+(story.querySelector('.summary')?.textContent||''));const hit=locations.find(loc=>loc[3].some(word=>txt.includes(word)));if(!hit)return;story.dataset.place=hit[0];if(!groups.has(hit[0]))groups.set(hit[0],{loc:hit,stories:[]});groups.get(hit[0]).stories.push(story);});
function choose(place){const group=groups.get(place);if(!group)return;info.replaceChildren();const heading=document.createElement('strong');heading.textContent=place+' · '+group.stories.length+' Meldungen';info.append(heading);group.stories.slice(0,6).forEach(story=>{const a=document.createElement('a');a.href=story.querySelector('h2 a').href;a.target='_blank';a.rel='noopener noreferrer';a.style.display='block';a.style.marginTop='8px';a.textContent=story.querySelector('h2').textContent;info.append(a)});map.flyTo({center:[group.loc[2],group.loc[1]],zoom:3,essential:true});}
btn.addEventListener('click',()=>{const open=panel.classList.toggle('open');btn.classList.toggle('active',open);btn.setAttribute('aria-pressed',String(open));if(!open)return;if(map){map.resize();return;}if(!window.maplibregl){info.textContent='Die Kartenbibliothek konnte nicht geladen werden.';return;}map=new maplibregl.Map({container:'newsGlobe',style:'https://demotiles.maplibre.org/style.json',center:[10,20],zoom:1.15,attributionControl:true});map.on('load',()=>{try{map.setProjection({type:'globe'});}catch(e){};groups.forEach((g,name)=>{const dot=document.createElement('button');dot.className='globe-marker';dot.type='button';dot.textContent=String(g.stories.length);dot.title=name+' · '+g.stories.length+' Meldungen';dot.addEventListener('click',()=>choose(name));markers.push(new maplibregl.Marker({element:dot}).setLngLat([g.loc[2],g.loc[1]]).addTo(map));});if(!groups.size)info.textContent='Noch keine eindeutigen Ereignisorte erkannt.';});});
})();
</script></body></html>