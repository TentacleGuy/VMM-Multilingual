/* Only plugin-owned shortcode roots, never form values or the surrounding page. */
(()=>{
    const config=window.vmmPluginOutput;if(!config)return;
    const excluded='script,style,textarea,pre,code,svg,noscript,[contenteditable]';
    const instances=new Map(), initialized=new WeakSet();
    const initialize=root=>{
        if(initialized.has(root))return;initialized.add(root);
        const plugin=root.dataset.plugin,context=root.dataset.context,map=config.translations?.[plugin]||{},seen=new Set();let timer;
        const instanceKey=plugin+'|'+context;instances.set(instanceKey,(instances.get(instanceKey)||0)+1);const elementMap=config.elements?.[plugin]?.[context+' · Instanz '+instances.get(instanceKey)]||[];
        const rows=[];
        const value=(source,tag,attr='')=>{
            const key=context+' · '+tag+(attr?' · '+attr:'');
            if(map[key]&&Object.hasOwn(map[key],source))return map[key][source];
            if(config.capture&&source.trim()&&source.length<=4000&&/\p{L}/u.test(source)){
                const id=JSON.stringify([tag,attr,source]);if(!seen.has(id)&&seen.size<500){seen.add(id);rows.push({tag,attr,source});}
            }return source;
        };
        const scan=()=>{
            observer.disconnect();
            for(const entry of elementMap){
                let target=root;for(const part of entry.path.split('/').slice(1)){const match=/^([a-z0-9-]+)\[(\d+)\]$/.exec(part);if(!match){target=null;break;}target=Array.from(target.children).filter(el=>el.tagName.toLowerCase()===match[1])[Number(match[2])-1];if(!target)break;}
                if(!target)continue;
                if(entry.slot.startsWith('text:')){const text=Array.from(target.childNodes).filter(node=>node.nodeType===Node.TEXT_NODE)[Number(entry.slot.split(':')[1])];if(text&&(text.nodeValue===entry.source||text.nodeValue===entry.value)&&text.nodeValue!==entry.value)text.nodeValue=entry.value;}
                else if((target.getAttribute(entry.slot)===entry.source||target.getAttribute(entry.slot)===entry.value)&&target.getAttribute(entry.slot)!==entry.value){target.setAttribute(entry.slot,entry.value);if(entry.slot==='src'){target.removeAttribute('srcset');target.removeAttribute('sizes');}}
            }
            const walker=document.createTreeWalker(root,NodeFilter.SHOW_TEXT);let node;
            while(node=walker.nextNode()){
                const parent=node.parentElement;if(!parent||parent.closest(excluded)||parent.closest('.vmm-plugin-output')!==root)continue;
                const source=node.nodeValue,result=value(source,parent.tagName.toLowerCase());if(result!==source)node.nodeValue=result;
            }
            root.querySelectorAll('*').forEach(el=>{
                if(el.closest(excluded)||el.closest('.vmm-plugin-output')!==root)return;
                for(const attr of ['placeholder','aria-label','aria-description','title','alt','src','href']){
                    if(!el.hasAttribute(attr)||(attr==='src'&&el.tagName!=='IMG'))continue;
                    const source=el.getAttribute(attr);if(/^(javascript:|data:|#)/i.test(source))continue;
                    const result=value(source,el.tagName.toLowerCase(),attr);if(source!==result){el.setAttribute(attr,result);if(attr==='src'){el.removeAttribute('srcset');el.removeAttribute('sizes');}}
                }
            });
            observer.observe(root,{childList:true,subtree:true,characterData:true,attributes:true,attributeFilter:['placeholder','aria-label','aria-description','title','alt','src','href']});
            if(config.capture&&rows.length){
                const batch=rows.splice(0,100),body=new URLSearchParams({action:'vmm_output_capture',nonce:config.nonce,plugin,context,rows:JSON.stringify(batch)});
                fetch(config.ajax,{method:'POST',credentials:'same-origin',body}).catch(()=>{});
                if(rows.length)timer=setTimeout(scan,400);
            }
        };
        const observer=new MutationObserver(()=>{clearTimeout(timer);timer=setTimeout(scan,40);});scan();
    };
    const discover=node=>{
        if(node.nodeType!==Node.ELEMENT_NODE)return;
        if(node.matches('.vmm-plugin-output'))initialize(node);
        node.querySelectorAll('.vmm-plugin-output').forEach(initialize);
    };
    discover(document.documentElement);
    // AJAX navigation can replace entire plugin roots after the initial page load.
    new MutationObserver(records=>{for(const record of records)for(const node of record.addedNodes)discover(node);}).observe(document.documentElement,{childList:true,subtree:true});
})();
