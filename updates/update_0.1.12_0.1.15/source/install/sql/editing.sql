-- Durable English editing sessions. Native language rows are never removed.
CREATE TABLE IF NOT EXISTS `my_una_translator_editing_sessions` (
  `id` int unsigned NOT NULL AUTO_INCREMENT,
  `created_at` int unsigned NOT NULL,
  `finished_at` int unsigned NOT NULL DEFAULT 0,
  `operator_id` int unsigned NOT NULL DEFAULT 0,
  `default_language` varchar(5) NOT NULL DEFAULT 'en',
  `status` varchar(16) NOT NULL DEFAULT 'active',
  `languages` mediumtext NOT NULL,
  `changed_keys` int unsigned NOT NULL DEFAULT 0,
  `restored_languages` int unsigned NOT NULL DEFAULT 0,
  `restore_complete` tinyint unsigned NOT NULL DEFAULT 0,
  `conflicts` int unsigned NOT NULL DEFAULT 0,
  `job_id` int unsigned NOT NULL DEFAULT 0,
  `unsupported_languages` text NOT NULL,
  `compile_pending` text NOT NULL,
  `error` text NOT NULL,
  PRIMARY KEY (`id`),
  KEY `editing_status` (`status`,`id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `my_una_translator_editing_keys` (
  `session_id` int unsigned NOT NULL,
  `key_id` int unsigned NOT NULL,
  `key_name` varchar(255) NOT NULL,
  `source_before` mediumtext NULL,
  `at_start` tinyint unsigned NOT NULL DEFAULT 0,
  `source_after` mediumtext NULL,
  `changed` tinyint unsigned NOT NULL DEFAULT 0,
  PRIMARY KEY (`session_id`,`key_id`),
  KEY `editing_changed` (`session_id`,`changed`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `my_una_translator_editing_values` (
  `session_id` int unsigned NOT NULL,
  `key_id` int unsigned NOT NULL,
  `language_id` int unsigned NOT NULL,
  `language` varchar(5) NOT NULL,
  `original_value` mediumtext NULL,
  `restored` tinyint unsigned NOT NULL DEFAULT 0,
  `conflict` varchar(255) NOT NULL DEFAULT '',
  PRIMARY KEY (`session_id`,`key_id`,`language_id`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS `my_una_translator_editing_expected` (
  `session_id` int unsigned NOT NULL,
  `key_id` int unsigned NOT NULL,
  `language_id` int unsigned NOT NULL,
  `value_hash` char(64) CHARACTER SET ascii COLLATE ascii_bin NOT NULL,
  PRIMARY KEY (`session_id`,`key_id`,`language_id`,`value_hash`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;
