/* Phase-aware counts from the saved job; pending provider work is never guessed. */
'use strict';
function MyUnaTranslatorProgress(job, operation = '') {
  const c = job.counts || {};
  const count = key => Math.max(0, Number(c[key]) || 0);
  let phase = 'generation', total = Math.max(0, Number(job.total) || 0);
  let done = total - count('pending') - count('processing');
  if (operation === 'rollback' || ['rolling_back','rolled_back','rollback_cache_error'].includes(job.status)) {
    phase = 'rollback';
    total = count('applied') + count('rolling_back') + count('rolled_back') + count('rollback_conflict');
    done = count('rolled_back') + count('rollback_conflict');
  } else if (operation === 'apply' || ['applying','applied','apply_cache_error'].includes(job.status)) {
    phase = 'apply';
    done = count('applied') + count('conflict') + count('rolled_back') + count('rollback_conflict') + count('rolling_back');
  }
  done = Math.min(total, Math.max(0, done));
  return {phase, done, total, percent:total ? Math.floor(done * 100 / total) : 100,
    conflicts:count('conflict') + count('rollback_conflict'),
    staged:count('staged'), applied:count('applied'), rolled_back:count('rolled_back')};
}
