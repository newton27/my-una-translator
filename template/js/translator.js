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
  let notice = '', noticeError = false, requestStarted = 0, activity = '', seenJobError = '';
  let snapshot = cfg.snapshot;
  const dialog = find('mut-status-dialog');
  function showStatus() {
    renderStatus();
    if (!dialog.open) dialog.showModal();
  }
  function renderStatus() {
    const p = activeJob ? MyUnaTranslatorProgress(activeJob, operation) : null;
    find('mut-status-job').textContent = activeJob ? label('job') + ' #' + Number(activeJob.id) + ' · ' + activeJob.targets.map(code => snapshot.languages.find(l => l.code === code)?.native || code).join(', ') : label('no_job_selected');
    find('mut-status-state').textContent = busy && operation ? label(operation === 'batch' ? 'generating' : operation === 'apply' ? 'applying' : 'rolling_back') : activeJob ? stateLabel(activeJob.status) : label('working');
    const bar = find('mut-status-progress');
    bar.hidden = !p;
    if (p) bar.value = p.percent;
    find('mut-status-count').textContent = p ? label(p.phase === 'generation' ? 'preview_progress' : p.phase === 'apply' ? 'apply_progress' : 'rollback_progress') + ': ' + number(p.done) + ' / ' + number(p.total) + ' · ' + p.percent + '%' : '';
    find('mut-status-totals').textContent = p ? number(p.staged) + ' ' + label('staged') + ' · ' + number(p.applied) + ' ' + label('applied') + ' · ' + number(p.conflicts) + ' ' + label('conflicts') : '';
    find('mut-status-characters').textContent = activeJob ? label('used_reserved') + ': ' + number(activeJob.spent) : '';
    const elapsed = requestStarted ? Math.floor((Date.now() - requestStarted) / 1000) : 0;
    find('mut-status-activity').textContent = requestStarted ? label(activity) + ' · ' + elapsed + ' ' + label('seconds') + (elapsed >= 60 ? ' · ' + label('slow_request') : '') : '';
    find('mut-status-activity').hidden = !requestStarted;
    const savedError = activeJob?.error || '';
    const problem = [savedError, noticeError && notice !== savedError ? notice : ''].filter(Boolean).join('\n\n');
    const errorBox = find('mut-status-error');
    errorBox.textContent = problem;
    errorBox.hidden = !problem;
    find('mut-status-note').textContent = !noticeError ? notice : '';
    find('mut-status-pause').hidden = !(busy && operation === 'batch');
    find('mut-status-pause').disabled = stopRequested;
    find('mut-show-status').textContent = busy && operation ? label('show_progress') : label('show_status');
  }
  const message = (text, error = false) => {
    const box = find('mut-message'); box.hidden = false; box.textContent = text;
    box.classList.toggle('mut-error', error);
    notice = text; noticeError = error; showStatus();
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
    const action = name => root.querySelector('[data-mut-action="' + name + '"]');
    root.querySelectorAll('[data-mut-action="pause"]').forEach(control => { control.disabled = !(busy && operation === 'batch') || stopRequested; });
    if (busy) return;
    const state = activeJob?.status || '';
    const ready = snapshot.settings.provider_ready ?? snapshot.settings.has_key;
    action('batch').disabled = !activeJob || !ready || !['planned','running'].includes(state);
    action('resume').disabled = !activeJob || !ready || !['paused','planned','running'].includes(state);
    root.querySelectorAll('[data-mut-action="pause"]').forEach(control => { control.disabled = !activeJob || !['planned','running'].includes(state); });
    action('apply').disabled = !activeJob || !['ready','applying','applied','apply_cache_error'].includes(state);
    action('rollback').disabled = !activeJob || !['applied','apply_cache_error','rolling_back','rolled_back','rollback_cache_error'].includes(state);
    find('mut-prev').disabled = !activeJob || offset === 0;
    find('mut-next').disabled = !activeJob || offset + 50 >= Number(activeJob.total);
    renderStatus();
  }
  function renderSnapshot(selected = null) {
    find('mut-coverage').innerHTML = snapshot.languages.map(l => '<tr><th>' + esc(l.native) + '<small>' + esc(l.name) + '</small></th><td>' + number(l.translated) + '</td><td>' + number(l.missing) + '</td><td>' + number(l.english_copies) + '</td><td><span class="mut-pill">' + esc(label(l.enabled ? 'active' : l.installed ? 'disabled' : 'not_created')) + '</span></td></tr>').join('');
    const select = find('mut-job-select');
    const wanted = selected ?? select.value;
    const jobs = snapshot.jobs.slice();
    if (activeJob && !jobs.some(j => String(j.id) === String(activeJob.id)) && String(wanted) === String(activeJob.id)) jobs.push(activeJob);
    select.innerHTML = '<option value="">' + esc(label('select_job')) + '</option>' + jobs.map(j => '<option value="' + Number(j.id) + '">#' + Number(j.id) + ' · ' + esc(stateLabel(j.status)) + ' · ' + number(j.total) + ' ' + esc(label('total_keys')) + '</option>').join('');
    if (jobs.some(j => String(j.id) === String(wanted))) select.value = String(wanted);
    setButtons();
  }
  function renderJob(job) {
    activeJob = job;
    const counts = job.counts;
    const progress = MyUnaTranslatorProgress(job, operation);
    find('mut-progress').value = progress.percent;
    find('mut-resume-budget').value = job.budget;
    const estimate = number(job.estimated) + ' / ' + number(job.budget) + ' ' + label('characters');
    const details = [stateLabel(job.status), number(counts.staged) + ' ' + label('staged'), number(counts.applied) + ' ' + label('applied'), number(Number(counts.conflict || 0) + Number(counts.rollback_conflict || 0)) + ' ' + label('conflicts')];
    find('mut-job-details').innerHTML = '<div class="mut-stat-row">' + details.map(d => '<span class="mut-pill">' + esc(d) + '</span>').join('') + '</div><p>' + esc(estimate) + '</p><p class="mut-note">' + esc(label('used_reserved')) + ': ' + number(job.spent) + '</p>' + (Number(job.estimated) > Number(job.budget) ? '<p class="mut-warning">' + esc(label('ceiling_warning')) + '</p>' : '') + (job.error ? '<p class="mut-warning">' + esc(job.error) + '</p>' : '');
    if (['paused', 'applied','rolled_back','failed','empty_preview'].includes(String(job.status))) {
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
    const errorKey = job.error ? String(job.id) + ':' + job.error : '';
    if (errorKey && errorKey !== seenJobError) { notice = ''; noticeError = false; showStatus(); }
    seenJobError = errorKey;
  }
  async function preview() {
    if (!activeJob) return;
    const data = await request('preview', {job_id:activeJob.id, offset});
    renderJob(data.job);
    find('mut-preview').innerHTML = data.items.length ? '<table class="mut-preview-table"><thead><tr><th>' + esc(label('key')) + '</th><th>' + esc(label('source')) + '</th><th>' + esc(label('translation')) + '</th></tr></thead><tbody>' + data.items.map(i => '<tr><td><strong>' + esc(i.key_name) + '</strong><small>' + esc(i.language) + ' · ' + esc(stateLabel(i.state)) + '</small>' + (i.error ? '<small class="mut-warning">' + esc(i.error) + '</small>' : '') + '</td><td><pre>' + esc(i.source) + '</pre></td><td><pre>' + esc(i.result ?? label('no_translation')) + '</pre></td></tr>').join('') + '</tbody></table>' : '<p class="mut-note">' + esc(label('empty_preview')) + '</p>';
    find('mut-page-info').textContent = label('preview_page') + ' ' + (Math.floor(offset / 50) + 1);
    setButtons();
  }
  async function refresh() {
    const id = activeJob?.id || find('mut-job-select').value;
    snapshot = await request('snapshot'); renderSnapshot(id);
    if (id) { const data = await request('status', {job_id:id}); renderJob(data.job); await preview(); }
  }
  async function run(action) {
    if (!activeJob || busy) return;
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
    const body = new FormData(event.currentTarget); busy = true; setButtons();
    try { const data = await request('create', body); offset = 0; renderJob(data.job); snapshot = await request('snapshot'); renderSnapshot(data.job.id); await preview(); message(label('job_created')); }
    catch (error) { message(error.message,true); }
    finally { busy = false; setButtons(); }
  });
  find('mut-job-lookup').addEventListener('submit', async event => {
    event.preventDefault(); if (busy) return;
    const id = new FormData(event.currentTarget).get('job_id'); busy = true; setButtons();
    try { const data = await request('status', {job_id:id}); offset = 0; renderJob(data.job); renderSnapshot(data.job.id); await preview(); }
    catch (error) { message(error.message,true); }
    finally { busy = false; setButtons(); }
  });
  root.addEventListener('click', async event => {
    const button = event.target.closest('button[data-mut-action]'); if (!button) return;
    const action = button.dataset.mutAction;
    if (action === 'delete_job') {
        if (busy || !activeJob) return;
        const jobId = Number(activeJob.id);
        if (!window.confirm('Remove saved job #' + jobId + ' and its preview history? Any translations already applied on the site will remain.')) return;
        busy = true; setButtons();
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
      else if (action === 'usage') { busy = true; setButtons(); const data = await request('usage'); message(data.usage.provider === 'libre' ? label('libre_available') : label('quota_used') + ': ' + number(data.usage.used) + ' / ' + number(data.usage.limit)); }
      else if (action === 'pause') { const data = await request('pause', {job_id:activeJob.id}); renderJob(data.job); message(stateLabel(data.job.status)); }
      else await run(action);
    } catch (error) { message(error.message,true); }
    finally { if (action === 'usage') busy = false; setButtons(); }
  });
  find('mut-job-select').addEventListener('change', async event => {
    notice = ''; noticeError = false;
    if (!event.target.value) { activeJob = null; find('mut-preview').innerHTML = ''; find('mut-job-details').textContent = ''; find('mut-progress').value = 0; setButtons(); return; }
    offset = 0; try { const data = await request('status', {job_id:event.target.value}); renderJob(data.job); await preview(); } catch (error) { message(error.message,true); }
  });
  [['mut-prev',-50],['mut-next',50]].forEach(([id, step]) => find(id).addEventListener('click', async () => { offset = Math.max(0,offset + step); try { await preview(); } catch (error) { message(error.message,true); } }));
  find('mut-show-status').addEventListener('click', showStatus);
  find('mut-hide-status').addEventListener('click', () => dialog.close());
  const providerSelect = find('mut-settings').elements.provider;
  function providerFields() { if (providerSelect) root.querySelectorAll('[data-mut-provider]').forEach(field => { field.hidden = field.dataset.mutProvider !== providerSelect.value; }); }
  if (providerSelect) providerSelect.addEventListener('change', providerFields);
  providerFields();
  // A heartbeat describes waiting time only; saved counts change after server responses.
  setInterval(() => { if (dialog.open && requestStarted) renderStatus(); }, 1000);
  renderSnapshot();
  if (snapshot.jobs.length) { find('mut-job-select').value = String(snapshot.jobs[0].id); request('status',{job_id:snapshot.jobs[0].id}).then(data => { renderJob(data.job); return preview(); }).catch(error => message(error.message,true)); }
})();
