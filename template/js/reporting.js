(function(scope){
  'use strict';
  class ReportFlow {
    constructor(request){this.request=request;this.packet=null;this.fields=null;this.locked=false;this.sending=false;this.result=null;}
    async prepare(jobId,newReport=false){this.packet=await this.request('report_prepare',jobId?{job_id:jobId}:(newReport?{new_report:'1'}:{}));return this.packet;}
    async send(fields){
      if(this.sending || this.result?.accepted)return this.result;
      if(!this.packet)throw new Error('Open the report form first.');
      if(!this.locked)this.fields={email:fields.email,summary:fields.summary,description:fields.description};
      this.sending=true;
      try{this.result=await this.request('report_send',{submission_id:this.packet.submission_id,...this.fields});}
      catch(_){this.result={accepted:false,ambiguous:true,message:'Delivery not confirmed. Retry the same report later.'};}
      finally{this.sending=false;}
      this.locked=Boolean(this.result?.ambiguous);return this.result;
    }
  }
  function mount(doc,storage){
    const config=doc.getElementById('mut-config'),dialog=doc.getElementById('mut-report-dialog'),root=doc.getElementById('my-una-translator');
    if(!config||!dialog||!root)return;
    const cfg=JSON.parse(config.textContent);if(!cfg.support_enabled)return;
    const form=doc.getElementById('mut-report-form'),message=doc.getElementById('mut-report-message'),send=doc.getElementById('mut-report-send'),close=doc.getElementById('mut-report-close'),access=doc.getElementById('mut-report-access');
    const newReport=doc.getElementById('mut-report-new');let startNew=false;
    const fields=['email','summary','description'],flows=new Map();let flow=null,opening=false;
    const key=id=>'my-una-report-v1:'+id;
    const cached=(id)=>{try{return JSON.parse(storage.getItem(key(id))||'null');}catch(_){return null;}};
    const persist=()=>{try{if(flow?.result?.accepted || !flow?.locked)storage.removeItem(key(flow.packet.submission_id));else storage.setItem(key(flow.packet.submission_id),JSON.stringify(flow.fields));}catch(_){}};
    async function request(action,data){
      const body=new FormData();body.set('mt_action',action);body.set('csrf_token',cfg.token);for(const [k,v]of Object.entries(data))body.set(k,String(v));
      const controller=new AbortController(),timer=setTimeout(()=>controller.abort(),18000);
      try{const response=await fetch(cfg.endpoint,{method:'POST',credentials:'same-origin',body,signal:controller.signal,headers:{'X-Requested-With':'XMLHttpRequest'}});const r=await response.json();if(!response.ok||r.code!==0)throw new Error(action==='report_prepare'?'The report form is unavailable. Refresh Studio or use the support page.':'Delivery not confirmed. Retry the same report later.');return r.data;}finally{clearTimeout(timer);}
    }
    function render(){
      const accepted=flow?.result?.accepted;
      form.hidden=Boolean(accepted);send.disabled=Boolean(flow?.sending||accepted);close.disabled=Boolean(flow?.sending);
      for(const name of fields)form.elements[name].readOnly=Boolean(flow?.locked||flow?.sending);
      access.hidden=!accepted;newReport.hidden=!(accepted && flow.packet.diagnostics.operation==='support');
      if(accepted){message.textContent='Saved as ticket #'+flow.result.ticket_id+' — pending support review.'+(flow.result.receipt==='delayed'?' Receipt email may be delayed.':'');access.href='https://myunaworld.com/modules/newton/my_una_tickets/request.php?page=access';}
      else if(flow?.sending)message.textContent='Sending your reviewed report…';
      else if(flow?.result)message.textContent=flow.result.message||'Delivery not confirmed. Retry the same report later.';
      send.textContent=flow?.locked?'Retry the same report':'Send report to My UNA World';
    }
    root.addEventListener('click',async event=>{
      const button=event.target.closest('[data-mut-report]');if(!button||opening||flow?.sending)return;opening=true;button.disabled=true;
      try{
        const candidate=new ReportFlow(request);const packet=await candidate.prepare(button.dataset.mutReport==='general'?null:doc.getElementById('mut-job-select')?.value,startNew);startNew=false;
        flow=flows.get(packet.submission_id)||candidate;flows.set(packet.submission_id,flow);
        const saved=cached(packet.submission_id);if(saved&&!flow.fields){flow.fields=saved;flow.locked=true;}
        if(packet.ticket_id)flow.result={accepted:true,ticket_id:packet.ticket_id,receipt:'delayed'};
        form.reset();for(const name of fields)form.elements[name].value=flow.fields?.[name]||(name==='summary'?'My UNA Translator: '+packet.diagnostics.error_code:'');
        doc.getElementById('mut-report-diagnostics').textContent=JSON.stringify(packet.diagnostics,null,2);
        message.textContent='Review the details before sending. Passwords, API keys, source text and member content are not collected.';
        render();if(!dialog.open)dialog.showModal();
      }catch(_){message.textContent='The report form is unavailable. Refresh Studio or use the support page.';form.hidden=true;access.hidden=true;if(!dialog.open)dialog.showModal();}
      finally{button.disabled=false;opening=false;}
    });
    form.addEventListener('submit',async event=>{
      event.preventDefault();if(!flow||flow.sending)return;
      const values=Object.fromEntries(fields.map(name=>[name,form.elements[name].value]));
      // Retain only the user-reviewed form in this browser tab if acknowledgment is lost.
      if(!flow.locked)flow.fields=values;
      try{storage.setItem(key(flow.packet.submission_id),JSON.stringify(flow.fields));}catch(_){}
      const pending=flow.send(values);render();await pending;persist();render();
    });
    newReport.addEventListener('click',()=>{if(flow?.sending)return;startNew=true;dialog.close();root.querySelector('[data-mut-report="general"]')?.click();});
    close.addEventListener('click',()=>{if(!flow?.sending)dialog.close();});
    dialog.addEventListener('cancel',event=>{if(flow?.sending)event.preventDefault();});
  }
  if(typeof module!=='undefined'&&module.exports)module.exports={ReportFlow,mount};
  else{scope.MyUnaSupportReporting={ReportFlow,mount};let storage;try{storage=scope.sessionStorage;}catch(_){}mount(document,storage);}
})(typeof window!=='undefined'?window:this);
