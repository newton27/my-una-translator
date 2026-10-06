UPDATE `sys_objects_menu`
SET `override_class_name`='BxTemplMenuSwitchLanguage',`override_class_file`=''
WHERE `object` IN ('sys_switch_language_popup','sys_switch_language_inline')
  AND `override_class_name`='MyUnaTranslatorMenuSwitchLanguage'
  AND `override_class_file`='modules/newton/my_una_translator/classes/MyUnaTranslatorMenuSwitchLanguage.php';
DELETE FROM `sys_std_pages_widgets` WHERE `widget_id` IN (SELECT `id` FROM `sys_std_widgets` WHERE `module`='my_una_translator');
DELETE FROM `sys_std_pages_widgets` WHERE `page_id` IN (SELECT `id` FROM `sys_std_pages` WHERE `name`='my_una_translator');
DELETE FROM `sys_std_widgets` WHERE `module`='my_una_translator';
DELETE FROM `sys_std_pages` WHERE `name`='my_una_translator';
DROP TABLE IF EXISTS `my_una_translator_items`;
DROP TABLE IF EXISTS `my_una_translator_jobs`;
DROP TABLE IF EXISTS `my_una_translator_languages`;
DROP TABLE IF EXISTS `my_una_translator_settings`;
-- Keep site translations and registered languages. Roll back before uninstall
-- when those translations should be removed; later operator edits are preserved.
