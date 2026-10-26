/* Native sibling closure must cancel stale height callbacks before reopening. */
const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
function group(reduced = false, named = true) {
 const items = [];
 function make(open) {
  const handlers = {}, summaryHandlers = {}, transitions = new Set(), classes = new Set();
  const answer = {style:{},scrollHeight:120,offsetHeight:120,classList:{add:c=>classes.add(c),remove:c=>classes.delete(c),toggle(c,on){on?classes.add(c):classes.delete(c);}},addEventListener(type,cb){if(type==='transitionend')transitions.add(cb);},removeEventListener(type,cb){if(type==='transitionend')transitions.delete(cb);}};
  const summary = {addEventListener(type,cb){summaryHandlers[type]=cb;}};
  const item = {dataset:{},hasAttribute:()=>named,querySelector:s=>s.includes('question')?summary:answer,addEventListener(type,cb){handlers[type]=cb;}};
  Object.defineProperty(item,'open',{get:()=>open,set(value){open=value;if(value&&named)for(const peer of items)if(peer!==item&&peer.open)peer.open=false;handlers.toggle?.();}});
  item.click=()=>summaryHandlers.click({preventDefault(){}});item.finish=()=>[...transitions].forEach(cb=>cb({propertyName:'height'}));item.pending=()=>transitions.size;item.answer=answer;items.push(item);return item;
 }
 const first=make(true),second=make(false);
 vm.runInNewContext(fs.readFileSync(path.join(__dirname,'../../js/front-end-faq.js'),'utf8'),{document:{readyState:'complete',querySelectorAll:()=>items},window:{matchMedia:()=>({matches:reduced})}});
 return {first,second};
}
for(const reduced of [false,true]){
 const {first,second}=group(reduced);
 second.click();assert(!first.open&&second.open);second.finish();assert.equal(second.answer.style.height,'auto');
 second.click();if(!reduced)assert.equal(second.pending(),1);
 first.click();assert(first.open&&!second.open);assert.equal(second.pending(),0,'Native closure removes interrupted close callback');
 second.click();second.finish();first.finish();assert(second.open&&!first.open,'Reopening cannot be closed by a stale transition');assert.equal(second.answer.style.height,'auto');assert.equal(second.dataset.mrnFaqAnimating??'false','false');
}
const ordinary=group(false,false);ordinary.second.click();ordinary.second.finish();assert(ordinary.first.open&&ordinary.second.open,'Existing unnamed groups remain independently open');
console.log('Passed: exclusive sibling closure, interrupted close/reopen, reduced motion and unchanged unnamed groups');
