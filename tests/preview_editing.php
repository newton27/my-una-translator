<?php
define('BX_DOL', true);
foreach (['Text','Store','Engine','Access'] as $part) require dirname(__DIR__).'/classes/MyUnaTranslator'.$part.'.php';
require __DIR__.'/preview_test_db.php';
$failures=0;
function check($name, callable $test) {
    global $failures;
    try { $test(); echo "PASS $name\n"; }
    catch(Throwable $e) { ++$failures; echo "FAIL $name: {$e->getMessage()}\n"; }
}
function expect($ok) { if (!$ok) throw new RuntimeException('Unexpected result'); }
function rejects(callable $test, $message) {
    try { $test(); } catch(RuntimeException $e) { expect(str_contains($e->getMessage(),$message)); return; }
    throw new RuntimeException('Expected rejection: '.$message);
}
function fixture() {
    $db=new PreviewTestDb(); $store=new MyUnaTranslatorStore($db);
    $id=$store->create(['targets'=>['fi'],'category_id'=>1,'include_english'=>1,'budget'=>10000]);
    $store->insertItems($id,[['key_id'=>1,'key_name'=>'_welcome','language'=>'fi','source'=>'Welcome {0}','before_value'=>null]]);
    $item=$store->items($id,['pending'])[0];
    $store->changeItem($item['id'],['state'=>'staged','result'=>'Welcome {0}']);
    $store->changeJob($id,['status'=>'ready','total'=>1]);
    $engine=new MyUnaTranslatorEngine($store,fn()=>throw new RuntimeException('No provider calls expected'),fn()=>true);
    return [$db,$store,$engine,['job_id'=>$id,'item_id'=>$item['id'],'expected_hash'=>hash('sha256','Welcome {0}'),'result'=>'Tervetuloa {0}']];
}
check('save persists edited preview and provenance without changing Polyglot or its baseline',function(){
    [$db,$store,$e,$data]=fixture(); $result=$e->dispatch('edit_item',$data);
    $item=$store->preview($data['job_id'])[0];
    expect($item['result']==='Tervetuloa {0}' && (int)$item['manually_edited']===1);
    expect($item['source']==='Welcome {0}' && $item['before_value']===null && $item['state']==='staged');
    expect($store->valueNow(1,1)==='Welcome {0}' && $store->valueNow(1,2)===null);
    expect($result['item']['result']==='Tervetuloa {0}');
});
check('manual provenance survives a second edit and stale browser saves fail',function(){
    [$db,$s,$e,$d]=fixture(); $e->dispatch('edit_item',$d);
    rejects(fn()=>$e->dispatch('edit_item',array_replace($d,['result'=>'Hei {0}'])),'changed');
    $e->dispatch('edit_item',array_replace($d,['expected_hash'=>hash('sha256','Tervetuloa {0}'),'result'=>'Hei {0}']));
    expect($s->preview($d['job_id'])[0]['result']==='Hei {0}');
});
check('missing duplicated or introduced placeholders and markup are rejected without saving',function(){
    foreach(['Tervetuloa','Tervetuloa {0} {0}','Tervetuloa {title}','<b>Tervetuloa {0}</b>','',"Hi\0 {0}"] as $bad) {
        [$db,$s,$e,$d]=fixture(); rejects(fn()=>$e->dispatch('edit_item',array_replace($d,['result'=>$bad])),'');
        expect($s->preview($d['job_id'])[0]['result']==='Welcome {0}');
    }
});
check('item must belong to the selected job and valid positive IDs and hash are required',function(){
    foreach([['item_id'=>0],['item_id'=>999],['job_id'=>0],['expected_hash'=>''],['result'=>[]]] as $bad) {
        [$db,$s,$e,$d]=fixture(); rejects(fn()=>$e->dispatch('edit_item',array_replace($d,$bad)),'');
    }
    [$db,$s,$e,$d]=fixture(); $other=$s->create(['targets'=>['fi'],'category_id'=>1,'include_english'=>1,'budget'=>10000]);
    $s->changeJob($other,['status'=>'ready']);
    rejects(fn()=>$e->dispatch('edit_item',array_replace($d,['job_id'=>$other])),'');
});
check('only staged items in ready or paused jobs may be edited',function(){
    foreach(['applying','applied','running','planned','rolled_back','apply_cache_error'] as $status) {
        [$db,$s,$e,$d]=fixture(); $s->changeJob($d['job_id'],['status'=>$status]);
        rejects(fn()=>$e->dispatch('edit_item',$d),'');
    }
    foreach(['pending','processing','applied','conflict'] as $state) {
        [$db,$s,$e,$d]=fixture(); $s->changeItem($d['item_id'],['state'=>$state]);
        rejects(fn()=>$e->dispatch('edit_item',$d),'');
    }
    [$db,$s,$e,$d]=fixture(); $s->changeJob($d['job_id'],['status'=>'paused']); $e->dispatch('edit_item',$d);
    expect($s->preview($d['job_id'])[0]['result']==='Tervetuloa {0}');
});
check('edited result applies and rolls back normally with provenance retained',function(){
    [$db,$s,$e,$d]=fixture(); $e->dispatch('edit_item',$d); $e->dispatch('apply',$d);
    $lang=(int)$s->language('fi')['ID']; expect($s->valueNow(1,$lang)==='Tervetuloa {0}');
    expect((int)$s->preview($d['job_id'])[0]['manually_edited']===1);
    $e->dispatch('rollback',$d); expect($s->valueNow(1,$lang)===null);
});
check('newer Polyglot edit survives applying an edited preview',function(){
    [$db,$s,$e,$d]=fixture(); $e->dispatch('edit_item',$d);
    $lang=$s->ensureLanguage('fi',MyUnaTranslatorLanguages::preset('fi'),$d['job_id']);
    $s->q('INSERT INTO sys_localization_strings VALUES (1,?,?)',$lang,'Oma korjaus {0}');
    $e->dispatch('apply',$d);
    expect($s->valueNow(1,$lang)==='Oma korjaus {0}' && $s->preview($d['job_id'])[0]['state']==='conflict');
});
check('edit endpoint requires administrator POST and CSRF',function(){
    rejects(fn()=>MyUnaTranslatorAccess::check('POST',false,'a','a'),'Administrator');
    rejects(fn()=>MyUnaTranslatorAccess::check('GET',true,'a','a'),'POST');
    rejects(fn()=>MyUnaTranslatorAccess::check('POST',true,'b','a'),'token');
});
echo "failures=$failures\n"; exit($failures?1:0);
