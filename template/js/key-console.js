(() => {
  const root = document.getElementById('mut-key-console');
  const configNode = document.getElementById('mut-config');
  if (!root || !configNode) return;
  const cfg = JSON.parse(configNode.textContent || '{}');
  const list = root.querySelector('#mut-key-list');
  const message = root.querySelector('#mut-key-message');
  const secretBox = root.querySelector('#mut-key-secret');
  const requestId = () => (crypto.randomUUID ? crypto.randomUUID() : 'req-' + Date.now() + '-' + Math.random().toString(36).slice(2));
  const call = async (action, data = {}) => {
    const body = new FormData();
    Object.entries(data).forEach(([key, value]) => body.append(key, String(value)));
    body.set('mt_action', 'key_' + action);
    body.set('csrf_token', cfg.token);
    const response = await fetch(cfg.endpoint, {method:'POST', body, credentials:'same-origin', headers:{'X-Requested-With':'XMLHttpRequest'}});
    const result = await response.json();
    if (!response.ok || Number(result.code) !== 0) throw new Error(result.message || 'Key operation failed.');
    return result.data || {};
  };
  const showMessage = text => { message.textContent = text; };
  const showSecret = data => {
    secretBox.replaceChildren();
    const heading = document.createElement('strong'); heading.textContent = 'Copy this key now. It will not be shown again.';
    const secret = document.createElement('code'); secret.textContent = data.secret || '';
    const meta = document.createElement('p'); meta.textContent = 'Site: ' + (data.hostname || '') + ' · Fingerprint: ' + (data.secret_fingerprint || '');
    secretBox.append(heading, secret, meta);
  };
  const render = rows => {
    list.replaceChildren();
    if (!rows.length) { list.textContent = 'No customer keys have been issued.'; return; }
    const table = document.createElement('table'); const head = document.createElement('tr');
    ['Site', 'Hostname', 'Status', 'Limits', 'Fingerprint', 'Actions'].forEach(label => { const th=document.createElement('th'); th.textContent=label; head.append(th); });
    const thead=document.createElement('thead'); thead.append(head); table.append(thead); const body=document.createElement('tbody');
    rows.forEach(row => {
      const tr=document.createElement('tr');
      [row.site_label, row.hostname, row.status, String(row.requests_per_minute) + '/min · ' + String(row.characters_per_request) + ' chars', row.fingerprint].forEach(value => { const td=document.createElement('td'); td.textContent=String(value ?? ''); tr.append(td); });
      const actions=document.createElement('td');
      if (row.status === 'active') {
        const rotate=document.createElement('button'); rotate.type='button'; rotate.textContent='Rotate';
        rotate.addEventListener('click', async () => { if (!confirm('Issue a replacement key? The current key stays active until you revoke it.')) return; try { const result=await call('rotate',{key_id:row.id,site_label:row.site_label,hostname:row.hostname,requests_per_minute:row.requests_per_minute,characters_per_request:row.characters_per_request,request_id:requestId()}); showSecret(result); await load(); showMessage('Replacement issued. Update the customer site, then revoke the previous key.'); } catch (error) { showMessage(error.message); } });
        const revoke=document.createElement('button'); revoke.type='button'; revoke.textContent='Revoke';
        revoke.addEventListener('click', async () => { if (!confirm('Revoke this key now? Requests using it will stop working.')) return; try { await call('revoke',{key_id:row.id}); secretBox.replaceChildren(); await load(); showMessage('Key revoked.'); } catch (error) { showMessage(error.message); } });
        actions.append(rotate, revoke);
      }
      tr.append(actions); body.append(tr);
    });
    table.append(body); list.append(table);
  };
  const load = async () => { try { const data=await call('list'); render(data.items || []); } catch (error) { showMessage(error.message); } };
  root.querySelector('#mut-key-create').addEventListener('submit', async event => {
    event.preventDefault(); secretBox.replaceChildren(); showMessage('Issuing key…');
    const data=Object.fromEntries(new FormData(event.currentTarget).entries()); data.request_id=requestId();
    try { const result=await call('create',data); showSecret(result); event.currentTarget.reset(); await load(); showMessage('Key issued. Copy the secret now; it is shown only once.'); } catch (error) { showMessage(error.message); }
  });
  root.querySelector('#mut-key-refresh').addEventListener('click', load);
  load();
})();
