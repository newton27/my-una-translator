'use strict';
const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const vm=require('node:vm');
const {JSDOM}=require('jsdom');
const scripts=['progress','workflow','translator'].map(n=>fs.readFileSync(path.join(__dirname,'../template/js/'+n+'.js'),'utf8'));
test('review flags block Apply even on a ready job',()=>{
 const c={};vm.runInNewContext(scripts[1],c);
 const w=c.MyUnaTranslatorWorkflow({status:'ready',total:3,counts:{staged:2,needs_review:1}});
 assert.equal(w.apply,false);assert.equal(w.hasPreview,true);assert.equal(w.step,2);
});
test('generation progress counts reviewed failures as processed work',()=>{
 const c={};vm.runInNewContext(scripts[0],c);
 const p=c.MyUnaTranslatorProgress({status:'needs_review',total:3,counts:{staged:2,needs_review:1}});
 assert.equal(p.done,3);assert.equal(p.percent,100);assert.equal(p.needsReview,1);
});
async function fixture(t){
 const dom=new JSDOM(fs.readFileSync(process.env.MUT_FIXTURE,'utf8'),{url:'https://example.test/studio/module.php?name=my_una_translator',runScripts:'outside-only'});
 t.after(()=>dom.window.close());const w=dom.window,d=w.document;
 const job={id:7,targets:['sq'],status:'needs_review',total:3,counts:{staged:2,needs_review:1,applied:0,conflict:0},budget:10000,estimated:200,spent:31,error:'Correct the strings marked Needs review before applying.'};
 const item={id:13,key_name:'_queue_2',language:'sq',source:'Cannot create set',result:null,original_result:null,state:'needs_review',error:'Translation reordered protected markup or format arguments.'};
 const snapshot=JSON.parse(d.getElementById('mut-config').textContent).snapshot;snapshot.jobs=[job];
 const saves=[];let reject=false,pendingRead=null,failReads=false;
 w.HTMLDialogElement.prototype.showModal=function(){this.open=true;};
 w.fetch=async(url,options)=>{
  const a=options.body.get('mt_action');if(a==='preview'&&failReads)throw new Error('offline');if(a==='preview'&&pendingRead){const p=pendingRead;pendingRead=null;await p;}
  if(a==='save_preview'){
   saves.push(Object.fromEntries(options.body));if(reject)return {ok:true,json:async()=>({code:1,message:'Protected placeholders must match.'})};
   item.result=options.body.get('result');item.state='staged';item.error='';job.status='ready';job.counts={staged:3,needs_review:0,applied:0};job.error='';
  }
  const data=a==='snapshot'?snapshot:a==='preview'?{job,items:[item],total:1}:{job};
  return {ok:true,json:async()=>({code:0,data:JSON.parse(JSON.stringify(data))})};
 };
 w.eval(scripts.join('\n'));const settle=async()=>{await new Promise(setImmediate);};
 const action=n=>d.querySelector('[data-mut-action="'+n+'"]');
 const click=async n=>{assert.ok(action(n),'missing '+n+' control');action(n).click();await settle();};
 d.getElementById('mut-job-lookup').elements.job_id.value='7';d.getElementById('mut-job-lookup').dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));await settle();
 return {w,d,job,item,saves,action,click,settle,reject:()=>reject=true,failReads:()=>failReads=true,delay:()=>{let resolve;pendingRead=new Promise(r=>resolve=r);return resolve;}};
}
test('flagged row is visible and editable while Apply is blocked',async t=>{
 const f=await fixture(t);assert.match(f.d.getElementById('mut-job-details').textContent,/1 Needs review/);
 assert.ok(f.d.querySelector('#mut-preview-filter option[value="needs_review"]'));assert.ok(f.action('edit_preview'));
 assert.equal(f.action('apply').disabled,true);assert.match(f.d.getElementById('mut-preview').textContent,/reordered protected/);
});
test('correction sends its exact baseline and enables Apply only after save',async t=>{
 const f=await fixture(t);await f.click('edit_preview');const editor=f.d.querySelector('#mut-preview textarea');assert.ok(editor);assert.equal(editor.value,'');
 editor.value='Nuk mund të krijohet grupi';assert.equal(f.action('apply').disabled,true);assert.equal(f.d.getElementById('mut-job-select').disabled,true);
 await f.click('save_preview');assert.equal(f.saves.length,1);assert.equal(f.saves[0].expected_state,'needs_review');assert.equal(f.saves[0].expected_null,'1');
 assert.equal(f.action('apply').disabled,false);assert.equal(f.job.counts.applied,0);assert.match(f.d.getElementById('mut-preview').textContent,/Nuk mund/);
});
test('rejected correction retains the draft and keeps Apply blocked',async t=>{
 const f=await fixture(t);f.reject();await f.click('edit_preview');f.d.querySelector('#mut-preview textarea').value='Keep this draft';await f.click('save_preview');
 assert.equal(f.d.querySelector('#mut-preview textarea').value,'Keep this draft');assert.equal(f.action('save_preview').disabled,false);assert.equal(f.action('apply').disabled,true);
});
test('delayed refresh cannot erase an open correction',async t=>{
 const f=await fixture(t);const release=f.delay();f.action('refresh').click();await f.settle();await f.click('edit_preview');
 f.d.querySelector('#mut-preview textarea').value='Draft must survive';release();await f.settle();
 assert.equal(f.d.querySelector('#mut-preview textarea')?.value,'Draft must survive');
});
test('Cancel recovers locally when preview reads are offline',async t=>{
 const f=await fixture(t);await f.click('edit_preview');f.d.querySelector('#mut-preview textarea').value='Draft to cancel';f.failReads();await f.click('cancel_preview');
 assert.equal(f.d.querySelector('#mut-preview textarea'),null);assert.equal(f.d.getElementById('mut-job-select').disabled,false);assert.equal(f.action('refresh').disabled,false);assert.equal(f.saves.length,0);assert.ok(f.action('edit_preview'));
});
