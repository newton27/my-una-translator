<?php
/** First-response 429 and local text-boundary behavior; no external service or mail. */
define('BX_DOL', true);
foreach (['Text','LibreProvider','Store','Engine'] as $part) require dirname(__DIR__).'/classes/MyUnaTranslator'.$part.'.php';
require __DIR__.'/preview_test_db.php';
$failed=0;
function check($name, callable $run) {
    global $failed;
    try { $run(); echo "PASS $name\n"; }
    catch(Throwable $error) { ++$failed; echo "FAIL $name: ".$error->getMessage()."\n"; }
}
function same($actual,$expected) { if($actual!==$expected)throw new RuntimeException('Expected '.json_encode($expected).' got '.json_encode($actual)); }
function fixture($firstStatus=429,$firstSource='Welcome {0}') {
    $db=new PreviewTestDb();
    // MySQL permits ROLLBACK after a committed provider reservation; SQLite rejects that no-op.
    $adapter=new class($db) {
        private $db;
        public function __construct($db){$this->db=$db;}
        public function __call($name,$arguments) {
            try{return $this->db->$name(...$arguments);}
            catch(PDOException $error) {
                if($name==='query' && trim($arguments[0])==='ROLLBACK' && str_contains($error->getMessage(),'no transaction is active'))return 0;
                throw $error;
            }
        }
    };
    $store=new MyUnaTranslatorStore($adapter);$requests=[];
    $id=$store->create(['targets'=>['ar'],'category_id'=>1,'include_english'=>1,'budget'=>10000]);
    $store->insertItems($id,[
        ['key_id'=>1,'key_name'=>'_welcome','language'=>'ar','source'=>$firstSource,'before_value'=>null],
        ['key_id'=>2,'key_name'=>'_another','language'=>'ar','source'=>'Another {1}','before_value'=>null],
        ['key_id'=>3,'key_name'=>'_kept','language'=>'ar','source'=>'Kept {2}','before_value'=>null],
    ]);
    $rows=$store->items($id,['pending']);$store->changeItem($rows[2]['id'],['state'=>'staged','result'=>'Saved {2}','manually_edited'=>1]);
    $store->changeJob($id,['status'=>'running','spent'=>11,'total'=>3]);
    $provider=new MyUnaTranslatorLibreProvider('http://127.0.0.1:5001','',function($method,$url,$headers,$body)use(&$requests,$firstStatus){
        if($method==='GET')return ['status'=>200,'body'=>'[{"code":"en","name":"English","targets":["ar"]},{"code":"ar","name":"Arabic"}]'];
        $requests[]=json_decode($body,true,512,JSON_THROW_ON_ERROR);
        if(count($requests)===1)return ['status'=>$firstStatus,'headers'=>['rEtRy-AfTeR'=>'17'],'body'=>'{}'];
        return ['status'=>200,'body'=>json_encode(['translatedText'=>array_map(fn($text)=>'Translated '.$text,end($requests)['q'])])];
    },false);
    $engine=new MyUnaTranslatorEngine($store,fn()=>$provider,fn()=>true);
    return [$db,$store,$engine,$id,$rows,static function()use(&$requests){return $requests;}];
}
check('first-response 429 restores pending rows and prior spending without changing settings or saved previews',function(){
    [$db,$store,$engine,$id,$rows,$requests]=fixture();
    $settings=$db->getAll('SELECT * FROM my_una_translator_settings');$polyglot=$db->getAll('SELECT * FROM sys_localization_strings');
    $saved=$store->item($id,$rows[2]['id']);$result=$engine->dispatch('batch',['job_id'=>$id]);
    if(!isset($result['retry_after']))throw new RuntimeException('No retry: '.json_encode($result));same($result['retry_after'],17);same($result['more'],true);same($result['job']['status'],'running');same((int)$result['job']['spent'],11);
    foreach(array_slice($rows,0,2) as $row){$item=$store->item($id,$row['id']);same($item['state'],'pending');same($item['result'],null);same($item['error'],'');}
    same($store->item($id,$rows[2]['id']),$saved);same($db->getAll('SELECT * FROM my_una_translator_settings'),$settings);same($db->getAll('SELECT * FROM sys_localization_strings'),$polyglot);same(count($requests()),1);
});
check('a later explicit batch retries the same unprocessed fragments and stages results without applying Polyglot',function(){
    [$db,$store,$engine,$id,$rows,$requests]=fixture();$engine->dispatch('batch',['job_id'=>$id]);$result=$engine->dispatch('batch',['job_id'=>$id]);
    $calls=$requests();same(count($calls),2);same($calls[0],$calls[1]);same($result['more'],false);same($result['job']['status'],'ready');same((int)$result['job']['spent'],25);
    same($store->item($id,$rows[0]['id'])['result'],'Translated Welcome {0}');same($store->item($id,$rows[1]['id'])['result'],'Translated Another {1}');
    same($store->item($id,$rows[2]['id'])['result'],'Saved {2}');same((int)$store->item($id,$rows[2]['id'])['manually_edited'],1);same($store->valueNow(1,1),'Welcome {0}');
});
check('a later-chunk 429 retains the reservation and pauses without automatically submitting accepted fragments again',function(){
    [$db,$store,,$id,$rows]=fixture(429,implode(' ',array_fill(0,21,'<b>Word</b>')));
    $store->changeItem($rows[1]['id'],['state'=>'staged','result'=>'Saved sibling {1}','original_result'=>'Saved sibling {1}']);
    $saved=$store->item($id,$rows[2]['id']);$polyglot=$db->getAll('SELECT * FROM sys_localization_strings');$posts=[];$acceptedCharacters=0;
    $provider=new MyUnaTranslatorLibreProvider('http://127.0.0.1:5001','',function($method,$url,$headers,$body)use(&$posts,&$acceptedCharacters){
        if($method==='GET')return ['status'=>200,'body'=>'[{"code":"en","name":"English","targets":["ar"]},{"code":"ar","name":"Arabic"}]'];
        $fragments=json_decode($body,true,512,JSON_THROW_ON_ERROR)['q'];$posts[]=count($fragments);
        if(count($posts)===2)return ['status'=>429,'headers'=>['Retry-After'=>'1'],'body'=>'{}'];
        $acceptedCharacters+=array_sum(array_map(fn($text)=>mb_strlen($text,'UTF-8'),$fragments));
        return ['status'=>200,'body'=>json_encode(['translatedText'=>array_map(fn($text)=>'Translated '.$text,$fragments)])];
    },false);
    $engine=new MyUnaTranslatorEngine($store,fn()=>$provider,fn()=>true);$result=$engine->dispatch('batch',['job_id'=>$id]);
    same($posts,[20,1]);same($acceptedCharacters,80);same($result['more'],false);same(isset($result['retry_after']),false);same($result['job']['status'],'paused');
    // 11 existing characters + 505 XML reservation for 21 Word fragments.
    same((int)$result['job']['spent'],516);same($result['job']['counts']['processing'],1);same($result['job']['counts']['pending'],0);
    if(!str_contains($result['job']['error'],'may process the same characters again'))throw new RuntimeException('Missing partial-work retry warning');
    $again=$engine->dispatch('batch',['job_id'=>$id]);same($again['more'],false);same($posts,[20,1]);same((int)$again['job']['spent'],516);
    same($store->item($id,$rows[2]['id']),$saved);same($db->getAll('SELECT * FROM sys_localization_strings'),$polyglot);
});
check('non-429 provider rejection remains paused and never schedules an automatic retry',function(){
    [$db,$store,$engine,$id]=fixture(403);$result=$engine->dispatch('batch',['job_id'=>$id]);
    same($result['more'],false);same($result['job']['status'],'paused');same(isset($result['retry_after']),false);
});
check('Retry-After values retain the reviewed bounds and case-insensitive header matching',function(){
    foreach([['17',17],['9999',300],['0',1],['invalid',60],[null,60]] as [$header,$seconds]) {
        $provider=new MyUnaTranslatorLibreProvider('https://example.invalid','',fn()=>['status'=>429,'headers'=>$header===null?[]:['retry-after'=>$header],'body'=>'{}']);
        try{$provider->languages();throw new RuntimeException('Expected a rate-limit exception');}
        catch(MyUnaTranslatorRateLimitException $error){same($error->retryAfter(),$seconds);}
    }
});
check('leading percentage and parenthesis boundaries never enter model prose and are restored byte-for-byte',function(){
    foreach([['{0}% remaining','{0}% translated'],['{0}) remaining','{0}) translated'],['(% remaining','(% translated']] as [$source,$expected]) {
        $provider=new MyUnaTranslatorLibreProvider('https://example.invalid','',function($method,$url,$headers,$body){
            $data=json_decode($body,true,512,JSON_THROW_ON_ERROR);same($data['q'],['remaining']);
            return ['status'=>200,'body'=>'{"translatedText":["translated"]}'];
        },false);
        same($provider->translate([$source],'FR')[0]['text'],$expected);
    }
});
check('native cURL captures Retry-After and keeps a single service-route prefix',function(){
    $server= <<<'PHP'
$socket=stream_socket_server('tcp://127.0.0.1:0',$errno,$error);
if(!$socket)exit(2);
echo stream_socket_get_name($socket,false)."\n";flush();
$client=stream_socket_accept($socket,10);if(!$client)exit(3);
$line=trim(fgets($client));while(($header=fgets($client))!==false&&trim($header)!==''){}
echo json_encode($line)."\n";flush();
$body='{}';fwrite($client,"HTTP/1.1 429 Too Many Requests\r\nRetry-After: 17\r\nContent-Length: 2\r\nConnection: close\r\n\r\n".$body);
fclose($client);fclose($socket);
PHP;
    $process=proc_open([PHP_BINARY,'-r',$server],[0=>['pipe','r'],1=>['pipe','w'],2=>['pipe','w']],$pipes);
    if(!is_resource($process))throw new RuntimeException('Could not start loopback HTTP fixture');
    try {
        $address=trim(fgets($pipes[1]));if(!preg_match('/^127\.0\.0\.1:\d+$/D',$address))throw new RuntimeException('Loopback fixture did not start');
        $provider=new MyUnaTranslatorLibreProvider('http://'.$address.'/translator-api');
        try{$provider->languages();throw new RuntimeException('Expected native HTTP429 rejection');}
        catch(MyUnaTranslatorRateLimitException $error){same($error->retryAfter(),17);}
        same(json_decode(trim(fgets($pipes[1])),true,512,JSON_THROW_ON_ERROR),'GET /translator-api/languages HTTP/1.1');
    } finally {
        foreach($pipes as $pipe)fclose($pipe);
        proc_close($process);
    }
});
echo 'failures='.$failed.PHP_EOL;exit($failed?1:0);
