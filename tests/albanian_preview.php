<?php
define('BX_DOL', true);
require dirname(__DIR__).'/classes/MyUnaTranslatorText.php';
require dirname(__DIR__).'/classes/MyUnaTranslatorLibreProvider.php';

$failed=0;
function albanianCheck($name, callable $run) {
    global $failed;
    try { $run(); echo "PASS $name\n"; }
    catch (Throwable $e) { ++$failed; echo "FAIL $name: ".$e->getMessage()."\n"; }
}
function albanianEqual($actual, $expected) {
    if ($actual!==$expected) throw new RuntimeException('Unexpected translation: '.json_encode($actual,JSON_UNESCAPED_UNICODE));
}

// Recorded from job #112: the model invents a format argument for plain text.
$observed=[
    'Cannot add list'=>'I pamundur shtimi i listës',
    'Cannot update list'=>'I pamundur krijimi i socket: %s',
    'Enter title, more than 3 characters'=>'Shkruaj titullin, më shumë se 3 shkronja',
];
$transport=static function ($method,$url,$headers,$body) use ($observed) {
    $data=json_decode($body,true,512,JSON_THROW_ON_ERROR);
    albanianEqual([$method,$data['source'],$data['target']],['POST','en','sq']);
    $texts=[];
    foreach ($data['q'] as $source) {
        if (!isset($observed[$source])) throw new RuntimeException('Unexpected model input.');
        $texts[]=$observed[$source];
    }
    return ['status'=>200,'body'=>json_encode(['translatedText'=>$texts])];
};

albanianCheck('stopped Albanian batch produces a complete protected preview',function() use ($observed,$transport) {
    $provider=new MyUnaTranslatorLibreProvider('http://127.0.0.1:5001','',$transport);
    $rows=$provider->translate(array_keys($observed),'SQ');
    albanianEqual(array_column($rows,'text'),[
        'I pamundur shtimi i listës',
        'Nuk mund të përditësohet lista',
        'Shkruaj titullin, më shumë se 3 shkronja',
    ]);
    albanianEqual($rows[1]['origin'],'terminology');
});

albanianCheck('reviewed Albanian wording preserves surrounding markup and placeholder',function() {
    $provider=new MyUnaTranslatorLibreProvider('http://127.0.0.1:5001','',static function() {
        throw new RuntimeException('Reviewed wording unexpectedly reached the model.');
    });
    $row=$provider->translate(['<strong>Cannot update list</strong> {0}'],'SQ')[0];
    albanianEqual($row['text'],'<strong>Nuk mund të përditësohet lista</strong> {0}');
});

albanianCheck('Albanian image-error captions preserve their trailing separators',function() {
    $provider=new MyUnaTranslatorLibreProvider('http://127.0.0.1:5001','',static function($method,$url,$headers,$body) {
        $observed=[
            'Cannot process image:'=>'I pamundur procesimi i figurës: %s',
            'Cannot remove icon image:'=>'I pamundur eliminimi i figurës së ikonës: %s',
        ];
        $data=json_decode($body,true,512,JSON_THROW_ON_ERROR);
        return ['status'=>200,'body'=>json_encode(['translatedText'=>array_map(static function($source) use ($observed) {
            if (!isset($observed[$source])) throw new RuntimeException('Unexpected image caption.');
            return $observed[$source];
        },$data['q'])])];
    });
    $rows=$provider->translate(['Cannot process image: ','Cannot remove icon image: '],'SQ');
    albanianEqual(array_column($rows,'text'),[
        'Nuk mund të përpunohet imazhi: ',
        'Nuk mund të hiqet imazhi i ikonës: ',
    ]);
    albanianEqual(array_column($rows,'origin'),['terminology','terminology']);
});

albanianCheck('raw model output with an invented format argument is still blocked',function() use ($transport) {
    $provider=new MyUnaTranslatorLibreProvider('http://127.0.0.1:5001','',$transport,false);
    try { $provider->translate(['Cannot update list'],'SQ'); }
    catch (RuntimeException $e) {
        albanianEqual($e->getMessage(),'Translation reordered protected markup or format arguments.');
        return;
    }
    throw new RuntimeException('Invented format argument was accepted.');
});

albanianCheck('Albanian correction does not change another language route',function() {
    $provider=new MyUnaTranslatorLibreProvider('http://127.0.0.1:5001','',static function($method,$url,$headers,$body) {
        $data=json_decode($body,true,512,JSON_THROW_ON_ERROR);
        albanianEqual([$data['target'],$data['q']],['fr',['Cannot update list']]);
        return ['status'=>200,'body'=>json_encode(['translatedText'=>['Impossible de mettre à jour la liste']])];
    });
    albanianEqual($provider->translate(['Cannot update list'],'FR')[0]['text'],'Impossible de mettre à jour la liste');
});

exit($failed?1:0);
