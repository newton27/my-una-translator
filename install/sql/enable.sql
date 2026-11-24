-- Translation jobs run only from the authenticated Studio dashboard.
UPDATE `sys_objects_menu`
SET `override_class_name`='MyUnaTranslatorMenuSwitchLanguage',
    `override_class_file`='modules/newton/my_una_translator/classes/MyUnaTranslatorMenuSwitchLanguage.php'
WHERE `object` IN ('sys_switch_language_popup','sys_switch_language_inline')
  AND `override_class_name`='BxTemplMenuSwitchLanguage' AND `override_class_file`='';

-- Idempotent owned native form hooks. Keep native validation and CSRF intact.
DELETE FROM `sys_alerts` WHERE `handler_id` IN (SELECT `id` FROM `sys_alerts_handlers` WHERE `name`='my_una_translator_editing');
DELETE FROM `sys_alerts_handlers` WHERE `name`='my_una_translator_editing';
INSERT INTO `sys_alerts_handlers` (`name`,`class`,`file`,`service_call`) VALUES
('my_una_translator_editing','MyUnaTranslatorEditingAlertsResponse','modules/newton/my_una_translator/classes/MyUnaTranslatorEditingAlertsResponse.php','');
SET @iMyUnaTranslatorEditingHandler=LAST_INSERT_ID();
INSERT INTO `sys_alerts` (`unit`,`action`,`handler_id`) VALUES
('system','form_output',@iMyUnaTranslatorEditingHandler),('system','form_check',@iMyUnaTranslatorEditingHandler);
