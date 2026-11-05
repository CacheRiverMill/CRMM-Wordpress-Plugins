const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const source = fs.readFileSync(require('node:path').join(__dirname, '../crmm-trim-catalog/assets/js/admin-readiness.js'), 'utf8');
function harness() {
 const document = {}, handlers = {}, requests = [], timers = new Map(); let subscription, counter = 0;
 const state = { saving: false, metaSaving: false, success: true, autosaving: false, message: '', html: 'Saved blockers' };
 const config = { savedMessage: 'Saved details checked', changedMessage: 'Save draft first', errorMessage: 'Refresh failed' };
 function $(selector) {
  if (typeof selector === 'function') { selector(); return; }
  if (selector === document) return { on(event, selector, fn) { handlers[selector.includes('acf-field') ? 'edit' : 'refresh'] = fn; } };
  return { text(value) { state.message = value; }, html(value) { state.html = value; } };
 }
 $.post = () => { const r = { done(fn) { this.doneFn=fn; return this; }, fail(fn) { this.failFn=fn; return this; }, resolve(html) { this.doneFn({success:true,data:{html}}); } }; requests.push(r); return r; };
 const editor = { isSavingPost:()=>state.saving,isAutosavingPost:()=>state.autosaving,didPostSaveRequestSucceed:()=>state.success };
 const wp = { data:{subscribe(fn){subscription=fn;}, select(name){return name==='core/editor'?editor:{isSavingMetaBoxes:()=>state.metaSaving};}}};
 vm.runInNewContext(source,{jQuery:$,document,window:{wp,CRMMTrimReadiness:config},wp,setTimeout(fn){timers.set(++counter,fn);return counter;},clearTimeout(id){timers.delete(id);}});
 return {state,requests,edit:()=>handlers.edit(),refresh:()=>handlers.refresh(),tick(){const todo=[...timers.values()];timers.clear();todo.forEach(fn=>fn());},save(values){Object.assign(state,values);subscription();}};
}
test('unsaved edits and manual saved-check refresh cannot masquerade as saved changes',()=>{
 const e=harness();e.edit();assert.equal(e.state.message,'Save draft first');
 e.refresh();e.requests[0].resolve('Still blocked');
 assert.equal(e.state.message,'Save draft first');assert.equal(e.state.html,'Still blocked');
});
test('successful save refreshes checks after metadata saving finishes',()=>{
 const e=harness();e.edit();e.save({saving:true});e.save({saving:false,metaSaving:true});e.tick();
 assert.equal(e.requests.length,0);
 e.save({metaSaving:false});e.tick();e.requests[0].resolve('Ready to publish');
 assert.equal(e.state.message,'Saved details checked');assert.equal(e.state.html,'Ready to publish');
});
test('edits made during saving stay marked unsaved',()=>{
 const e=harness();e.edit();e.save({saving:true});e.edit();e.save({saving:false});e.tick();e.requests[0].resolve('Saved requirements');
 assert.equal(e.state.message,'Save draft first');
});
test('failed saves and autosaves do not claim user corrections were saved',()=>{
 const e=harness();e.edit();e.save({saving:true});e.save({saving:false,success:false});e.tick();
 assert.equal(e.requests.length,0);assert.equal(e.state.message,'Save draft first');
 e.save({saving:true,autosaving:true,success:true});e.save({saving:false,autosaving:false});e.tick();assert.equal(e.requests.length,0);
});
test('older responses and failed refreshes cannot replace the latest result',()=>{
 const e=harness();e.refresh();e.refresh();e.requests[1].resolve('Current requirements');e.requests[0].resolve('Old requirements');e.requests[0].failFn();
 assert.equal(e.state.html,'Current requirements');assert.equal(e.state.message,'Saved details checked');
 e.refresh();e.requests[2].failFn();assert.equal(e.state.html,'Current requirements');assert.equal(e.state.message,'Refresh failed');
});
