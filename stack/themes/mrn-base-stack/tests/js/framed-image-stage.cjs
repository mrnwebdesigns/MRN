/* Framed zoom: responsive peak, cubic return, reduced motion and unchanged defaults. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const source = fs.readFileSync(path.join(__dirname, '../../js/presentation.js'), 'utf8');
function fixture(options = {}) {
 const media = new Map(), handlers = {}, frames = [];
 let rect = {top:1000,height:600};
 const image = {offsetWidth: options.imageWidth ?? 800,style:{transform:''}};
 const stage = {dataset:{mrnImageStage:options.mode ?? 'framed-zoom',mrnImageZoomInset:options.inset ?? '60'},querySelector:()=>image,querySelectorAll:()=>[],getBoundingClientRect:()=>rect};
 const matchMedia = query => {if (!media.has(query)) media.set(query,{matches:query.includes('reduce')?!!options.reduced:true,listeners:[],addEventListener(type,cb){this.listeners.push(cb);}});return media.get(query);};
 const document = {readyState:'complete',querySelector:()=>null,querySelectorAll:selector=>selector==='[data-mrn-image-stage]'?[stage]:[]};
 const window = {innerHeight:1000,innerWidth:1280,matchMedia,requestAnimationFrame:cb=>frames.push(cb),addEventListener(type,cb){(handlers[type]??=[]).push(cb);}};
 vm.runInNewContext(source,{window,document});
 const update = () => {for(const cb of handlers.scroll??[])cb();while(frames.length)frames.shift()();};
 return {image,window,position(top){rect={...rect,top};update();},reduce(value){for(const [q,m]of media)if(q.includes('reduce')){m.matches=value;for(const cb of m.listeners)cb();}},resize(width){window.innerWidth=width;for(const cb of handlers.resize??[])cb();while(frames.length)frames.shift()();}};
}
const scale = f => Number(f.image.style.transform.match(/scale\(([^)]+)\)/)?.[1]??1);
const f = fixture();
assert.equal(scale(f),1,'Image enters at its original proportions');
f.position(600);assert.ok(Math.abs(scale(f)-(1+.525*.875))<1e-9,'First half follows the cubic viewport expansion');
f.position(200);assert.equal(scale(f),1.525,'Peak reaches viewport width minus the authored inset');
f.position(-200);assert.ok(Math.abs(scale(f)-(1+.525*.875))<1e-9,'Second half returns symmetrically');
f.position(-600);assert.equal(scale(f),1,'Image leaves at original proportions');
f.position(200);f.resize(390);assert.equal(scale(f),1,'Narrow viewport never shrinks an already wider image');
f.image.offsetWidth=313;f.resize(390);assert.ok(Math.abs(scale(f)-330/313)<1e-9,'Phone peak follows resized untransformed image width');
f.reduce(true);assert.equal(f.image.style.transform,'','Live reduced-motion change resets immediately');
f.reduce(false);assert.ok(scale(f)>1,'Removing reduced motion restores the current position');
for(const inset of ['-1','241','60.5','invalid'])assert.equal(fixture({inset}).image.style.transform,'','Invalid inset does not activate the effect');
assert.equal(scale(fixture({imageWidth:0})),1,'Unmeasured media remains at its original size');
const standard=fixture({mode:'scroll-zoom'});standard.position(200);assert.equal(scale(standard),1.08,'Existing standard zoom retains its sinusoidal peak');
assert.equal(fixture({reduced:true}).image.style.transform,'','Initial reduced motion remains still');
console.log('Passed: entry/exit proportions, viewport peak, cubic return, responsive resize, zero-width guard, live reduced motion, invalid controls and unchanged standard zoom');
