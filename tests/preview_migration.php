<?php
define('BX_DOL',true);
define('BX_DOL_STUDIO_INSTALLER_SUCCESS',true);
require __DIR__.'/preview_test_db.php';
class MigrationDb {
    public $pdo;
    public function __construct($pdo) { $this->pdo=$pdo; }
    public function getRow($query) {
        foreach($this->pdo->query('PRAGMA table_info(my_una_translator_items)')->fetchAll(PDO::FETCH_ASSOC) as $column)
            if($column['name']==='manually_edited')return $column;
        return [];
    }
}
class BxDolStudioUpdater {
    protected $oDb;
    public function __construct($config){$this->oDb=$config['db'];}
    protected function actionExecuteSql($operation){
        $sql=file_get_contents(dirname(__DIR__).'/updates/update_0.1.10_0.1.11/install/sql/install.sql');
        $this->oDb->pdo->exec($sql);return true;
    }
}
require dirname(__DIR__).'/updates/update_0.1.10_0.1.11/install/installer.php';
$db=new PreviewTestDb();$db->pdo->exec('ALTER TABLE my_una_translator_items DROP COLUMN manually_edited');
$db->pdo->exec("INSERT INTO my_una_translator_items (job_id,key_id,key_name,language,source,before_value,result,state) VALUES (7,1,'_welcome','fi','Welcome {0}','Before','After','staged')");
$before=$db->pdo->query('SELECT * FROM my_una_translator_items')->fetch(PDO::FETCH_ASSOC);
$updater=new MyUnaTranslatorUpdater(['db'=>new MigrationDb($db->pdo)]);
$method=new ReflectionMethod($updater,'actionExecuteSql');
foreach([1,2] as $attempt){
    if($method->invoke($updater,'install')!==true)throw new RuntimeException('Migration failed');
    $after=$db->pdo->query('SELECT * FROM my_una_translator_items')->fetch(PDO::FETCH_ASSOC);
    if((int)$after['manually_edited']!==0)throw new RuntimeException('Default flag missing');
    unset($after['manually_edited']);
    if($before!==$after)throw new RuntimeException('Saved job changed');
    echo "PASS migration attempt $attempt preserves existing saved preview\n";
}

