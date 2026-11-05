'use strict';
const {test}=require('node:test');
const assert=require('node:assert/strict');
const fs=require('node:fs');
const path=require('node:path');
const {JSDOM}=require('jsdom');
async function fixture(t,delay=2) {
  const dom=new JSDOM(fs.readFileSync(process.env.MUT_FIXTURE,'utf8'),{url:'https://example.invalid/studio/',runScripts:'outside-only'});
  t.after(()=>dom.window.close());
  const w=dom.window,doc=w.document,timers=[],calls=[];
  const job={id:7,targets:['ar'],status:'planned',total:1,counts:{pending:1,processing:0,staged:0,applied:0,conflict:0},budget:10000,estimated:10,spent:0,error:''};
  const snapshot=JSON.parse(doc.getElementById('mut-config').textContent).snapshot;
  snapshot.jobs=[job];snapshot.settings.provider='libre';
  w.HTMLDialogElement.prototype.showModal=function(){this.open=true;};
  w.setTimeout=(fn,ms)=>{timers.push({fn,ms});return timers.length;};
  w.fetch=async(url,options)=>{
    const action=options.body.get('mt_action');calls.push(action);let data;
    if(action==='batch') {
      const count=calls.filter(x=>x==='batch').length;
      job.status=count===1?'running':'ready';job.counts.pending=count===1?1:0;job.counts.staged=count===1?0:1;
      data={job,more:count===1,...(count===1?{retry_after:delay}:{})};
    } else if(action==='pause') {job.status='paused';data={job,more:false};}
    else if(action==='snapshot') data=snapshot;
    else if(action==='preview') data={job,items:[]};
    else data={job};
    return {ok:true,json:async()=>({code:0,data:JSON.parse(JSON.stringify(data))})};
  };
  w.eval(['progress','workflow','translator'].map(f=>fs.readFileSync(path.join(__dirname,'../template/js/'+f+'.js'),'utf8')).join('\n'));
  const settle=async()=>{await new Promise(setImmediate);};
  const click=async action=>{const button=doc.querySelector('[data-mut-action="'+action+'"]');assert.ok(button,'missing '+action);button.click();await settle();};
  doc.getElementById('mut-job-lookup').elements.job_id.value='7';
  doc.getElementById('mut-job-lookup').dispatchEvent(new w.Event('submit',{bubbles:true,cancelable:true}));await settle();
  const tick=async()=>{const timer=timers.shift();assert.ok(timer,'missing countdown delay');assert.equal(timer.ms,1000);timer.fn();await settle();};
  return {doc,calls,timers,job,click,tick};
}
test('Retry-After countdown waits before another generation request',async t=>{
  const f=await fixture(t);await f.click('batch');
  assert.equal(f.calls.filter(x=>x==='batch').length,1);assert.equal(f.timers.length,1);
  assert.match(f.doc.getElementById('mut-status-activity').textContent,/2/);
  await f.tick();assert.equal(f.calls.filter(x=>x==='batch').length,1);
  await f.tick();assert.equal(f.calls.filter(x=>x==='batch').length,2);assert.equal(f.job.status,'ready');
});
test('Pause during Retry-After wait stops generation before another batch request',async t=>{
  const f=await fixture(t);await f.click('batch');await f.click('pause');await f.tick();
  assert.equal(f.calls.filter(x=>x==='batch').length,1);assert.equal(f.calls.filter(x=>x==='pause').length,1);assert.equal(f.job.status,'paused');
});
test('Oversized retry delay is capped and still cancellable',async t=>{
  const f=await fixture(t,9999);await f.click('batch');
  assert.match(f.doc.getElementById('mut-status-activity').textContent,/300/);
  await f.click('pause');await f.tick();assert.equal(f.calls.filter(x=>x==='batch').length,1);assert.equal(f.job.status,'paused');
});
