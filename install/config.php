<?php
/** My UNA Translator — native Polyglot translation manager. */
$aConfig = [
    'type' => BX_DOL_MODULE_TYPE_MODULE,
    'name' => 'my_una_translator',
    'title' => 'My UNA Translator',
    'note' => 'Choose supported languages, review translation previews, and apply or roll back Polyglot text in Studio.',
    'version' => '0.1.12',
    'vendor' => 'My UNA World',
    'help_url' => '',
    'compatible_with' => ['15.0.x'],
    'home_dir' => 'newton/my_una_translator/',
    'home_uri' => 'my_una_translator',
    'db_prefix' => 'my_una_translator_',
    'class_prefix' => 'MyUnaTranslator',
    'language_category' => 'My UNA Translator',
    'install' => ['execute_sql'=>1,'update_languages'=>1,'clear_db_cache'=>1],
    'uninstall' => ['execute_sql'=>1,'update_languages'=>1,'clear_db_cache'=>1],
    'enable' => ['execute_sql'=>1,'clear_db_cache'=>1,'clear_template_cache'=>1],
    'disable' => ['execute_sql'=>1,'clear_db_cache'=>1,'clear_template_cache'=>1],
    'dependencies' => []
];

