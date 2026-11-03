<?php defined('BX_DOL') or die('hack attempt');
class MyUnaTranslatorUpdater extends BxDolStudioUpdater
{
    public function __construct($aConfig) { parent::__construct($aConfig); }

    protected function actionExecuteSql($operation)
    {
        // A later native step can fail after MySQL commits the column addition.
        if (!$this->oDb->getRow("SHOW COLUMNS FROM `my_una_translator_items` LIKE 'manually_edited'")) {
            $result = $this->oDb->executeSQL($this->_sHomePath . 'install/sql/preview.sql', $this->getMarkersForDb());
            if ($result !== true)
                return ['code' => BX_DOL_STUDIO_INSTALLER_FAILED, 'content' => $result];
        }

        // Always run this step, including after a partially completed attempt.
        return parent::actionExecuteSql($operation);
    }
}
