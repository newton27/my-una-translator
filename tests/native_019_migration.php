<?php
/** Execute the real direct updater against an isolated SQL fixture, not a site. */
define('BX_DOL', true);
define('BX_DOL_STUDIO_INSTALLER_SUCCESS', 0);
define('BX_DOL_STUDIO_INSTALLER_FAILED', 1);
require __DIR__ . '/preview_test_db.php';

final class Native019Database
{
    public PDO $pdo;
    public array $files = [];
    public ?string $failFile = null;

    public function __construct(PDO $pdo) { $this->pdo = $pdo; }

    public function getRow(string $query): array
    {
        if (!preg_match("/\\ASHOW COLUMNS FROM `([a-z_]+)` LIKE '([a-z_]+)'\\z/D", $query, $names))
            throw new RuntimeException('Unexpected native column query');
        foreach ($this->pdo->query('PRAGMA table_info(' . $names[1] . ')')->fetchAll(PDO::FETCH_ASSOC) as $column)
            if ($column['name'] === $names[2]) return $column;
        return [];
    }

    public function executeSQL(string $file, array $markers)
    {
        if ($markers !== ['db_prefix' => 'my_una_translator_']) throw new RuntimeException('Native markers lost');
        $this->files[] = basename($file);
        if ($this->failFile === basename($file)) return 'Controlled SQL execution failure';
        $sql = file_get_contents($file);
        // SQLite runs the actual additive statements; engine/index syntax is MySQL-only.
        $sql = str_replace([' CHARACTER SET ascii COLLATE ascii_bin', ', KEY `support_incident` (`incident_hash`,`created_at`)', ' ENGINE=InnoDB DEFAULT CHARSET=utf8mb4'], '', $sql);
        $this->pdo->exec($sql);
        return true;
    }
}

class BxDolStudioUpdater
{
    protected Native019Database $oDb;
    protected string $_sHomePath;

    public function __construct(array $config)
    {
        $this->oDb = $config['db'];
        $this->_sHomePath = dirname(__DIR__) . '/updates/update_0.1.9_0.1.12/';
    }

    protected function getMarkersForDb(): array { return ['db_prefix' => 'my_una_translator_']; }

    protected function actionExecuteSql($operation)
    {
        if ($operation !== 'install') throw new RuntimeException('Native operation lost');
        $result = $this->oDb->executeSQL($this->_sHomePath . 'install/sql/install.sql', $this->getMarkersForDb());
        return ['code' => $result === true ? BX_DOL_STUDIO_INSTALLER_SUCCESS : BX_DOL_STUDIO_INSTALLER_FAILED, 'content' => $result];
    }
}

require dirname(__DIR__) . '/updates/update_0.1.9_0.1.12/install/installer.php';

function fixture019(): Native019Database
{
    $fixture = new PreviewTestDb();
    $pdo = $fixture->pdo;
    $pdo->exec('ALTER TABLE my_una_translator_items DROP COLUMN manually_edited');
    $pdo->exec('ALTER TABLE my_una_translator_items DROP COLUMN original_result');
    $pdo->exec("UPDATE my_una_translator_settings SET api_key='synthetic-encrypted-key',libre_key='synthetic-encrypted-libre-key';
        INSERT INTO my_una_translator_jobs (id,created_at,operator_id,targets,budget,status,total,spent) VALUES (7,123,9,'[\"fr\"]',1000,'ready',1,12);
        INSERT INTO my_una_translator_items (job_id,key_id,key_name,language,source,before_value,result,state) VALUES (7,1,'_welcome','fr','Welcome {0}','Before','After','staged');
        INSERT INTO my_una_translator_languages VALUES ('fr',2,7);");
    return new Native019Database($pdo);
}

function preservedRows019(Native019Database $db): array
{
    $snapshot = [];
    foreach (['settings', 'jobs', 'items', 'languages'] as $table) {
        $rows = $db->pdo->query('SELECT * FROM my_una_translator_' . $table . ' ORDER BY rowid')->fetchAll(PDO::FETCH_ASSOC);
        if ($table === 'items') {
            foreach ($rows as &$row) unset($row['manually_edited'],$row['original_result']);
            unset($row);
        }
        $snapshot[$table] = $rows;
    }
    return $snapshot;
}

function run019(Native019Database $db): array
{
    $updater = new MyUnaTranslatorUpdater(['db' => $db]);
    return (new ReflectionMethod($updater, 'actionExecuteSql'))->invoke($updater, 'install');
}

function editColumnExists019(Native019Database $db): bool
{
    return (int) $db->pdo->query("SELECT COUNT(*) FROM pragma_table_info('my_una_translator_items') WHERE name='manually_edited'")->fetchColumn() === 1;
}

function check019(bool $ok, string $name): void
{
    if (!$ok) throw new RuntimeException($name);
    echo 'PASS ' . $name . PHP_EOL;
}

$db = fixture019();
$before = preservedRows019($db);
check019(run019($db)['code'] === BX_DOL_STUDIO_INSTALLER_SUCCESS, 'direct updater executes both additive migrations');
check019($db->files === ['preview.sql', 'review.sql', 'install.sql'] && (int) $db->pdo->query('SELECT manually_edited FROM my_una_translator_items')->fetchColumn() === 0, 'original items receive the edit flag before support schema creation');
check019((int)$db->pdo->query("SELECT COUNT(*) FROM pragma_table_info('my_una_translator_items') WHERE name='original_result'")->fetchColumn() === 1, 'review baseline column is installed on genuine 0.1.9');
check019(preservedRows019($db) === $before, 'provider ciphertext and complete saved fixture rows survive direct migration');
$db->pdo->exec("INSERT INTO my_una_translator_support_receipts VALUES ('123e4567-e89b-42d3-a456-426614174000','payload','incident',321,123)");
$receipts = $db->pdo->query('SELECT * FROM my_una_translator_support_receipts')->fetchAll(PDO::FETCH_ASSOC);
$db->files = [];
check019(run019($db)['code'] === BX_DOL_STUDIO_INSTALLER_SUCCESS && $db->files === ['install.sql'], 'replayed updater skips duplicate ALTER and still executes support migration');
check019($db->pdo->query('SELECT * FROM my_una_translator_support_receipts')->fetchAll(PDO::FETCH_ASSOC) === $receipts && preservedRows019($db) === $before, 'replayed migration preserves existing receipts and saved rows');

$db = fixture019();
$db->failFile = 'preview.sql';
$before = preservedRows019($db);
check019(run019($db)['code'] === BX_DOL_STUDIO_INSTALLER_FAILED && $db->files === ['preview.sql'] && !editColumnExists019($db) && preservedRows019($db) === $before, 'failed preview SQL returns native failure without running later migration');

$db = fixture019();
$db->failFile = 'install.sql';
$before = preservedRows019($db);
check019(run019($db)['code'] === BX_DOL_STUDIO_INSTALLER_FAILED && editColumnExists019($db) && preservedRows019($db) === $before, 'later SQL failure retains additive column and propagates native rejection');
$db->failFile = null;
$db->files = [];
check019(run019($db)['code'] === BX_DOL_STUDIO_INSTALLER_SUCCESS && $db->files === ['install.sql'] && (int) $db->pdo->query("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND name='my_una_translator_support_receipts'")->fetchColumn() === 1 && preservedRows019($db) === $before, 'retry after partial failure completes support schema without changing saved data');
