<?php
final class PreviewTestDb {
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
            CREATE TABLE my_una_translator_items (id INTEGER PRIMARY KEY AUTOINCREMENT,job_id INTEGER,key_id INTEGER,key_name TEXT,language TEXT,source TEXT,before_value TEXT,result TEXT,state TEXT DEFAULT 'pending',error TEXT DEFAULT '',manually_edited INTEGER DEFAULT 0);
            INSERT INTO sys_localization_languages VALUES (1,'en','gb','English','LTR','en_US',1);
            INSERT INTO sys_localization_categories VALUES (1,'Interface');
            INSERT INTO sys_localization_keys VALUES (1,'_welcome',1);
            INSERT INTO sys_localization_strings VALUES (1,1,'Welcome {0}');
            INSERT INTO my_una_translator_settings VALUES (1,'libre','http://127.0.0.1:5001','','','free',20,1000000);");
    }
    public function prepare($sql,...$args) {
        if (str_starts_with($sql,'UPDATE sys_localization_languages l JOIN my_una_translator_languages m') && str_contains($sql,'SET l.Enabled=0'))
            $sql="UPDATE sys_localization_languages SET Enabled=0 WHERE ID IN (SELECT language_id FROM my_una_translator_languages WHERE job_id=?) AND NOT EXISTS (SELECT 1 FROM sys_localization_strings WHERE IDLanguage=sys_localization_languages.ID)";
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

