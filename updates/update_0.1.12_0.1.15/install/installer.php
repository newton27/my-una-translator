<?php defined('BX_DOL') or die('hack attempt');
class MyUnaTranslatorUpdater extends BxDolStudioUpdater
{
    public function __construct($aConfig) { parent::__construct($aConfig); }

    protected function actionExecuteSql($operation)
    {
        // MySQL commits additive DDL even when a later native action fails.
        foreach (['manually_edited'=>'preview.sql','original_result'=>'review.sql'] as $column=>$file) {
            if ($this->oDb->getRow("SHOW COLUMNS FROM `my_una_translator_items` LIKE '".$column."'")) continue;
            $result=$this->oDb->executeSQL($this->_sHomePath.'install/sql/'.$file,$this->getMarkersForDb());
            if ($result!==true) return ['code'=>BX_DOL_STUDIO_INSTALLER_FAILED,'content'=>$result];
        }
        $result=parent::actionExecuteSql($operation);
        if ($result!==BX_DOL_STUDIO_INSTALLER_SUCCESS) return $result;
        $result=$this->oDb->executeSQL($this->_sHomePath.'install/sql/editing.sql',$this->getMarkersForDb());
        return $result===true ? BX_DOL_STUDIO_INSTALLER_SUCCESS : ['code'=>BX_DOL_STUDIO_INSTALLER_FAILED,'content'=>$result];
    }

    protected function actionUpdateFiles($bInstall=true)
    {
        $result=parent::actionUpdateFiles($bInstall);
        if ($result!==BX_DOL_STUDIO_INSTALLER_SUCCESS) return $result;
        // Hooks reference the new adapter, so register them only after its native copy succeeds.
        // Updating a disabled module must leave it disabled and without active hooks.
        $module=$this->oDb->getModuleByUri($this->_aConfig['module_uri']);
        if (empty($module)) return BX_DOL_STUDIO_INSTALLER_FAILED;
        if ((int)$module['enabled']!==1) return BX_DOL_STUDIO_INSTALLER_SUCCESS;
        $result=$this->oDb->executeSQL($this->_sHomePath.'source/install/sql/enable.sql',$this->getMarkersForDb());
        return $result===true ? BX_DOL_STUDIO_INSTALLER_SUCCESS : ['code'=>BX_DOL_STUDIO_INSTALLER_FAILED,'content'=>$result];
    }
}
