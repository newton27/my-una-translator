<?php defined('BX_DOL') or die('hack attempt');
class MyUnaTranslatorUpdater extends BxDolStudioUpdater
{
    public function __construct($aConfig) { parent::__construct($aConfig); }

    protected function actionExecuteSql($operation)
    {
        if (!$this->oDb->getRow("SHOW COLUMNS FROM `my_una_translator_items` LIKE 'original_result'")) {
            $result = $this->oDb->executeSQL($this->_sHomePath . 'install/sql/review.sql', $this->getMarkersForDb());
            if ($result !== true)
                return ['code' => BX_DOL_STUDIO_INSTALLER_FAILED, 'content' => $result];
        }
        return parent::actionExecuteSql($operation);
    }
}
