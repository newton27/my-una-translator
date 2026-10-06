const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');

test('successful asynchronous key issuance resets the form and refreshes the list', async () => {
  class Element extends EventTarget {
    textContent = '';
    children = [];
    append(...children) { this.children.push(...children); }
    replaceChildren(...children) { this.children = children; }
  }
  const form = new Element();
  form.values = {site_label:'Test site', hostname:'customer.example', requests_per_minute:'60', characters_per_request:'4000'};
  let resets = 0;
  form.reset = () => { resets++; };
  const ids = Object.fromEntries(['mut-key-list','mut-key-message','mut-key-secret','mut-key-refresh'].map(id => ['#' + id, new Element()]));
  ids['#mut-key-create'] = form;
  const root = {querySelector: id => ids[id]};
  class TestFormData {
    constructor(source) { this.fields = {...(source?.values || {})}; }
    append(key, value) { this.fields[key] = value; }
    set(key, value) { this.fields[key] = value; }
    entries() { return Object.entries(this.fields); }
  }
  const requests = [];
  const context = vm.createContext({
    document: {
      getElementById: id => id === 'mut-key-console' ? root : {textContent:JSON.stringify({endpoint:'/test',token:'test-token'})},
      createElement: () => new Element(),
    },
    FormData:TestFormData,
    crypto:{randomUUID: () => 'test-request-id'},
    fetch:async (url, options) => {
      requests.push(options.body.fields);
      const data = options.body.fields.mt_action === 'key_create'
        ? {secret:'test-secret',hostname:'customer.example',secret_fingerprint:'test-fingerprint'}
        : {items:[]};
      return {ok:true,json:async () => ({code:0,data})};
    },
  });
  vm.runInContext(fs.readFileSync(require.resolve('../template/js/key-console.js'),'utf8'),context);
  await new Promise(setImmediate);
  form.dispatchEvent(new Event('submit',{cancelable:true}));
  await new Promise(setImmediate);
  assert.equal(resets,1);
  assert.deepEqual(requests.map(row => row.mt_action),['key_list','key_create','key_list']);
  assert.equal(requests[1].csrf_token,'test-token');
  assert.match(ids['#mut-key-message'].textContent,/Key issued/);
  assert.equal(ids['#mut-key-secret'].children[1].textContent,'test-secret');
});
