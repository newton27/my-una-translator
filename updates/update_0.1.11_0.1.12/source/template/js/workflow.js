/* Available actions follow saved job state; making a plan never publishes a language. */
'use strict';
function MyUnaTranslatorWorkflow(job) {
  const status=job?.status || '';
  const counts=job?.counts || {};
  const empty=status==='ready' && Number(job.total)===0;
  const generate=['planned','running'].includes(status);
  const resume=status==='paused';
  const apply=!empty && ['ready','applying','apply_cache_error'].includes(status);
  const finished=empty || ['applied','rolled_back','rollback_cache_error','rolling_back','failed'].includes(status);
  const hints={paused:'next_resume',ready:'next_review',applying:'next_finish_apply',apply_cache_error:'next_retry_apply',applied:'next_done',rolled_back:'next_rolled_back',rolling_back:'next_finish_rollback',rollback_cache_error:'next_finish_rollback',failed:'next_failed'};
  return {step:!job?1:apply?3:finished?4:2,generate,resume,apply,
    rollback:['applied','apply_cache_error','rolling_back','rolled_back','rollback_cache_error'].includes(status),
    hasPreview:['staged','applying','applied','conflict','rolling_back','rolled_back','rollback_conflict'].some(state=>Number(counts[state] || 0)>0),
    hint:!job?'next_choose':empty?'next_empty':hints[status] || 'next_generate'};
}

