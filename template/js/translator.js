(() => {
  'use strict';
  const root = document.getElementById('my-una-translator');
  const configNode = document.getElementById('mut-config');
  if (!root || !configNode || root.dataset.initialized) return;
  root.dataset.initialized = '1';
  const cfg = JSON.parse(configNode.textContent);
  const find = id => document.getElementById(id);
  const label = key => cfg.labels[key] || key;
  const esc = value => String(value ?? '').replace(/[&<>"']/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;',"'":'&#39;'}[c]));
  const number = value => Number(value || 0).toLocaleString();
  let activeJob = null, busy = false, stopRequested = false, offset = 0, operation = '';
  let previewTotal = 0, previewSearch = '', previewFilter = 'all', previewSequence = 0;
  let previewRows = new Map(), draft = null;
  let viewSequence = 0, selectionLoading = false;
  const invalidateReads = () => { ++viewSequence; ++previewSequence; };
  let notice = '', noticeError = false, requestStarted = 0, activity = '';
  let snapshot = cfg.snapshot;
  const dialog = find('mut-status-dialog');
  // Search lives above the existing preview grid. The server searches the entire job.
  const previewControls = document.createElement('div');
  previewControls.className = 'mut-fields mut-preview-filters';
  previewControls.innerHTML = '<label>Search translation keys or text<input id="mut-preview-search" type="search" maxlength="200" placeholder="Key, English or translated text" autocomplete="off"></label>' +
    '<label>Status<select id="mut-preview-filter"><option value="all">All</option><option value="staged">Preview Ready</option><option value="needs_review">Needs review</option><option value="conflict">Conflicts / Needs Review</option><option value="applied">Applied</option><option value="pending">Pending</option></select></label>' +
    '<button type="button" id="mut-review-flagged" class="mut-secondary">Review flagged strings</button>' +
    '<button type="button" id="mut-show-conflicts" class="mut-secondary">Show Conflicts</button>';
  find('mut-preview').parentNode.insertBefore(previewControls,find('mut-preview'));
  function showStatus() {
    renderStatus();
    if (!dialog.open) dialog.showModal();
  }
  function renderStatus() {
    const p = activeJob ? MyUnaTranslatorProgress(activeJob, operation) : null;
    find('mut-status-job').textContent = activeJob ? label('job') + ' #' + Number(activeJob.id) + ' · ' + activeJob.targets.map(code => snapshot.languages.find(l => l.code === code)?.native || code).join(', ') : label('no_job_selected');
    find('mut-status-state').textContent = busy && operation ? label(operation === 'batch' ? 'generating' : operation === 'apply' ? 'applying' : 'rolling_back') : activeJob ? stateLabel(activeJob.status) : label('no_job_selected');
    const bar = find('mut-status-progress');
    bar.hidden = !p;
    if (p) bar.value = p.percent;
    find('mut-status-count').textContent = p ? label(p.phase === 'generation' ? 'preview_progress' : p.phase === 'apply' ? 'apply_progress' : 'rollback_progress') + ': ' + number(p.done) + ' / ' + number(p.total) + ' · ' + p.percent + '%' : '';
    find('mut-status-totals').textContent = p ? number(p.staged) + ' ' + label('staged') + ' · ' + number(p.needsReview) + ' ' + label('needs_review') + ' · ' + number(p.applied) + ' ' + label('applied') + ' · ' + number(p.conflicts) + ' ' + label('conflicts') : '';
    find('mut-status-characters').textContent = activeJob ? label('used_reserved') + ': ' + number(activeJob.spent) : '';
    const elapsed = requestStarted ? Math.floor((Date.now() - requestStarted) / 1000) : 0;
    find('mut-status-activity').textContent = requestStarted ? label(activity) + ' · ' + elapsed + ' ' + label('seconds') + (elapsed >= 60 ? ' · ' + label('slow_request') : '') : '';
    find('mut-status-activity').hidden = !requestStarted;
    const savedError = activeJob?.error || '';
    const problem = [savedError, noticeError && notice !== savedError ? notice : ''].filter(Boolean).join('\n\n');
    const errorBox = find('mut-status-error');
    errorBox.textContent = problem;
    errorBox.hidden = !problem;
    find('mut-report-error').hidden = !problem || !cfg.support_enabled;
    find('mut-status-note').textContent = !noticeError ? notice : '';
    find('mut-status-pause').hidden = !(busy && operation === 'batch');
    find('mut-status-pause').disabled = stopRequested;
    find('mut-show-status').textContent = busy && operation ? label('show_progress') : label('show_status');
  }
  const message = (text, error = false, popup = error || Boolean(busy && operation)) => {
    const box = find('mut-message'); box.hidden = false; box.textContent = text;
    box.classList.toggle('mut-error', error);
    notice = text; noticeError = error;
    if (popup) showStatus(); else renderStatus();
  };
  async function request(action, data = {}) {
    const body = data instanceof FormData ? data : new FormData();
    if (!(data instanceof FormData)) Object.entries(data).forEach(([key, value]) => {
      if (Array.isArray(value)) value.forEach(v => body.append(key + '[]', String(v)));
      else body.append(key, String(value));
    });
    body.set('mt_action', action); body.set('csrf_token', cfg.token);
    const tracksActivity = busy && operation;
    if (tracksActivity) { requestStarted = Date.now(); activity = action === 'batch' ? (snapshot.settings.provider === 'libre' ? 'waiting_libre' : 'waiting_deepl') : action === 'apply' ? 'waiting_apply' : action === 'rollback' ? 'waiting_rollback' : 'waiting_server'; renderStatus(); }
    try {
      const response = await fetch(cfg.endpoint, {method:'POST', body, credentials:'same-origin', headers:{'X-Requested-With':'XMLHttpRequest'}});
      let result;
      try { result = await response.json(); } catch (_) { throw new Error(label('session_error')); }
      if (!response.ok || Number(result.code) !== 0) throw new Error(result.message || label('connection_error'));
      return result.data;
    } finally { if (tracksActivity) { requestStarted = 0; renderStatus(); } }
  }
  const stateLabel = state => label(state);
  function setButtons() {
    root.querySelectorAll('button, input, select').forEach(control => { if (!control.classList.contains('mut-status-control')) control.disabled = busy; });
    if (selectionLoading && !draft) find('mut-job-select').disabled=false;
    const action = name => root.querySelector('[data-mut-action="' + name + '"]');
    const workflow=MyUnaTranslatorWorkflow(activeJob);
    const generating=busy && operation==='batch';
    action('batch').hidden=!workflow.generate && !generating;
    action('resume').hidden=!workflow.resume || generating;
    root.querySelectorAll('[data-mut-action="pause"]').forEach(control => { control.disabled = !(busy && operation === 'batch') || stopRequested; });
    root.querySelector('#mut-step-2 [data-mut-action="pause"]').hidden=!generating;
    find('mut-preview-pages').hidden=!workflow.hasPreview;
    find('mut-apply-hint').textContent=label(activeJob?.status==='applied'?'apply_done':workflow.apply?'next_review':'apply_wait');
    find('mut-next-step').textContent=label(workflow.hint);
    root.querySelectorAll('[data-mut-step-nav]').forEach(link=>{
      const step=Number(link.dataset.mutStepNav);
      link.classList.toggle('mut-current',step===workflow.step);
      link.classList.toggle('mut-complete',step<workflow.step);
      if(step===workflow.step)link.setAttribute('aria-current','step');else link.removeAttribute('aria-current');
    });
    const selected=Array.from(find('mut-targets').querySelectorAll('input:checked')).map(input=>snapshot.languages.find(l=>l.code===input.value)?.name || input.value);
    find('mut-selection-note').textContent=selected.length?label('selected_languages')+': '+selected.join(', '):'';
    find('mut-create').querySelector('button[type="submit"]').disabled=busy || selected.length<1 || selected.length>5 || Boolean(snapshot.language_error);
    find('mut-targets').querySelectorAll('input:not(:checked)').forEach(input=>{input.disabled=busy || selected.length>=5;});
    if (busy) return;
    const state = activeJob?.status || '';
    const ready = snapshot.settings.provider_ready ?? snapshot.settings.has_key;
    action('batch').disabled = !ready || !workflow.generate;
    action('resume').disabled = !ready || !workflow.resume;
    root.querySelectorAll('[data-mut-action="pause"]').forEach(control => { control.disabled = !activeJob || !['planned','running'].includes(state); });
    action('apply').disabled = !workflow.apply;
    action('rollback').disabled = !workflow.rollback;
    find('mut-enable-languages').disabled=!workflow.apply;
    find('mut-prev').disabled = !activeJob || offset === 0;
    find('mut-next').disabled = !activeJob || offset + 50 >= previewTotal;
    find('mut-review-flagged').disabled = busy || Boolean(draft) || !Number(activeJob?.counts?.needs_review || 0);
    if (draft) {
      ['mut-job-select','mut-prev','mut-next','mut-preview-search','mut-preview-filter','mut-show-conflicts'].forEach(id=>{find(id).disabled=true;});
      root.querySelectorAll('form input, form select, form button, [data-mut-action="apply"], [data-mut-action="batch"], [data-mut-action="resume"], [data-mut-action="refresh"], [data-mut-action="rollback"], [data-mut-action="delete_job"]').forEach(control=>{control.disabled=true;});
    }
    renderStatus();
  }
  function renderSnapshot(selected = null) {
    const targets = find('mut-targets');
    const chosen = new Set(Array.from(targets.querySelectorAll('input:checked')).map(input => input.value));
    targets.innerHTML = snapshot.languages.map(l => '<label><input type="checkbox" name="targets[]" value="' + esc(l.code) + '"' + (chosen.has(l.code) ? ' checked' : '') + '><span><bdi>' + esc(l.native) + '</bdi><small>' + esc(l.name) + '</small></span></label>').join('');
    filterLanguages();
    const languageError = find('mut-language-error');
    languageError.textContent = snapshot.language_error || '';
    languageError.hidden = !snapshot.language_error;
    find('mut-coverage').innerHTML = snapshot.languages.map(l => '<tr><th>' + esc(l.native) + '<small>' + esc(l.name) + '</small></th><td>' + number(l.translated) + '</td><td>' + number(l.missing) + '</td><td>' + number(l.english_copies) + '</td><td><span class="mut-pill">' + esc(label(l.enabled ? 'active' : l.installed ? 'disabled' : 'not_created')) + '</span></td></tr>').join('');
    find('mut-site-languages').innerHTML=(snapshot.site_languages || []).map(l=>{
      const contents='<bdi>'+esc(l.native)+'</bdi><small>'+esc(label(l.enabled?'available_visitors':'unavailable_visitors'))+(l.code===cfg.default_language?' · '+esc(label('site_default')):'')+'</small>';
      return l.enabled?'<a class="mut-site-language" href="'+esc(cfg.site_url+'?lang='+encodeURIComponent(l.code))+'">'+contents+'</a>':'<div class="mut-site-language mut-unavailable">'+contents+'</div>';
    }).join('');
    const select = find('mut-job-select');
    const wanted = selected ?? select.value;
    const jobs = snapshot.jobs.slice();
    if (activeJob && !jobs.some(j => String(j.id) === String(activeJob.id)) && String(wanted) === String(activeJob.id)) jobs.push(activeJob);
    select.innerHTML = '<option value="">' + esc(label('select_job')) + '</option>' + jobs.map(j => '<option value="' + Number(j.id) + '">#' + Number(j.id) + ' · ' + esc(stateLabel(j.status)) + ' · ' + number(j.total) + ' ' + esc(label('total_keys')) + '</option>').join('');
    if (jobs.some(j => String(j.id) === String(wanted))) select.value = String(wanted);
    setButtons();
  }
  function clearJobView() {
    activeJob=null; previewRows=new Map(); previewTotal=0;
    find('mut-preview').innerHTML=''; find('mut-job-details').textContent='';
    find('mut-page-info').textContent=''; find('mut-progress').value=0;
  }
  function renderJob(job) {
    activeJob = job;
    const counts = job.counts;
    const progress = MyUnaTranslatorProgress(job, operation);
    find('mut-progress').value = progress.percent;
    find('mut-resume-budget').value = job.budget;
    const estimate = number(job.estimated) + ' / ' + number(job.budget) + ' ' + label('characters');
    const details = [stateLabel(job.status), number(counts.staged) + ' ' + label('staged'), number(counts.needs_review) + ' ' + label('needs_review'), number(counts.applied) + ' ' + label('applied'), number(Number(counts.conflict || 0) + Number(counts.rollback_conflict || 0)) + ' ' + label('conflicts')];
    const names=job.targets.map(code=>snapshot.languages.find(l=>l.code===code)?.name || snapshot.site_languages?.find(l=>l.code===code)?.native || code);
    find('mut-job-details').innerHTML = '<p class="mut-job-targets">'+esc(label('job_languages'))+': <strong>'+esc(names.join(', '))+'</strong></p><div class="mut-stat-row">' + details.map(d => '<span class="mut-pill">' + esc(d) + '</span>').join('') + '</div><p>' + esc(estimate) + '</p><p class="mut-note">' + esc(label('used_reserved')) + ': ' + number(job.spent) + '</p>' + (Number(job.estimated) > Number(job.budget) ? '<p class="mut-warning">' + esc(label('ceiling_warning')) + '</p>' : '') + (job.error ? '<p class="mut-warning">' + esc(job.error) + '</p>' : '');
    if (['paused','needs_review','applied','rolled_back','failed','empty_preview'].includes(String(job.status))) {
        const removeButton = document.createElement('button');
        removeButton.type = 'button';
        removeButton.className = 'mut-secondary';
        removeButton.dataset.mutAction = 'delete_job';
        removeButton.textContent = 'Remove job';
        removeButton.setAttribute('aria-label', 'Remove job #' + Number(job.id));
        const removeWrap = document.createElement('p');
        removeWrap.className = 'mut-note';
        removeWrap.appendChild(removeButton);
        find('mut-job-details').appendChild(removeWrap);
    }
    setButtons();
    renderStatus();
  }
  function previewRow(item) {
    const editable=['staged','needs_review'].includes(item.state) && ['ready','needs_review','paused'].includes(activeJob?.status);
    const manual=item.manually_edited?'<small class="mut-manual">'+esc(label('manually_edited'))+'</small>':'';
    const text=item.state==='needs_review'?label('correction_required'):item.result??label('no_translation');
    const controls=editable?'<button type="button" class="mut-secondary" data-mut-action="edit_preview" data-item-id="'+Number(item.id)+'">'+(item.state==='needs_review'?'Correct':'Edit')+'</button>'+
      (item.original_result!==null && item.result!==item.original_result?'<button type="button" class="mut-secondary" data-mut-action="reset_preview" data-item-id="'+Number(item.id)+'">Reset</button>':''):'';
    return '<tr data-item-id="'+Number(item.id)+'"><td><strong>'+esc(item.key_name)+'</strong><small>'+esc(item.language)+' · '+esc(stateLabel(item.state))+'</small>'+manual+
      (item.error?'<small class="mut-warning">'+esc(item.error)+'</small>':'')+'</td><td><pre>'+esc(item.source)+'</pre></td><td><pre>'+esc(text)+'</pre>'+controls+'</td></tr>';
  }
  async function preview() {
    if (!activeJob) return false;
    const sequence = ++previewSequence, view = viewSequence;
    const data = await request('preview', {job_id:activeJob.id, offset, search:previewSearch, filter:previewFilter});
    if (view !== viewSequence || sequence !== previewSequence || !activeJob || Number(data.job.id) !== Number(activeJob.id)) return false;
    previewTotal = Number(data.total || 0);
    if (draft) return false;
    renderJob(data.job);
    previewRows = new Map(data.items.map(item=>[Number(item.id),item]));
    find('mut-preview').innerHTML = data.items.length ? '<table class="mut-preview-table"><thead><tr><th>' + esc(label('key')) + '</th><th>' + esc(label('source')) + '</th><th>' + esc(label('translation')) + '</th></tr></thead><tbody>' + data.items.map(previewRow).join('') + '</tbody></table>' : '<p class="mut-note">' + esc(label('no_preview_yet')) + '</p>';
    find('mut-page-info').textContent = label('preview_page') + ' ' + (Math.floor(offset / 50) + 1) + ' · ' + number(previewTotal) + ' matching';
    setButtons();
    return true;
  }
  async function refresh() {
    if (draft) return;
    const id = activeJob?.id || find('mut-job-select').value;
    invalidateReads(); const view=viewSequence;
    const previousBusy=busy; busy=true; selectionLoading=true; setButtons();
    try {
      const nextSnapshot=await request('snapshot'); if(view!==viewSequence)return;
      snapshot=nextSnapshot; renderSnapshot(id);
      if (id) { const data=await request('status',{job_id:id}); if(view!==viewSequence)return; if(Number(data.job.id)!==Number(id))throw new Error(label('connection_error')); renderJob(data.job); if(!await preview() && view===viewSequence)throw new Error(label('connection_error')); }
    } catch(error) { if(view===viewSequence)clearJobView(); throw error;
    } finally { if(view===viewSequence){busy=previousBusy;selectionLoading=false;setButtons();} }
  }
  async function run(action) {
    if (!activeJob || busy || draft) return;
    invalidateReads(); selectionLoading=false;
    busy = true; operation = action === 'resume' ? 'batch' : action; stopRequested = false;
    const id = activeJob.id;
    const resumeBudget = find('mut-resume-budget').value;
    const enable = find('mut-enable-languages').checked ? '1' : '';
    notice = ''; noticeError = false;
    setButtons(); message(label(operation === 'batch' ? 'generating' : operation === 'apply' ? 'applying' : 'rolling_back'));
    try {
      if (action === 'resume') { const r = await request('resume', {job_id:id, character_budget:resumeBudget}); renderJob(r.job); action = 'batch'; }
      while (true) {
        const data = await request(action, {job_id:id, enable_languages:enable}); renderJob(data.job);
        const retryAfter = Math.max(0, Math.min(300, Number(data.retry_after) || 0));
        if (retryAfter > 0 && action === 'batch') {
          for (let remaining = retryAfter; remaining > 0; remaining--) {
            if (stopRequested) break;
            const text = label('waiting_libre') + ' - ' + remaining + ' ' + label('seconds');
            find('mut-status-activity').textContent = text;
            find('mut-status-activity').hidden = false;
            await new Promise(resolve => setTimeout(resolve, 1000));
          }
          if (stopRequested) { const paused = await request('pause', {job_id:id}); renderJob(paused.job); break; }
          continue;
        }
        if (!data.more) break;
        if (stopRequested && action === 'batch') { const paused = await request('pause', {job_id:id}); renderJob(paused.job); break; }
      }
      message(activeJob.error || stateLabel(activeJob.status), Boolean(activeJob.error));
    } catch (error) { message(error.message || label('connection_error'), true); }
    finally { busy = false; operation = ''; requestStarted = 0; await refresh().catch(error => message(error.message,true)); setButtons(); renderStatus(); }
  }
  find('mut-settings').addEventListener('submit', async event => {
    event.preventDefault(); if (busy) return;
    const body = new FormData(event.currentTarget); busy = true; setButtons();
    try { await request('settings', body); find('mut-settings').elements.api_key.value = ''; if (find('mut-settings').elements.libre_key) find('mut-settings').elements.libre_key.value = ''; if (find('mut-settings').elements.clear_libre_key) find('mut-settings').elements.clear_libre_key.checked = false; message(label('saved')); snapshot = await request('snapshot'); renderSnapshot(); find('mut-create-budget').value = snapshot.settings.character_budget; }
    catch (error) { message(error.message,true); }
    finally { busy = false; setButtons(); }
  });
  find('mut-create').addEventListener('submit', async event => {
    event.preventDefault(); if (busy) return;
    const body = new FormData(event.currentTarget);
    if (body.getAll('targets[]').length < 1 || body.getAll('targets[]').length > 5) { message(label('target_hint'), true, false); return; }
    busy = true; setButtons();
    try { const data = await request('create', body); offset = 0; renderJob(data.job); snapshot = await request('snapshot'); renderSnapshot(data.job.id); await preview(); message(label('job_created')); }
    catch (error) { message(error.message,true); }
    finally { busy = false; setButtons(); if(activeJob?.status==='planned')find('mut-generate').focus(); }
  });
  find('mut-job-lookup').addEventListener('submit', async event => {
    event.preventDefault(); if (busy) return;
    invalidateReads(); clearJobView(); const id = new FormData(event.currentTarget).get('job_id'); busy = true; setButtons();
    try { const data = await request('status', {job_id:id}); if(Number(data.job.id)!==Number(id))throw new Error(label('connection_error')); offset = 0; renderJob(data.job); renderSnapshot(data.job.id); if(!await preview())throw new Error(label('connection_error')); }
    catch (error) { clearJobView(); message(error.message,true); }
    finally { busy = false; setButtons(); }
  });
  root.addEventListener('click', async event => {
    const button = event.target.closest('button[data-mut-action]'); if (!button) return;
    const action = button.dataset.mutAction;
    if (draft && !['save_preview','cancel_preview'].includes(action)) return;
    if (action === 'edit_preview') {
      if (busy || !['ready','needs_review','paused'].includes(activeJob?.status)) return;
      const item=previewRows.get(Number(button.dataset.itemId));
      if (!item || !['staged','needs_review'].includes(item.state)) return;
      draft={jobId:activeJob.id,itemId:item.id,expectedResult:item.result,expectedState:item.state};
      const cell = button.closest('td');
      const original = item.result ?? '';
      cell.dataset.original = original;
      cell.innerHTML = '<label>Review translation<textarea class="mut-preview-editor" rows="4" maxlength="1048576"></textarea></label><p class="mut-warning" role="alert" hidden></p><div class="mut-buttons"><button type="button" data-mut-action="save_preview" data-item-id="' + Number(button.dataset.itemId) + '">Save correction</button><button type="button" class="mut-secondary" data-mut-action="cancel_preview">Cancel</button></div>';
      cell.querySelector('textarea').value = original;
      cell.querySelector('textarea').focus();
      setButtons();
      return;
    }
    if (action === 'cancel_preview') {
      const row=button.closest('tr');
      const item=previewRows.get(Number(draft?.itemId));
      draft=null;
      if (row && item) row.outerHTML=previewRow(item);
      setButtons();
      return;
    }
    if (action === 'reset_preview') {
      if (busy || !['ready','needs_review','paused'].includes(activeJob?.status)) return;
      const item=previewRows.get(Number(button.dataset.itemId));
      if (!item) return;
      if (!window.confirm('Restore the original machine translation for this preview?')) return;
      invalidateReads(); busy=true; setButtons();
      try { await request('reset_preview',{job_id:activeJob.id,item_id:item.id,expected_state:item.state,expected_result:item.result??'',expected_null:item.result===null?'1':''}); await preview(); message('Original machine translation restored.'); }
      catch(error) { message(error.message || label('connection_error'),true); }
      finally { busy=false; setButtons(); }
      return;
    }
    if (action === 'save_preview') {
      if (busy || !draft || !['ready','needs_review','paused'].includes(activeJob?.status)) return;
      const value = button.closest('td')?.querySelector('textarea')?.value;
      if (typeof value !== 'string') return;
      invalidateReads(); busy = true; setButtons();
      try {
        await request('save_preview',{job_id:draft.jobId,item_id:draft.itemId,result:value,expected_state:draft.expectedState,expected_result:draft.expectedResult??'',expected_null:draft.expectedResult===null?'1':''});
        draft=null;
        await preview();
        message('Reviewed translation saved. Apply will use your correction.');
      } catch (error) { const inline=find('mut-preview').querySelector('[role="alert"]'); if(inline){inline.textContent=error.message || label('connection_error');inline.hidden=false;} message(error.message || label('connection_error'),true); }
      finally { busy=false; setButtons(); }
      return;
    }
    if (action === 'delete_job') {
        if (busy || !activeJob) return;
        const jobId = Number(activeJob.id);
        if (!window.confirm('Remove saved job #' + jobId + ' and its preview history? Any translations already applied on the site will remain.')) return;
        invalidateReads(); busy = true; setButtons();
        try {
            await request('delete_job', {job_id:jobId});
            activeJob = null; offset = 0;
            find('mut-preview').innerHTML = '';
            find('mut-progress').value = 0;
            snapshot = await request('snapshot');
            renderSnapshot();
            find('mut-job-select').value = '';
            find('mut-job-details').innerHTML = '';
            message('Job #' + jobId + ' removed. Applied site translations were preserved.');
        } catch (error) { message(error.message || label('connection_error'), true); }
        finally { busy = false; setButtons(); renderStatus(); }
        return;
    }

    if (action === 'pause' && busy && operation === 'batch') { stopRequested = true; button.disabled = true; message(label('finish_batch')); return; }
    if (busy) return;
    try {
      if (action === 'refresh') await refresh();
      else if (action === 'usage') { busy = true; setButtons(); const data = await request('usage'); message(data.usage.provider === 'libre' ? label('libre_languages_available') : label('quota_used') + ': ' + number(data.usage.used) + ' / ' + number(data.usage.limit)); }
      else if (action === 'pause') { const data = await request('pause', {job_id:activeJob.id}); renderJob(data.job); message(stateLabel(data.job.status)); }
      else await run(action);
    } catch (error) { message(error.message,true); }
    finally { if (action === 'usage') busy = false; setButtons(); }
  });
  find('mut-job-select').addEventListener('change', async event => {
    if(draft || (busy && !selectionLoading))return;
    const id=event.target.value;
    invalidateReads(); const view=viewSequence;
    notice=''; noticeError=false; offset=0;
    clearJobView();
    if(!id){busy=false;selectionLoading=false;setButtons();renderStatus();return;}
    busy=true;selectionLoading=true;setButtons();
    try { const data=await request('status',{job_id:id}); if(view!==viewSequence || find('mut-job-select').value!==id)return; if(Number(data.job.id)!==Number(id))throw new Error(label('connection_error')); renderJob(data.job); if(!await preview() && view===viewSequence)throw new Error(label('connection_error')); }
    catch(error){if(view===viewSequence){clearJobView();message(error.message,true);}}
    finally{if(view===viewSequence){busy=false;selectionLoading=false;setButtons();}}
  });
  [['mut-prev',-50],['mut-next',50]].forEach(([id, step]) => find(id).addEventListener('click', async () => { offset = Math.max(0,offset + step); try { await preview(); } catch (error) { message(error.message,true); } }));
  let searchTimer = null;
  find('mut-preview-search').addEventListener('input', event => {
    previewSearch = event.target.value.trim(); offset = 0;
    clearTimeout(searchTimer);
    searchTimer = setTimeout(() => { if (activeJob && !busy) preview().catch(error => message(error.message,true)); }, 350);
  });
  find('mut-preview-filter').addEventListener('change', event => {
    previewFilter = event.target.value; offset = 0;
    if (activeJob && !busy) preview().catch(error => message(error.message,true));
  });
  find('mut-show-conflicts').addEventListener('click', () => {
    previewFilter='conflict'; offset=0; find('mut-preview-filter').value='conflict';
    if (activeJob && !busy) preview().catch(error => message(error.message,true));
  });
  find('mut-review-flagged').addEventListener('click', () => {
    if (busy || draft || !activeJob) return;
    previewFilter='needs_review'; offset=0; find('mut-preview-filter').value='needs_review';
    preview().catch(error=>message(error.message,true));
  });
  window.addEventListener('beforeunload',event=>{if(draft){event.preventDefault();event.returnValue='';}});
  find('mut-show-status').addEventListener('click', showStatus);
  find('mut-hide-status').addEventListener('click', () => dialog.close());
  const providerSelect = find('mut-settings').elements.provider;
  function providerFields() { if (providerSelect) root.querySelectorAll('[data-mut-provider]').forEach(field => { field.hidden = field.dataset.mutProvider !== providerSelect.value; }); }
  if (providerSelect) providerSelect.addEventListener('change', providerFields);
  providerFields();
  function filterLanguages() {
    const search = find('mut-language-search').value.trim().toLocaleLowerCase();
    find('mut-targets').querySelectorAll('label').forEach(row => { row.hidden = search !== '' && !row.textContent.toLocaleLowerCase().includes(search) && !row.querySelector('input').value.includes(search); });
    find('mut-no-languages').hidden=!search || Boolean(snapshot.language_error) || Array.from(find('mut-targets').querySelectorAll('label')).some(row=>!row.hidden);
  }
  find('mut-language-search').addEventListener('input', filterLanguages);
  find('mut-targets').addEventListener('change',setButtons);
  // A heartbeat describes waiting time only; saved counts change after server responses.
  setInterval(() => { if (dialog.open && requestStarted) renderStatus(); }, 1000);
  renderSnapshot();
})();
