<?php defined('BX_DOL') or die('hack attempt');
class MyUnaTranslatorInstaller extends BxDolStudioInstaller
{
    public function __construct($aConfig) { parent::__construct($aConfig); }

    protected function acquireEditingLifecycleLock()
    {
        $db=BxDolDb::getInstance();
        if (!$db->getOne("SHOW TABLES LIKE 'my_una_translator_editing_sessions'")) return null;
        require_once dirname(__DIR__).'/classes/MyUnaTranslatorEditingRuntime.php';
        $store=new MyUnaTranslatorStore($db);
        $store->lock();
        return $store;
    }

    protected function restoreEditingSession()
    {
        $db=BxDolDb::getInstance();
        if (!$db->getOne("SHOW TABLES LIKE 'my_una_translator_editing_sessions'")) return;
        require_once dirname(__DIR__).'/classes/MyUnaTranslatorEditingRuntime.php';
        $editing=MyUnaTranslatorEditingRuntime::create($db);
        if ($active=$editing->getActiveState()) {
            $editing->finish($active['id']);
            if ($editing->getActiveState())
                throw new RuntimeException('English editing has restoration conflicts. Resolve them in My UNA Translator before disabling or uninstalling the module.');
        }
    }

    public function disable($aParams)
    {
        $lock=null;
        try { $lock=$this->acquireEditingLifecycleLock(); $this->restoreEditingSession(); return parent::disable($aParams); }
        catch (Throwable $error) { return ['code'=>BX_DOL_STUDIO_INSTALLER_FAILED,'message'=>$error->getMessage(),'result'=>false]; }
        finally { if ($lock) $lock->unlock(); }
    }

    public function uninstall($aParams,$bAutoDisable=false)
    {
        $lock=null;
        try { $lock=$this->acquireEditingLifecycleLock(); $this->restoreEditingSession(); return parent::uninstall($aParams,$bAutoDisable); }
        catch (Throwable $error) { return ['code'=>BX_DOL_STUDIO_INSTALLER_FAILED,'message'=>$error->getMessage(),'result'=>false]; }
        finally { if ($lock) $lock->unlock(); }
    }
}
