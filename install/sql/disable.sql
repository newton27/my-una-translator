-- Disabling the module retains site translations and registered languages.
-- The native installer restores any active editing session before this step.
DELETE FROM `sys_alerts` WHERE `handler_id` IN (SELECT `id` FROM `sys_alerts_handlers` WHERE `name`='my_una_translator_editing');
DELETE FROM `sys_alerts_handlers` WHERE `name`='my_una_translator_editing';
UPDATE `sys_objects_menu`
SET `override_class_name`='BxTemplMenuSwitchLanguage',`override_class_file`=''
WHERE `object` IN ('sys_switch_language_popup','sys_switch_language_inline')
  AND `override_class_name`='MyUnaTranslatorMenuSwitchLanguage'
  AND `override_class_file`='modules/newton/my_una_translator/classes/MyUnaTranslatorMenuSwitchLanguage.php';
