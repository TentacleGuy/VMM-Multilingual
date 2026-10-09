document.addEventListener('DOMContentLoaded',()=>{
    const dialog=document.querySelector('#vmm-string-dialog');if(!dialog)return;
    const edit=dialog.querySelector('#vmm-dialog-value'),source=dialog.querySelector('#vmm-dialog-source');
    let active=null,dirty=false,mediaFrame=null,rich=false;
    const readEditor=()=>{const editor=window.tinymce?.get(edit.id);if(editor&&!editor.isHidden())edit.value=editor.getContent();};
    const writeEditor=()=>window.tinymce?.get(edit.id)?.setContent(edit.value);
    const startEditor=()=>{rich=active?.dataset.type==='html';if(rich&&window.wp?.editor)wp.editor.initialize(edit.id,{tinymce:{wpautop:false,menubar:false,toolbar1:'formatselect,bold,italic,bullist,numlist,blockquote,link,unlink,undo,redo',toolbar2:'',height:260},quicktags:true,mediaButtons:false});};
    const sync=row=>{const custom=row.querySelector('.vmm-mode').value==='custom';row.querySelector('.vmm-inherit').setAttribute('aria-pressed',String(!custom));row.querySelector('.vmm-translate').setAttribute('aria-pressed',String(custom));row.querySelector('.vmm-state').textContent=custom?'Eigene Übersetzung':'Ausgangssprache übernehmen';};
    document.querySelectorAll('.vmm-string').forEach(row=>{
        row.querySelector('.vmm-inherit').addEventListener('click',()=>{row.querySelector('.vmm-mode').value='inherit';sync(row);dirty=true;});
        row.querySelector('.vmm-translate').addEventListener('click',()=>{
            active=row;const original=row.querySelector('.vmm-source').textContent;
            dialog.querySelector('#vmm-dialog-title').textContent=row.querySelector('.vmm-label').textContent;
            const context=row.querySelector('.vmm-field-context-value')?.textContent||'';
            dialog.querySelector('#vmm-dialog-context').textContent=(context?'Kontext / zugehöriges Element: '+context+' · ':'')+(row.querySelector('.vmm-path')?.textContent||'');
            source.textContent=original||'Dieses Feld ist in der Ausgangssprache leer. Der oben genannte Text beschreibt das zugehörige Element.';
            dialog.querySelector('#vmm-dialog-hint').textContent=row.querySelector('.vmm-hint').textContent;
            dialog.querySelector('#vmm-dialog-tokens').textContent=row.querySelector('.vmm-tokens').textContent?'Platzhalter: '+row.querySelector('.vmm-tokens').textContent:'';
            edit.value=row.querySelector('.vmm-mode').value==='inherit'?original:row.querySelector('.vmm-value').value;
            edit.setCustomValidity('');
            dialog.querySelector('#vmm-dialog-media').hidden=!['image','html'].includes(row.dataset.type);
            dialog.querySelector('#vmm-dialog-media').textContent=row.dataset.type==='html'?'Bild in Inhalt einfügen':'Bild auswählen';
            const img=dialog.querySelector('#vmm-dialog-source-image');img.hidden=row.dataset.type!=='image'||!/^https?:\/\//i.test(original);img.removeAttribute('src');if(!img.hidden)img.src=original;
            dialog.showModal();startEditor();if(!rich)edit.focus();
        });sync(row);
    });
    dialog.querySelectorAll('.vmm-cancel').forEach(button=>button.addEventListener('click',()=>dialog.close()));
    dialog.addEventListener('close',()=>{readEditor();if(rich)window.wp?.editor?.remove(edit.id);rich=false;active=null;});
    dialog.querySelector('#vmm-dialog-copy').addEventListener('click',()=>{edit.value=active.querySelector('.vmm-source').textContent;edit.setCustomValidity('');writeEditor();if(!rich)edit.focus();});
    const tokens=value=>(value.match(/\{[a-zA-Z_][a-zA-Z0-9_]*\}|(?<![0-9])%(?:[0-9]+\$)?[-+0' #]*[0-9]*(?:\.[0-9]+)?[bcdeEfFgGosuxX]/g)||[]).sort().join('|');
    edit.addEventListener('input',()=>edit.setCustomValidity(''));
    dialog.querySelector('#vmm-dialog-apply').addEventListener('click',()=>{
        readEditor();if(tokens(active.querySelector('.vmm-source').textContent)!==tokens(edit.value)){edit.setCustomValidity('Bitte alle Platzhalter unverändert und gleich oft übernehmen.');if(rich)window.switchEditors?.go(edit.id,'html');edit.reportValidity();return;}
        active.querySelector('.vmm-mode').value='custom';active.querySelector('.vmm-value').value=edit.value;sync(active);dirty=true;dialog.close();
    });
    dialog.querySelector('#vmm-dialog-media').addEventListener('click',()=>{
        // WP media runs outside the dialog; release the native top layer while it is open.
        const row=active;dialog.close();
        mediaFrame=wp.media({title:'Abweichendes Bild',multiple:false,library:{type:'image'}});
        mediaFrame.on('select',()=>{const image=mediaFrame.state().get('selection').first().toJSON();if(row.dataset.type==='html'){const tag=document.createElement('img');tag.src=image.url;tag.alt=image.alt||'';edit.value=edit.value.slice(0,edit.selectionStart)+tag.outerHTML+edit.value.slice(edit.selectionEnd);}else edit.value=image.url;});
        mediaFrame.on('close',()=>{active=row;dialog.showModal();startEditor();if(!rich)edit.focus();});mediaFrame.open();
    });
    window.addEventListener('beforeunload',event=>{if(dirty){event.preventDefault();event.returnValue='';}});
    document.querySelector('form input[value="vmm_plugin_save"]')?.form.addEventListener('submit',()=>{dirty=false;});
});
