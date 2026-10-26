/* Native section-bound word colors preserve DOM identity and reduced motion. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../../js/presentation.js'), 'utf8');
class Element {
 constructor(tag = 'div', text = '') { this.tag = tag; this.nodeType = tag === '#text' ? 3 : 1; this.value = text; this.childNodes = []; this.style = {}; this.dataset = {}; this.parentElement = null; }
 get textContent() { return this.nodeType === 3 ? this.value : this.childNodes.map(n => n.textContent).join(''); }
 set textContent(value) { this.childNodes = []; this.appendChild(new Element('#text', value)); }
 appendChild(node) { node.parentElement = this; this.childNodes.push(node); return node; }
 replaceWith(fragment) { const parent = this.parentElement, index = parent.childNodes.indexOf(this); parent.childNodes.splice(index, 1, ...fragment.childNodes); fragment.childNodes.forEach(n => { n.parentElement = parent; }); }
 closest(selector) { if (selector.startsWith('.mrn-content')) return this.scope; for (let node = this; node; node = node.parentElement) { if (['script','style','textarea','select','button','svg','math','pre','code'].includes(node.tag) || node.protected) return node; } return null; }
}
function fixture(options = {}) {
 const listeners = {}, frames = [], resizeObservers = [], queries = new Map(); let rect = {top:1000,height:400};
 const scope = {getBoundingClientRect: () => rect};
 const stage = new Element('blockquote'); stage.scope = scope; stage.dataset.mrnScrollWords = options.json ?? '{"from":"var(--site-color-muted)","to":"var(--site-color-ink)","scope":"split"}';
 stage.appendChild(new Element('#text',' One ')); const emphasis = stage.appendChild(new Element('em')); emphasis.appendChild(new Element('#text','two '));
 const link = stage.appendChild(new Element('a')); link.href = '/keep/?one=1&two=2'; link.handler = () => 'original'; link.appendChild(new Element('#text','three'));
 const br = stage.appendChild(new Element('br')); const strong = stage.appendChild(new Element('strong')); strong.appendChild(new Element('#text','\nfour'));
 const code = stage.appendChild(new Element('code')); code.appendChild(new Element('#text','code unchanged'));
 const hidden = stage.appendChild(new Element('span')); hidden.protected = true; hidden.appendChild(new Element('#text','decorative unchanged'));
 const original = stage.textContent;
 const allWords = node => node.childNodes.flatMap(child => [...(child.className === 'mrn-scroll-word' ? [child] : []), ...allWords(child)]);
 const document = {readyState:'complete',querySelector:()=>null,querySelectorAll:selector=>selector === '[data-mrn-scroll-words]' && !options.absent ? [stage] : [],createTextNode:text=>new Element('#text',text),createElement:tag=>new Element(tag),createDocumentFragment:()=>new Element('fragment'),createTreeWalker(root) { const nodes=[]; const walk=n=>n.childNodes.forEach(child=>{if(child.nodeType===3)nodes.push(child);else walk(child);});walk(root);let index=0;return {currentNode:null,nextNode(){this.currentNode=nodes[index++];return !!this.currentNode;}};}};
 const matchMedia = q => { if(!queries.has(q))queries.set(q,{matches:q.includes('reduce') ? !!options.reduced : true,listeners:[],addEventListener(t,cb){this.listeners.push(cb);}});return queries.get(q); };
 class ResizeObserver {constructor(cb){resizeObservers.push(cb);}observe(){}}
 const window = {innerHeight:1000,matchMedia,ResizeObserver,requestAnimationFrame:cb=>frames.push(cb),addEventListener(t,cb){(listeners[t]??=[]).push(cb);}};
 const context = {window,document,ResizeObserver,NodeFilter:{SHOW_TEXT:4},getComputedStyle:()=>({getPropertyValue:key=>key.endsWith('muted') ? (options.from??'#5D6180') : '#0F111A'})};
 const run = () => vm.runInNewContext(source,context); run();
 const flush = () => {while(frames.length)frames.shift()();};
 return {stage,link,emphasis,strong,br,code,hidden,original,words:()=>allWords(stage),rect(value){rect=value;},scroll(){for(const cb of listeners.scroll??[])cb();flush();},resize(){for(const cb of resizeObservers)cb();flush();},reduce(value){for(const [q,m]of queries)if(q.includes('reduce')){m.matches=value;for(const cb of m.listeners)cb();}flush();},run};
}
const f = fixture();
assert.equal(f.words().length,4,'Only visible rich text receives word wrappers');
assert.equal(f.stage.textContent,f.original,'All wording, whitespace, paragraph content and hidden/code text survive');
assert.ok(f.stage.childNodes.includes(f.link) && f.stage.childNodes.includes(f.emphasis) && f.stage.childNodes.includes(f.strong) && f.stage.childNodes.includes(f.br),'Existing links, emphasis, strong and break nodes keep their identity');
assert.equal(f.link.href,'/keep/?one=1&two=2');assert.equal(f.link.handler(),'original');assert.equal(f.code.childNodes[0].nodeType,3);assert.equal(f.hidden.childNodes[0].nodeType,3);
assert.ok(f.words().every(w=>w.style.color==='rgb(93,97,128)'),'Entry starts at the selected muted color');
f.rect({top:650,height:400});f.scroll();assert.deepEqual(f.words().map(w=>w.style.color),['rgb(15,17,26)','rgb(15,17,26)','rgb(93,97,128)','rgb(93,97,128)'],'Half progress reveals the first half in reading order');
f.rect({top:737.5,height:400});f.scroll();assert.deepEqual(f.words().map(w=>w.style.color),['rgb(15,17,26)','rgb(54,57,77)','rgb(93,97,128)','rgb(93,97,128)'],'Each word interpolates through its own scroll interval');
f.rect({top:800,height:600});f.resize();assert.equal(f.words()[0].style.color,'rgb(15,17,26)','Section resizing uses current enclosing geometry');
f.reduce(true);assert.ok(f.words().every(w=>w.style.color==='rgb(15,17,26)'),'Live reduced motion displays final text color');
f.reduce(false);assert.equal(f.words()[1].style.color,'rgb(93,97,128)','Re-enabling motion restores current scroll progress');
f.rect({top:-1000,height:400});f.scroll();assert.ok(f.words().every(w=>w.style.color==='rgb(15,17,26)'),'Past the section clamps at the final color');
f.rect({top:2000,height:400});f.scroll();assert.ok(f.words().every(w=>w.style.color==='rgb(93,97,128)'),'Reverse scroll clamps at the starting color');
f.run();assert.equal(f.words().length,4,'A second initializer cannot wrap words twice');
assert.ok(fixture({reduced:true}).words().every(w=>w.style.color==='rgb(15,17,26)'),'Initial reduced motion is static and readable');
for(const options of [{absent:true},{json:'null'},{json:'invalid'},{json:'{"scope":"body > *","from":"var(--site-color-muted)","to":"var(--site-color-ink)"}'},{json:'{"scope":"split","from":"red;display:none","to":"var(--site-color-ink)"}'},{from:'transparent'},{from:'rgb(999,0,0)'}]) {const bad=fixture(options);assert.equal(bad.words().length,0);assert.equal(bad.stage.textContent,bad.original,'Invalid or unselected settings leave DOM readable and unchanged');}
assert.equal(fixture({from:'#abc'}).words()[0].style.color,'rgb(170,187,204)');
assert.equal(fixture({from:'rgb(1, 2, 3)'}).words()[0].style.color,'rgb(1,2,3)');
console.log('Passed: section geometry, sequential/reverse interpolation, live reduced motion, rich-node identity, links, whitespace, protected nodes, idempotence and invalid defaults');
