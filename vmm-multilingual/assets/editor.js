/** Version-bound bridge for the exposed YOOtheme 5.0.46 app/events/HTTP APIs. */
export function install(api, config) {
    if (!api?.hooks || !api?.http?.addons) throw new Error('VMM: Unsupported YOOtheme editor API.');
    api.hooks.before('app.init', ({ Vue }) => {
        const languageName = locale => config.languages?.[locale] ?? locale;
        const clone = value => JSON.parse(JSON.stringify(value));
        const state = Vue.observable({ locale: config.sourceLocale ?? 'de_DE', busy: false, dirty: false, pageId: 0, message: '', error: false });
        const observer = new Vue();
        let builder, document, stopWatching, reset = false, generation = 0, normalizing = false;
        let identities = new WeakMap();
        const notify = (message, error = false) => { state.message = message; state.error = error; };
        const walk = (node, visitor) => { visitor(node); for (const child of node.children ?? []) walk(child, visitor); };
        const uid = () => crypto.randomUUID().replaceAll('-', '');
        function remember(tree) {
            identities = new WeakMap();
            walk(tree, node => identities.set(node, node.vmm_id));
        }
        function normalize() {
            if (!builder?.node?.type || normalizing || reset || !document) return;
            normalizing = true;
            try {
                const originals = new Map();
                walk(builder.node, node => { if (identities.has(node)) originals.set(identities.get(node), node); });
                const seen = new Set();
                walk(builder.node, node => {
                    let id = node.vmm_id;
                    if (!/^[a-f0-9]{32}$/.test(id ?? '') || seen.has(id) || (originals.has(id) && originals.get(id) !== node)) {
                        if (/^[a-f0-9]{32}$/.test(id ?? '') && originals.has(id)) Vue.set(node, 'vmm_origin_id', id);
                        id = uid();
                        Vue.set(node, 'vmm_id', id);
                    }
                    if (state.locale !== config.sourceLocale) {
                        let source;
                        walk(document.master, item => { if(item.vmm_id === (node.vmm_origin_id ?? id)) source=item; });
                        for (const field of Object.keys(document.resources?.[node.type] ?? {})) {
                            if (node.source?.props?.[field] || node.props?.['vmm_override_' + field] !== false || !source) continue;
                            const value=source.props?.[field] ?? '';
                            if(node.props[field] !== value) Vue.set(node.props,field,value);
                        }
                    }
                    seen.add(id);
                    identities.set(node, id);
                });
                state.dirty = true;
            } finally { normalizing = false; }
        }
        async function call(id, method, suffix, payload) {
            const response = await fetch(config.endpoint + id + suffix, {
                method, credentials: 'same-origin', cache: 'no-store',
                headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce }, body: JSON.stringify(payload),
            });
            const result = await response.json();
            if (!response.ok) throw new Error(result.message ?? 'VMM konnte nicht speichern.');
            return result;
        }
        function project(doc) {
            reset = true;
            document = doc;
            const tree = clone(doc.tree);
            if (doc.locale !== config.sourceLocale) walk(tree, node => {
                node.props ??= {};
                for (const field of Object.keys(doc.resources?.[node.type] ?? {})) {
                    if (node.source?.props?.[field]) continue;
                    node.props['vmm_override_' + field] = ['translate','replace'].includes(doc.records?.[node.vmm_id]?.[field]?.mode);
                }
            });
            builder.reset(tree);
            remember(builder.node);
            reset = false;
            state.dirty = false;
            state.pageId = doc.id;
            Vue.events.trigger('loadPreview', { page: { id: doc.id, content: builder.empty ? null : builder.node } });
        }
        async function load(locale = state.locale) {
            const page = api.store.usePreviewStore().data.page;
            if (!builder || !page?.id) return;
            const serial = ++generation;
            state.busy = true;
            notify('Inhalte werden geladen …');
            try {
                const doc = await call(page.id, 'POST', '/open', { locale });
                if (serial !== generation || api.store.usePreviewStore().data.page?.id !== doc.id) return;
                state.locale = locale;
                project(doc);
                notify(locale !== config.sourceLocale ? languageName(locale) + ' · Inhalte übersetzen, Layout global bearbeiten' : languageName(config.sourceLocale) + ' · Ausgangssprache bearbeiten');
            } catch (error) { if (serial === generation) notify(error.message, true); }
            finally { if (serial === generation) state.busy = false; }
        }
        function payload(tree) {
            if (state.busy || !document || document.id !== api.store.usePreviewStore().data.page?.id) {
                throw new Error('Bitte warten, bis VMM diese Seite vollständig geladen hat.');
            }
            normalize();
            const inherit={}, overrides={};
            walk(tree,node=>{
                for(const field of Object.keys(document.resources?.[node.type] ?? {})) {
                    if(node.source?.props?.[field])continue;
                    const flag=node.props?.['vmm_override_'+field];
                    if(flag !== undefined) {
                        const map=flag?overrides:inherit;
                        (map[node.vmm_id]??=[]).push(field);
                        delete node.props['vmm_override_'+field];
                    }
                }
            });
            return { locale: state.locale, source_locale: config.sourceLocale, tree, inherit, overrides, revision: document.revision, master_hash: document.master_hash };
        }
        async function save() {
            try {
                normalize();
                const input = payload(clone(builder.node));
                state.busy = true;
                notify('Wird gespeichert …');
                await Vue.events.trigger('replaceImages', [input.tree], true);
                const result = await call(document.id, 'PUT', '', input);
                project(result.vmm);
                notify('Gespeichert · ' + (state.locale !== config.sourceLocale ? 'Ausgangssprache bleibt erhalten' : 'Master aktualisiert'));
            } catch (error) { notify(error.message, true); }
            finally { state.busy = false; }
        }
        // The native YOOtheme save button also uses the secured overlay save endpoint.
        api.http.addons.push({ beforeRequest(request, options) {
            const url = new URL(request._url, window.location.href);
            if (url.searchParams.get('yootheme') !== 'page' || options.method !== 'POST') return request;
            try {
                const native = JSON.parse(options.body);
                const page = JSON.parse(new TextDecoder().decode(Uint8Array.from(atob(native.page), c => c.charCodeAt(0))));
                if (document?.id !== page.id) throw new Error('VMM-Seitenkontext fehlt. Bitte den Builder neu laden.');
                const input = payload(page.content ?? { type: 'layout', vmm_id: document.master.vmm_id, children: [] });
                return request.url(config.endpoint + page.id, true).options({ method: 'PUT' }).headers({ 'X-WP-Nonce': config.nonce }).json(input);
            } catch (error) { notify(error.message, true); throw error; }
        } });
        Vue.events.on('initBuilder', event => {
            if (event.origin?.prefix !== 'page#') return;
            builder = event.origin;
            document = undefined;
            state.dirty = false;
            stopWatching?.();
            stopWatching = observer.$watch(() => builder.node, normalize, { deep: true, sync: true });
        });
        Vue.events.on('resetNode', (_event, _node, owner) => {
            if (!reset && owner?.prefix === 'page#') { builder = owner; void load(); }
        }, -100);
        Vue.events.on('savedPage', (_event, result) => {
            if (!result.vmm) return;
            document = result.vmm;
            setTimeout(() => { project(result.vmm); notify('Gespeichert · ' + (languageName(state.locale))); }, 0);
        });
        Vue.events.on('loadPreview', (_event, query) => {
            if (document && query && typeof query === 'object') query.vmm_lang = state.locale;
        }, 100);
        const ResourceSwitch = {
            name:'VmmResourceSwitch', props:['field','values'],
            render(h) {
                const {vmmNode:node,name:key,text}=this.field;
                return h('label',{class:'vmm-resource-switch'},[
                    h('input',{class:'uk-checkbox',attrs:{type:'checkbox'},domProps:{checked:!!node.props?.[key]},on:{change:event=>{
                        Vue.set(node.props,key,event.target.checked);
                        this.$emit('change');
                    }}}), ' '+text,
                ]);
            },
        };
        Vue.events.on('prepareFields', (event, fields) => {
            if (state.locale === config.sourceLocale || !document || event.origin?.builder?.prefix !== 'page#') return;
            const node = event.origin.node;
            if (!node?.vmm_id) return;
            let original;
            walk(document.master, item => { if (item.vmm_id === node.vmm_id) original = item; });
            if (!original) return;
            for (const field of [...fields]) {
                if (!(document.fields[node.type] ?? []).includes(field.name) || node.source?.props?.[field.name]) continue;
                const kind=document.resources?.[node.type]?.[field.name];
                const value=original.props?.[field.name] ?? '';
                const text = String(kind==='select-widget' ? (config.widgetNames?.[value] ?? value) : value).replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
                if(kind) {
                    const key='vmm_override_'+field.name;
                    const label=kind==='image'?'Abweichendes Bild':kind==='link'?'Abweichender Link':'Abweichendes Widget';
                    const originalShow=field.show;
                    fields.splice(fields.indexOf(field),0,{name:key,type:'checkbox',component:ResourceSwitch,vmmNode:node,label, text:label+' für '+languageName(state.locale),show:originalShow});

                    // Use the native field picker only when an override is enabled.
                    field.show=originalShow ? '('+originalShow+') && '+key : key;
                    const toggle=fields[fields.indexOf(field)-1];
                    const image=String(original.props?.[field.name]??'');
                    let preview='';
                    if(kind==='image' && image) {
                        const url=new URL(image,config.siteUrl).href.replace(/[&<>"']/g,c=>({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
                        if(/^https?:/.test(url))preview='<img class="vmm-inherited-image" src="'+url+'" alt="">';
                    }
                    toggle.description='<div class="vmm-original">Übernehmen aus '+languageName(config.sourceLocale)+preview+'<br>'+text+'</div>';
                }
                field.description = (field.description ?? '') + '<div class="vmm-original"><strong>Original (' + languageName(config.sourceLocale) + ')</strong><br>' + text + '</div>';
            }
        }, -100);
        const Toolbar = {
            name: 'VmmLanguageToolbar',
            mounted() { window.document.body.appendChild(this.$el); },
            beforeDestroy() { this.$el?.remove(); },
            render(h) {
                return h('section', { class: 'vmm-language-toolbar', attrs: { 'aria-label': 'VMM Bearbeitungssprache' } }, [
                    h('div', { class: 'vmm-language-row' }, [
                        h('label', { attrs: { for: 'vmm-editor-language' } }, ['Bearbeitungssprache']),
                        h('select', { attrs: { id: 'vmm-editor-language', disabled: state.busy }, domProps: { value: state.locale }, on: { change: event => {
                            const locale = event.target.value;
                            event.target.value = state.locale;
                            if (builder?.modified) { notify('Bitte Änderungen zuerst speichern oder über Abbrechen verwerfen.', true); return; }
                            void load(locale);
                        } } }, Object.entries(config.languages ?? {de_DE:'Deutsch',en_GB:'English'}).map(([locale,name]) => h('option', {attrs:{value:locale}}, [name]))),
                        h('button', { class: 'uk-button uk-button-primary uk-button-small', attrs: { type: 'button', disabled: state.busy || !state.dirty }, on: { click: save } }, ['Speichern']),
                    ]),
                    h('p', { class: { 'vmm-editor-error': state.error }, attrs: { role: 'status', 'aria-live': 'polite' } }, [state.message]),
                ]);
            },
        };
        // No DOM injection: add a Vue child through the app lifecycle extension.
        // Component identity is version-bound and verified by browser integration tests.
        Vue.mixin({ beforeDestroy() {
            if (this.$options.__name !== 'BuilderSection') return;
            stopWatching?.();
            document = undefined;
            generation++;
        }, beforeCreate() {
            if (this.$options.__name !== 'BuilderSection' || this.$options.vmmWrapped) return;
            const render = this.$options.render;
            if (typeof render !== 'function') return;
            this.$options.vmmWrapped = true;
            this.$options.render = function (context, cache, props, setup, data, options) {
                const vnode = render.call(this, context, cache, props, setup, data, options);
                vnode.children ??= [];
                vnode.children.unshift(this.$createElement(Toolbar));
                vnode.children.unshift(this.$createElement('p', { class: 'vmm-editor-current' }, ['Du bearbeitest ' + (languageName(state.locale)) + ' · Layout gilt für alle Sprachen']));
                return vnode;
            };
        } });
    });
}
