CREATE TABLE `my_una_translator_settings` (
  `id` tinyint unsigned NOT NULL,
  `plan` varchar(8) NOT NULL DEFAULT 'free',
  `api_key` text NOT NULL,
  `provider` varchar(16) NOT NULL DEFAULT 'deepl',
  `libre_url` varchar(500) NOT NULL DEFAULT '',
  `libre_key` text NOT NULL,
  `batch_size` tinyint unsigned NOT NULL DEFAULT 20,
  `character_budget` bigint unsigned NOT NULL DEFAULT 500000,
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
INSERT INTO `my_una_translator_settings` (`id`,`api_key`,`libre_key`) VALUES (1,'','');

CREATE TABLE `my_una_translator_jobs` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `created_at` int unsigned NOT NULL,
  `operator_id` int unsigned NOT NULL DEFAULT 0,
  `status` varchar(32) NOT NULL DEFAULT 'planned',
  `targets` text NOT NULL,
  `category_id` int unsigned NOT NULL DEFAULT 0,
  `include_english` tinyint unsigned NOT NULL DEFAULT 1,
  `estimated` bigint unsigned NOT NULL DEFAULT 0,
  `budget` bigint unsigned NOT NULL,
  `spent` bigint unsigned NOT NULL DEFAULT 0,
  `total` int unsigned NOT NULL DEFAULT 0,
  `enable_languages` tinyint unsigned NOT NULL DEFAULT 0,
  `error` varchar(1000) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `my_una_translator_items` (
  `id` bigint unsigned NOT NULL AUTO_INCREMENT,
  `job_id` int unsigned NOT NULL,
  `key_id` int unsigned NOT NULL,
  `key_name` varchar(255) NOT NULL,
  `language` varchar(5) NOT NULL,
  `source` mediumtext NOT NULL,
  `before_value` mediumtext NULL,
  `result` mediumtext NULL,
  `original_result` mediumtext NULL,
  `state` varchar(24) NOT NULL DEFAULT 'pending',
  `error` varchar(500) NOT NULL DEFAULT '',
  PRIMARY KEY (`id`),
  UNIQUE KEY `job_key_language` (`job_id`,`key_id`,`language`),
  KEY `job_state` (`job_id`,`state`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE `my_una_translator_languages` (
  `language` varchar(5) NOT NULL,
  `language_id` int unsigned NOT NULL,
  `job_id` int unsigned NOT NULL,
  PRIMARY KEY (`language`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

SET @sName = 'my_una_translator';
INSERT INTO `sys_std_pages` (`index`,`name`,`header`,`caption`,`icon`) VALUES
(3,@sName,'_my_una_translator_title','_my_una_translator_title','my_una_translator@modules/newton/my_una_translator/|std-icon.svg');
SET @iPageId = LAST_INSERT_ID();
SET @iParentPageId = (SELECT `id` FROM `sys_std_pages` WHERE `name`='home');
SET @iOrder = (SELECT MAX(`order`) FROM `sys_std_pages_widgets` WHERE `page_id`=@iParentPageId);
INSERT INTO `sys_std_widgets` (`page_id`,`module`,`type`,`url`,`click`,`icon`,`caption`,`cnt_notices`,`cnt_actions`) VALUES
(@iPageId,@sName,'extensions',CONCAT('{url_studio}module.php?name=',@sName),'','my_una_translator@modules/newton/my_una_translator/|std-icon.svg','_my_una_translator_title','','');
INSERT INTO `sys_std_pages_widgets` (`page_id`,`widget_id`,`order`) VALUES
(@iParentPageId,LAST_INSERT_ID(),IFNULL(@iOrder,0)+1);
