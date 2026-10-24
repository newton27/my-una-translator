'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
let renderSrc;
test.before(() => {
  const src = fs.readFileSync(__dirname + '/../template/js/translator.js', 'utf8');
  const m = src.match(/  function renderStatus\(\) \{[\s\S]*?\n  \}\n  const message =/);
  assert.ok(m, 'renderStatus source must be captured unambiguously');
  renderSrc = m[0].slice(0, m[0].lastIndexOf('  const message ='));
});
function render(job, busy = false, operation = '') {
  const elements = {};
  const find = id => elements[id] ??= {textContent:'', hidden:false, disabled:false, value:0};
  const label = key => ({no_job_selected:'No job selected',working:'Working...',generating:'Generating preview',show_progress:'Show progress',show_status:'Show translation status',job:'Job',ready:'Ready',seconds:'seconds',staged:'staged',applied:'applied',conflicts:'conflicts',used_reserved:'used',preview_progress:'Preview progress'})[key] ?? key;
  const ctx = {
    find, label, activeJob:job, busy, operation, stopRequested:false,
    requestStarted:0, activity:'', notice:'', noticeError:false,
    snapshot:{languages:[]}, stateLabel:label, number:v=>String(Number(v||0)),
    MyUnaTranslatorProgress:()=>({phase:'generation',done:0,total:1,percent:0,staged:0,applied:0,conflicts:0})
  };
  vm.runInNewContext(renderSrc+';renderStatus()',ctx);
  return elements;
}
test('empty site never reports work in progress',()=>{
  const e=render(null);
  assert.equal(e['mut-status-job'].textContent,'No job selected');
  assert.equal(e['mut-status-state'].textContent,'No job selected');
  assert.equal(e['mut-status-progress'].hidden,true);
  assert.equal(e['mut-show-status'].textContent,'Show translation status');
});
test('saved ready job still displays its real state',()=>{
  const e=render({id:79,status:'ready',targets:[],spent:2206,error:''});
  assert.equal(e['mut-status-state'].textContent,'Ready');
  assert.match(e['mut-status-job'].textContent,/Job #79/);
});
test('genuinely active work still displays generation',()=>{
  const e=render({id:79,status:'running',targets:[],spent:2206,error:''},true,'batch');
  assert.equal(e['mut-status-state'].textContent,'Generating preview');
  assert.equal(e['mut-show-status'].textContent,'Show progress');
});
