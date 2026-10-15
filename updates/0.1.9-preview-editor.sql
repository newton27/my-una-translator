-- My UNA Translator 0.1.9 -> preview-editor candidate.
-- Run only after SQL backup and maintenance coordination on the intended UNA site.
-- MariaDB supports ADD COLUMN IF NOT EXISTS.
-- Existing staged previews are assumed to be unedited because the installed editor was absent.
ALTER TABLE `my_una_translator_items`
  ADD COLUMN IF NOT EXISTS `original_result` MEDIUMTEXT NULL AFTER `result`;
UPDATE `my_una_translator_items`
  SET `original_result`=`result`
  WHERE `original_result` IS NULL
    AND `result` IS NOT NULL
    AND `state`='staged';
-- Rollback requires stopping editor actions first; preserve the column to retain audit values.
-- Do NOT drop the column during application rollback.
