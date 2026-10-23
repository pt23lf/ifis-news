(()=>{
 'use strict';
 const style=document.createElement('style');style.textContent='.profile-thread-list{overflow-wrap:anywhere}.profile-thread{border-top:1px solid var(--line,#8885);padding:10px 0}.profile-thread h3{font-size:14px;margin:5px 0}.profile-thread p{white-space:pre-wrap;font-size:13px}.profile-thread a{display:block;margin:7px 0;color:var(--accent,inherit)}.profile-own-comment{border-left:3px solid #448c73;padding-left:10px;margin-top:12px}';document.head.append(style);
 const text=(root,tag,value)=>{const el=document.createElement(tag);el.textContent=value;root.append(el);return el;};
 const commonStyle=document.createElement('style');commonStyle.textContent='.source-post{border-top:1px solid var(--line,#8885);padding:10px 0;overflow-wrap:anywhere}.source-post .globe-story-link{display:flex;flex-direction:column;gap:5px;color:inherit;text-decoration:none;padding:8px 0;background:transparent;border:0;box-shadow:none}.source-post .globe-story-title{font-size:14px;font-weight:650}.source-post .globe-story-category,.source-post .globe-story-meta,.source-post .globe-story-places{font-size:11px;opacity:.8;display:flex;gap:8px;flex-wrap:wrap}.source-post .globe-story-summary{font-size:12px;white-space:pre-wrap}.source-post-actions{display:flex;gap:10px;flex-wrap:wrap;align-items:center}.source-post-actions button{font:inherit;color:var(--accent,inherit);background:transparent;border:1px solid var(--line,#8885);border-radius:7px;padding:5px 8px;cursor:pointer}.source-post-actions a{font-size:12px}.source-post p{white-space:pre-wrap}.source-post details{font-size:12px}';document.head.append(commonStyle);
 async function shareOriginal(url,title,feedback){
  try{
   if(navigator.share){try{await navigator.share({url,title});return}catch(e){if(e.name==='AbortError')return;}}
   await navigator.clipboard.writeText(url);feedback.textContent='Link kopiert';
  }catch{feedback.textContent='Link zum Kopieren: '+url;}
 }
 window.appendSourcePost=(root,post,options={})=>{
  const entry=document.createElement('article');entry.className='source-post';root.append(entry);
  const card=document.createElement('a');card.className='globe-story-link';entry.append(card);
  let original='';try{const u=new URL(post.url);if(['http:','https:'].includes(u.protocol)&&!u.username&&!u.password)original=u.href;}catch{}
  card.href=options.href||original||'#';
  if(options.open)card.addEventListener('click',e=>{e.preventDefault();options.open();});
  else if(original){card.target='_blank';card.rel='noopener noreferrer';}
  else card.addEventListener('click',e=>e.preventDefault());
  text(card,'span',post.category||'Beitrag').className='globe-story-category';
  text(card,'span',post.title||'Gespeicherter Beitrag').className='globe-story-title';
  const meta=text(card,'small',[post.source,post.date].filter(Boolean).join(' · '));meta.className='globe-story-meta';
  if(post.summary)text(card,'span',post.summary.length>400?post.summary.slice(0,397)+'…':post.summary).className='globe-story-summary';
  if(post.sourcePlace||post.eventPlace)text(card,'small',[post.sourcePlace,post.eventPlace].filter(Boolean).join(' · ')).className='globe-story-places';
  if(post.summary?.length>400){const details=document.createElement('details');entry.append(details);text(details,'summary','Gespeicherten Beitragskontext anzeigen');text(details,'p',post.summary);}
  const actions=document.createElement('div');actions.className='source-post-actions';entry.append(actions);
  if(original){
   const a=text(actions,'a','Original öffnen ↗');a.href=original;a.target='_blank';a.rel='noopener noreferrer';
   const share=text(actions,'button','↗ Teilen');share.type='button';const feedback=text(actions,'small','');feedback.setAttribute('role','status');
   share.addEventListener('click',()=>shareOriginal(original,post.title,feedback));
  }else text(actions,'small','Original-Link noch nicht erfasst.');
  return entry;
 };
 function link(root,label,url){
  if(!url)return;
  try{const u=new URL(url,location.href);if(u.origin!==location.origin&&(u.protocol!=='https:'||!(u.hostname==='linkedin.com'||u.hostname.endsWith('.linkedin.com')||u.hostname==='lnkd.in')))return;
   const a=text(root,'a',label);a.href=u.href;if(u.origin!==location.origin){a.target='_blank';a.rel='noopener noreferrer';}
  }catch{}
 }
 function render(root,profile,options={}){
  root.replaceChildren();root.classList.add('profile-thread-list');
  text(root,'p',profile.post_count+' gespeicherte Posts · '+profile.comment_count+' eigene Kommentare');
  if(!options.keepFallback){
   const urls=[...new Set(profile.posts.map(post=>post.url).filter(Boolean))];
   const actions=document.createElement('div');actions.className='source-post-actions';root.append(actions);
   const share=text(actions,'button','↗ Teilen');share.type='button';share.disabled=!urls.length;share.title='Original-Links der Beiträge teilen; eigene Kommentare bleiben privat';
   const feedback=text(actions,'small',urls.length?'':'Zum Teilen fehlen noch Original-Links.');feedback.setAttribute('role','status');
   share.addEventListener('click',async()=>{
    const payload={title:profile.name+' · Beiträge',text:urls.join('\n')};
    try{
     if(navigator.share){try{await navigator.share(payload);return}catch(e){if(e.name==='AbortError')return;}}
     await navigator.clipboard.writeText(payload.text);feedback.textContent='Beitragslinks kopiert';
    }catch{feedback.textContent='Links zum Kopieren: '+payload.text;}
   });
  }
  if(!profile.posts.length)text(root,'p','Noch keine Posts dieses Autors gespeichert.');
  for(const post of profile.posts){
   const entry=window.appendSourcePost(root,{title:post.title,url:post.url,summary:post.excerpt,source:profile.name,sourcePlace:profile.place,category:'Beitrag'});
   for(const comment of post.my_comments){
    const box=document.createElement('div');box.className='profile-own-comment';entry.append(box);
    text(box,'strong','Mein Kommentar'+(comment.status==='excerpt'?' · sichtbarer Ausschnitt':''));text(box,'p',comment.text);
    link(box,'Originalkommentar ↗',comment.url);
   }
   link(entry,'Im privaten Archiv öffnen',post.archive_url);
  }
 }
 window.loadProfileThreads=async(root,options={})=>{
  if(root._profileThreadLoading)return;
  root._profileThreadLoading=true;
  const params=new URLSearchParams();
  if(options.contactId)params.set('contact_id',String(options.contactId));
  else for(const url of (options.postUrls||[]).slice(0,40))params.append('post_urls[]',url);
  const region=document.createElement('div');region.className='profile-thread-list';region.setAttribute('aria-live','polite');
  text(region,'p','Posts und eigene Kommentare laden …');
  if(!options.replace)root.append(region);
  else if(!options.keepFallback)root.replaceChildren(region);
  try{
   const response=await fetch('profile-threads-data.php?'+params,{credentials:'same-origin',cache:'no-store'});
   if(response.status===403||response.status===404){
    if(options.keepFallback)return;
    region.replaceChildren();text(region,'p',response.status===403?'Für Posts und eigene Kommentare bitte im Netzwerk anmelden.':'Noch keine Posts zugeordnet.');
    if(response.status===403)link(region,'Anmelden','admin-netzwerk.php');return;
   }
   if(!response.ok)throw Error('unavailable');
   const profile=await response.json();render(region,profile,options);
   if(options.replace)root.replaceChildren(region);
   options.onLoaded?.(profile);
  }catch{
   if(!options.keepFallback){region.replaceChildren();text(region,'p','Posts konnten nicht geladen werden. Bitte das Profil erneut öffnen.');}
  }finally{root._profileThreadLoading=false;}
 };
 document.querySelectorAll('details[data-profile-threads]').forEach(details=>{
  details.addEventListener('toggle',()=>{if(details.open)window.loadProfileThreads(details.querySelector('.profile-thread-body'),{contactId:details.dataset.profileThreads,replace:true});});
 });
})();

