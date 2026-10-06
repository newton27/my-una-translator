-- Disabling the module retains site translations and registered languages.
UPDATE `sys_objects_menu`
SET `override_class_name`='BxTemplMenuSwitchLanguage',`override_class_file`=''
WHERE `object` IN ('sys_switch_language_popup','sys_switch_language_inline')
  AND `override_class_name`='MyUnaTranslatorMenuSwitchLanguage'
  AND `override_class_file`='modules/newton/my_una_translator/classes/MyUnaTranslatorMenuSwitchLanguage.php';
