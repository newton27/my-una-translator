<?php defined('BX_DOL') or die('hack attempt');
class MyUnaTranslatorUpdater extends BxDolStudioUpdater
{
    public function __construct($aConfig) { parent::__construct($aConfig); }
    protected function actionExecuteSql($operation)
    {
        // Allow a native retry after a later file/language step failed.
        if ($this->oDb->getRow("SHOW COLUMNS FROM `my_una_translator_items` LIKE 'manually_edited'"))
            return BX_DOL_STUDIO_INSTALLER_SUCCESS;
        return parent::actionExecuteSql($operation);
    }
}
