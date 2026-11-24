<?php
define('BX_DOL',true);
foreach (['Text','LibreProvider','Provider','Store','Engine'] as $part) require dirname(__DIR__).'/classes/MyUnaTranslator'.$part.'.php';
$failures=0;
function queueCheck($name,callable $fn) { global $failures; try {$fn();echo "PASS $name\n";}catch(Throwable $e){++$failures;echo "FAIL $name: ".$e->getMessage()."\n";} }
function queueEqual($got,$want) {if($got!==$want)throw new RuntimeException('Expected '.json_encode($want).' got '.json_encode($got));}
function queueReject(callable $fn) {try{$fn();}catch(RuntimeException $e){return;}throw new RuntimeException('Unsafe operation was accepted.');}

final class QueueTestDb {
    public PDO $pdo;
    public function __construct() {
        $this->pdo=new PDO('sqlite::memory:');$this->pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        foreach(['GET_LOCK','RELEASE_LOCK'] as $name)$this->pdo->sqliteCreateFunction($name,fn()=>1);
        $this->pdo->sqliteCreateFunction('DATABASE',fn()=>'review-queue');
        $this->pdo->exec("CREATE TABLE sys_localization_languages(ID INTEGER PRIMARY KEY,Name TEXT,Title TEXT,Enabled INTEGER,Flag TEXT,Direction TEXT,LanguageCountry TEXT);
            CREATE TABLE sys_localization_categories(ID INTEGER PRIMARY KEY,Name TEXT);
            CREATE TABLE sys_localization_keys(ID INTEGER PRIMARY KEY,`Key` TEXT,IDCategory INTEGER);
            CREATE TABLE sys_localization_strings(IDKey INTEGER,IDLanguage INTEGER,String TEXT);
            CREATE TABLE my_una_translator_languages(language TEXT,language_id INTEGER,job_id INTEGER);
            CREATE TABLE my_una_translator_settings(id INTEGER,provider TEXT,libre_url TEXT,libre_key TEXT,api_key TEXT,plan TEXT,batch_size INTEGER,character_budget INTEGER);
            CREATE TABLE my_una_translator_jobs(id INTEGER PRIMARY KEY AUTOINCREMENT,created_at INTEGER,operator_id INTEGER,targets TEXT,category_id INTEGER,include_english INTEGER,budget INTEGER,status TEXT DEFAULT 'planned',total INTEGER DEFAULT 0,estimated INTEGER DEFAULT 0,spent INTEGER DEFAULT 0,error TEXT DEFAULT '',enable_languages INTEGER DEFAULT 0);
            CREATE TABLE my_una_translator_items(id INTEGER PRIMARY KEY AUTOINCREMENT,job_id INTEGER,key_id INTEGER,key_name TEXT,language TEXT,source TEXT,before_value TEXT,result TEXT,original_result TEXT,manually_edited INTEGER DEFAULT 0,state TEXT DEFAULT 'pending',error TEXT DEFAULT '');
            INSERT INTO sys_localization_languages VALUES(1,'en','English',1,'gb','LTR','en_US'),(2,'sq','Shqip',1,'al','LTR','sq_AL');
            INSERT INTO sys_localization_categories VALUES(1,'Interface');
            INSERT INTO my_una_translator_settings VALUES(1,'libre','http://127.0.0.1:5001','','','free',3,1000000);");
        foreach(['Welcome {0}','Cannot create set','Goodbye'] as $i=>$text){$id=$i+1;$this->pdo->prepare('INSERT INTO sys_localization_keys VALUES(?,?,1)')->execute([$id,'_queue_'.$id]);$this->pdo->prepare('INSERT INTO sys_localization_strings VALUES(?,1,?)')->execute([$id,$text]);}
    }
    public function prepare($sql,...$args) {$parts=explode('?',$sql);$out=array_shift($parts);foreach($parts as $part){$arg=array_shift($args);$out.=($arg===null?'NULL':$this->pdo->quote((string)$arg)).$part;}return str_replace(['START TRANSACTION','BINARY '],['BEGIN',''],$out);}
    public function query($sql){if($sql==='ROLLBACK'&&!$this->pdo->inTransaction())return 0;return $this->pdo->exec($sql);}
    public function getRow($sql){return $this->pdo->query($sql)->fetch(PDO::FETCH_ASSOC);}
    public function getAll($sql){return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC);}
    public function getOne($sql){return $this->pdo->query($sql)->fetchColumn();}
    public function lastId(){return (int)$this->pdo->lastInsertId();}
}
function queueFixture($bad='I pamundur krijimi i file: %s',$status=200,$truncate=false,$retryFirst=false) {
    $db=new QueueTestDb();$posts=0;
    $provider=new MyUnaTranslatorLibreProvider('http://127.0.0.1:5001','',function($method,$url,$headers,$body)use(&$posts,$bad,$status,$truncate,$retryFirst){
        if($method==='GET')return ['status'=>200,'body'=>'[{"code":"en","name":"English","targets":["sq"]},{"code":"sq","name":"Albanian"}]'];
        ++$posts;if($retryFirst && $posts===1)return ['status'=>429,'headers'=>['Retry-After'=>'2'],'body'=>'{}'];if($status!==200)return ['status'=>$status,'body'=>'{}'];
        $map=['Welcome'=>'Mirësevjen','Cannot create set'=>$bad,'Goodbye'=>'Mirupafshim'];$q=json_decode($body,true)['q'];
        $translated=array_map(fn($s)=>$map[$s],$q);
        return ['status'=>200,'body'=>json_encode(['translatedText'=>$truncate?array_slice($translated,0,-1):$translated])];
    },false);
    $store=new MyUnaTranslatorStore($db);$engine=new MyUnaTranslatorEngine($store,fn()=>$provider,fn()=>true);
    $id=(int)$engine->dispatch('create',['targets'=>['sq'],'category_id'=>1,'character_budget'=>10000])['job']['id'];
    return [$db,$store,$engine,$id,function()use(&$posts){return $posts;}];
}
queueCheck('bad middle translation is isolated and safe siblings are retained',function(){
    [$db,$store,$engine,$id,$posts]=queueFixture();$r=$engine->dispatch('batch',['job_id'=>$id]);
    queueEqual($r['job']['status'],'needs_review');queueEqual($r['more'],false);
    $rows=$store->all('SELECT * FROM my_una_translator_items ORDER BY id');
    queueEqual(array_column($rows,'state'),['staged','needs_review','staged']);
    queueEqual(array_column($rows,'result'),['Mirësevjen {0}',null,'Mirupafshim']);
    queueEqual((int)$r['job']['spent'],31);queueEqual($posts(),1);
    if(!$rows[1]['error'])throw new RuntimeException('Review reason missing.');
    queueEqual($rows[1]['original_result'],null);queueEqual($rows[1]['before_value'],null);
});
queueCheck('blank individual output is flagged instead of stopping valid siblings',function(){
    [$db,$store,$engine,$id]=queueFixture('   ');$r=$engine->dispatch('batch',['job_id'=>$id]);
    queueEqual((int)$r['job']['counts']['staged'],2);queueEqual((int)$r['job']['counts']['needs_review'],1);
});
queueCheck('pending work continues past a rejected item',function(){
    [$db,$store,$engine,$id]=queueFixture();$db->pdo->exec('UPDATE my_una_translator_settings SET batch_size=2');
    $r=$engine->dispatch('batch',['job_id'=>$id]);queueEqual($r['more'],true);queueEqual($r['job']['status'],'running');
    $r=$engine->dispatch('batch',['job_id'=>$id]);queueEqual($r['more'],false);queueEqual($r['job']['status'],'needs_review');
    queueEqual((int)$r['job']['counts']['staged'],2);
});
queueCheck('HTTP failure still pauses the job without flagging every row',function(){
    [$db,$store,$engine,$id]=queueFixture('',503);$r=$engine->dispatch('batch',['job_id'=>$id]);
    queueEqual($r['job']['status'],'paused');queueEqual($r['more'],false);queueEqual((int)($r['job']['counts']['needs_review']??0),0);
    queueEqual((int)$r['job']['counts']['staged'],0);
});
queueCheck('incomplete provider response pauses without discarding reserved spending',function(){
    [$db,$store,$engine,$id,$posts]=queueFixture('',200,true);$r=$engine->dispatch('batch',['job_id'=>$id]);
    queueEqual($r['job']['status'],'paused');queueEqual((int)$r['job']['counts']['needs_review'],0);queueEqual((int)$r['job']['counts']['staged'],0);
    if((int)$r['job']['spent']<=0)throw new RuntimeException('Interrupted request reservation was lost.');queueEqual($posts(),1);
});
queueCheck('review flags do not bypass the ceiling for remaining pending strings',function(){
    [$db,$store,$engine,$id,$posts]=queueFixture();$db->pdo->exec('UPDATE my_una_translator_settings SET batch_size=2');
    $r=$engine->dispatch('batch',['job_id'=>$id]);$store->changeJob($id,['budget'=>$r['job']['spent']]);
    $r=$engine->dispatch('batch',['job_id'=>$id]);queueEqual($r['job']['status'],'paused');queueEqual((int)$r['job']['counts']['needs_review'],1);queueEqual((int)$r['job']['counts']['staged'],1);queueEqual($posts(),1);
});
queueCheck('Apply is blocked even if the caller forges ready status',function(){
    [$db,$store,$engine,$id]=queueFixture();$engine->dispatch('batch',['job_id'=>$id]);
    $store->changeJob($id,['status'=>'ready']);queueReject(fn()=>$engine->dispatch('apply',['job_id'=>$id]));
    queueEqual((int)$store->one('SELECT COUNT(*) FROM sys_localization_strings WHERE IDLanguage=2'),0);
});
queueCheck('valid correction clears the final flag and enables explicit Apply',function(){
    [$db,$store,$engine,$id,$posts]=queueFixture();$engine->dispatch('batch',['job_id'=>$id]);
    $item=$store->row("SELECT * FROM my_una_translator_items WHERE state='needs_review'");
    $r=$engine->dispatch('save_preview',['job_id'=>$id,'item_id'=>$item['id'],'expected_result'=>null,'expected_state'=>'needs_review','result'=>'Nuk mund të krijohet grupi']);
    queueEqual($r['job']['status'],'ready');queueEqual((int)$r['job']['counts']['needs_review'],0);queueEqual($posts(),1);
    queueEqual((int)$store->one('SELECT COUNT(*) FROM sys_localization_strings WHERE IDLanguage=2'),0);
    $r=$engine->dispatch('apply',['job_id'=>$id]);queueEqual((int)$r['job']['counts']['applied'],3);
});
queueCheck('unsafe correction and changed English source leave the review item intact',function(){
    [$db,$store,$engine,$id]=queueFixture();$engine->dispatch('batch',['job_id'=>$id]);$item=$store->row("SELECT * FROM my_una_translator_items WHERE state='needs_review'");
    $data=['job_id'=>$id,'item_id'=>$item['id'],'expected_result'=>null,'expected_state'=>'needs_review','result'=>'Wrong %s'];
    queueReject(fn()=>$engine->dispatch('save_preview',$data));$data['result']='Korrigjim';
    $db->pdo->exec("UPDATE sys_localization_strings SET String='Different source' WHERE IDKey=2 AND IDLanguage=1");
    queueReject(fn()=>$engine->dispatch('save_preview',$data));queueEqual($store->row('SELECT state FROM my_una_translator_items WHERE id=?',$item['id'])['state'],'needs_review');
});
queueCheck('stale correction cannot overwrite another saved correction',function(){
    [$db,$store,$engine,$id]=queueFixture();$engine->dispatch('batch',['job_id'=>$id]);$item=$store->row("SELECT * FROM my_una_translator_items WHERE state='needs_review'");
    $data=['job_id'=>$id,'item_id'=>$item['id'],'expected_result'=>null,'expected_state'=>'needs_review','result'=>'Korrigjimi i parë'];
    $engine->dispatch('save_preview',$data);$data['result']='Stale overwrite';queueReject(fn()=>$engine->dispatch('save_preview',$data));
    queueEqual($store->row('SELECT result FROM my_una_translator_items WHERE id=?',$item['id'])['result'],'Korrigjimi i parë');
});
queueCheck('completed review job remains removable without touching native text',function(){
    [$db,$store,$engine,$id]=queueFixture();$engine->dispatch('batch',['job_id'=>$id]);
    $engine->dispatch('delete_job',['job_id'=>$id]);
    queueEqual((int)$store->one('SELECT COUNT(*) FROM my_una_translator_jobs'),0);queueEqual((int)$store->one('SELECT COUNT(*) FROM sys_localization_strings WHERE IDLanguage=1'),3);
});
queueCheck('strict provider calls still reject invented arguments',function(){
    $p=new MyUnaTranslatorLibreProvider('http://127.0.0.1:5001','',fn()=>['status'=>200,'body'=>'{"translatedText":["Wrong %s"]}'],false);
    queueReject(fn()=>$p->translate(['Cannot create set'],'SQ'));
});
queueCheck('DeepL preview mode isolates invalid XML and retains valid siblings',function(){
    $p=new MyUnaTranslatorProvider('test-key','free',fn()=>['status'=>200,'body'=>json_encode(['translations'=>[
        ['text'=>'<doc><seg id="t0">Hola</seg></doc>','billed_characters'=>5],
        ['text'=>'<doc><seg id="t0">Wrong %s</seg></doc>','billed_characters'=>5],
    ]])]);
    $r=$p->translate(['Hello','World'],'ES',true);queueEqual($r[0]['text'],'Hola');queueEqual($r[1]['text'],null);queueEqual($r[1]['billed'],5);
    if(empty($r[1]['error']))throw new RuntimeException('Missing review error.');
});
queueCheck('DeepL empty middle output is isolated without losing its siblings',function(){
    $p=new MyUnaTranslatorProvider('test-key','free',fn()=>['status'=>200,'body'=>json_encode(['translations'=>[
        ['text'=>'<doc><seg id="t0">Hola</seg></doc>','billed_characters'=>5],
        ['text'=>'','billed_characters'=>5],
        ['text'=>'<doc><seg id="t0">Adiós</seg></doc>','billed_characters'=>7],
    ]])]);
    $r=$p->translate(['Hello','World','Goodbye'],'ES',true);queueEqual(array_column($r,'text'),['Hola',null,'Adiós']);queueEqual(array_sum(array_column($r,'billed')),17);
    if(empty($r[1]['error']))throw new RuntimeException('Blank result was not marked for review.');
    queueReject(fn()=>$p->translate(['Hello','World','Goodbye'],'ES'));
});
queueCheck('review queue survives a first-response retry and retains manual correction provenance',function(){
    [$db,$store,$engine,$id,$posts]=queueFixture('Unsafe %s',200,false,true);
    $first=$engine->dispatch('batch',['job_id'=>$id]);queueEqual($first['retry_after'],2);queueEqual((int)$first['job']['spent'],0);queueEqual((int)$first['job']['counts']['pending'],3);
    $second=$engine->dispatch('batch',['job_id'=>$id]);queueEqual($second['job']['status'],'needs_review');queueEqual((int)$second['job']['counts']['staged'],2);queueEqual($posts(),2);
    $item=$store->row("SELECT * FROM my_una_translator_items WHERE state='needs_review'");
    $store->q('UPDATE my_una_translator_items SET before_value=? WHERE id=?','Saved destination baseline',$item['id']);
    $engine->dispatch('save_preview',['job_id'=>$id,'item_id'=>$item['id'],'expected_result'=>null,'expected_state'=>'needs_review','result'=>'Corrected']);
    $saved=$store->item($id,$item['id']);queueEqual((int)$saved['manually_edited'],1);queueEqual($saved['before_value'],'Saved destination baseline');queueEqual($saved['original_result'],null);
    queueEqual($store->job($id)['status'],'ready');queueEqual($posts(),2);queueEqual((int)$store->one('SELECT COUNT(*) FROM sys_localization_strings WHERE IDLanguage=2'),0);
});

echo 'REVIEW QUEUE FAILURES '.$failures.PHP_EOL;exit($failures?1:0);
