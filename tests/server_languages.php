<?php
define('BX_DOL', true);
foreach (['Text','LibreProvider','Store','Engine'] as $part) require dirname(__DIR__).'/classes/MyUnaTranslator'.$part.'.php';
$failed=0;
function check($name, callable $run) {
    global $failed;
    try { if (!$run()) throw new RuntimeException('unexpected result'); echo "PASS $name\n"; }
    catch (Throwable $e) { ++$failed; echo "FAIL $name: ".$e->getMessage()."\n"; }
}
$inventory=json_decode(file_get_contents(__DIR__.'/fixtures/libre-languages.json'),true,512,JSON_THROW_ON_ERROR);
check('server inventory offers 49 destinations including Arabic', function() use($inventory) {
    $p=MyUnaTranslatorEngine::presets($inventory);
    return count($p)===49 && isset($p['ar']) && !isset($p['en']) && $p['ar']['direction']==='RTL';
});
check('existing Portuguese and Chinese job codes remain compatible', function() use($inventory) {
    $p=MyUnaTranslatorEngine::presets($inventory);
    return $p['pt-br']['target']==='PT-BR' && $p['zh-cn']['target']==='ZH-HANS' && isset($p['zh-tw']);
});
check('only English-reachable installed routes are selectable', function() {
    $p=MyUnaTranslatorEngine::presets([['code'=>'en','name'=>'English','targets'=>['en','ar']],['code'=>'ar','name'=>'Arabic'],['code'=>'fr','name'=>'French']]);
    return array_keys($p)===['ar'];
});
check('Arabic translation uses the ar route and preserves protected content', function() {
    $provider=new MyUnaTranslatorLibreProvider('http://127.0.0.1:5001','',function($method,$url,$headers,$body) {
        $data=json_decode($body,true,512,JSON_THROW_ON_ERROR);
        if ($method!=='POST' || $data['target']!=='ar' || $data['source']!=='en') throw new RuntimeException('wrong translation route');
        return ['status'=>200,'body'=>json_encode(['translatedText'=>array_map(fn($text)=>strtr($text,['Your code is'=>'رمزك هو','Visit'=>'زيارة']),$data['q'])])];
    });
    $row=$provider->translate(['Your code is {0}. Visit UNA.IO'],'AR')[0];
    return $row['text']==='رمزك هو {0}. زيارة UNA.IO';
});
check('Arabic-only server is usable without the five legacy models', function() {
    $provider=new MyUnaTranslatorLibreProvider('http://127.0.0.1:5001','',fn()=>['status'=>200,'body'=>json_encode([['code'=>'en','name'=>'English','targets'=>['ar']],['code'=>'ar','name'=>'Arabic']])]);
    return $provider->usage()['available']===true;
});

// SQLite executes Store SQL; advisory locks and MySQL syntax are adapted only here.
final class LanguageTestDb {
    public PDO $pdo;
    public function __construct() {
        $this->pdo=new PDO('sqlite::memory:'); $this->pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);
        $this->pdo->sqliteCreateFunction('GET_LOCK',fn()=>1); $this->pdo->sqliteCreateFunction('RELEASE_LOCK',fn()=>1); $this->pdo->sqliteCreateFunction('DATABASE',fn()=>'language-test');
        $this->pdo->exec("CREATE TABLE sys_localization_languages (ID INTEGER PRIMARY KEY AUTOINCREMENT,Name TEXT UNIQUE,Flag TEXT,Title TEXT,Direction TEXT,LanguageCountry TEXT,Enabled INTEGER DEFAULT 0);
            CREATE TABLE sys_localization_categories (ID INTEGER PRIMARY KEY,Name TEXT);
            CREATE TABLE sys_localization_keys (ID INTEGER PRIMARY KEY,`Key` TEXT,IDCategory INTEGER);
            CREATE TABLE sys_localization_strings (IDKey INTEGER,IDLanguage INTEGER,String TEXT);
            CREATE TABLE my_una_translator_languages (language TEXT,language_id INTEGER,job_id INTEGER);
            CREATE TABLE my_una_translator_settings (id INTEGER,provider TEXT,libre_url TEXT,libre_key TEXT,api_key TEXT,plan TEXT,batch_size INTEGER,character_budget INTEGER);
            CREATE TABLE my_una_translator_jobs (id INTEGER PRIMARY KEY AUTOINCREMENT,created_at INTEGER,operator_id INTEGER,targets TEXT,category_id INTEGER,include_english INTEGER,budget INTEGER,status TEXT DEFAULT 'planned',total INTEGER DEFAULT 0,estimated INTEGER DEFAULT 0,spent INTEGER DEFAULT 0,error TEXT DEFAULT '',enable_languages INTEGER DEFAULT 0);
            CREATE TABLE my_una_translator_items (id INTEGER PRIMARY KEY AUTOINCREMENT,job_id INTEGER,key_id INTEGER,key_name TEXT,language TEXT,source TEXT,before_value TEXT,result TEXT,state TEXT DEFAULT 'pending',error TEXT DEFAULT '');
            INSERT INTO sys_localization_languages VALUES (1,'en','gb','English','LTR','en_US',1);
            INSERT INTO sys_localization_categories VALUES (1,'Interface');
            INSERT INTO sys_localization_keys VALUES (1,'_welcome',1);
            INSERT INTO sys_localization_strings VALUES (1,1,'Welcome {0}');
            INSERT INTO my_una_translator_settings VALUES (1,'libre','http://127.0.0.1:5001','','','free',20,1000000);");
    }
    public function prepare($sql,...$args) {
        $parts=explode('?',$sql); $out=array_shift($parts);
        foreach($parts as $part) { $arg=array_shift($args); $out.=($arg===null?'NULL':$this->pdo->quote((string)$arg)).$part; }
        return str_replace(['START TRANSACTION','BINARY '],['BEGIN',''],$out);
    }
    public function query($sql) { return $this->pdo->exec($sql); }
    public function getRow($sql) { return $this->pdo->query($sql)->fetch(PDO::FETCH_ASSOC); }
    public function getAll($sql) { return $this->pdo->query($sql)->fetchAll(PDO::FETCH_ASSOC); }
    public function getOne($sql) { return $this->pdo->query($sql)->fetchColumn(); }
    public function lastId() { return $this->pdo->lastInsertId(); }
}
check('native Arabic language records use RTL layout', function() {
    $db=new LanguageTestDb(); $store=new MyUnaTranslatorStore($db);
    $id=$store->ensureLanguage('ar',['flag'=>'sa','native'=>'العربية','country'=>'ar_SA','direction'=>'RTL'],1);
    return $db->getRow('SELECT * FROM sys_localization_languages WHERE ID='.(int)$id)['Direction']==='RTL';
});
check('engine discovers server languages and accepts an Arabic job', function() use($inventory) {
    $db=new LanguageTestDb(); $store=new MyUnaTranslatorStore($db);
    $engine=new MyUnaTranslatorEngine($store,fn()=>new MyUnaTranslatorLibreProvider('http://127.0.0.1:5001','',fn()=>['status'=>200,'body'=>json_encode($inventory)]),fn()=>true);
    $s=$engine->snapshot(); if(count($s['languages'])!==49) return false;
    $job=$engine->dispatch('create',['targets'=>['ar'],'category_id'=>1,'character_budget'=>10000,'operator_id'=>1])['job'];
    return $job['targets']===['ar'] && (int)$job['total']===1 && $db->getRow('SELECT * FROM my_una_translator_items')['language']==='ar';
});
check('server outage leaves Studio status available and no targets selectable', function() {
    $db=new LanguageTestDb(); $engine=new MyUnaTranslatorEngine(new MyUnaTranslatorStore($db),fn()=>new MyUnaTranslatorLibreProvider('http://127.0.0.1:5001','',fn()=>['status'=>503,'body'=>'']),fn()=>true);
    $s=$engine->snapshot(); return $s['languages']===[] && !empty($s['language_error']);
});
check('DeepL retains its existing five destinations without probing Libre', function() {
    $db=new LanguageTestDb(); $db->pdo->exec("UPDATE my_una_translator_settings SET provider='deepl',api_key='stored'");
    $engine=new MyUnaTranslatorEngine(new MyUnaTranslatorStore($db),fn()=>throw new RuntimeException('Unexpected network discovery'),fn()=>true);
    return count($engine->snapshot()['languages'])===5;
});
class BxTemplStudioModule {}
class BxDolSession {
    public static function getInstance() { return new self(); }
    public function getValue($name) { return str_repeat('a',64); }
}
function isAdmin() { return true; }
function _t($key) { return $key; }
define('BX_DOL_URL_ROOT','https://example.test/');
define('BX_DOL_URL_STUDIO','https://example.test/studio/');
require dirname(__DIR__).'/classes/MyUnaTranslatorStudioPage.php';
check('Studio renders all server targets and permits choosing Arabic alone', function() use($inventory) {
    $db=new LanguageTestDb(); $engine=new MyUnaTranslatorEngine(new MyUnaTranslatorStore($db),fn()=>new MyUnaTranslatorLibreProvider('http://127.0.0.1:5001','',fn()=>['status'=>200,'body'=>json_encode($inventory)]),fn()=>true);
    $page=(new ReflectionClass(MyUnaTranslatorStudioPage::class))->newInstanceWithoutConstructor();
    (new ReflectionProperty($page,'translator'))->setValue($page,$engine);
    $html=(new ReflectionMethod($page,'getTranslator'))->invoke($page);
    $doc=new DOMDocument(); @$doc->loadHTML('<?xml encoding="UTF-8">'.$html); $x=new DOMXPath($doc);
    return $x->query('//input[@name="targets[]"]')->length===49 && $x->query('//input[@name="targets[]" and @value="ar"]')->length===1 && $x->query('//input[@name="targets[]" and @checked]')->length===0 && $x->query('//input[@id="mut-language-search"]')->length===1;
});
echo "failures=$failed\n"; exit($failed?1:0);
