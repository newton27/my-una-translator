<?php
/** Exercise 0.1.11 review migration with saved manual previews and replay. */
define('BX_DOL',true);define('BX_DOL_STUDIO_INSTALLER_SUCCESS',0);define('BX_DOL_STUDIO_INSTALLER_FAILED',1);
require __DIR__.'/preview_test_db.php';
class ReviewMigrationDb {
    public PDO $pdo;
    public bool $fail=false;
    public function __construct($pdo){$this->pdo=$pdo;}
    public function getRow($sql){foreach($this->pdo->query('PRAGMA table_info(my_una_translator_items)')->fetchAll(PDO::FETCH_ASSOC) as $c)if($c['name']==='original_result')return $c;return [];}
    public function executeSQL($path,$markers){if($this->fail)return 'Controlled migration failure';$this->pdo->exec(file_get_contents($path));return true;}
}
class BxDolStudioUpdater {
    protected $oDb;protected $_sHomePath;
    public function __construct($cfg){$this->oDb=$cfg['db'];$this->_sHomePath=dirname(__DIR__).'/updates/update_0.1.11_0.1.12/';}
    protected function getMarkersForDb(){return [];}
    protected function actionExecuteSql($op){return ['code'=>0];}
}
require dirname(__DIR__).'/updates/update_0.1.11_0.1.12/install/installer.php';
$fixture=new PreviewTestDb();$db=new ReviewMigrationDb($fixture->pdo);
$db->pdo->exec('ALTER TABLE my_una_translator_items DROP COLUMN original_result');
$db->pdo->exec("INSERT INTO my_una_translator_items(job_id,key_id,key_name,language,source,before_value,result,state,manually_edited) VALUES(7,1,'_welcome','fr','Welcome {0}','Baseline','Reviewed {0}','staged',1)");
$before=$db->pdo->query('SELECT * FROM my_una_translator_items')->fetchAll(PDO::FETCH_ASSOC);
$up=new MyUnaTranslatorUpdater(['db'=>$db]);$run=new ReflectionMethod($up,'actionExecuteSql');
for($i=0;$i<2;++$i){
 if($run->invoke($up,'install')['code']!==0)throw new RuntimeException('Migration failed');
 $rows=$db->pdo->query('SELECT * FROM my_una_translator_items')->fetchAll(PDO::FETCH_ASSOC);
 foreach($rows as &$row){if(!array_key_exists('original_result',$row)||$row['original_result']!==null)throw new RuntimeException('Missing or fabricated machine baseline');unset($row['original_result']);}unset($row);
 if($rows!==$before)throw new RuntimeException('Manual preview or destination baseline changed');
 echo "PASS 0.1.11 review migration/replay preserves manual previews and destination baseline\n";
}
$db->pdo->exec('ALTER TABLE my_una_translator_items DROP COLUMN original_result');$db->fail=true;
if($run->invoke($up,'install')['code']!==1)throw new RuntimeException('SQL failure was hidden');
echo "PASS review migration SQL failure retains native rejection\n";
