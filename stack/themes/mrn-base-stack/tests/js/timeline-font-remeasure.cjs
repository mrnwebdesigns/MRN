/* Native timeline pins at its settled height even when fonts load after the first measurement. */
const assert=require('node:assert/strict'),fs=require('node:fs'),path=require('node:path'),vm=require('node:vm');
const source=fs.readFileSync(path.join(__dirname,'../../js/presentation.js'),'utf8');
const body=source.slice(source.indexOf('\tfunction initTimeline('),source.indexOf('\tfunction initHover('));
function run() {
 let bodyHeight=100; const handlers={}; let fontsDone; const fontsReady=new Promise(r=>{fontsDone=r;});
 const style={}, stageStyle={}, dataset={mrnTimelineStepStyle:'numbered'}, attrs=new Set();
 const mk=()=>({querySelector:q=>q==='summary'?{getBoundingClientRect:()=>({height:40}),addEventListener(){}}:{getBoundingClientRect:()=>({height:bodyHeight})},set open(v){},get open(){return true;},parentElement:{}});
 const steps=[mk(),mk(),mk()];
 const timeline={dataset,style:{setProperty(){}},removeAttribute:n=>{attrs.delete(n);delete dataset[n.replace('data-','')];},hasAttribute:n=>n==='data-enhanced'?dataset.enhanced==='true':attrs.has(n),contains:()=>false,
  querySelector:q=>q==='.mrn-timeline__range'?{style}:q==='.mrn-timeline__stage'?{style:stageStyle}:q==='.mrn-timeline__skip'?{addEventListener(){}}:q==='.mrn-timeline__end'?{focus(){}}:null,
  querySelectorAll:()=>steps};
 const media=q=>({matches:!q.includes('reduce'),addEventListener(){}});
 const window={innerHeight:2000,matchMedia:media,addEventListener:(t,f)=>{handlers[t]=f;},getComputedStyle:()=>({marginBottom:'0'})};
 const document={activeElement:null,fonts:{ready:fontsReady}};
 vm.runInNewContext(body+'\ninitTimeline(timeline);',{window,document,timeline,getComputedStyle:()=>({marginBottom:'0'}),wide:media('(min-width: 1000px)'),reduced:media('(prefers-reduced-motion: reduce)'),clamp:v=>v,schedule(){},topOffset:()=>0,watch(){}});
 return {style,handlers,setBody:v=>{bodyHeight=v;},fontsDone};
}
const x=run();const first=x.style.height;
x.setBody(126);x.fontsDone();
Promise.resolve().then(()=>Promise.resolve()).then(()=>{
 assert.notEqual(x.style.height,first,'fonts settling must re-measure the pinned height');
 const settled=x.style.height;x.setBody(150);x.handlers.load();
 assert.notEqual(x.style.height,settled,'the load event must re-measure the pinned height');
 console.log('Passed: timeline re-measures after fonts and page load.');
});
