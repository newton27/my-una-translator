-- Translation jobs run only from the authenticated Studio dashboard.
UPDATE `sys_objects_menu`
SET `override_class_name`='MyUnaTranslatorMenuSwitchLanguage',
    `override_class_file`='modules/newton/my_una_translator/classes/MyUnaTranslatorMenuSwitchLanguage.php'
WHERE `object` IN ('sys_switch_language_popup','sys_switch_language_inline')
  AND `override_class_name`='BxTemplMenuSwitchLanguage' AND `override_class_file`='';
