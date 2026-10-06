'use strict';
const test = require('node:test');
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const context = vm.createContext({});
if (fs.existsSync(__dirname + '/../template/js/workflow.js')) vm.runInContext(fs.readFileSync(__dirname + '/../template/js/workflow.js', 'utf8'), context);
const state = (status, counts = {}) => context.MyUnaTranslatorWorkflow(status ? {status, counts} : null);
test('new plan starts with language choice and does not offer generation or apply', () => {
  const s = state(null); assert.equal(s.step,1); assert.equal(s.generate,false); assert.equal(s.resume,false); assert.equal(s.apply,false);
});
test('planned jobs offer generation; paused jobs offer resuming their saved preview', () => {
  const planned=state('planned'); assert.equal(planned.step,2);assert.equal(planned.generate,true);assert.equal(planned.resume,false);assert.equal(planned.apply,false);
  const paused=state('paused',{staged:40,pending:60});assert.equal(paused.step,2);assert.equal(paused.generate,false);assert.equal(paused.resume,true);assert.equal(paused.apply,false);assert.equal(paused.hasPreview,true);
});
test('a ready preview offers apply and does not offer generation', () => {
  const s=state('ready',{staged:100});assert.equal(s.step,3);assert.equal(s.generate,false);assert.equal(s.resume,false);assert.equal(s.apply,true);assert.equal(s.hasPreview,true);
});
test('failed cache compilation offers apply retry instead of starting translation again', () => {
  const s=state('apply_cache_error',{applied:100});assert.equal(s.step,3);assert.equal(s.apply,true);assert.equal(s.resume,false);
});
test('applied jobs show completion without offering another apply', () => {
  const s=state('applied',{applied:100});assert.equal(s.step,4);assert.equal(s.apply,false);assert.equal(s.generate,false);assert.equal(s.rollback,true);
});
test('an apply in progress can continue; a rolled back job remains terminal', () => {
  const applying=state('applying',{applied:40,staged:60});assert.equal(applying.apply,true);assert.equal(applying.step,3);
  assert.equal(applying.hint,'next_finish_apply');
  const rolled=state('rolled_back',{rolled_back:100});assert.equal(rolled.apply,false);assert.equal(rolled.generate,false);assert.equal(rolled.resume,false);
});
test('a plan with no missing text points to existing site languages without offering apply', () => {
  for(const total of [0,'0']) {
    const s=context.MyUnaTranslatorWorkflow({status:'ready',total,counts:{}});
    assert.equal(s.apply,false);assert.equal(s.generate,false);assert.equal(s.step,4);assert.equal(s.hint,'next_empty');
  }
});
test('retained translation results stay pageable throughout apply and rollback history', () => {
  for(const itemState of ['applying','rolling_back','rolled_back','rollback_conflict']) {
    const s=state('rolled_back',{[itemState]:'100'});
    assert.equal(s.hasPreview,true,itemState);
  }
  assert.equal(state('planned',{pending:100}).hasPreview,false);
});
