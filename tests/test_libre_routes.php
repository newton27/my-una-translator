<?php
define('BX_DOL',true);
$root=getenv('TEST_MODULE') ?: dirname(__DIR__);
require_once $root.'/classes/MyUnaTranslatorText.php';
require_once $root.'/classes/MyUnaTranslatorLibreProvider.php';
$pass=0;$fail=0;
function runCheck($name,$fn){global $pass,$fail;try{$fn();++$pass;echo 'PASS '.$name.PHP_EOL;}catch(Throwable $e){++$fail;echo 'FAIL '.$name.': '.$e->getMessage().PHP_EOL;}}
function equal($got,$want){if($got!==$want)throw new RuntimeException('Expected '.json_encode($want).' got '.json_encode($got));}
foreach(['https://libre.example/translator-api','https://libre.example/translator-api/'] as $base){
runCheck('GET languages uses the service route '.$base,function()use($base){
$p=new MyUnaTranslatorLibreProvider($base,'',function($method,$url,$headers,$body){
equal($method,'GET');equal($url,'https://libre.example/translator-api/languages');equal($body,null);
return ['status'=>200,'headers'=>[],'body'=>'[{"code":"en","name":"English","targets":["ar"]}]'];});
equal($p->languages()[0]['code'],'en');});}
runCheck('POST translation uses the service route',function(){
$p=new MyUnaTranslatorLibreProvider('https://libre.example/translator-api','test-only-key',function($method,$url,$headers,$body){
equal($method,'POST');equal($url,'https://libre.example/translator-api/translate');
$data=json_decode($body,true,512,JSON_THROW_ON_ERROR);equal($data['source'],'en');equal($data['target'],'ar');equal($data['q'],['An uncommon test phrase']);
return ['status'=>200,'headers'=>[],'body'=>json_encode(['translatedText'=>['عبارة اختبار']])];},false);
$r=$p->translate(['An uncommon test phrase'],'ar');equal($r[0]['text'],'عبارة اختبار');});
runCheck('429 remains a resumable rate-limit exception',function(){
$p=new MyUnaTranslatorLibreProvider('https://libre.example/translator-api','',function(){return ['status'=>429,'headers'=>['Retry-After'=>'17'],'body'=>'{}'];});
try{$p->languages();throw new RuntimeException('429 was accepted');}catch(MyUnaTranslatorRateLimitException $e){equal($e->retryAfter(),17);}
});
echo 'RESULT PASS='.$pass.' FAIL='.$fail.PHP_EOL;exit($fail?1:0);
