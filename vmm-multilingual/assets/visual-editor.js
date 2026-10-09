(()=>{
 'use strict';const c=window.vmmVisual;if(!c)return;
 const frame=document.getElementById('vmm-visual-frame'),panel=document.getElementById('vmm-visual-panel'),status=document.getElementById('vmm-visual-status'),language=document.getElementById('vmm-visual-language');
 const drafts=new Map();let switching=false;const redos=[];const steps=[],journal=[],sessionDocs=new Map();let restoring=false,active=null;let page,selected,dirty=false,navigation=false,busy=false,generation=0;const plugins=new Map();let sourceView=false,restoreSelection=null;
 function updateActions(){panel.inert=busy;document.getElementById('vmm-visual-save').disabled=busy||!dirty;document.getElementById('vmm-visual-undo').disabled=busy||!steps.length;document.getElementById('vmm-visual-redo').disabled=busy||!redos.length;document.getElementById('vmm-visual-session').disabled=busy||(!dirty&&!journal.length);}
 const el=(tag,text,cls)=>{const e=document.createElement(tag);if(text!==undefined)e.textContent=text;if(cls)e.className=cls;return e;};
 const message=(text,error=false)=>{status.textContent=text;status.classList.toggle('error',error);};
 const request=async(path,data)=>{const response=await fetch(c.rest+path,{method:data?'PUT':'GET',credentials:'same-origin',headers:{'X-WP-Nonce':c.nonce,'Content-Type':'application/json'},body:data?JSON.stringify(data):undefined});const value=await response.json();if(!response.ok)throw new Error(value.message||'Anfrage fehlgeschlagen.');return value;};
 const index=tree=>{const map=new Map();const walk=n=>{map.set(n.vmm_id,n);(n.children||[]).forEach(walk);};if(tree)walk(tree);return map;};
  const reloadFrame=()=>{const url=new URL(frame.src);url.searchParams.set('vmm_lang',sourceView?c.source:c.locale);frame.title=sourceView?'Website in der Ausgangssprache':'Website in der Übersetzungssprache';frame.src=url.href;};
 const load=async()=>{page=await request('visual/page/'+c.id+'?locale='+encodeURIComponent(c.locale));plugins.clear();};
 const leave=async()=>!dirty||await confirmAction('Ungespeicherte Änderungen verwerfen?');
 const sourceBox=value=>{const box=el('pre',value||'Leer','vmm-visual-source');return box;};
  const sources=new Map();const fields=new Map();let editorSerial=0;const richEditors=new Set();
 const clearEditors=()=>{for(const id of richEditors)window.wp?.editor?.remove(id);richEditors.clear();};
 const normalizeValue=value=>String(value??'').replace(/<\/?p(?:\s[^>]*)?>|<br\s*\/?>/gi,' ').replace(/\s+/g,' ').trim();
 const syncRich=()=>{for(const id of richEditors){const editor=window.tinymce?.get(id),input=document.getElementById(id);if(editor&&input&&!editor.isHidden()&&normalizeValue(input.value)!==normalizeValue(editor.getContent())){input.value=editor.getContent();input.dispatchEvent(new Event('input'));}}};
 function field(key,label,source,value,custom,type='text',confirmed=false){
  custom=custom&&(String(source)!==''||String(value)!=='');
  const box=el('section',undefined,'vmm-visual-field'),heading=el('div',undefined,'vmm-visual-field-heading'),title=el('label',label),mode=el('select');
  mode.setAttribute('aria-label',label+' · Behandlung');for(const [v,t] of [['inherit','Übernehmen'],['custom','Abweichend bearbeiten']]){const option=el('option',t);option.value=v;mode.append(option);}mode.value=custom?'custom':'inherit';
  const input=el('textarea');input.value=custom?value:source;input.disabled=!custom;input.rows=['editor','content','textarea','html'].includes(type)?5:2;input.setAttribute('aria-label',label+' · Übersetzung');
  title.htmlFor='vmm-field-'+(++editorSerial);input.id=title.htmlFor;heading.append(title,mode);box.append(heading,sourceBox(source),input);
  const entry={mode:mode.value,value:input.value,confirmed_same:!!confirmed};fields.set(key,entry);sources.set(key,source);
  const badge=el('span',undefined,'vmm-field-badge');title.prepend(badge);const state=el('div',undefined,'vmm-field-state'),confirm=el('button','Gleicher Inhalt ist korrekt');confirm.type='button';
  const paint=()=>{box.dataset.mode=entry.mode;const st=fieldState(source,entry);badge.textContent=st.icon;badge.dataset.state=st.name;badge.title=st.label;badge.setAttribute('aria-label',st.label);state.hidden=st.name==='inherit';state.replaceChildren(el('span',st.icon+' '+st.label));if(st.name==='empty')state.append(el('p','Kein Text eingetragen. Beim Speichern bleibt dieser Inhalt in der Zielsprache leer. Wähle „Übernehmen“, wenn der Ausgangstext erscheinen soll.','vmm-empty-warning'));confirm.hidden=st.name!=='same';state.append(confirm);};
  box.append(state);paint();let previous=JSON.stringify(entry),editing=false,visit=null;const beginVisit=()=>{if(!editing)visit=null;editing=true;},endVisit=()=>queueMicrotask(()=>{if(!box.contains(document.activeElement)){editing=false;visit=null;}});input.addEventListener('focus',beginVisit);input.addEventListener('blur',endVisit);
  const track=()=>{if(!box.isConnected)return;const next=JSON.stringify(entry);if(next!==previous&&!restoring){redos.length=0;if(editing&&visit&&steps[steps.length-1]===visit){visit.after=JSON.parse(next);}else{const step={local:true,key,before:JSON.parse(previous),after:JSON.parse(next),target:selected,owner:{kind:active.kind,identity:active.identity}};steps.push(step);visit=editing?step:null;}}previous=next;paint();stash();updateActiveBadge();updateActions();};
  entry.refresh=()=>{visit=null;mode.value=entry.mode;input.value=entry.value;input.disabled=entry.mode==='inherit';const ed=window.tinymce?.get(input.id);if(ed){ed.setContent(entry.value);ed.setMode(entry.mode==='inherit'?'readonly':'design');}previous=JSON.stringify(entry);paint();};
  confirm.addEventListener('click',()=>{entry.confirmed_same=true;dirty=true;track();});
  input.addEventListener('input',()=>{entry.confirmed_same=false;});
  mode.addEventListener('change',()=>queueMicrotask(track));input.addEventListener('input',()=>queueMicrotask(track));
  box.addEventListener('change',()=>queueMicrotask(track));box.addEventListener('input',()=>queueMicrotask(track));
  mode.addEventListener('change',()=>{editing=false;visit=null;entry.mode=mode.value;input.disabled=mode.value==='inherit';dirty=true;updateActions();});input.addEventListener('input',()=>{entry.value=input.value;dirty=true;updateActions();});
  if(['image','url','link'].includes(type)&&type!=='link'){if(type==='image'&&source){const img=el('img');img.src=/^\d+$/.test(source)?'':source;img.alt='Ausgangsbild';if(img.src)box.append(img);}}
  if(type==='image'&&window.wp?.media){const choose=el('button','Bild auswählen');choose.type='button';choose.addEventListener('click',()=>{const media=wp.media({title:label,library:{type:'image'},multiple:false});media.on('select',()=>{const image=media.state().get('selection').first().toJSON();input.value=key==='thumbnail'?String(image.id):image.url;entry.value=input.value;mode.value=entry.mode='custom';input.disabled=false;entry.confirmed_same=false;dirty=true;track();updateActions();});media.open();});box.append(choose);}
  if(type==='select-widget'){const choices=el('select');choices.setAttribute('aria-label','Alternatives Widget');for(const [id,name] of Object.entries(c.widgets||{})){const option=el('option',name);option.value=id;choices.append(option);}choices.value=input.value;choices.addEventListener('change',()=>{input.value=entry.value=choices.value;mode.value=entry.mode='custom';input.disabled=false;dirty=true;updateActions();});box.append(choices);}
  // Attributes inside a rich-text field remain part of that exact original field.
  if(['editor','content','html'].includes(type)&&/<[a-z]/i.test(input.value)){
   const details=el('details'),summary=el('summary','Links, Bilder & Barrierefreiheit im Inhalt');details.append(summary);
   const parsed=new DOMParser().parseFromString(input.value,'text/html');let count=0;
   parsed.body.querySelectorAll('*').forEach(node=>{
    for(const attribute of ['href','src','alt','title','aria-label','aria-description','placeholder']){
     if(!node.hasAttribute(attribute))continue;const label=el('label',node.tagName.toLowerCase()+' · '+attribute),edit=el('input');edit.addEventListener('focus',()=>{editing=true;visit=null;});edit.addEventListener('blur',()=>{editing=false;visit=null;});edit.value=node.getAttribute(attribute);edit.setAttribute('aria-label',label.textContent+' · Inhalt');label.append(edit);details.append(label);count++;
     edit.addEventListener('input',()=>{node.setAttribute(attribute,edit.value);input.value=parsed.body.innerHTML;entry.value=input.value;mode.value=entry.mode='custom';input.disabled=false;dirty=true;updateActions();});
    }
   });if(count){box.append(details);input.addEventListener('input',()=>{details.hidden=true;});}
  }
  panel.append(box);
  const rich=['editor','content','html'].includes(type)||(['textarea','text'].includes(type)&&!/(?:alt|aria|placeholder|title|url|link)/i.test(key)&&/<\/?(?:p|br|strong|em|ul|ol|li|h[1-6]|a|img|blockquote|div|span)\b/i.test(source+' '+value));
  if(rich&&window.wp?.editor){
   richEditors.add(input.id);
   const changed=editor=>{if(entry.mode==='inherit')return;const value=editor.getContent();if(normalizeValue(input.value)===normalizeValue(value))return;input.value=value;entry.value=input.value;entry.confirmed_same=entry.confirmed_same&&normalizeValue(input.value)===normalizeValue(source);dirty=true;track();updateActions();box.querySelectorAll('details').forEach(d=>d.hidden=true);};
   wp.editor.initialize(input.id,{tinymce:{wpautop:false,menubar:false,toolbar1:'formatselect,bold,italic,bullist,numlist,blockquote,link,unlink',toolbar2:'',height:220,setup:editor=>{editor.on('keydown',historyShortcut);editor.on('focus',beginVisit);editor.on('blur',()=>{changed(editor);endVisit();});editor.on('init',()=>editor.setMode(entry.mode==='inherit'?'readonly':'design'));editor.on('change input undo redo',()=>changed(editor));}},quicktags:true,mediaButtons:false});
   mode.addEventListener('change',()=>{window.tinymce?.get(input.id)?.setMode(entry.mode==='inherit'?'readonly':'design');});
   box.querySelectorAll('details input').forEach(edit=>edit.addEventListener('input',()=>{window.tinymce?.get(input.id)?.setContent(input.value);window.tinymce?.get(input.id)?.setMode('design');}));
  }
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
    if(!sourceView){
   if(!plugins.has(root.dataset.plugin))plugins.set(root.dataset.plugin,await request('visual/plugin?plugin='+encodeURIComponent(root.dataset.plugin)+'&locale='+encodeURIComponent(c.locale)));
   const known=plugins.get(root.dataset.plugin),context=root.dataset.context+' · Instanz '+(siblings.indexOf(root)+1),path='root/'+parts.join('/'),bindings={};
   for(const [key,row] of Object.entries(known.rows))if(row.visual&&row.context===context&&row.path===path+' · '+row.slot)bindings[row.slot]=key;
   if(Object.keys(bindings).length){target.dataset.vmmPlugin=root.dataset.plugin;target.dataset.vmmFields=JSON.stringify(bindings);return;}
   throw new Error('Dieses nachgeladene Element ist noch nicht zugeordnet. Bitte einmal in der Ausgangssprache anklicken, damit der Originaltext erfasst wird.');
  }
  const response=await fetch(c.rest+'visual/discover',{method:'POST',credentials:'same-origin',headers:{'X-WP-Nonce':c.nonce,'Content-Type':'application/json'},body:JSON.stringify({plugin:root.dataset.plugin,source_locale:c.source,context:root.dataset.context+' · Instanz '+(siblings.indexOf(root)+1),path:'root/'+parts.join('/'),fields:values})});
  const result=await response.json();if(!response.ok)throw new Error(result.message||'Element konnte nicht erfasst werden.');
  target.dataset.vmmPlugin=root.dataset.plugin;target.dataset.vmmElement=result.element;target.dataset.vmmFields=JSON.stringify(result.bindings);plugins.delete(root.dataset.plugin);
 }
 async function select(target,force=false){
  if(busy)return;syncRich();await Promise.resolve();stash();const ticket=++generation;switching=true;clearEditors();active=null;selected=target;updateActions();fields.clear();sources.clear();panel.replaceChildren(el('h2','Element wird geladen …'));
  try{
   if(!page)await load();if(!target?.closest("[data-vmm-menu]"))await discover(target);let kind,identity,documentData,menuMode;
   const plugin=target?.matches('[data-vmm-plugin][data-vmm-fields]')?target:null,builder=target?.closest('[data-vmm-node]'),native=target?.closest('[data-vmm-native]');
   if(target?.closest('[data-vmm-menu]')){kind='menu';identity=target.closest('[data-vmm-menu]').dataset.vmmMenu;documentData=await request('visual/menu/'+identity+'?locale='+encodeURIComponent(c.locale));}
   else if(plugin&&c.plugins){kind='plugin';identity=plugin.dataset.vmmPlugin;if(!plugins.has(identity))plugins.set(identity,await request('visual/plugin?plugin='+encodeURIComponent(identity)+'&locale='+encodeURIComponent(c.locale)));documentData=plugins.get(identity);}
   else if(!target?.closest('.vmm-plugin-output')&&builder&&Number(builder.dataset.vmmPage)===c.id&&page.builder){kind='builder';identity=builder.dataset.vmmNode;documentData=page.builder;}
   else if(!target?.closest('.vmm-plugin-output')&&native&&Number(native.dataset.vmmPage)===c.id){kind='native';identity=native.dataset.vmmNative;documentData=page.native;}
   else if(target===null){kind='native';identity='page';documentData=page.native;}
   else {panel.replaceChildren(el('h2','Keine eindeutige Zuordnung'),el('p','Dieses Element besitzt noch keine Verbindung zu einem Übersetzungsfeld.'));inspector(target);return;}
   if(ticket!==generation)return;active={kind,identity,documentData};remember(active);panel.replaceChildren(el('h2',kind==='builder'?'YOOtheme · '+(index(documentData.master).get(identity)?.type||'Element'):kind==='plugin'?'Plugin · Element':kind==='menu'?'Menüpunkt':'WordPress · '+(identity==='page'?'Seitenfelder':identity)));
   panel.append(el('p',c.sourceName+' → '+c.languages[c.locale],'vmm-visual-languages'));
   if(kind==='menu'){
    menuMode=el('select');menuMode.setAttribute('aria-label','Menüpunkt · Behandlung');for(const [value,label] of [['inherit','Übernehmen'],['custom','Anpassen'],['hide','Nicht anzeigen']]){const option=el('option',label);option.value=value;menuMode.append(option);}menuMode.value=documentData.row.mode;panel.append(el('label','Menüpunkt in dieser Sprache'),menuMode);menuMode.dataset.previousMode=menuMode.value;menuMode.addEventListener('change',()=>{redos.length=0;steps.push({local:true,menu:true,mode:menuMode.dataset.previousMode,before:copy(Object.fromEntries(fields)),target:selected,owner:{kind:active.kind,identity:active.identity}});menuMode.dataset.previousMode=menuMode.value;if(menuMode.value==='inherit')for(const field of fields.values()){field.mode='inherit';field.refresh();}dirty=true;stash();updateActiveBadge();updateActions();});
    for(const [key,label,type] of [['label','Beschriftung','text'],['url','Linkziel','url']])field(key,label,documentData.source[key],documentData.row[key]||documentData.source[key],documentData.row.mode==='custom'&&documentData.row[key]!=='',type,!!documentData.confirmed_same?.[key]);
   }else if(kind==='builder'){
    const source=index(documentData.master).get(identity),translated=index(documentData.tree).get(identity);if(!source)throw new Error('Element nicht vorhanden.');
    const labels={content:'Inhalt',image:'Bild',image_alt:'Alternativtext',link:'Linkziel',link_aria_label:'ARIA-Beschriftung des Links',title:'Titel',meta:'Zusatztext',widget:'Widget',description:'Beschreibung'};
    for(const key of documentData.fields[source.type]||[]){if(Object.hasOwn(source.source?.props||{},key))continue;const meta=page.labels[source.type]?.[key]||{};field(key,labels[key]||meta.label||key,String(source.props?.[key]??''),String(translated.props?.[key]??''),!!documentData.records[identity]?.[key]&&documentData.records[identity][key].mode!=='inherit',meta.type||'text',!!documentData.records[identity]?.[key]?.confirmed_same);}
   }else if(kind==='plugin'){
    const keys=JSON.parse(plugin.dataset.vmmFields),related=[];
    if(target.id)target.ownerDocument.querySelectorAll('label[for]').forEach(label=>{if(label.htmlFor===target.id)related.push(label);});
    for(const attr of ['aria-labelledby','aria-describedby'])for(const id of (target.getAttribute(attr)||'').split(/\s+/)){const ref=target.ownerDocument.getElementById(id);if(ref)related.push(ref);}
    related.forEach((node,i)=>{const candidates=[node,...node.querySelectorAll('[data-vmm-fields]')];for(const candidate of candidates)if(candidate.dataset.vmmPlugin===identity&&candidate.dataset.vmmFields)for(const [slot,key] of Object.entries(JSON.parse(candidate.dataset.vmmFields)))keys['Beschriftung/Hinweis '+(i+1)+' · '+slot]=key;});
    for(const [slot,key] of Object.entries(keys)){const row=documentData.rows[key];if(!row)continue;field(key,slot.startsWith('text:')?'Text '+(Number(slot.split(':')[1])+1):slot,row.source,documentData.records[key]?.value??row.source,!!documentData.records[key],row.type,!!documentData.records[key]?.confirmed_same);}
   }else{
    const allowed=identity==='content'?['content']:identity==='thumbnail'?['thumbnail','image_alt','image_caption']:Object.keys(documentData.fields).filter(key=>key!=='content');
    for(const key of allowed){const meta=documentData.fields[key];if(meta)field(key,meta.label,documentData.source[key],documentData.values[key],!!documentData.records[key],meta.type,!!documentData.records[key]?.confirmed_same);}
   }
   active.baseline=copy(Object.fromEntries(fields));const draft=drafts.get(draftKey(active));if(draft){restoring=true;for(const [key,value] of Object.entries(draft.fields)){if(fields.has(key)){Object.assign(fields.get(key),value);fields.get(key).refresh();}}if(menuMode&&draft.mode){menuMode.value=menuMode.dataset.previousMode=draft.mode;}restoring=false;}switching=false;updateActiveBadge();
   if(!fields.size)panel.append(el('p','Dieses Element enthält keine statischen übersetzbaren Felder. Dynamische Daten werden an ihrer Quelle bearbeitet.'));
   inspector(target);const save=document.getElementById('vmm-visual-save');save.disabled=!fields.size;updateActions();
   save.onclick=saveDrafts;

  }catch(error){switching=false;message(error.message,true);panel.replaceChildren(el('h2','Laden fehlgeschlagen'),el('p',error.message));}
 }
 const draftKey=a=>a.kind+':'+(a.kind==='native'?'page':a.identity);
 const comparable=value=>value?.mode==='inherit'?{mode:'inherit'}:{mode:'custom',value:String(value?.value??''),confirmed_same:!!value?.confirmed_same};
 function stash(){
  if(switching||restoring||!active?.baseline)return;
  const key=draftKey(active),old=drafts.get(key),draft=old||{kind:active.kind,identity:active.kind==='native'?'page':active.identity,documentData:copy(active.documentData),fields:{}};
  for(const [name,entry] of fields){if(JSON.stringify(comparable(entry))===JSON.stringify(comparable(active.baseline[name])))delete draft.fields[name];else draft.fields[name]=copy(entry);}
  if(active.kind==='menu'){const mode=panel.querySelector('[aria-label="Menüpunkt · Behandlung"]')?.value||active.documentData.row.mode;draft.mode=mode==='hide'?'hide':Object.values(draft.fields).some(f=>f.mode==='custom')?'custom':mode;}
  if(Object.keys(draft.fields).length||(active.kind==='menu'&&draft.mode!==active.documentData.row.mode))drafts.set(key,draft);else drafts.delete(key);
  dirty=drafts.size>0;renderDraftPreview();if(dirty&&!busy)message(drafts.size+' Element'+(drafts.size===1?'':'e')+' im Entwurf · noch nicht gespeichert.');updateActions();
 }
 async function saveDrafts(){
  if(busy)return;syncRich();await Promise.resolve();stash();if(!drafts.size)return;busy=true;updateActions();message('Alle Änderungen werden gespeichert …');const saved=[];
  try{
   // Check every draft before writing; existing services still enforce revision locks.
   for(const draft of drafts.values()){const doc=await fresh(draft),keys=Object.keys(draft.fields);if(JSON.stringify(operation({...draft,documentData:doc},keys))!==JSON.stringify(operation(draft,keys)))throw new Error('Ein bearbeitetes Element wurde inzwischen geändert. Der Entwurf bleibt erhalten; bitte den Konflikt vor dem Speichern auflösen.');}
   for(const [key,draft] of Array.from(drafts)){const doc=await fresh(draft),keys=Object.keys(draft.fields),before=operation({...draft,documentData:doc},keys);if(JSON.stringify(before)!==JSON.stringify(operation(draft,keys)))throw new Error('Ein bearbeitetes Element wurde inzwischen geändert.');const updated=await writeOperation(draft,doc);before.after=operation({...draft,documentData:updated},keys);saved.push(before);}
   journal.push(...saved);drafts.clear();steps.length=0;redos.length=0;if(saved.length)steps.push({group:true,operations:saved.slice()});dirty=false;page=null;active=null;selected=null;clearEditors();fields.clear();sources.clear();panel.replaceChildren(el('h2','Element auswählen'));reloadFrame();message('Alle Änderungen gespeichert.');
  }catch(error){let rollbackError='';for(const op of saved.slice().reverse()){try{await reverse(op);}catch(e){rollbackError=' Einige Änderungen konnten nicht zurückgerollt werden: '+e.message;journal.push(op);}}
   message(error.message+' Der Entwurf bleibt erhalten.'+rollbackError,true);
  }finally{busy=false;updateActions();}
 }

 function stageOperation(op,doc){const key=draftKey(op),draft=drafts.get(key)||{kind:op.kind,identity:op.identity,documentData:copy(doc),fields:{}};const baseline=operation({...op,documentData:doc},Object.keys(op.fields));for(const [field,value] of Object.entries(op.fields)){if(JSON.stringify(comparable(value))===JSON.stringify(comparable(baseline.fields[field])))delete draft.fields[field];else draft.fields[field]=copy(value);}if(op.kind==='menu')draft.mode=op.mode;if(Object.keys(draft.fields).length||(op.kind==='menu'&&draft.mode!==doc.row.mode))drafts.set(key,draft);else drafts.delete(key);}

 let previewDoc,previewOriginals=new Map();
 function safePreviewHtml(value){const parsed=new DOMParser().parseFromString(value,'text/html'),allowed=new Set('p br b strong em i u s a ul ol li blockquote div span figure figcaption img table thead tbody tr td th hr pre code h1 h2 h3 h4 h5 h6'.split(' '));for(const node of Array.from(parsed.body.querySelectorAll('*'))){if(!allowed.has(node.tagName.toLowerCase())){if(['SCRIPT','STYLE','IFRAME','OBJECT','EMBED'].includes(node.tagName))node.remove();else node.replaceWith(...node.childNodes);continue;}for(const attr of Array.from(node.attributes))if(!['class','href','title','src','alt','width','height','target','rel','aria-label','aria-description'].includes(attr.name)||(['href','src'].includes(attr.name)&&/^\s*(?:javascript|vbscript|data):/i.test(attr.value)))node.removeAttribute(attr.name);if(node.tagName==='A')node.setAttribute('rel','noopener noreferrer');}return parsed.body.innerHTML;}
 function previewFieldTarget(node,key,type){
  const mapping='[data-vmm-menu],[data-vmm-node],[data-vmm-native],[data-vmm-fields]';
  const own=child=>child===node||child.closest(mapping)===node;
  const find=selector=>node.matches(selector)?node:Array.from(node.querySelectorAll(selector)).find(own);
  const selector={content:'.el-content',title:'.el-title',meta:'.el-meta',image:'img',link:'a'}[key];
  let target=selector?find(selector):null;
  if(key==='content'&&!target&&(node.matches('h1,h2,h3,h4,h5,h6')||['text','headline'].includes(type)))target=node;
  // Preserve a title's link and its attributes while changing the visible label.
  if(key==='title'&&target){const link=Array.from(target.children).find(n=>n.matches('a'));if(link)target=link;}
  return target;
 }
 function renderDraftPreview(){
  const doc=frame.contentDocument;if(!doc?.body)return;if(previewDoc!==doc){previewDoc=doc;previewOriginals=new Map();}
  const set=(node,property,value)=>{if(!node)return;let saved=previewOriginals.get(node);if(!saved){saved=new Map();previewOriginals.set(node,saved);}const read=()=>property==='html'?node.innerHTML:property==='text'?node.nodeValue:node.getAttribute(property);if(!saved.has(property))saved.set(property,read());if(read()===value)return;if(property==='html')node.innerHTML=value;else if(property==='text')node.nodeValue=value;else if(value===null)node.removeAttribute(property);else node.setAttribute(property,value);};
  // Restore only changes whose draft no longer exists. Reapply current values below.
  const desired=new Map(),put=(node,property,value)=>{if(node){let values=desired.get(node);if(!values){values=new Map();desired.set(node,values);}values.set(property,value);}};
  for(const draft of drafts.values()){
   const attr={builder:'data-vmm-node',native:'data-vmm-native',menu:'data-vmm-menu'}[draft.kind];const nodes=draft.kind==='plugin'?Array.from(doc.querySelectorAll('[data-vmm-plugin][data-vmm-fields]')).filter(n=>n.dataset.vmmPlugin===draft.identity):attr?Array.from(doc.querySelectorAll('['+attr+']')).filter(n=>draft.kind==='native'||n.getAttribute(attr)===draft.identity):[];
   for(const node of nodes){if(draft.kind==='plugin'){const bindings=JSON.parse(node.dataset.vmmFields);for(const [slot,key] of Object.entries(bindings)){const entry=draft.fields[key];if(!entry)continue;const value=entry.mode==='inherit'?draft.documentData.rows[key]?.source:entry.value;if(slot.startsWith('text:')){const text=Array.from(node.childNodes).filter(n=>n.nodeType===3)[Number(slot.split(':')[1])];put(text,'text',String(value??''));}else if(['alt','title','aria-label','aria-description','placeholder','href','src'].includes(slot)&&!/^\s*(javascript|vbscript|data):/i.test(String(value)))put(node,slot,String(value??''));}continue;}
    const source=draft.kind==='builder'?index(draft.documentData.master).get(draft.identity)?.props:draft.documentData.source;
    for(const [key,entry] of Object.entries(draft.fields)){const value=String(entry.mode==='inherit'?source?.[key]??'':entry.value);
     if(draft.kind==='menu'){if(key==='label')put(node,'html',safePreviewHtml(value));if(key==='url'&&!/^\s*(javascript|vbscript|data):/i.test(value))put(node,'href',value);continue;}
     if(['content','title','meta'].includes(key)&&! /\[[a-z][\w-]*(?:\s|\])/i.test(value)){const target=draft.kind==='native'?(key==='content'&&node.dataset.vmmNative==='content'?node:null):previewFieldTarget(node,key,index(draft.documentData.master).get(draft.identity)?.type);put(target,'html',safePreviewHtml(value));}
     const image=previewFieldTarget(node,'image');if(key==='image'&&value&&!/^\s*(javascript|vbscript|data):/i.test(value)){put(image,'src',value);put(image,'srcset',null);}if(key==='image_alt')put(image,'alt',value);
     const link=previewFieldTarget(node,'link');if(key==='link'&&!/^\s*(javascript|vbscript|data):/i.test(value))put(link,'href',value);if(key==='link_aria_label')put(link,'aria-label',value);if(key==='link_title')put(link,'title',value);

    }
   }
  }
  for(const [node,properties] of previewOriginals)if(node.isConnected)for(const [property,value] of properties)if(!desired.get(node)?.has(property))set(node,property,value);
  for(const [node,properties] of desired)for(const [property,value] of properties)set(node,property,value);
 }

 function historyTarget(step){if(step.owner?.kind==='native'&&step.owner.identity==='page')return null;const attr={builder:'data-vmm-node',native:'data-vmm-native',menu:'data-vmm-menu'}[step.owner?.kind];if(attr)return Array.from(frame.contentDocument.querySelectorAll('['+attr+']')).find(n=>n.getAttribute(attr)===step.owner.identity)||step.target;return step.target;}

 function fieldState(source,record){
  if(!record||record.mode==='inherit'||(String(source??'')===''&&String(record.value??'')===''))return {name:'inherit',icon:'↪',label:'Ausgangssprache übernehmen'};
  if(!String(record.value??'').trim())return {name:'empty',icon:'∅',label:'Übersetzung leer'};
  if(normalizeValue(record.value)===normalizeValue(source)&&!record.confirmed_same)return {name:'same',icon:'?',label:'Gleicher Inhalt · Bestätigung erforderlich'};
  return {name:'translated',icon:'✓',label:normalizeValue(record.value)===normalizeValue(source)?'Gleicher Inhalt bestätigt':'Abweichender Inhalt übersetzt'};
 }
 const copy=value=>JSON.parse(JSON.stringify(value));
 const resourceKey=a=>a.kind+':'+a.identity+':'+c.locale;
 function remember(a){if(!sessionDocs.has(resourceKey(a)))sessionDocs.set(resourceKey(a),copy(a));}
 function operation(a,keys){const d=a.documentData;const values={};for(const key of keys){const record=a.kind==='builder'?d.records[a.identity]?.[key]:a.kind==='menu'?(d.row.mode==='custom'&&d.row[key]!==''?{value:d.row[key],confirmed_same:d.confirmed_same?.[key]}:null):d.records[key];values[key]=record?{mode:'custom',value:String(record.value??''),confirmed_same:!!record.confirmed_same}:{mode:'inherit',value:''};}return {kind:a.kind,identity:a.identity,sourceFingerprint:d.master_hash||d.source_hash||JSON.stringify(d.source),fields:values,...(a.kind==='menu'?{mode:d.row.mode}:{})};}
 async function fresh(a){if(a.kind==='plugin')return request('visual/plugin?plugin='+encodeURIComponent(a.identity)+'&locale='+encodeURIComponent(c.locale));if(a.kind==='menu')return request('visual/menu/'+a.identity+'?locale='+encodeURIComponent(c.locale));const p=await request('visual/page/'+c.id+'?locale='+encodeURIComponent(c.locale));return p[a.kind==='builder'?'builder':'native'];}
 async function writeOperation(op,doc){const data={locale:c.locale,source_locale:c.source,fields:op.fields,revision:doc.revision};if(op.kind==='menu')return request('visual/menu/'+op.identity,{...data,mode:op.mode});if(op.kind==='plugin')return request('visual/plugin',{...data,plugin:op.identity,source_hash:doc.source_hash});const result=await request('visual/page/'+c.id,{...data,kind:op.kind,node:op.identity,...(op.kind==='builder'?{master_hash:doc.master_hash}:{source_hash:doc.source_hash})});return result[op.kind==='builder'?'builder':'native'];}
 async function reverse(step){const doc=await fresh(step),now=operation({...step,documentData:doc},Object.keys(step.fields));if(step.after&&JSON.stringify(now)!==JSON.stringify(step.after))throw new Error('Die betroffenen Übersetzungen wurden inzwischen geändert. Rücksetzung abgebrochen; bitte neu laden.');await writeOperation(step,doc);}
 async function moveHistory(redo=false){
  const from=redo?redos:steps,to=redo?steps:redos;if(busy||!from.length)return;const step=from[from.length-1];
  if(step.local){if(!active||active.kind!==step.owner?.kind||active.identity!==step.owner?.identity||(!step.menu&&!fields.has(step.key))){await select(historyTarget(step),true);}if(!active||(!step.menu&&!fields.has(step.key)))return;restoring=true;
   if(step.menu){const menu=panel.querySelector('[aria-label="Menüpunkt · Behandlung"]'),inverse={...step,mode:menu.value,before:copy(Object.fromEntries(fields))};menu.value=menu.dataset.previousMode=step.mode;for(const [key,value] of Object.entries(step.before)){Object.assign(fields.get(key),value);fields.get(key).refresh();}to.push(inverse);}
   else {const field=fields.get(step.key),inverse={...step,before:copy(field),after:step.before};Object.assign(field,step.before);field.refresh();to.push(inverse);}
   from.pop();restoring=false;stash();updateActiveBadge();updateActions();message(redo?'Feldeingabe wiederhergestellt.':'Feldeingabe rückgängig gemacht.');return;
  }
  syncRich();await Promise.resolve();stash();const snapshot=copy(Array.from(drafts));busy=true;updateActions();
  try{if(step.draftGroup){drafts.clear();for(const [key,draft] of step.before)drafts.set(key,copy(draft));}
   else {for(const op of (step.group?step.operations:[step]).slice().reverse()){const doc=await fresh(op),now=operation({...op,documentData:doc},Object.keys(op.fields));if(op.after&&JSON.stringify(now)!==JSON.stringify(op.after))throw new Error('Die gespeicherte Übersetzung wurde inzwischen geändert.');stageOperation(op,doc);}}
   from.pop();to.push({draftGroup:true,before:snapshot});dirty=drafts.size>0;renderDraftPreview();switching=true;clearEditors();active=null;fields.clear();sources.clear();switching=false;panel.replaceChildren(el('h2','Element auswählen'));message(redo?'Entwurf wiederhergestellt.':'Änderung im Entwurf rückgängig gemacht.');
  }catch(e){drafts.clear();for(const [key,draft] of snapshot)drafts.set(key,draft);message(e.message,true);}finally{busy=false;updateActions();}

 }
 const undoStep=()=>moveHistory(false),redoStep=()=>moveHistory(true);
 function historyShortcut(event){if(!(event.ctrlKey||event.metaKey)||event.altKey)return;const key=(event.key||'').toLowerCase();if(key!=='z'&&key!=='y')return;event.preventDefault();event.stopPropagation();syncRich();queueMicrotask(()=>key==='y'||event.shiftKey?redoStep():undoStep());}
 panel.addEventListener('keydown',historyShortcut);

 function confirmAction(text,sharedOption=false){return new Promise(resolve=>{const dialog=el('dialog',undefined,'vmm-confirm-dialog'),title=el('h2','Änderungen zurücksetzen?'),description=el('p',text),actions=el('div'),cancel=el('button','Abbrechen'),confirm=el('button','Ja, zurücksetzen');cancel.type=confirm.type='button';confirm.className='vmm-confirm-danger';actions.append(cancel,confirm);dialog.append(title,description,actions);const shared=el('input');shared.type='checkbox';if(sharedOption){const label=el('label');label.append(shared,document.createTextNode(' Gemeinsam verwendete Menü- und Plugin-Inhalte ebenfalls zurücksetzen (wirkt auch auf anderen Seiten).'));dialog.insertBefore(label,actions);}document.body.append(dialog);const done=value=>{dialog.close();dialog.remove();resolve(value);};cancel.onclick=()=>done(false);confirm.onclick=()=>done(sharedOption?{shared:shared.checked}:true);dialog.addEventListener('cancel',e=>{e.preventDefault();done(false);});dialog.showModal();cancel.focus();});}

 async function discardSession(){if(busy||!await confirmAction('Alle Änderungen seit dem Öffnen dieses Editors in der aktuellen Sprache verwerfen? Auch bereits gespeicherte Änderungen dieser Sitzung werden zurückgesetzt.'))return;
  busy=true;updateActions();try{while(journal.length){const step=journal[journal.length-1];await reverse(step);journal.pop();}drafts.clear();redos.length=0;steps.length=0;dirty=false;page=null;active=null;clearEditors();panel.replaceChildren(el('h2','Element auswählen'));reloadFrame();message('Änderungen dieser Sitzung verworfen.');}catch(e){message('Rücksetzung angehalten: '+e.message,true);}finally{busy=false;updateActions();}
 }
 async function resetPage(){if(busy)return;const choice=await confirmAction('Alle seiteneigenen Übersetzungen der aktuellen Seite für '+c.languages[c.locale]+' auf Übernehmen zurücksetzen? Die Rücksetzung wird zunächst vorgemerkt und erst mit Speichern dauerhaft übernommen.',true);if(!choice)return;syncRich();await Promise.resolve();stash();redos.length=0;const snapshot=copy(Array.from(drafts));
  busy=true;updateActions();try{
   await load();const operations=[];const native={kind:'native',identity:'page',documentData:page.native};operations.push(operation(native,Array.from(new Set([...Object.keys(page.native.fields),...Object.keys(page.native.records||{}),...Object.keys(drafts.get('native:page')?.fields||{})]))));
   if(page.builder)for(const [id,node] of index(page.builder.master)){const keys=Array.from(new Set([...(page.builder.fields[node.type]||[]).filter(k=>!Object.hasOwn(node.source?.props||{},k)),...Object.keys(page.builder.records[id]||{}),...Object.keys(drafts.get('builder:'+id)?.fields||{})]));if(keys.length)operations.push(operation({kind:'builder',identity:id,documentData:page.builder},keys));}
   if(choice.shared){const doc=frame.contentDocument,menuIds=new Set(Array.from(doc.querySelectorAll('[data-vmm-menu]')).map(n=>n.dataset.vmmMenu));for(const id of menuIds){const a={kind:'menu',identity:id};operations.push(operation({...a,documentData:await fresh(a)},['label','url']));}
   const pluginKeys=new Map();for(const n of doc.querySelectorAll('[data-vmm-plugin][data-vmm-fields]')){if(!c.plugins)continue;const keys=pluginKeys.get(n.dataset.vmmPlugin)||new Set();Object.values(JSON.parse(n.dataset.vmmFields)).forEach(k=>keys.add(k));pluginKeys.set(n.dataset.vmmPlugin,keys);}for(const [id,keys] of pluginKeys){const a={kind:'plugin',identity:id};operations.push(operation({...a,documentData:await fresh(a)},Array.from(keys)));}}
   for(const op of operations){const reset={...op,fields:Object.fromEntries(Object.keys(op.fields).map(k=>[k,{mode:'inherit',value:''}])),...(op.kind==='menu'?{mode:'inherit'}:{})};const doc=op.kind==='builder'?page.builder:op.kind==='native'?page.native:await fresh(op);stageOperation(reset,doc);}
   steps.push({draftGroup:true,before:snapshot});dirty=drafts.size>0;switching=true;clearEditors();active=null;fields.clear();sources.clear();switching=false;renderDraftPreview();await paintPreview(frame.contentDocument);panel.replaceChildren(el('h2','Element auswählen'));message('Seitenübersetzungen im Entwurf zurückgesetzt. Zum dauerhaften Übernehmen speichern.');
  }catch(e){drafts.clear();for(const [key,draft] of snapshot)drafts.set(key,draft);message(e.message,true);}finally{busy=false;updateActions();}
 }

 function updateActiveBadge(){if(!active)return;const doc=frame.contentDocument;const attr=active.kind==='builder'?'data-vmm-node':active.kind==='menu'?'data-vmm-menu':active.kind==='native'?'data-vmm-native':null;let node=attr?Array.from(doc.querySelectorAll('['+attr+']')).find(n=>n.getAttribute(attr)===active.identity):selected;const badge=Array.from(doc.querySelectorAll('.vmm-preview-badge')).find(b=>b.vmmTarget===node);if(!badge)return;const order=['empty','same','translated','inherit'],states=Array.from(fields,([k,r])=>fieldState(sources.get(k),r)),st=states.sort((a,b)=>order.indexOf(a.name)-order.indexOf(b.name))[0];if(st){badge.dataset.state=st.name;badge.textContent=st.icon;badge.title=Array.from(new Set(states.map(s=>s.label))).join(' · ');badge.setAttribute('aria-label',badge.title);}}


 // Anchor to the painted content, never the full-width builder wrapper.
 function contentAnchor(node){
  const doc=node.ownerDocument,mapping='[data-vmm-menu],[data-vmm-node],[data-vmm-native],[data-vmm-fields]';
  const own=child=>child===node||child.closest(mapping)===node;
  const walker=doc.createTreeWalker(node,doc.defaultView.NodeFilter.SHOW_TEXT);let text;
  while((text=walker.nextNode())){if(!text.textContent.trim()||!own(text.parentElement)||text.parentElement.closest('script,style,svg,button.vmm-preview-badge'))continue;const range=doc.createRange();range.selectNodeContents(text);const r=Array.from(range.getClientRects()).find(r=>r.width&&r.height);if(r)return {rect:r,element:text.parentElement,image:false};}
  const image=node.matches('img,video,canvas')?node:Array.from(node.querySelectorAll('img,video,canvas')).find(own);
  if(image)return {rect:image.getBoundingClientRect(),element:image,image:true};
  if(doc.defaultView.getComputedStyle(node).backgroundImage!=='none')return {rect:node.getBoundingClientRect(),element:node,image:true};
  return null;
 }
 function anchorPosition(anchor,win){
  if(!anchor)return null;const r=anchor.rect;if(!r.width||!r.height||r.right<=0||r.left>=win.innerWidth||r.bottom<=0||r.top>=win.innerHeight)return null;
  let left=0,top=0,right=win.innerWidth,bottom=win.innerHeight;
  for(let parent=anchor.element;parent&&parent!==parent.ownerDocument.documentElement;parent=parent.parentElement){const style=win.getComputedStyle(parent);if(style.display==='none'||style.visibility==='hidden'||Number(style.opacity)===0)return null;
   if(parent!==anchor.element){const box=parent.getBoundingClientRect();if(/hidden|clip|scroll|auto/.test(style.overflowX)){left=Math.max(left,box.left);right=Math.min(right,box.right);}if(/hidden|clip|scroll|auto/.test(style.overflowY)){top=Math.max(top,box.top);bottom=Math.min(bottom,box.bottom);}}
  }
  // Offscreen slides must not be clamped to the edge of the slider.
  if(r.left<left||r.left+20>right||r.top<top||r.top+20>bottom)return null;
  const x=anchor.image?r.left+5:r.left,y=anchor.image?r.top+5:r.top-19;
  return {x,y:y>=top?y:r.top};
 }

 async function paintPreview(doc){
  if(doc.vmmBadgeBusy)return;doc.vmmBadgeBusy=true;doc.vmmLayoutCleanup?.();doc.querySelectorAll('.vmm-preview-badge').forEach(b=>b.remove());
  try{if(!page)await load();if(doc!==frame.contentDocument)return;const master=index(page.builder?.master),badges=[];
   const style=doc.getElementById('vmm-badge-style')||doc.createElement('style');style.id='vmm-badge-style';style.textContent='.vmm-preview-badge{position:fixed!important;z-index:2147483000!important;width:20px!important;height:20px!important;min-height:0!important;min-width:0!important;padding:0!important;border:1px solid #fff!important;border-radius:50%!important;font:700 13px/18px sans-serif!important;box-shadow:0 1px 4px #0005!important;color:white!important;cursor:pointer!important}.vmm-preview-badge[data-state=inherit]{background:#687785!important}.vmm-preview-badge[data-state=translated]{background:#16804a!important}.vmm-preview-badge[data-state=same]{background:#a66c00!important}.vmm-preview-badge[data-state=empty]{background:#c33333!important}';doc.head.append(style);
   const nodes=doc.querySelectorAll('[data-vmm-menu],[data-vmm-node],[data-vmm-native],[data-vmm-plugin][data-vmm-fields]');
   for(const node of nodes){let states=[],a;
    if(node.dataset.vmmMenu){a={kind:'menu',identity:node.dataset.vmmMenu};const d=await fresh(a);remember({...a,documentData:d});states=['label','url'].map(k=>fieldState(d.source[k],drafts.get('menu:'+a.identity)?.fields[k]??(d.row.mode==='custom'&&d.row[k]!==''?{mode:'custom',value:d.row[k],confirmed_same:d.confirmed_same?.[k]}:null)));}
    else if(node.dataset.vmmFields&&c.plugins){const id=node.dataset.vmmPlugin;if(!plugins.has(id))plugins.set(id,await fresh({kind:'plugin',identity:id}));const d=plugins.get(id);remember({kind:'plugin',identity:id,documentData:d});states=Object.values(JSON.parse(node.dataset.vmmFields)).filter(k=>d.rows[k]).map(k=>fieldState(d.rows[k].source,drafts.get('plugin:'+id)?.fields[k]??d.records[k]));}
    else if(node.dataset.vmmNode&&Number(node.dataset.vmmPage)===c.id&&page.builder){const id=node.dataset.vmmNode,n=master.get(id);if(!n)continue;states=(page.builder.fields[n.type]||[]).filter(k=>!Object.hasOwn(n.source?.props||{},k)&&(String(n.props?.[k]??'')!==''||page.builder.records[id]?.[k])).map(k=>fieldState(n.props?.[k]??'',drafts.get('builder:'+id)?.fields[k]??page.builder.records[id]?.[k]));}
    else if(node.dataset.vmmNative&&Number(node.dataset.vmmPage)===c.id){const keys=node.dataset.vmmNative==='content'?['content']:['thumbnail','image_alt','image_caption'];states=keys.filter(k=>page.native.fields[k]).map(k=>fieldState(page.native.source[k],drafts.get('native:page')?.fields[k]??page.native.records[k]));}
    if(!states.length)continue;const order=['empty','same','translated','inherit'],st=states.slice().sort((a,b)=>order.indexOf(a.name)-order.indexOf(b.name))[0];const badge=doc.createElement('button');badge.vmmTarget=node;badge.type='button';badge.className='vmm-preview-badge';badge.dataset.state=st.name;badge.textContent=st.icon;badge.title=Array.from(new Set(states.map(s=>s.label))).join(' · ');badge.setAttribute('aria-label',badge.title);badge.addEventListener('click',e=>{e.preventDefault();e.stopImmediatePropagation();select(node);});doc.body.append(badge);badges.push({node,badge});
   }
   let animation,lastPaint=0;const position=()=>{const occupied=[];for(const {node,badge} of badges){const bounds=node.getBoundingClientRect();let point=null;if(!navigation&&node.isConnected&&bounds.bottom>0&&bounds.top<doc.defaultView.innerHeight&&bounds.right>0&&bounds.left<doc.defaultView.innerWidth)point=anchorPosition(contentAnchor(node),doc.defaultView);badge.hidden=!point;if(!point)continue;if(occupied.some(p=>Math.abs(p.x-point.x)<20&&Math.abs(p.y-point.y)<20)){badge.hidden=true;continue;}occupied.push(point);badge.style.left=point.x+'px';badge.style.top=point.y+'px';}};
   const tick=time=>{if(time-lastPaint>30){position();lastPaint=time;}animation=doc.defaultView.requestAnimationFrame(tick);};position();animation=doc.defaultView.requestAnimationFrame(tick);doc.vmmLayoutCleanup=()=>doc.defaultView.cancelAnimationFrame(animation);if(!doc.vmmBadgeObserver){let timer;const observer=new doc.defaultView.MutationObserver(changes=>{if(changes.some(change=>Array.from(change.addedNodes).some(n=>n.nodeType===1&&!n.matches('.vmm-preview-badge,#vmm-badge-style')))){doc.defaultView.clearTimeout(timer);timer=doc.defaultView.setTimeout(()=>paintPreview(doc),150);}});observer.observe(doc.body,{childList:true,subtree:true});doc.vmmBadgeObserver=observer;}
  }catch(e){message('Statussymbole konnten nicht vollständig geladen werden: '+e.message,true);}finally{doc.vmmBadgeBusy=false;}
 }

 function bind(){
  let doc;try{doc=frame.contentDocument;}catch{message('Die Vorschau muss auf derselben Domain liegen.',true);return;}if(!doc?.body)return;
  doc.querySelectorAll('[class*=vmm-menu-item-]').forEach(node=>{const match=Array.from(node.classList).map(c=>/^vmm-menu-item-(\d+)$/.exec(c)).find(Boolean);if(match){const link=node.matches('a')?node:node.querySelector('a');if(link)link.dataset.vmmMenu=match[1];}});
  paintPreview(doc);const style=doc.createElement('style');style.textContent='[data-vmm-node],[data-vmm-native],[data-vmm-plugin]{cursor:crosshair!important}';doc.head.append(style);

  let hovered;
  doc.addEventListener('pointerover',e=>{if(navigation)return;hovered?.classList.remove('vmm-visual-hover');hovered=e.target.closest('[data-vmm-fields],.vmm-plugin-output *,[data-vmm-node],[data-vmm-native]');hovered?.classList.add('vmm-visual-hover');});
  doc.addEventListener('click',e=>{if(e.target.closest('.vmm-preview-badge'))return;if(navigation){const link=e.target.closest('a[href]');if(link){const url=new URL(link.href,frame.src);if(url.origin===location.origin&&url.pathname!==new URL(frame.src).pathname){e.preventDefault();e.stopImmediatePropagation();url.searchParams.delete('vmm_visual_frame');url.searchParams.delete('vmm_lang');url.searchParams.set('vmm_visual','1');url.searchParams.set('locale',c.locale);location.href=url.href;}}return;}e.preventDefault();e.stopImmediatePropagation();select(e.target);},true);
  doc.addEventListener('submit',e=>{if(!navigation){e.preventDefault();e.stopImmediatePropagation();}},true);
  doc.addEventListener('keydown',e=>{if(e.key==='Escape'){hovered?.classList.remove('vmm-visual-hover');panel.focus();}});
 }
 frame.addEventListener('load',()=>{plugins.clear();selected=null;bind();if(restoreSelection){const previous=restoreSelection;restoreSelection=null;const target=Array.from(frame.contentDocument.querySelectorAll('['+previous.attribute+']')).find(n=>n.getAttribute(previous.attribute)===previous.value);if(target)select(target,true);}});if(frame.contentDocument?.readyState==='complete'){bind();}
 language.addEventListener('change',async()=>{if(!await leave()){language.value=c.locale;return;}drafts.clear();active=null;c.locale=language.value;redos.length=0;steps.length=0;journal.length=0;sessionDocs.clear();dirty=false;updateActions();page=null;selected=null;clearEditors();await load().catch(e=>message(e.message,true));panel.replaceChildren(el('h2','Element auswählen'));reloadFrame();});
 const chooser=document.getElementById('vmm-visual-languages');
 chooser.querySelectorAll('[data-vmm-locale]').forEach(button=>button.addEventListener('click',async()=>{if(busy)return;if(!await leave())return;dirty=false;updateActions();language.value=button.dataset.vmmLocale;chooser.querySelector('summary').replaceChildren(...Array.from(button.childNodes).map(n=>n.cloneNode(true)),el('span','▾'));chooser.open=false;language.dispatchEvent(new Event('change'));}));
 document.getElementById('vmm-visual-save').onclick=saveDrafts;document.getElementById('vmm-visual-undo').addEventListener('click',undoStep);document.getElementById('vmm-visual-redo').addEventListener('click',redoStep);
 document.getElementById('vmm-visual-session').addEventListener('click',discardSession);
 document.getElementById('vmm-visual-reset').addEventListener('click',resetPage); document.getElementById('vmm-visual-page').addEventListener('click',()=>select(null));
 document.getElementById('vmm-visual-navigation').addEventListener('click',async e=>{if(busy)return;syncRich();stash();navigation=!navigation;frame.contentDocument?.querySelectorAll('.vmm-preview-badge').forEach(b=>b.hidden=navigation);const toggle=e.currentTarget;toggle.setAttribute('aria-checked',String(navigation));toggle.title=navigation?'Navigieren aktiv · zu Bearbeiten wechseln':'Bearbeiten aktiv · zu Navigieren wechseln';message(navigation?'Navigieren: Links und Bedienelemente sind aktiv.':'Bearbeiten: Ein Element anklicken, um es zu übersetzen.');}); window.addEventListener('beforeunload',e=>{if(dirty){e.preventDefault();e.returnValue='';}});updateActions();load().catch(e=>message(e.message,true));
})();
