/* Private comment relations. No personal data is embedded in the public page. */
(()=>{
 'use strict';
 function arc(start,end){
  const r=Math.PI/180,toV=([lon,lat])=>[Math.cos(lat*r)*Math.cos(lon*r),Math.cos(lat*r)*Math.sin(lon*r),Math.sin(lat*r)];
  const a=toV(start),b=toV(end),dot=Math.max(-1,Math.min(1,a.reduce((n,v,i)=>n+v*b[i],0))),angle=Math.acos(dot);
  if(angle<1e-7)return [start,end];
  let tangent=b.map((v,i)=>v-dot*a[i]),length=Math.hypot(...tangent);
  if(length<1e-7){const axis=Math.abs(a[0])<.9?[1,0,0]:[0,1,0],projection=axis.reduce((n,v,i)=>n+v*a[i],0);tangent=axis.map((v,i)=>v-projection*a[i]);length=Math.hypot(...tangent);}
  tangent=tangent.map(v=>v/length);
  const points=[];
  for(let i=0;i<=64;i++){
   const theta=angle*i/64,v=a.map((n,k)=>n*Math.cos(theta)+tangent[k]*Math.sin(theta));
   let lon=Math.atan2(v[1],v[0])/r;const lat=Math.atan2(v[2],Math.hypot(v[0],v[1]))/r;
   if(points.length){while(lon-points.at(-1)[0]>180)lon-=360;while(lon-points.at(-1)[0]<-180)lon+=360;}
   points.push([lon,lat]);
  }
  return points;
 }
 window.installCommentMap=(map,focusMarker)=>{
  const toggle=document.getElementById('showMyComments'),status=document.getElementById('myCommentsStatus');
  if(!toggle||!status)return;
  let data=null,request=null,popup=null;
  const empty={type:'FeatureCollection',features:[]};
  function closePopup(){if(popup){popup.remove();popup=null;}}
  function render(){
   const features=[];
   if(data&&toggle.checked){
    features.push({type:'Feature',geometry:{type:'Point',coordinates:data.origin.coordinates},properties:{id:'home'}});
    for(const c of data.connections){
     features.push({type:'Feature',geometry:{type:'LineString',coordinates:arc(data.origin.coordinates,c.coordinates)},properties:{id:String(c.id)}});
     features.push({type:'Feature',geometry:{type:'Point',coordinates:c.coordinates},properties:{id:String(c.id)}});
    }
   }
   if(!map.isStyleLoaded())return;
   const source=map.getSource('my-comment-connections');
   if(source)source.setData({type:'FeatureCollection',features});
   else map.addSource('my-comment-connections',{type:'geojson',data:{type:'FeatureCollection',features}});
   if(!map.getLayer('my-comment-lines')){
    map.addLayer({id:'my-comment-lines',type:'line',source:'my-comment-connections',filter:['==',['geometry-type'],'LineString'],paint:{'line-color':'#8963b6','line-opacity':.8,'line-width':2}});
    map.addLayer({id:'my-comment-hit',type:'line',source:'my-comment-connections',filter:['==',['geometry-type'],'LineString'],paint:{'line-color':'#8963b6','line-opacity':0,'line-width':18}});
    map.addLayer({id:'my-comment-points',type:'circle',source:'my-comment-connections',filter:['==',['geometry-type'],'Point'],paint:{'circle-radius':7,'circle-color':'#8963b6','circle-stroke-color':'#fff','circle-stroke-width':2}});
   }
  }
  function clear(){
   request?.abort();request=null;data=null;closePopup();
   map.getSource('my-comment-connections')?.setData(empty);
  }
  function addText(root,tag,text){const el=document.createElement(tag);el.textContent=text;root.append(el);return el;}
  function addLink(root,label,url){
   try{const u=new URL(url,location.href);if(u.protocol!=='https:'&&u.origin!==location.origin)return;
    if(u.origin!==location.origin&&!(u.hostname==='lnkd.in'||u.hostname==='linkedin.com'||u.hostname.endsWith('.linkedin.com')))return;
    const a=addText(root,'a',label);a.href=u.href;if(u.origin!==location.origin){a.target='_blank';a.rel='noopener noreferrer';}
   }catch{}
  }
  function openConnection(connection){
   if(!data||!toggle.checked)return;
   closePopup();
   const root=document.createElement('div');root.className='my-comment-popup';
   addText(root,'strong',connection.name+' · '+connection.place);
   addText(root,'p','Meine Kommentare · Profilstandort, kein Ereignisort');
   window.loadProfileThreads?.(root,{contactId:connection.id,onLoaded:()=>{if(popup&&root.isConnected)focusMarker?.(connection.coordinates[0],connection.coordinates[1],popup.getElement());}});
   popup=new maplibregl.Popup({offset:18,maxWidth:'min(400px, calc(100vw - 40px))'}).setLngLat(connection.coordinates).setDOMContent(root).addTo(map);
   focusMarker?.(connection.coordinates[0],connection.coordinates[1],popup.getElement());
  }
  function click(e){
   const id=e.features?.[0]?.properties?.id;if(!data||!toggle.checked)return;
   if(id==='home'){closePopup();popup=new maplibregl.Popup().setLngLat(data.origin.coordinates).setText(data.origin.name+' · Ausgangspunkt meiner Kommentare').addTo(map);return;}
   const c=data.connections.find(c=>String(c.id)===String(id));if(c)openConnection(c);
  }
  for(const layer of ['my-comment-hit','my-comment-points']){
   map.on('click',layer,click);
   map.on('mouseenter',layer,()=>{map.getCanvas().style.cursor='pointer';});
   map.on('mouseleave',layer,()=>{map.getCanvas().style.cursor='';});
  }
  async function load(){
   clear();status.replaceChildren();if(!toggle.checked)return;
   const controller=new AbortController();request=controller;status.textContent='Meine Kommentare laden …';
   try{
    const response=await fetch('comment-map-data.php',{credentials:'same-origin',cache:'no-store',signal:controller.signal});
    if(response.status===403){toggle.checked=false;status.textContent='Bitte zuerst unter „Mein Netzwerk“ anmelden. ';addLink(status,'Anmelden','admin-netzwerk.php');return;}
    if(!response.ok)throw Error('unavailable');
    const result=await response.json();if(controller.signal.aborted||!toggle.checked)return;
    data=result;render();
    const comments=data.connections.reduce((n,c)=>n+c.posts.length,0);
    status.textContent=data.connections.length+' Profilorte · '+comments+' eigene Kommentare';
    if(!comments)status.append(document.createTextNode(' · Noch keine verorteten eigenen Kommentare gespeichert.'));
    if(data.missing.length){
     const details=document.createElement('details');status.append(details);addText(details,'summary',data.missing.length+' Profilorte noch zuzuordnen');
     const list=document.createElement('ul');details.append(list);
     for(const c of data.missing)addText(list,'li',c.name+(c.place?' · '+c.place:' · Ort fehlt'));
     addLink(details,'Orte im privaten Netzwerk ergänzen','admin-netzwerk.php');
    }
   }catch(e){if(e.name!=='AbortError'){clear();toggle.checked=false;status.textContent='Kommentare konnten nicht geladen werden. Bitte erneut einschalten.';}}
   finally{if(request===controller)request=null;}
  }
  toggle.checked=false;
  toggle.addEventListener('change',load);
  map.on('style.load',render);
  window.addEventListener('focus',()=>{if(toggle.checked)load();});
  window.addEventListener('pagehide',()=>{clear();toggle.checked=false;status.replaceChildren();});
 };
})();

