<?php declare(strict_types=1); header('Content-Type: text/html; charset=utf-8'); ?>
<!doctype html><html lang="de"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">
<title>Gestaltung · GDELT-Pilot · IFIS News</title>
<link href="https://unpkg.com/maplibre-gl@5.7.1/dist/maplibre-gl.css" rel="stylesheet">
<style>
:root{color-scheme:light dark;font-family:system-ui,-apple-system,sans-serif}*{box-sizing:border-box}
body{margin:0;background:#f8faf8;color:#182820}header{padding:17px max(18px,4vw);display:flex;align-items:center;gap:18px;flex-wrap:wrap;border-bottom:1px solid #dae2db}
header a{color:#176a49;text-decoration:none}header h1{font-size:1.3rem;margin:0}main{padding:18px max(18px,4vw)}.intro{max-width:1000px;line-height:1.5}
.controls{display:flex;gap:12px;align-items:center;flex-wrap:wrap;margin:18px 0}select{font-size:1rem;padding:10px;border-radius:8px;border:1px solid #aaa;background:white;color:#182820}
.layout{display:grid;grid-template-columns:minmax(300px,1.1fr) minmax(280px,1fr);gap:16px}.pane{background:white;border:1px solid #dce5df;border-radius:12px;overflow:hidden}
#map{height:min(65vh,620px);min-height:390px}.list{height:min(65vh,620px);min-height:390px;overflow:auto;padding:14px}
article{padding:12px 0;border-bottom:1px solid #e4e9e5}article a{color:#176a49;overflow-wrap:anywhere;font-weight:600}small{color:#51615a}
.notice{background:#eaf1ed;padding:12px;border-radius:8px;max-width:1050px}.status{font-size:.9rem;color:#53645c}
@media(max-width:820px){.layout{grid-template-columns:1fr}#map{height:390px}.list{height:auto;max-height:560px;min-height:100px}}
</style></head><body>
<header><a href="/">← IFIS News</a><h1>Gestaltung · GDELT-Pilot</h1></header>
<main><p class="intro">Weltweite Medienberichte über konstruktive Umweltmaßnahmen, direkt aus den GDELT-Schnittstellen DOC 2.0 und GEO 2.0. Die geografischen Punkte zeigen <strong>Orte, die in Berichten erwähnt werden</strong>, nicht verifizierte Umweltprojekte oder deren Wirkung.</p>
<div class="controls"><label for="topic">Thema</label><select id="topic">
<option value="entsiegelung">Entsiegelung &amp; Schwammstadt</option>
<option value="fluesse">Flüsse &amp; Gewässer</option>
<option value="stadtgruen">Stadtgrün &amp; Bäume</option>
<option value="lebensraeume">Lebensräume &amp; Renaturierung</option>
<option value="gemeinschaft">Gemeinschaftsprojekte</option>
</select><span id="status" class="status" role="status">Lade Daten …</span></div>
<div class="layout"><section class="pane"><div id="map" aria-label="Karte der geografischen Medienerwähnungen"></div></section>
<section class="pane list"><h2>Artikel aus GDELT DOC</h2><div id="articles"></div></section></div>
<p class="notice">Pilotansicht · Zeitraum: letzte 7 Tage · Artikel bleiben bei ihren Originalquellen. Keine automatische Prüfung, keine Projektbewertungen, keine von IFIS berechneten Wirkungsdaten. Angaben können unvollständig sein.</p>
</main><script src="https://unpkg.com/maplibre-gl@5.7.1/dist/maplibre-gl.js"></script><script>
const map=new maplibregl.Map({container:'map',style:'https://demotiles.maplibre.org/style.json',center:[10,20],zoom:1.4});
map.addControl(new maplibregl.NavigationControl(),'top-right');
const topic=document.getElementById('topic'),status=document.getElementById('status'),articles=document.getElementById('articles');
let requestId=0,activeData=null;
function showMap(data){
 const fc=data.geo||{type:'FeatureCollection',features:[]};
 if(!map.isStyleLoaded()){activeData=data;return;}
 if(!map.getSource('mentions'))map.addSource('mentions',{type:'geojson',data:fc});
 else map.getSource('mentions').setData(fc);
 if(!map.getLayer('mentions'))map.addLayer({id:'mentions',type:'circle',source:'mentions',paint:{'circle-radius':6,'circle-color':'#25895c','circle-stroke-color':'#ffffff','circle-stroke-width':1.4,'circle-opacity':.85}});
}
map.on('load',()=>{if(activeData)showMap(activeData)});
map.on('click','mentions',e=>{const f=e.features?.[0];if(!f)return;
 const div=document.createElement('div');div.textContent=(f.properties.name||'Geografische Erwähnung')+' · In Nachrichten erwähnter Ort, kein bestätigtes Projekt.';
 new maplibregl.Popup({maxWidth:'260px'}).setLngLat(f.geometry.coordinates).setDOMContent(div).addTo(map);
});
map.on('mouseenter','mentions',()=>map.getCanvas().style.cursor='pointer');
map.on('mouseleave','mentions',()=>map.getCanvas().style.cursor='');
async function load(){
 const id=++requestId;articles.replaceChildren();status.textContent='GDELT wird abgefragt …';
 try{
  const res=await fetch('gestaltung-data.php?topic='+encodeURIComponent(topic.value),{cache:'no-store'});
  if(!res.ok)throw Error('HTTP '+res.status);
  const d=await res.json();if(id!==requestId)return;
  activeData=d;showMap(d);
  for(const a of d.articles){
   const card=document.createElement('article'),link=document.createElement('a'),meta=document.createElement('small');
   link.href=a.url;link.target='_blank';link.rel='noopener noreferrer';link.textContent=a.title||a.url;
   meta.textContent=' · '+[a.domain,a.date,a.language].filter(Boolean).join(' · ');
   card.append(link,meta);articles.append(card);
  }
  if(!d.articles.length)articles.textContent='Derzeit keine Artikel aus der API verfügbar.';
  status.textContent=d.articles.length+' Artikel · '+d.geo.features.length+' geografische Erwähnungen'+(d.stale?' · Zwischenspeicher':'')+(!d.doc_available||!d.geo_available?' · API teilweise nicht verfügbar':'');
 }catch(e){if(id===requestId){status.textContent='Daten momentan nicht erreichbar';articles.textContent='Bitte später erneut versuchen.';}}
}
topic.addEventListener('change',load);load();
</script></body></html>