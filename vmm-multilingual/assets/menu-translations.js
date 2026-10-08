document.addEventListener('DOMContentLoaded',()=>{
 const panel=document.getElementById('vmm-menu-sidebar'),side=document.getElementById('side-sortables'),form=document.getElementById('update-nav-menu'),nonce=document.getElementById('vmm-menu-nonce');
 if(!panel||!form)return;
 if(side){side.prepend(panel);panel.hidden=false;}if(nonce)form.append(nonce);
 const language=document.getElementById('vmm-menu-locale'),source=panel.dataset.source;
 const remembered=sessionStorage.getItem('vmm-menu-locale');if([...language.options].some(o=>o.value===remembered))language.value=remembered;
 const initial=new Set([...document.querySelectorAll('#menu-to-edit .menu-item')].map(el=>el.id));const seen=new WeakSet();
 const text=(el,value)=>{if(el&&el.textContent!==value)el.textContent=value;};
 const getFields=(item,locale)=>[...item.querySelectorAll('.vmm-menu-translation-fields')].find(el=>el.dataset.locale===locale);
 function update(){
  const locale=language.value;
  form.querySelectorAll('.bulk-select-switcher,.bulk-actions').forEach(el=>el.hidden=locale!==source);
  sessionStorage.setItem('vmm-menu-locale',locale);
  document.querySelectorAll('#menu-to-edit .menu-item').forEach(item=>{
   const only=item.querySelector('.vmm-menu-only');if(!only)return;
   if(!seen.has(item)){seen.add(item);if(!initial.has(item.id)){only.value=locale===source?'':locale;const field=getFields(item,locale);if(field)field.querySelector('.vmm-menu-mode').value='custom';}}
   item.hidden=!!only.value&&only.value!==locale;
   const nativeTitle=item.querySelector('.edit-menu-item-title'),nativeUrl=item.querySelector('.edit-menu-item-url');
   if(nativeTitle)nativeTitle.readOnly=true;if(nativeUrl)nativeUrl.readOnly=true;
   const baseline=getFields(item,source),sourceMode=baseline?.querySelector('.vmm-menu-mode').value;
   const sourceLabel=(sourceMode==='custom'?baseline.querySelector('.vmm-menu-label').value:'')||nativeTitle?.value||'';
   const sourceUrl=(sourceMode==='custom'?baseline.querySelector('.vmm-menu-url').value:'')||nativeUrl?.value||'';
   const fields=getFields(item,locale);const mode=fields?.querySelector('.vmm-menu-mode').value;
   item.querySelectorAll('.vmm-menu-translation-fields').forEach(el=>{el.hidden=el!==fields;const custom=el.querySelector('.vmm-menu-custom');if(custom)custom.hidden=el.querySelector('.vmm-menu-mode').value!=='custom';});
   text(item.querySelector('.vmm-menu-scope'),only.value?'Nur '+([...language.options].find(o=>o.value===only.value)?.textContent||only.value):'Grundstruktur · alle Sprachen');
   if(fields){text(fields.querySelector('.vmm-menu-original'),'Original: '+sourceLabel+(sourceUrl?' → '+sourceUrl:''));const input=fields.querySelector('.vmm-menu-label');input.placeholder=sourceLabel;fields.querySelector('.vmm-menu-url').placeholder=sourceUrl;}
   const label=mode==='custom'?(fields.querySelector('.vmm-menu-label').value||sourceLabel):sourceLabel;
   text(item.querySelector('.menu-item-title'),label+(mode==='hide'?' · ausgeblendet':'')+(only.value?' · nur '+language.selectedOptions[0].textContent:''));
   item.style.opacity=mode==='hide'?'0.55':'';
   text(item.querySelector('a.item-delete'),locale!==source&&!only.value?'In dieser Sprache ausblenden':'Entfernen');
  });
 }
 form.addEventListener('click',event=>{
  const link=event.target.closest('a.item-delete');if(!link||language.value===source)return;
  const item=link.closest('.menu-item');if(!item||item.querySelector('.vmm-menu-only')?.value)return;
  const field=getFields(item,language.value);if(!field)return;
  event.preventDefault();event.stopImmediatePropagation();field.querySelector('.vmm-menu-mode').value='hide';update();
 },true);
 language.addEventListener('change',update);form.addEventListener('change',update);form.addEventListener('input',event=>{if(event.target.matches('.vmm-menu-label,.vmm-menu-url'))update();});update();
 new MutationObserver(update).observe(document.getElementById('menu-to-edit')||form,{childList:true,subtree:true});
});
