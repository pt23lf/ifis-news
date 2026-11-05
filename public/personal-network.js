const search=document.getElementById('search');
if(search){search.addEventListener('input',()=>{const term=search.value.trim().toLocaleLowerCase();document.querySelectorAll('#contacts .contact').forEach(el=>{el.hidden=!el.dataset.filter.includes(term);});});}

const language=document.getElementById('personal-language');
if(language){language.addEventListener('change',()=>language.form?.requestSubmit());}
