<?php defined('BX_DOL') or die('hack attempt');
require_once __DIR__.'/MyUnaTranslatorStore.php';
require_once __DIR__.'/MyUnaTranslatorEditing.php';

/** Native cache and compilation integration shared by Studio and lifecycle recovery. */
final class MyUnaTranslatorEditingRuntime
{
    public static function create($database=null)
    {
        $database=$database ?: BxDolDb::getInstance();
        bx_import('BxDolStudioLanguagesUtils');
        return new MyUnaTranslatorEditing(new MyUnaTranslatorStore($database),
            function($code) { return BxDolStudioLanguagesUtils::getInstance()->compileLanguage($code,true); },
            function() use($database) {
                // Use the same native database-cache invalidation as a language
                // enable/disable, including cached Enabled-dependent lookups.
                if ($database->getDbCacheObject()->removeAllByPrefix('db_')===false || $database->cleanMemoryAll()===false)
                    throw new RuntimeException('The language availability cache could not be refreshed. Retry restoration.');
            });
    }
}
