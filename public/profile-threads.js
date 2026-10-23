(()=>{
 'use strict';
 const style=document.createElement('style');style.textContent='.profile-thread-list{overflow-wrap:anywhere}.profile-thread{border-top:1px solid var(--line,#8885);padding:10px 0}.profile-thread h3{font-size:14px;margin:5px 0}.profile-thread p{white-space:pre-wrap;font-size:13px}.profile-thread a{display:block;margin:7px 0;color:var(--accent,inherit)}.profile-own-comment{border-left:3px solid #448c73;padding-left:10px;margin-top:12px}';document.head.append(style);
 const text=(root,tag,value)=>{const el=document.createElement(tag);el.textContent=value;root.append(el);return el;};
 function link(root,label,url){
  if(!url)return;
  try{const u=new URL(url,location.href);if(u.origin!==location.origin&&(u.protocol!=='https:'||!(u.hostname==='linkedin.com'||u.hostname.endsWith('.linkedin.com')||u.hostname==='lnkd.in')))return;
   const a=text(root,'a',label);a.href=u.href;if(u.origin!==location.origin){a.target='_blank';a.rel='noopener noreferrer';}
  }catch{}
 }
 function render(root,profile){
  root.replaceChildren();root.classList.add('profile-thread-list');
  text(root,'p',profile.post_count+' gespeicherte Posts · '+profile.comment_count+' eigene Kommentare');
  if(!profile.posts.length)text(root,'p','Noch keine Posts dieses Autors gespeichert.');
  for(const post of profile.posts){
   const entry=document.createElement('section');entry.className='profile-thread';root.append(entry);
   text(entry,'h3',post.title);
   if(post.excerpt){const details=document.createElement('details');entry.append(details);text(details,'summary','Gespeicherten Beitragskontext anzeigen');text(details,'p',post.excerpt);}
   if(post.url)link(entry,'Originalbeitrag ↗',post.url);else text(entry,'small','Original-Link noch nicht erfasst.');
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
   const profile=await response.json();render(region,profile);
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
