'use strict';
const {test}=require('node:test');const assert=require('node:assert/strict');const fs=require('node:fs');const {JSDOM}=require('jsdom');
async function fixture(t){
 const dom=new JSDOM(fs.readFileSync(process.env.MUT_FIXTURE,'utf8'),{url:'https://example.test/studio/',runScripts:'outside-only'});t.after(()=>dom.window.close());const w=dom.window,d=w.document;
 const job=id=>({id,targets:['fi'],status:'ready',total:1,counts:{staged:1,pending:0,needs_review:0},budget:10000,estimated:1,spent:1,error:''});
 const cfg=JSON.parse(d.getElementById('mut-config').textContent);cfg.snapshot.jobs=[job(7),job(8)];d.getElementById('mut-config').textContent=JSON.stringify(cfg);
 const calls=[],holds=new Map(),failures=new Set();
 const item=id=>({id:id*10,key_name:'_job_'+id,language:'fi',source:'Source for job '+id,result:'Translation for job '+id,original_result:'Translation for job '+id,state:'staged',error:'',manually_edited:false});
 w.HTMLDialogElement.prototype.showModal=function(){this.open=true;};
 w.fetch=async(url,opts)=>{const a=opts.body.get('mt_action'),id=Number(opts.body.get('job_id')),key=a+':'+id;calls.push({a,id});if(holds.has(key))await holds.get(key).promise;if(failures.has(key))throw Error('Offline selecting job '+id);const data=a==='snapshot'?cfg.snapshot:a==='preview'?{job:job(id),items:[item(id)],total:1}:a==='apply'?{job:{...job(id),status:'applied'},more:false}:{job:job(id)};return {ok:true,json:async()=>({code:0,data})};};
 w.eval(['progress','workflow','translator'].map(n=>fs.readFileSync(__dirname+'/../template/js/'+n+'.js','utf8')).join('\n'));
 const settle=async()=>{await new Promise(setImmediate);};const select=async id=>{d.getElementById('mut-job-select').value=String(id);d.getElementById('mut-job-select').dispatchEvent(new w.Event('change',{bubbles:true}));await settle();};
 const lookup=async id=>{d.getElementById('mut-job-lookup').elements.job_id.value=String(id);d.getElementById('mut-job-lookup').dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));await settle();};
 return {w,d,calls,settle,select,lookup,fail:(action,id)=>failures.add(action+':'+id),hold:(action='status',id=7)=>{let release;const promise=new Promise(r=>release=r);holds.set(action+':'+id,{promise,release});},release:async(action='status',id=7)=>{holds.get(action+':'+id).release();await settle();},apply:async()=>{d.querySelector('[data-mut-action="apply"]').click();await settle();}};
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
for(const open of ['select','lookup'])for(const action of ['status','preview'])test('a failed job '+open+' '+action+' clears the previous preview and disables Apply',async t=>{
 const f=await fixture(t);await f.select(7);assert.match(f.d.getElementById('mut-preview').textContent,/_job_7/);assert.equal(f.d.querySelector('[data-mut-action="apply"]').disabled,false);
 f.fail(action,8);await f[open](8);
 if(open==='select')assert.equal(f.d.getElementById('mut-job-select').value,'8');assert.equal(f.d.getElementById('mut-preview').textContent,'');assert.equal(f.d.getElementById('mut-job-details').textContent,'');
 assert.doesNotMatch(f.d.getElementById('mut-status-job').textContent,/#7|#8/);assert.equal(f.d.querySelector('[data-mut-action="apply"]').disabled,true);
 await f.apply();assert.deepEqual(f.calls.filter(c=>c.a==='apply'),[]);
});
test('selecting a different job immediately clears the old preview until matching status and preview finish',async t=>{
 const f=await fixture(t);await f.select(7);f.hold('preview',8);await f.select(8);
 assert.equal(f.d.getElementById('mut-preview').textContent,'');assert.equal(f.d.querySelector('[data-mut-action="apply"]').disabled,true);
 await f.release('preview',8);assert.match(f.d.getElementById('mut-preview').textContent,/_job_8/);assert.equal(f.d.querySelector('[data-mut-action="apply"]').disabled,false);
 await f.apply();assert.deepEqual(f.calls.filter(c=>c.a==='apply').map(c=>c.id),[8]);
});
test('an older delayed preview cannot replace the latest selected preview or Apply target',async t=>{
 const f=await fixture(t);f.hold('preview',7);await f.select(7);await f.select(8);await f.release('preview',7);
 assert.equal(f.d.getElementById('mut-job-select').value,'8');assert.match(f.d.getElementById('mut-preview').textContent,/_job_8/);assert.doesNotMatch(f.d.getElementById('mut-preview').textContent,/_job_7/);
 await f.apply();assert.deepEqual(f.calls.filter(c=>c.a==='apply').map(c=>c.id),[8]);
});
