'use strict';
const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const {JSDOM}=require('jsdom');
async function fixture(t) {
 const html=fs.readFileSync(process.env.MUT_FIXTURE||'/tmp/translator-fixture.html','utf8');
 const dom=new JSDOM(html,{url:'https://example.test/studio/module.php?name=my_una_translator',runScripts:'outside-only'});
 t.after(()=>dom.window.close());
 const w=dom.window,doc=w.document;
 const job={id:7,targets:['fi'],status:'ready',total:141,counts:{staged:141,applied:0,conflict:0},budget:10000,estimated:100,spent:100,error:''};
 const item={id:13,key_name:'_mz_onlinetv',language:'fi',source:'Online TV',result:'Online TV',state:'staged',error:'',manually_edited:false,result_hash:'initial-hash'};
 const saves=[];let rejectSave=false, pendingRead=null;
 w.HTMLDialogElement.prototype.showModal=function(){this.open=true;};
 w.fetch=async(url,options)=>{
  const action=options.body.get('mt_action');
  if (action==='preview' && pendingRead) { const wait=pendingRead; pendingRead=null; await wait; }
  if(action==='edit_item') {
   saves.push(Object.fromEntries(options.body));
   if(rejectSave)return {ok:true,json:async()=>({code:1,message:'Translation omitted a placeholder.'})};
   item.result=options.body.get('result');item.manually_edited=true;item.result_hash='saved-hash';
   return {ok:true,json:async()=>({code:0,data:{job,item}})};
  }
  return {ok:true,json:async()=>({code:0,data:action==='snapshot'?JSON.parse(doc.getElementById('mut-config').textContent).snapshot:action==='status'?{job}:{job,items:[item]}})};
 };
 w.eval(['progress','workflow','translator'].map(f=>fs.readFileSync(path.join(__dirname,'../template/js/'+f+'.js'),'utf8')).join('\n')); 
 const settle=async()=>{await new Promise(setImmediate);};
 const action=name=>doc.querySelector('[data-mut-action="'+name+'"]');
 const click=async name=>{assert.ok(action(name),'missing '+name+' control');action(name).click();await settle();};
 doc.getElementById('mut-job-lookup').elements.job_id.value='7';
 doc.getElementById('mut-job-lookup').dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));await settle();
 return {w,doc,job,item,saves,action,click,settle,reject:()=>rejectSave=true, delayPreview:()=>{let resolve; pendingRead=new Promise(r=>resolve=r);return resolve;}};
}
test('Edit Save persists draft with stale hash and manual label while Apply stays separate',async t=>{
 const f=await fixture(t);await f.click('edit_item');
 const editor=f.doc.querySelector('#mut-preview textarea');assert.ok(editor);editor.value='Verkko-TV';
 assert.equal(f.action('apply').disabled,true);assert.equal(f.doc.getElementById('mut-next').disabled,true);
 await f.click('save_edit');
 assert.equal(f.saves.length,1);assert.equal(f.saves[0].expected_hash,'initial-hash');assert.equal(f.saves[0].result,'Verkko-TV');
 assert.match(f.doc.getElementById('mut-preview').textContent,/Manually edited/);
 assert.equal(f.action('apply').disabled,false);
});
test('Cancel restores preview and sends no save',async t=>{
 const f=await fixture(t);await f.click('edit_item');f.doc.querySelector('#mut-preview textarea').value='Draft';
 await f.click('cancel_edit');assert.equal(f.saves.length,0);assert.equal(f.doc.querySelector('#mut-preview textarea'),null);
 assert.match(f.doc.getElementById('mut-preview').textContent,/Online TV/);
});
test('Validation failure retains draft and shows inline error',async t=>{
 const f=await fixture(t);f.reject();await f.click('edit_item');
 f.doc.querySelector('#mut-preview textarea').value='Keep this draft';await f.click('save_edit');
 assert.equal(f.doc.querySelector('#mut-preview textarea').value,'Keep this draft');
 assert.match(f.doc.querySelector('#mut-preview [role="alert"]').textContent,/omitted a placeholder/);
 assert.equal(f.action('apply').disabled,true);
 assert.equal(f.action('save_edit').disabled,false);
});
test('Applied history has manual label but no edit control and escapes markup',async t=>{
 const f=await fixture(t);f.job.status='applied';f.item.state='applied';f.item.manually_edited=true;f.item.result='<img src=x onerror="alert(1)">';
 await f.click('refresh');
 assert.match(f.doc.getElementById('mut-preview').textContent,/Manually edited/);
 assert.equal(f.action('edit_item'),null);assert.equal(f.doc.querySelector('#mut-preview img'),null);
});

test('A delayed preview refresh cannot erase a newly opened editor',async t=>{
 const f=await fixture(t);const release=f.delayPreview();
 f.action('refresh').click();await f.settle();
 f.action('edit_item').click();
 const editor=f.doc.querySelector('#mut-preview textarea');
 if(editor)editor.value='Draft must survive';
 release();await f.settle();
 if(editor) {
   assert.equal(f.doc.querySelector('#mut-preview textarea')?.value,'Draft must survive');
   assert.ok(f.action('cancel_edit'));
 } else {
   assert.ok(f.action('edit_item'));assert.equal(f.action('edit_item').disabled,false);
   await f.click('edit_item');assert.ok(f.doc.querySelector('#mut-preview textarea'));
 }
});

