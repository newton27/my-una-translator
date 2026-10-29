<?php
define('BX_DOL', true);
require dirname(__DIR__).'/classes/MyUnaTranslatorText.php';
require dirname(__DIR__).'/classes/MyUnaTranslatorLibreProvider.php';
$failed=0;
function verify($name,callable $run) {
    global $failed;
    try { if (!$run()) throw new RuntimeException('unexpected result'); echo "PASS $name\n"; }
    catch(Throwable $e) { ++$failed; echo "FAIL $name: ".$e->getMessage()."\n"; }
}
$transport=fn()=>['status'=>200,'body'=>json_encode(['translatedText'=>['預設 {aptcha}']])];
verify('Traditional Chinese Default Captcha uses reviewed wording',function()use($transport) {
    $p=new MyUnaTranslatorLibreProvider('http://127.0.0.1:5001','',$transport);
    $row=$p->translate(['Default Captcha'],'ZH-HANT')[0];
    return $row['text']==='預設驗證碼' && $row['origin']==='terminology';
});
verify('raw model invented placeholders are still rejected',function()use($transport) {
    $p=new MyUnaTranslatorLibreProvider('http://127.0.0.1:5001','',$transport,false);
    try { $p->translate(['Default Captcha'],'ZH-HANT'); }
    catch(RuntimeException $e) { return $e->getMessage()==='Translation introduced or omitted a placeholder, link, or markup.'; }
    return false;
});
verify('reviewed wording preserves surrounding markup and UNA placeholder',function() {
    $p=new MyUnaTranslatorLibreProvider('http://127.0.0.1:5001','',fn()=>throw new RuntimeException('Unexpected provider request'));
    $row=$p->translate(['<strong>Default Captcha</strong> {0}'],'ZH-HANT')[0];
    return $row['text']==='<strong>預設驗證碼</strong> {0}';
});
verify('UNA label with Cyrillic C uses reviewed wording',function() {
    $p=new MyUnaTranslatorLibreProvider('http://127.0.0.1:5001','',fn()=>throw new RuntimeException('Unexpected provider request'));
    $source=hex2bin('44656661756c7420d0a1617074636861');
    $row=$p->translate([$source],'ZH-HANT')[0];
    return $row['text']==='預設驗證碼' && $row['origin']==='terminology';
});
exit($failed?1:0);
