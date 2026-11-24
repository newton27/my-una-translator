<?php
/** Preservation/failure tests; SQLite is a fixture, not native-site acceptance. */
define('BX_DOL',true);
require __DIR__.'/preview_test_db.php';
require dirname(__DIR__).'/classes/MyUnaTranslatorStore.php';
require dirname(__DIR__).'/classes/MyUnaTranslatorEditing.php';

function fixtureEditing($compile=null):array {
    $db=new PreviewTestDb();
    $db->pdo->exec("CREATE TABLE sys_pages_blocks(title TEXT,description TEXT,content_empty TEXT,help TEXT,content TEXT,type TEXT);
        CREATE TABLE sys_objects_page(title TEXT,title_system TEXT,cover_title TEXT);
        INSERT INTO sys_pages_blocks VALUES('_welcome','','','','','service');
        CREATE TABLE my_una_translator_editing_sessions(id INTEGER PRIMARY KEY AUTOINCREMENT,created_at INTEGER,finished_at INTEGER DEFAULT 0,operator_id INTEGER,default_language TEXT,status TEXT DEFAULT 'active',languages TEXT,changed_keys INTEGER DEFAULT 0,restored_languages INTEGER DEFAULT 0,restore_complete INTEGER DEFAULT 0,conflicts INTEGER DEFAULT 0,job_id INTEGER DEFAULT 0,unsupported_languages TEXT,compile_pending TEXT,error TEXT);
        CREATE TABLE my_una_translator_editing_keys(session_id INTEGER,key_id INTEGER,key_name TEXT,source_before TEXT,at_start INTEGER,source_after TEXT,changed INTEGER DEFAULT 0,PRIMARY KEY(session_id,key_id));
        CREATE TABLE my_una_translator_editing_values(session_id INTEGER,key_id INTEGER,language_id INTEGER,language TEXT,original_value TEXT,restored INTEGER DEFAULT 0,conflict TEXT DEFAULT '',PRIMARY KEY(session_id,key_id,language_id));
        CREATE TABLE my_una_translator_editing_expected(session_id INTEGER,key_id INTEGER,language_id INTEGER,value_hash TEXT,PRIMARY KEY(session_id,key_id,language_id,value_hash));
        INSERT INTO sys_localization_languages VALUES(2,'he','il','Hebrew','RTL','he_IL',1),(3,'sv','se','Swedish','LTR','sv_SE',1),(4,'fr','fr','French','LTR','fr_FR',0);
        INSERT INTO sys_localization_strings VALUES(1,2,'שלום {0}'),(1,3,'rå'),(1,4,'Bienvenue {0}');");
    $store=new MyUnaTranslatorStore($db);
    return [$db,$store,new MyUnaTranslatorEditing($store,$compile?:fn()=>true,fn()=>true)];
}
function sameEditing(PreviewTestDb $db,string $table):array {return $db->pdo->query('SELECT * FROM '.$table.' ORDER BY rowid')->fetchAll(PDO::FETCH_ASSOC);}
function okEditing(bool $ok,string $message):void {if(!$ok)throw new RuntimeException($message);echo "PASS $message\n";}
function rejectsEditing(callable $f,string $message):void {try{$f();}catch(RuntimeException $e){okEditing(true,$message);return;}throw new RuntimeException($message);}

[$db,$store,$edit]=fixtureEditing();
$languages=sameEditing($db,'sys_localization_languages');$strings=sameEditing($db,'sys_localization_strings');
rejectsEditing(fn()=>$edit->start(9,'he'),'non-English default refuses editing without pausing languages');
okEditing(sameEditing($db,'sys_localization_languages')===$languages,'rejected start preserves complete language rows');
$state=$edit->start(9)['active'];$id=$state['id'];
okEditing($state['paused_count']===2 && (int)$store->one("SELECT Enabled FROM sys_localization_languages WHERE Name='en'")===1,'start pauses all previously enabled non-English languages and retains English');
okEditing((int)$store->one("SELECT SUM(Enabled) FROM sys_localization_languages WHERE Name<>'en'")===0,'originally disabled language remains disabled');
okEditing(sameEditing($db,'sys_localization_strings')===$strings,'start preserves every existing string including two-character Unicode');
rejectsEditing(fn()=>$edit->start(9),'overlapping editing sessions are refused');
$done=$edit->finish($id);
okEditing($done['active']===null && $done['recent']['changed_keys']===0,'unchanged English finishes with no changed keys');
okEditing(sameEditing($db,'sys_localization_languages')===$languages && sameEditing($db,'sys_localization_strings')===$strings,'finish restores exact original language flags and text');
okEditing($edit->finish($id)===$done,'repeated finish is idempotent');
okEditing((int)$store->one('SELECT COUNT(*) FROM my_una_translator_jobs')===0,'editing cycle creates no translation job or provider work');

[$db,$store,$edit]=fixtureEditing();$strings=sameEditing($db,'sys_localization_strings');$id=$edit->start(9)['active']['id'];
$edit->captureExpected($id,[['key_name'=>'_welcome','language'=>'sv','value'=>'English placeholder']]);
$store->q("UPDATE sys_localization_strings SET String=? WHERE IDKey=1 AND IDLanguage=3",'English placeholder');
$store->q("UPDATE sys_localization_strings SET String=? WHERE IDKey=1 AND IDLanguage=1",'Updated welcome {0}');
$done=$edit->finish($id);
okEditing($store->valueNow(1,3)==='rå','recorded native placeholder write restores the original short translation');
okEditing($done['recent']['changed_keys']===1 && count($edit->affectedSources($id))===1,'plan scope contains exactly the changed English key');
$edit->dismiss($id);okEditing($edit->publicState()['recent']['status']==='dismissed','skip keeps English and archived translations without a provider plan');
rejectsEditing(fn()=>$edit->captureExpected($id,[['key_name'=>'_welcome','language'=>'he','value'=>'stale']]),'stale native editor cannot save after finish');

[$db,$store,$edit]=fixtureEditing();$id=$edit->start(9)['active']['id'];
$store->q("UPDATE sys_localization_strings SET String='External edit' WHERE IDKey=1 AND IDLanguage=2");
$done=$edit->finish($id);
okEditing($done['active']['status']==='conflict' && $store->valueNow(1,2)==='External edit','unrecorded external translation edit survives and retains a restoration conflict');

$attempt=0;[$db,$store,$edit]=fixtureEditing(function()use(&$attempt){return ++$attempt>1;});$languages=sameEditing($db,'sys_localization_languages');$id=$edit->start(9)['active']['id'];
rejectsEditing(fn()=>$edit->finish($id),'language compilation failure remains retryable');
okEditing(sameEditing($db,'sys_localization_languages')===$languages && $edit->getActiveState()['status']==='restoring','compilation failure preserves restored flags and an unfinished session');
okEditing($edit->finish($id)['active']===null,'retry completes restoration after compilation recovers');
