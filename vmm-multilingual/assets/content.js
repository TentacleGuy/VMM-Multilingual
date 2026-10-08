/* Native content translations. Never updates the WordPress source editor. */
window.addEventListener('DOMContentLoaded', () => {
    const cfg=window.vmmContent;if(!cfg)return;
    const {createElement:h,useState}=wp.element;
    if(!wp.blocks.getBlockType('core/paragraph'))wp.blockLibrary.registerCoreBlocks();
    wp.apiFetch.use(wp.apiFetch.createNonceMiddleware(cfg.nonce));
    let doc=cfg.document, dirty=false, fields={};
    const root=document.getElementById('vmm-content-root');
    const el=(tag,text,attrs={})=>{const e=document.createElement(tag);if(text!==null)e.textContent=text;Object.entries(attrs).forEach(([k,v])=>e.setAttribute(k,v));return e;};
    const statusNames={inherited:'Übernommen',draft:'In Bearbeitung',translated:'Übersetzt',outdated:'Ausgangssprache geändert · Übersetzung prüfen'};
    const bar=el('div',null,{class:'vmm-content-bar'}), language=el('select',null,{'aria-label':'Übersetzungssprache'});
    Object.entries(cfg.languages).forEach(([locale,name])=>{if(locale!==doc.source_locale){const o=el('option',name,{value:locale});language.append(o);}});language.value=doc.locale;
    bar.append(el('strong','Ausgangssprache: '+cfg.languages[doc.source_locale]),el('label','Übersetzung: '),language);
    const enabled=el('input',null,{type:'checkbox',id:'vmm-content-enabled'});enabled.checked=doc.enabled;
    const enabledLabel=el('label','Öffentlich verfügbar ');enabledLabel.prepend(enabled);bar.append(enabledLabel);
    const status=el('select',null,{'aria-label':'Übersetzungsstatus'});['draft','translated'].forEach(s=>status.append(el('option',statusNames[s],{value:s})));status.value=doc.status==='translated'?'translated':'draft';bar.append(status);
    const save=el('button','Übersetzung speichern',{class:'button button-primary',type:'button'}),notice=el('span',statusNames[doc.status],{role:'status',class:'vmm-status'});bar.append(save,notice);root.append(bar);
    if(doc.locale===doc.source_locale){root.append(el('p','Aktiviere zuerst eine weitere Sprache in den VMM-Einstellungen.'));save.disabled=true;return;}
    language.addEventListener('change',()=>{if(dirty&&!confirm('Ungespeicherte Übersetzung verwerfen?')){language.value=doc.locale;return;}location.href=cfg.base+'&locale='+encodeURIComponent(language.value);});
    enabled.addEventListener('change',()=>dirty=true);status.addEventListener('change',()=>dirty=true);
    const blockRoots=[];
    Object.entries(doc.fields).forEach(([key,field])=>{
        const pair=el('section',null,{class:'vmm-pair','data-field':key}),left=el('div'),right=el('div');
        left.append(el('h2',field.label+' · '+cfg.languages[doc.source_locale]));right.append(el('h2',field.label+' · '+cfg.languages[doc.locale]));
        const original=el('div',doc.source[key],{class:'vmm-original'});left.append(original);
        if(field.type==='image'){original.textContent=field.source_url?'':'Kein Bild';if(field.source_url)original.append(el('img',null,{src:field.source_url,alt:'Bild der Ausgangssprache'}));}
        if(field.type==='content'){original.textContent='';const frame=el('iframe',null,{title:'Ausgangsinhalt',sandbox:'',style:'width:100%;min-height:420px;border:0'});frame.srcdoc='<meta charset="utf-8"><style>body{font:16px/1.6 sans-serif}img{max-width:100%}</style>'+field.preview;original.append(frame);}
        const mode=el('select',null,{'aria-label':field.label+' Behandlung'});mode.append(el('option','Ausgangssprache übernehmen',{value:'inherit'}),el('option','Abweichend bearbeiten',{value:'custom'}));mode.value=doc.records[key]?'custom':'inherit';
        const copy=el('button','In Übersetzung kopieren',{type:'button',class:'button'}),tools=el('div',null,{class:'vmm-field-mode'});tools.append(mode,copy);right.append(tools);
        const input=el(['textarea','content'].includes(field.type)?'textarea':'input',null,{id:'vmm-field-'+Object.keys(fields).length,'aria-label':field.label+' Übersetzung'});
        if(input.tagName==='TEXTAREA')input.rows=field.type==='content'?18:4;else input.type=field.type==='url'?'url':'text';
        input.value=doc.values[key];right.append(input);const entry={mode,input,value:input.value};fields[key]=entry;
        pair.append(left,right);root.append(pair);
        let blockMount=null;
        if(field.type==='content'&&doc.block_editor){
            input.hidden=true;blockMount=el('div',null,{class:'vmm-block-editor'});right.append(blockMount);
            const {BlockEditorProvider,BlockTools,WritingFlow,BlockList,Inserter,BlockInspector}=wp.blockEditor;
            function Blocks(){const [blocks,setBlocks]=useState(wp.blocks.parse(entry.value));entry.replace=value=>setBlocks(wp.blocks.parse(value));return h(BlockEditorProvider,{value:blocks,onInput:next=>{setBlocks(next);entry.value=wp.blocks.serialize(next);dirty=true;},onChange:next=>{setBlocks(next);entry.value=wp.blocks.serialize(next);dirty=true;},settings:{hasFixedToolbar:true,allowedBlockTypes:true}},h('div',{className:'editor-styles-wrapper'},h(Inserter),h(BlockTools,null,h(WritingFlow,null,h(BlockList)))),h('details',null,h('summary',null,'Block-Einstellungen'),h(BlockInspector)));}
            wp.element.createRoot(blockMount).render(h(Blocks));blockRoots.push(blockMount);
        }else if(field.type==='content'){wp.editor.initialize(input.id,{tinymce:{wpautop:true,toolbar1:'formatselect,bold,italic,bullist,numlist,blockquote,link,unlink,undo,redo',setup:editor=>editor.on('change input undo redo',()=>{dirty=true;})},quicktags:true,mediaButtons:true});}
        function sync(){const inherited=mode.value==='inherit';input.disabled=inherited;if(blockMount)blockMount.hidden=inherited;const wrap=document.getElementById('wp-'+input.id+'-wrap');if(wrap)wrap.hidden=inherited;input.hidden=!!blockMount||inherited;copy.disabled=false;}
        mode.addEventListener('change',()=>{dirty=true;sync();});copy.addEventListener('click',()=>{mode.value='custom';entry.value=doc.source[key];input.value=entry.value;if(entry.replace)entry.replace(entry.value);const editor=window.tinymce?.get(input.id);if(editor)editor.setContent(entry.value);dirty=true;sync();});
        input.addEventListener('input',()=>{entry.value=input.value;dirty=true;});
        if(field.type==='image'||key==='social_image'){const preview=el('img',null,{class:'vmm-image-preview',alt:'Ausgewähltes Bild'});if(field.image_url){preview.src=field.image_url;right.append(preview);}const choose=el('button','Bild auswählen',{type:'button',class:'button'});right.append(choose);choose.addEventListener('click',()=>{const media=wp.media({title:'Abweichendes Bild',multiple:false,library:{type:'image'}});media.on('select',()=>{const selected=media.state().get('selection').first().toJSON();input.value=field.type==='image'?String(selected.id):selected.url;entry.value=input.value;preview.src=selected.sizes?.medium?.url||selected.url;right.insertBefore(preview,choose);mode.value='custom';dirty=true;sync();});media.open();});}
        const changed=doc.records[key]&&doc.records[key].source_hash!==field.source_hash;if(changed)right.append(el('p','Ausgangswert geändert · Bitte Übersetzung prüfen.',{class:'vmm-outdated'}));
        sync();
    });
    save.addEventListener('click',async()=>{save.disabled=true;notice.textContent='Wird gespeichert …';const values={};Object.entries(fields).forEach(([key,e])=>{const editor=window.tinymce?.get(e.input.id);values[key]={mode:e.mode.value,value:editor&&!editor.isHidden()?editor.getContent():e.replace?e.value:e.input.value};});try{doc=await wp.apiFetch({path:cfg.endpoint,method:'PUT',data:{locale:doc.locale,source_locale:doc.source_locale,revision:doc.revision,source_hash:doc.source_hash,fields:values,status:status.value,enabled:enabled.checked}});dirty=false;notice.textContent='Gespeichert · '+statusNames[doc.status];}catch(error){notice.textContent=error.message||'Speichern fehlgeschlagen.';}finally{save.disabled=false;}});
    window.addEventListener('beforeunload',e=>{if(dirty){e.preventDefault();e.returnValue='';}});
});
