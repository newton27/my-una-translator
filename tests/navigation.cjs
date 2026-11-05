'use strict';
const {test}=require('node:test');const assert=require('node:assert/strict');const fs=require('node:fs');const {JSDOM}=require('jsdom');
async function fixture(t){
 const dom=new JSDOM(fs.readFileSync(process.env.MUT_FIXTURE,'utf8'),{url:'https://example.test/studio/',runScripts:'outside-only'});t.after(()=>dom.window.close());const w=dom.window,d=w.document;
 const job=id=>({id,targets:['fi'],status:'ready',total:1,counts:{staged:1,pending:0,needs_review:0},budget:10000,estimated:1,spent:1,error:''});
 const cfg=JSON.parse(d.getElementById('mut-config').textContent);cfg.snapshot.jobs=[job(7),job(8)];d.getElementById('mut-config').textContent=JSON.stringify(cfg);
 const calls=[];let hold7=false,release7;const wait7=new Promise(r=>release7=r);
 w.HTMLDialogElement.prototype.showModal=function(){this.open=true;};
 w.fetch=async(url,opts)=>{const a=opts.body.get('mt_action'),id=Number(opts.body.get('job_id'));calls.push({a,id});if(a==='status'&&id===7&&hold7)await wait7;const data=a==='snapshot'?cfg.snapshot:a==='preview'?{job:job(id),items:[],total:0}:a==='apply'?{job:{...job(id),status:'applied'},more:false}:{job:job(id)};return {ok:true,json:async()=>({code:0,data})};};
 w.eval(['progress','workflow','translator'].map(n=>fs.readFileSync(__dirname+'/../template/js/'+n+'.js','utf8')).join('\n'));
 const settle=async()=>{await new Promise(setImmediate);};const select=async id=>{d.getElementById('mut-job-select').value=String(id);d.getElementById('mut-job-select').dispatchEvent(new w.Event('change',{bubbles:true}));await settle();};
 return {w,d,calls,settle,select,hold:()=>hold7=true,release:async()=>{release7();await settle();},apply:async()=>{d.querySelector('[data-mut-action="apply"]').click();await settle();}};
}
test('a delayed older selection never changes the latest displayed job or Apply target',async t=>{
 const f=await fixture(t);f.hold();await f.select(7);assert.equal(f.d.querySelector('[data-mut-action="apply"]').disabled,true);
 await f.select(8);await f.release();assert.equal(f.d.getElementById('mut-job-select').value,'8');assert.match(f.d.getElementById('mut-status-job').textContent,/#8/);
 await f.apply();assert.deepEqual(f.calls.filter(c=>c.a==='apply').map(c=>c.id),[8]);
});
test('a delayed refresh cannot replace a newer selected job',async t=>{
 const f=await fixture(t);await f.select(7);f.hold();f.d.querySelector('[data-mut-action="refresh"]').click();await f.settle();
 await f.select(8);await f.release();assert.equal(f.d.getElementById('mut-job-select').value,'8');assert.match(f.d.getElementById('mut-status-job').textContent,/#8/);
 await f.apply();assert.deepEqual(f.calls.filter(c=>c.a==='apply').map(c=>c.id),[8]);
});
