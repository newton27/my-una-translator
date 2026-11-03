<?php
if(!class_exists('MyUnaSupportV1\\ReportContract',false)) require_once __DIR__.'/support/ReportContract.php';
require_once __DIR__.'/support/ReportClient.php';
final class MyUnaTranslatorSupport
{
    private $store; private array $env; private $client; private $load; private $save;
    public function __construct($store,array $environment,MyUnaSupportV1\ReportClient $client,callable $load,callable $save) { $this->store=$store;$this->env=$environment;$this->client=$client;$this->load=$load;$this->save=$save; }
    public function dispatch(string $action,array $input): array
    {
        if($action==='report_prepare') return $this->prepare($input);
        if($action!=='report_send') throw new RuntimeException('Unknown support action.');
        $id=$input['submission_id']??'';$drafts=($this->load)();
        if(!is_string($id) || !isset($drafts[$id])) throw new RuntimeException('Reopen the reporting form before sending.');
        $draft=$drafts[$id];
        $report=['schema'=>1,'submission_id'=>$id,'email'=>$input['email']??'','summary'=>$input['summary']??'','description'=>$input['description']??'','diagnostics'=>$draft['diagnostics']];
        try{$body=MyUnaSupportV1\ReportContract::encode($report);}catch(InvalidArgumentException $e){return ['accepted'=>false,'ambiguous'=>false,'message'=>$e->getMessage()];}
        $hash=hash('sha256',$body);
        // Serialize sends on this source site, including simultaneous tabs.
        $this->store->lock();
        try {
            $old=$this->store->row('SELECT payload_hash,ticket_id FROM my_una_translator_support_receipts WHERE submission_id=?',$id);
            if($old && !hash_equals($old['payload_hash'],$hash)) throw new RuntimeException('Retry the same report details; delivery of the earlier report may already have completed.');
            if(!empty($old['ticket_id'])) return ['accepted'=>true,'ticket_id'=>(int)$old['ticket_id'],'receipt'=>'delayed','access_url'=>MyUnaSupportV1\ReportClient::ACCESS_URL];
            if(!$old)$this->store->q('INSERT INTO my_una_translator_support_receipts(submission_id,payload_hash,incident_hash,ticket_id,created_at) VALUES(?,?,?,NULL,?)',$id,$hash,$draft['incident_hash'],time());
            $result=$this->client->send($report);
            if($result['accepted']) {
                $this->store->q('UPDATE my_una_translator_support_receipts SET ticket_id=? WHERE submission_id=?',(int)$result['ticket_id'],$id);
                $drafts[$id]['ticket_id']=(int)$result['ticket_id'];($this->save)($drafts);
            }
            elseif(empty($result['ambiguous']))$this->store->q('DELETE FROM my_una_translator_support_receipts WHERE submission_id=? AND ticket_id IS NULL',$id);
            return $result;
        } finally { $this->store->unlock(); }
    }
    private function prepare(array $input): array
    {
        $job=[];$id=$input['job_id']??0;
        if($id!==0 && $id!=='') {
            $id=filter_var($id,FILTER_VALIDATE_INT,['options'=>['min_range'=>1]]);
            if(!$id)throw new RuntimeException('Select a valid job.');
            $job=$this->store->job((int)$id);
        }
        $message=(string)($job['error']??'');$code=MyUnaSupportV1\ReportContract::code($message);
        $host=strtolower((string)parse_url($this->env['site_url'],PHP_URL_HOST));
        $d=['module'=>'my_una_translator','module_title'=>'My UNA Translator','module_version'=>$this->env['module_version'],'una_version'=>$this->env['una_version'],'php_version'=>$this->env['php_version'],'site_host'=>$host,'incident_at'=>gmdate('Y-m-d\TH:i:s\Z'),'operation'=>$job?(str_contains($job['status'],'rollback')?'rollback':(str_contains($job['status'],'appl')?'apply':'generation')):'support','error_code'=>$code,'error_message'=>MyUnaSupportV1\ReportContract::MESSAGES[$code]];
        if($job){$c=$job['counts'];$d+=['job_id'=>(int)$job['id'],'state'=>(string)$job['status'],'total'=>(int)$job['total'],'ready'=>(int)($c['staged']??0),'applied'=>(int)($c['applied']??0),'conflicts'=>(int)($c['conflict']??0)+(int)($c['rollback_conflict']??0),'provider'=>in_array($this->store->settings()['provider']??'',['libre','deepl'],true)?$this->store->settings()['provider']:'unknown','language_key'=>'unknown'];}
        if($job && count($job['targets']??[])===1)$d['target']=(string)$job['targets'][0];
        $d=MyUnaSupportV1\ReportContract::diagnostics($d);
        $incident=hash('sha256',json_encode([$job['id']??0,$job['status']??'support',$job['created_at']??0,$message]));
        $drafts=($this->load)(); if(!is_array($drafts))$drafts=[];
        foreach($drafts as $uuid=>$existing) {
            if(!empty($existing['ticket_id'])) {
                if(($existing['incident_hash']??'')===$incident && ($job || ($input['new_report']??'')!=='1'))return ['submission_id'=>$uuid,'diagnostics'=>$existing['diagnostics'],'ticket_id'=>$existing['ticket_id']];
                unset($drafts[$uuid]);continue;
            }
            if(($existing['incident_hash']??'')===$incident)return ['submission_id'=>$uuid,'diagnostics'=>$existing['diagnostics']];
        }
        if(count($drafts)>=10)throw new RuntimeException('Too many open support reports in this session. Finish an existing report first.');
        $saved=$job?$this->store->row('SELECT submission_id,ticket_id FROM my_una_translator_support_receipts WHERE incident_hash=? AND ticket_id IS NOT NULL ORDER BY created_at DESC LIMIT 1',$incident):[];
        if(!empty($saved['ticket_id']))return ['submission_id'=>$saved['submission_id'],'diagnostics'=>$d,'ticket_id'=>(int)$saved['ticket_id']];
        $uuid=MyUnaSupportV1\ReportContract::uuid();$drafts[$uuid]=['incident_hash'=>$incident,'diagnostics'=>$d];($this->save)($drafts);
        return ['submission_id'=>$uuid,'diagnostics'=>$d];
    }
}
