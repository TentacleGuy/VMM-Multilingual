(()=>{
 'use strict';const c=window.vmmVisual;if(!c)return;
 const frame=document.getElementById('vmm-visual-frame'),panel=document.getElementById('vmm-visual-panel'),status=document.getElementById('vmm-visual-status'),language=document.getElementById('vmm-visual-language');
 let page,selected,dirty=false,navigation=false,busy=false,generation=0;const plugins=new Map();
 const el=(tag,text,cls)=>{const e=document.createElement(tag);if(text!==undefined)e.textContent=text;if(cls)e.className=cls;return e;};
 const message=(text,error=false)=>{status.textContent=text;status.classList.toggle('error',error);};
 const request=async(path,data)=>{const response=await fetch(c.rest+path,{method:data?'PUT':'GET',credentials:'same-origin',headers:{'X-WP-Nonce':c.nonce,'Content-Type':'application/json'},body:data?JSON.stringify(data):undefined});const value=await response.json();if(!response.ok)throw new Error(value.message||'Anfrage fehlgeschlagen.');return value;};
 const index=tree=>{const map=new Map();const walk=n=>{map.set(n.vmm_id,n);(n.children||[]).forEach(walk);};if(tree)walk(tree);return map;};
 const preview=()=>{const url=new URL(c.preview);url.searchParams.set('vmm_lang',c.locale);document.getElementById('vmm-visual-preview').href=url.href;};
 const load=async()=>{page=await request('visual/page/'+c.id+'?locale='+encodeURIComponent(c.locale));plugins.clear();preview();};
 const leave=()=>!dirty||window.confirm('Ungespeicherte Änderungen verwerfen?');
 const sourceBox=value=>{const box=el('pre',value||'Leer','vmm-visual-source');return box;};
 const fields=new Map();
 function field(key,label,source,value,custom,type='text'){
  const box=el('section',undefined,'vmm-visual-field'),heading=el('div',undefined,'vmm-visual-field-heading'),title=el('label',label),mode=el('select');
  mode.setAttribute('aria-label',label+' · Behandlung');for(const [v,t] of [['inherit','Übernehmen'],['custom','Abweichend bearbeiten']]){const option=el('option',t);option.value=v;mode.append(option);}mode.value=custom?'custom':'inherit';
  const input=el('textarea');input.value=custom?value:source;input.disabled=!custom;input.rows=['editor','content','textarea','html'].includes(type)?5:2;input.setAttribute('aria-label',label+' · Übersetzung');
  title.htmlFor='vmm-field-'+fields.size;input.id=title.htmlFor;heading.append(title,mode);box.append(heading,sourceBox(source),input);
  const entry={mode:mode.value,value:input.value};fields.set(key,entry);
  mode.addEventListener('change',()=>{entry.mode=mode.value;input.disabled=mode.value==='inherit';dirty=true;});input.addEventListener('input',()=>{entry.value=input.value;dirty=true;});
  if(['image','url','link'].includes(type)&&type!=='link'){if(type==='image'&&source){const img=el('img');img.src=/^\d+$/.test(source)?'':source;img.alt='Ausgangsbild';if(img.src)box.append(img);}}
  if(type==='image'&&window.wp?.media){const choose=el('button','Bild auswählen');choose.type='button';choose.addEventListener('click',()=>{const media=wp.media({title:label,library:{type:'image'},multiple:false});media.on('select',()=>{const image=media.state().get('selection').first().toJSON();input.value=key==='thumbnail'?String(image.id):image.url;entry.value=input.value;mode.value=entry.mode='custom';input.disabled=false;dirty=true;});media.open();});box.append(choose);}
  if(type==='select-widget'){const choices=el('select');choices.setAttribute('aria-label','Alternatives Widget');for(const [id,name] of Object.entries(c.widgets||{})){const option=el('option',name);option.value=id;choices.append(option);}choices.value=input.value;choices.addEventListener('change',()=>{input.value=entry.value=choices.value;mode.value=entry.mode='custom';input.disabled=false;dirty=true;});box.append(choices);}
  // Attributes inside a rich-text field remain part of that exact original field.
  if(['editor','content','html'].includes(type)&&/<[a-z]/i.test(input.value)){
   const details=el('details'),summary=el('summary','Links, Bilder & Barrierefreiheit im Inhalt');details.append(summary);
   const parsed=new DOMParser().parseFromString(input.value,'text/html');let count=0;
   parsed.body.querySelectorAll('*').forEach(node=>{
    for(const attribute of ['href','src','alt','title','aria-label','aria-description','placeholder']){
     if(!node.hasAttribute(attribute))continue;const label=el('label',node.tagName.toLowerCase()+' · '+attribute),edit=el('input');edit.value=node.getAttribute(attribute);edit.setAttribute('aria-label',label.textContent+' · Inhalt');label.append(edit);details.append(label);count++;
     edit.addEventListener('input',()=>{node.setAttribute(attribute,edit.value);input.value=parsed.body.innerHTML;entry.value=input.value;mode.value=entry.mode='custom';input.disabled=false;dirty=true;});
    }
   });if(count){box.append(details);input.addEventListener('input',()=>{details.hidden=true;});}
  }
  panel.append(box);
 }
 function inspector(target){
  if(!target)return;const details=el('details'),summary=el('summary','Elementinformationen');details.append(summary);
  const list=el('dl');for(const attr of target.attributes){if(attr.name.startsWith('data-vmm-')||['style','class'].includes(attr.name))continue;list.append(el('dt',attr.name),el('dd',attr.value));}
  for(const attr of ['aria-labelledby','aria-describedby'])for(const id of (target.getAttribute(attr)||'').split(/\s+/)){const ref=target.ownerDocument.getElementById(id);if(ref)list.append(el('dt',attr+' → '+id),el('dd',ref.textContent));}
  details.append(list);panel.append(details);
 }
 async function discover(target){
  const root=target?.closest('.vmm-plugin-output');if(!root||!c.plugins||target.dataset.vmmFields||target.closest('script,style,textarea,pre,code,svg,noscript,[contenteditable]'))return;
  const parts=[];let node=target;while(node&&node!==root){const tag=node.tagName.toLowerCase(),siblings=Array.from(node.parentElement.children).filter(n=>n.tagName===node.tagName);parts.unshift(tag+'['+(siblings.indexOf(node)+1)+']');node=node.parentElement;}if(node!==root||!parts.length)return;
  const values={};let text=0;for(const child of target.childNodes)if(child.nodeType===3){const slot='text:'+text++;if(child.nodeValue.trim()&&/\p{L}/u.test(child.nodeValue))values[slot]=child.nodeValue;}
  for(const attr of ['alt','title','aria-label','aria-description','placeholder','src','href'])if(target.hasAttribute(attr)&&(attr!=='src'||target.tagName==='IMG')&&(attr!=='href'||target.tagName==='A'))values[attr]=target.getAttribute(attr);
  if(!Object.keys(values).length)return;
  const siblings=Array.from(target.ownerDocument.querySelectorAll('.vmm-plugin-output')).filter(n=>n.dataset.plugin===root.dataset.plugin&&n.dataset.context===root.dataset.context);
  const response=await fetch(c.rest+'visual/discover',{method:'POST',credentials:'same-origin',headers:{'X-WP-Nonce':c.nonce,'Content-Type':'application/json'},body:JSON.stringify({plugin:root.dataset.plugin,source_locale:c.source,context:root.dataset.context+' · Instanz '+(siblings.indexOf(root)+1),path:'root/'+parts.join('/'),fields:values})});
  const result=await response.json();if(!response.ok)throw new Error(result.message||'Element konnte nicht erfasst werden.');
  target.dataset.vmmPlugin=root.dataset.plugin;target.dataset.vmmElement=result.element;target.dataset.vmmFields=JSON.stringify(result.bindings);plugins.delete(root.dataset.plugin);
 }
 async function select(target,force=false){
  if(busy||(!force&&!leave()))return;const ticket=++generation;selected=target;dirty=false;fields.clear();panel.replaceChildren(el('h2','Element wird geladen …'));
  try{
   if(!page)await load();await discover(target);let kind,identity,documentData;
   const plugin=target?.matches('[data-vmm-plugin][data-vmm-fields]')?target:null,builder=target?.closest('[data-vmm-node]'),native=target?.closest('[data-vmm-native]');
   if(plugin&&c.plugins){kind='plugin';identity=plugin.dataset.vmmPlugin;if(!plugins.has(identity))plugins.set(identity,await request('visual/plugin?plugin='+encodeURIComponent(identity)+'&locale='+encodeURIComponent(c.locale)));documentData=plugins.get(identity);}
   else if(!target?.closest('.vmm-plugin-output')&&builder&&Number(builder.dataset.vmmPage)===c.id&&page.builder){kind='builder';identity=builder.dataset.vmmNode;documentData=page.builder;}
   else if(!target?.closest('.vmm-plugin-output')&&native&&Number(native.dataset.vmmPage)===c.id){kind='native';identity=native.dataset.vmmNative;documentData=page.native;}
   else if(target===null){kind='native';identity='page';documentData=page.native;}
   else {panel.replaceChildren(el('h2','Keine eindeutige Zuordnung'),el('p','Dieses Element besitzt noch keine Verbindung zu einem Übersetzungsfeld.'));inspector(target);return;}
   if(ticket!==generation)return;panel.replaceChildren(el('h2',kind==='builder'?'YOOtheme · '+(index(documentData.master).get(identity)?.type||'Element'):kind==='plugin'?'Plugin · Element':'WordPress · '+(identity==='page'?'Seitenfelder':identity)));
   panel.append(el('p',c.sourceName+' → '+c.languages[c.locale],'vmm-visual-languages'));
   if(kind==='builder'){
    const source=index(documentData.master).get(identity),translated=index(documentData.tree).get(identity);if(!source)throw new Error('Element nicht vorhanden.');
    const labels={content:'Inhalt',image:'Bild',image_alt:'Alternativtext',link:'Linkziel',link_aria_label:'ARIA-Beschriftung des Links',title:'Titel',meta:'Zusatztext',widget:'Widget',description:'Beschreibung'};
    for(const key of documentData.fields[source.type]||[]){if(Object.hasOwn(source.source?.props||{},key))continue;const meta=page.labels[source.type]?.[key]||{};field(key,labels[key]||meta.label||key,String(source.props?.[key]??''),String(translated.props?.[key]??''),!!documentData.records[identity]?.[key]&&documentData.records[identity][key].mode!=='inherit',meta.type||'text');}
   }else if(kind==='plugin'){
    const keys=JSON.parse(plugin.dataset.vmmFields),related=[];
    if(target.id)target.ownerDocument.querySelectorAll('label[for]').forEach(label=>{if(label.htmlFor===target.id)related.push(label);});
    for(const attr of ['aria-labelledby','aria-describedby'])for(const id of (target.getAttribute(attr)||'').split(/\s+/)){const ref=target.ownerDocument.getElementById(id);if(ref)related.push(ref);}
    related.forEach((node,i)=>{const candidates=[node,...node.querySelectorAll('[data-vmm-fields]')];for(const candidate of candidates)if(candidate.dataset.vmmPlugin===identity&&candidate.dataset.vmmFields)for(const [slot,key] of Object.entries(JSON.parse(candidate.dataset.vmmFields)))keys['Beschriftung/Hinweis '+(i+1)+' · '+slot]=key;});
    for(const [slot,key] of Object.entries(keys)){const row=documentData.rows[key];if(!row)continue;field(key,slot.startsWith('text:')?'Text '+(Number(slot.split(':')[1])+1):slot,row.source,documentData.records[key]?.value??row.source,!!documentData.records[key],row.type);}
   }else{
    const allowed=identity==='content'?['content']:identity==='thumbnail'?['thumbnail','image_alt','image_caption']:Object.keys(documentData.fields).filter(key=>key!=='content');
    for(const key of allowed){const meta=documentData.fields[key];if(meta)field(key,meta.label,documentData.source[key],documentData.values[key],!!documentData.records[key],meta.type);}
   }
   if(!fields.size)panel.append(el('p','Dieses Element enthält keine statischen übersetzbaren Felder. Dynamische Daten werden an ihrer Quelle bearbeitet.'));
   inspector(target);const save=el('button','Übersetzung speichern','vmm-visual-save');save.type='button';save.disabled=!fields.size;panel.append(save);
   save.addEventListener('click',async()=>{
    busy=true;save.disabled=true;language.disabled=true;message('Wird gespeichert …');const data={locale:c.locale,source_locale:c.source,fields:Object.fromEntries(fields)};
    try{
     if(kind==='plugin'){const updated=await request('visual/plugin',{...data,plugin:identity,revision:documentData.revision,source_hash:documentData.source_hash});plugins.set(identity,updated);}
     else {page=await request('visual/page/'+c.id,{...data,kind,node:identity,revision:documentData.revision,...(kind==='builder'?{master_hash:documentData.master_hash}:{source_hash:documentData.source_hash})});}
     dirty=false;message('Gespeichert. Die Übersetzung ist auch im Backend verfügbar.');
    }catch(error){message(error.message,true);}finally{busy=false;save.disabled=false;language.disabled=false;}
    if(!dirty)select(selected,true);
   });
  }catch(error){message(error.message,true);panel.replaceChildren(el('h2','Laden fehlgeschlagen'),el('p',error.message));}
 }
 function bind(){
  let doc;try{doc=frame.contentDocument;}catch{message('Die Vorschau muss auf derselben Domain liegen.',true);return;}if(!doc?.body)return;
  const style=doc.createElement('style');style.textContent='[data-vmm-node],[data-vmm-native],[data-vmm-plugin]{cursor:crosshair!important}.vmm-visual-hover{outline:2px solid #2271b1!important;outline-offset:3px}';doc.head.append(style);
  let hovered;
  doc.addEventListener('pointerover',e=>{if(navigation)return;hovered?.classList.remove('vmm-visual-hover');hovered=e.target.closest('[data-vmm-fields],.vmm-plugin-output *,[data-vmm-node],[data-vmm-native]');hovered?.classList.add('vmm-visual-hover');});
  doc.addEventListener('click',e=>{if(navigation){const link=e.target.closest('a[href]');if(link){const url=new URL(link.href,frame.src);if(url.origin===location.origin&&url.pathname!==new URL(frame.src).pathname){e.preventDefault();e.stopImmediatePropagation();url.searchParams.delete('vmm_visual_frame');url.searchParams.delete('vmm_lang');url.searchParams.set('vmm_visual','1');url.searchParams.set('locale',c.locale);location.href=url.href;}}return;}e.preventDefault();e.stopImmediatePropagation();select(e.target);},true);
  doc.addEventListener('submit',e=>{if(!navigation){e.preventDefault();e.stopImmediatePropagation();}},true);
  doc.addEventListener('keydown',e=>{if(e.key==='Escape'){hovered?.classList.remove('vmm-visual-hover');panel.focus();}});
 }
 frame.addEventListener('load',()=>{plugins.clear();bind();});if(frame.contentDocument?.readyState==='complete')bind();
 language.addEventListener('change',async()=>{if(!leave()){language.value=c.locale;return;}c.locale=language.value;dirty=false;page=null;await load().catch(e=>message(e.message,true));select(selected,true);});
 document.getElementById('vmm-visual-page').addEventListener('click',()=>select(null));
 document.getElementById('vmm-visual-navigation').addEventListener('click',e=>{if(!leave())return;navigation=!navigation;e.target.setAttribute('aria-pressed',String(navigation));e.target.textContent=navigation?'Elemente bearbeiten':'Navigieren';message(navigation?'Navigationsmodus: Links und Bedienelemente sind aktiv.':'Bearbeitungsmodus');});
 window.addEventListener('beforeunload',e=>{if(dirty){e.preventDefault();e.returnValue='';}});load().catch(e=>message(e.message,true));
})();
