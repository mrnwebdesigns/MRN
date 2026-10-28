/* Flow gallery preserves source travel and native keyboard/reduced-motion access. */
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const source=fs.readFileSync(path.join(__dirname,'../../js/presentation.js'),'utf8');
const body=source.slice(source.indexOf('\tfunction initStrip('),source.indexOf('\tfunction initTimeline('));
function fixture(options={}) {
 let rect={top:1000,height:656}, update; const media=new Map(), handlers={}, events={}, siblings=[];
 const track={scrollWidth:options.trackWidth??2130,style:{transform:''}};
 const viewport={clientWidth:1000,offsetHeight:656,scrollLeft:0,querySelector:()=>track,addEventListener:(t,f)=>{events[t]=f;},contains:()=>false};
 const trigger={getBoundingClientRect:()=>rect};
 const dataset={mrnStripBehavior:options.flow===false?'': 'flow',mrnStripPace:options.pace??'1.6'};
 const range={dataset,style:{height:'',setProperty(){}},querySelector:()=>viewport,closest:()=>trigger,getBoundingClientRect:()=>rect,insertAdjacentElement:(where,e)=>siblings.push(e),hasAttribute:n=>n==='data-enhanced'?!!dataset.enhanced:n==='data-flow-enhanced'?!!dataset.flowEnhanced:false,removeAttribute:n=>{if(n==='data-enhanced')delete dataset.enhanced;if(n==='data-flow-enhanced')delete dataset.flowEnhanced;}};
 const matchMedia=q=>{if(!media.has(q))media.set(q,{matches:q.includes('reduce')?!!options.reduced:!options.mobile,callbacks:[],addEventListener(t,f){this.callbacks.push(f);}});return media.get(q);};
 const window={innerHeight:1000,scrollY:0,matchMedia,addEventListener:(t,f)=>{handlers[t]=f;},scrollTo(){},scrollBy(){}};
 const document={createElement:()=>({addEventListener(){},focus(){}})};
 vm.runInNewContext(body+'\ninitStrip(range);',{window,document,range,wide:matchMedia('(min-width: 1000px)'),reduced:matchMedia('(prefers-reduced-motion: reduce)'),sequence:0,clamp:v=>Math.max(0,Math.min(1,v)),schedule:()=>{},topOffset:()=>32,ResizeObserver:class{observe(){}},watch:(el,cb)=>{assert.equal(el,options.flow===false?range:trigger);update=cb;cb();}});
 return {range,track,viewport,siblings,position(top){rect.top=top;update();},reduce(v){const m=matchMedia('(prefers-reduced-motion: reduce)');m.matches=v;for(const f of m.callbacks)f();update();},mobile(v){const m=matchMedia('(min-width: 769px)');m.matches=!v;for(const f of m.callbacks)f();update();},focus(){events.focusin({target:viewport});},blur(){events.focusout({relatedTarget:null});update();}};
}
const f=fixture();assert.equal(f.range.style.height,'');assert.equal(f.range.hasAttribute('data-enhanced'),false);assert.equal(f.range.hasAttribute('data-flow-enhanced'),true);assert.equal(f.siblings[1].className,'screen-reader-text mrn-strip-flow-skip');
f.position(1000);assert.equal(f.track.style.transform,'translateX(0px)');f.position(200);assert.equal(f.track.style.transform,'translateX(-500px)');f.position(-808);assert.equal(f.track.style.transform,'translateX(-1130px)');f.position(-2000);assert.equal(f.track.style.transform,'translateX(-1130px)');
f.focus();assert.equal(f.track.style.transform,'');assert.equal(f.range.hasAttribute('data-flow-enhanced'),false);f.viewport.scrollLeft=240;assert.equal(f.viewport.scrollLeft,240);f.blur();assert.equal(f.range.hasAttribute('data-flow-enhanced'),true);
f.reduce(true);assert.equal(f.track.style.transform,'');assert.equal(f.range.style.height,'');f.reduce(false);assert.equal(f.range.hasAttribute('data-flow-enhanced'),true);f.mobile(true);assert.equal(f.track.style.transform,'');assert.equal(f.range.hasAttribute('data-flow-enhanced'),false);f.mobile(false);assert.equal(f.range.hasAttribute('data-flow-enhanced'),true);
assert.equal(fixture({mobile:true}).range.hasAttribute('data-flow-enhanced'),false);assert.equal(fixture({reduced:true}).range.hasAttribute('data-flow-enhanced'),false);assert.equal(fixture({trackWidth:800}).range.hasAttribute('data-flow-enhanced'),false);
for(const pace of ['bad','0','11']){const x=fixture({pace});x.position(200);assert.equal(x.track.style.transform,'translateX(-500px)');}
const old=fixture({flow:false});assert.equal(old.range.dataset.enhanced,'true');assert.equal(old.range.style.height,'1503.5px');assert.equal(old.siblings[1].className,'mrn-presentation-skip');
console.log('Passed: exact flow travel, no added stage height, keyboard fallback, reduced motion, 768px resizing, overflow guard, pace validation and unchanged pinned default.');
