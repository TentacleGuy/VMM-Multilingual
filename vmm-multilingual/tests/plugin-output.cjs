const assert=require('node:assert/strict'),vm=require('node:vm'),fs=require('node:fs');
const observers=[];
class Element {
 constructor(tag,attrs={},parent=null){this.nodeType=1;this.tagName=tag.toUpperCase();this.attrs=attrs;this.parentElement=parent;this.children=[];this.childNodes=[];this.dataset={plugin:'example/main.php',context:'Shortcode [example]'};}
 matches(s){return s==='.vmm-plugin-output'&&!!this.attrs.root;}
 closest(s){if(s==='.vmm-plugin-output')return this.matches(s)?this:this.parentElement?.closest(s);return null;}
 querySelectorAll(s){return this.children.flatMap(c=>[...(s==='*'||c.matches(s)?[c]:[]),...c.querySelectorAll(s)]);}
 getAttribute(k){return this.attrs[k]??null;} hasAttribute(k){return k in this.attrs;} setAttribute(k,v){this.attrs[k]=v;} removeAttribute(k){delete this.attrs[k];}
}
function root(){const r=new Element('div',{root:true}),b=new Element('button',{'aria-description':'Hilfe'},r);b.childNodes=[{nodeType:3,nodeValue:'Laden',parentElement:b}];r.children=[b];r.childNodes=[b];return r;}
const first=root(),doc=new Element('html');doc.children=[first];
const context={window:{vmmPluginOutput:{translations:{'example/main.php':{'Shortcode [example] · button':{Laden:'Load'},'Shortcode [example] · button · aria-description':{Hilfe:'Help'}}},elements:{},capture:false}},document:{documentElement:doc,createTreeWalker(r){const nodes=r.querySelectorAll('*').flatMap(e=>e.childNodes.filter(n=>n.nodeType===3));return {nextNode:()=>nodes.shift()};}},Node:{ELEMENT_NODE:1,TEXT_NODE:3},NodeFilter:{SHOW_TEXT:4},MutationObserver:class{constructor(cb){this.cb=cb;observers.push(this);}observe(){}disconnect(){}},setTimeout(fn){fn();return 1;},clearTimeout(){},URLSearchParams,fetch(){throw Error('capture must remain off');}};
vm.runInNewContext(fs.readFileSync('vmm-multilingual/assets/plugin-output.js','utf8'),context);
assert.equal(first.children[0].childNodes[0].nodeValue,'Load');
assert.equal(first.children[0].getAttribute('aria-description'),'Help');
first.children[0].childNodes[0].nodeValue='Laden';observers[0].cb([]);
assert.equal(first.children[0].childNodes[0].nodeValue,'Load');
const later=root();doc.children.push(later);observers[1].cb([{addedNodes:[later]}]);
assert.equal(later.children[0].childNodes[0].nodeValue,'Load');
assert.equal(later.children[0].getAttribute('aria-description'),'Help');
observers[1].cb([{addedNodes:[later]}]);assert.equal(observers.length,3);
console.log('6 dynamic output checks passed');
