<?php
define('BX_DOL',true);
$root=dirname(__DIR__);
if(!is_file($root.'/classes/MyUnaTranslatorSupport.php')){echo "FAIL: Translator reporting integration is missing\n";exit(1);}
require $root.'/classes/MyUnaTranslatorSupport.php';
final class SupportStoreFixture {
    public array $receipts=[]; public array $job;
    public function __construct(){ $this->job=['id'=>2,'status'=>'paused','targets'=>['zh-cn'],'total'=>10229,'counts'=>['staged'=>2620,'applied'=>0,'conflict'=>0,'rollback_conflict'=>0],'error'=>'api_key=do-not-send','created_at'=>123]; }
    public function lock(){}
    public function unlock(){}
    public function job($id){return $this->job;}
    public function settings(){return ['provider'=>'libre','api_key'=>'TOPSECRET','libre_url'=>'https://secret.invalid/key','libre_key'=>'TOPSECRET'];}
    public function row($sql,...$args){if(str_contains($sql,'WHERE incident_hash')){foreach(array_reverse($this->receipts) as $id=>$r)if($r['incident_hash']===$args[0]&&!empty($r['ticket_id']))return ['submission_id'=>$id]+$r;return [];}return $this->receipts[$args[0]]??[];}
    public function q($sql,...$args){if(str_starts_with($sql,'INSERT')){$this->receipts[$args[0]]=['payload_hash'=>$args[1],'incident_hash'=>$args[2],'ticket_id'=>null];}elseif(str_starts_with($sql,'UPDATE')){$this->receipts[$args[1]]['ticket_id']=$args[0];}return true;}
}
$failed=0;
function check($name,callable $run){global $failed;try{if(!$run())throw new RuntimeException('unexpected');echo "PASS $name\n";}catch(Throwable $e){++$failed;echo "FAIL $name: ".$e->getMessage()."\n";}}
function setupSupport(callable $transport): array {
    $store=new SupportStoreFixture; $session=[];
    $support=new MyUnaTranslatorSupport($store,['module_version'=>'0.1.12','una_version'=>'15.0.4','php_version'=>'8.3.6','site_url'=>'https://example.com/private?key=secret'],new MyUnaSupportV1\ReportClient($transport),function()use(&$session){return $session;},function($s)use(&$session){$session=$s;});
    return [$support,$store];
}
check('prepare builds safe diagnostics and does not send',function(){ $calls=0;[$s,$store]=setupSupport(function()use(&$calls){++$calls;return [];});$p=$s->dispatch('report_prepare',['job_id'=>2]);$json=json_encode($p);return $calls===0 && !str_contains($json,'TOPSECRET') && !str_contains($json,'api_key=') && !str_contains($json,'private?') && $p['diagnostics']['error_code']==='operation_failed' && $p['diagnostics']['site_host']==='example.com' && $p['diagnostics']['language_key']==='unknown'; });
check('ambiguous delivery preserves identity and immutable retry',function(){ $calls=0;[$s,$store]=setupSupport(function($url,$body)use(&$calls){++$calls;if($calls===1)throw new RuntimeException('network');return ['status'=>200,'body'=>json_encode(['accepted'=>true,'ticket_id'=>19,'receipt'=>'delayed'])];});$p=$s->dispatch('report_prepare',['job_id'=>2]);$r=['submission_id'=>$p['submission_id'],'email'=>'a@example.com','summary'=>'Stopped','description'=>'Preview paused'];$a=$s->dispatch('report_send',$r);$b=$s->dispatch('report_send',$r);return !$a['accepted'] && $a['ambiguous'] && $b['accepted'] && $b['ticket_id']===19 && count($store->receipts)===1; });
check('ambiguous report cannot be changed into another payload',function(){[$s]=setupSupport(fn()=>throw new RuntimeException('network'));$p=$s->dispatch('report_prepare',['job_id'=>2]);$r=['submission_id'=>$p['submission_id'],'email'=>'a@example.com','summary'=>'Stopped','description'=>'Preview paused'];$s->dispatch('report_send',$r);$r['description']='Different';try{$s->dispatch('report_send',$r);}catch(RuntimeException $e){return str_contains($e->getMessage(),'same report');}return false;});
check('transport uses fixed HTTPS URL and ignores injected access URL',function(){ $seen='';$c=new MyUnaSupportV1\ReportClient(function($url,$body)use(&$seen){$seen=$url;return ['status'=>200,'body'=>'{"accepted":true,"ticket_id":22,"receipt":"sent","access_url":"https://evil.invalid"}'];});[$s]=setupSupport(fn()=>[]);$p=$s->dispatch('report_prepare',['job_id'=>2]);$r=['schema'=>1,'submission_id'=>$p['submission_id'],'email'=>'a@example.com','summary'=>'Stopped','description'=>'Preview paused','diagnostics'=>$p['diagnostics']];$res=$c->send($r);return $seen===MyUnaSupportV1\ReportClient::ENDPOINT && $res['access_url']===MyUnaSupportV1\ReportClient::ACCESS_URL; });
check('general support works without a saved job',function(){[$s]=setupSupport(fn()=>[]);$p=$s->dispatch('report_prepare',[]);return !isset($p['diagnostics']['job_id']) && $p['diagnostics']['operation']==='support';});
check('accepted job reopens with ticket acknowledgment',function(){[$s]=setupSupport(fn()=>['status'=>200,'body'=>'{"accepted":true,"ticket_id":33,"receipt":"sent"}']);$p=$s->dispatch('report_prepare',['job_id'=>2]);$s->dispatch('report_send',['submission_id'=>$p['submission_id'],'email'=>'a@example.com','summary'=>'Stopped','description'=>'Paused']);return ($s->dispatch('report_prepare',['job_id'=>2])['ticket_id']??0)===33;});
check('completed general reports permit fresh independent requests beyond ten',function(){[$s]=setupSupport(fn()=>['status'=>200,'body'=>'{"accepted":true,"ticket_id":33,"receipt":"sent"}']);$ids=[];for($i=0;$i<12;$i++){$p=$s->dispatch('report_prepare',['new_report'=>'1']);if(isset($p['ticket_id'])||isset($ids[$p['submission_id']]))return false;$ids[$p['submission_id']]=true;$s->dispatch('report_send',['submission_id'=>$p['submission_id'],'email'=>'a@example.com','summary'=>'Question '.$i,'description'=>'Help']);}return count($ids)===12;});
check('general reopen recovers acceptance until explicitly starting new report',function(){[$s]=setupSupport(fn()=>['status'=>200,'body'=>'{"accepted":true,"ticket_id":33,"receipt":"sent"}']);$p=$s->dispatch('report_prepare',[]);$s->dispatch('report_send',['submission_id'=>$p['submission_id'],'email'=>'a@example.com','summary'=>'Question','description'=>'Help']);$again=$s->dispatch('report_prepare',[]);$next=$s->dispatch('report_prepare',['new_report'=>'1']);return ($again['ticket_id']??0)===33 && $again['submission_id']===$p['submission_id'] && $next['submission_id']!==$p['submission_id'];});
exit($failed?1:0);
